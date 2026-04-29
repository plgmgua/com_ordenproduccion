<?php
/**
 * Assemble a work order row from a confirmed quotation line (pre-cotización) + optional wizard data.
 * Does not perform INSERT; returns column map + JSON for `orden_source_json` (see migration 3.115.0).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel;
use Grimpsa\Component\Ordenproduccion\Site\Model\PrecotizacionModel;

/**
 * OT creation helper for quotation ↔ PRE flows (distinct from webhook fixed-form mapping).
 *
 * ACL: callers must enforce ventas / quotation access. Idempotency: one active OT per `pre_cotizacion_id`.
 *
 * @since  3.115.0
 */
class OrdenFromQuotationService
{
    public const SOURCE_QUOTATION_PRE = 'quotation_pre_cotizacion';

    public const SOURCE_SCHEMA_VERSION = 1;

    /** @var DatabaseInterface */
    protected $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Whether migration 3.115.0 applied (orden_source_json column).
     */
    public function hasOrdenSourceJsonColumn(): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_ordenes', false);
        if (!\is_array($cols)) {
            return false;
        }
        foreach ($cols as $name => $_) {
            if (strtolower((string) $name) === 'orden_source_json') {
                return true;
            }
        }

        return false;
    }

    /**
     * Active orden with same PRE id (state = 1), if any — use to block duplicate creation.
     */
    public function findExistingActiveOrderByPreCotizacionId(int $preCotizacionId): ?object
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return null;
        }

        $cols = $this->db->getTableColumns('#__ordenproduccion_ordenes', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['pre_cotizacion_id'])) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName('pre_cotizacion_id') . ' = ' . $preCotizacionId)
            ->where($this->db->quoteName('state') . ' = 1');

        $this->db->setQuery($q, 0, 1);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Build normalized insert payload for `#__ordenproduccion_ordenes`.
     *
     * @param   int    $quotationId          Cotización PK
     * @param   int    $preCotizacionId      PRE PK (must match a quotation item line)
     * @param   array  $wizard               Optional: tipo_entrega (domicilio|recoger), delivery_address,
     *                                       instrucciones_entrega, contact_person_name, contact_person_phone,
     *                                       ot_fecha_entrega (Y-m-d, wizard step 3)
     * @param   User   $user                 Current user (created_by / modified_by)
     * @param   bool   $requireConfirmed     If true, quotation must have cotizacion_confirmada = 1 when column exists
     *
     * @return  array{success:bool, message:string, columns?:array<string,mixed>, orden_source_json?:string|null}
     */
    public function buildOrdenInsertData(
        int $quotationId,
        int $preCotizacionId,
        array $wizard,
        User $user,
        bool $requireConfirmed = true
    ): array {
        $quotationId     = (int) $quotationId;
        $preCotizacionId = (int) $preCotizacionId;

        if ($quotationId < 1 || $preCotizacionId < 1) {
            return ['success' => false, 'message' => 'Invalid quotation or pre-cotización id'];
        }

        $existing = $this->findExistingActiveOrderByPreCotizacionId($preCotizacionId);
        if ($existing !== null) {
            return [
                'success' => false,
                'message' => 'An active work order already exists for this pre-cotización.',
            ];
        }

        $qRow = $this->loadQuotation($quotationId);
        if ($qRow === null) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $qCols = $this->db->getTableColumns('#__ordenproduccion_quotations', false);
        $qCols = \is_array($qCols) ? array_change_key_case($qCols, CASE_LOWER) : [];

        if ($requireConfirmed && isset($qCols['cotizacion_confirmada'])) {
            $confirmed = (int) $this->getProp($qRow, 'cotizacion_confirmada');
            if ($confirmed !== 1) {
                return ['success' => false, 'message' => 'Quotation is not confirmed'];
            }
        }

        $itemRow = $this->loadQuotationItemForPre($quotationId, $preCotizacionId);
        if ($itemRow === null) {
            return ['success' => false, 'message' => 'No quotation line for this pre-cotización'];
        }

        /** @var PrecotizacionModel $preModel */
        $preModel = Factory::getApplication()->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);

        $preItem = $preModel->getItem($preCotizacionId);
        if (!$preItem || (int) ($preItem->state ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Pre-cotización not found or inactive'];
        }

        $preTotal = $preModel->getTotalForPreCotizacion($preCotizacionId);

        $valorFinal = null;
        $itemCols = $this->db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = \is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        if (isset($itemCols['valor_final'])) {
            $valorFinal = isset($itemRow->valor_final) ? (float) $itemRow->valor_final : (float) ($itemRow->subtotal ?? 0);
        } else {
            $valorFinal = (float) ($itemRow->subtotal ?? 0);
        }

        if (!\is_finite($valorFinal)) {
            $valorFinal = 0.0;
        }

        $confirmation = $this->loadLatestConfirmation($quotationId, $preCotizacionId);

        $ordenColsRaw = $this->db->getTableColumns('#__ordenproduccion_ordenes', false);
        $ordenActualByLower = [];
        if (\is_array($ordenColsRaw)) {
            foreach ($ordenColsRaw as $colName => $_def) {
                $ordenActualByLower[strtolower((string) $colName)] = (string) $colName;
            }
        }

        if ($ordenActualByLower === []) {
            return ['success' => false, 'message' => 'Work orders table is not accessible'];
        }

        $orderNumber = $this->generateNextOrderNumber();

        $workDesc = $this->composeWorkDescription($qRow, $preItem, $itemRow);

        $deliveryDateYmd = $this->normalizeDeliveryDateYmd(trim((string) ($wizard['ot_fecha_entrega'] ?? '')));

        $formLike = $this->wizardToFormLikeArray($wizard);

        $shippingType    = $this->deriveShippingType($formLike);
        $shippingAddress = $this->deriveShippingAddress($formLike);
        $shippingContact = $this->deriveShippingContact($formLike);
        $shippingPhone   = $this->deriveShippingPhone($formLike);

        $instruccionesEntrega = trim((string) ($formLike['instrucciones_entrega'] ?? ''));

        $now        = Factory::getDate()->toSql();
        $uid        = (int) $user->id;
        $clientName = trim((string) $this->getProp($qRow, 'client_name'));
        $nit        = trim((string) $this->getProp($qRow, 'client_nit'));
        $salesAgent = trim((string) ($this->getProp($qRow, 'sales_agent') ?? ''));
        $clientId   = $this->getProp($qRow, 'client_id');

        $payload = [];
        $payload = array_merge($payload, $this->baseProcessDefaults());

        $mapBool = [
            'order_number'           => $orderNumber,
            'orden_de_trabajo'       => $orderNumber,
            'client_name'            => $clientName,
            'nombre_del_cliente'     => $clientName,
            'nit'                    => $nit,
            'work_description'       => $workDesc,
            'descripcion_de_trabajo' => $workDesc,
            'sales_agent'            => $salesAgent !== '' ? $salesAgent : null,
            'agente_de_ventas'       => $salesAgent !== '' ? $salesAgent : null,
            'invoice_value'          => $valorFinal,
            'valor_a_facturar'       => $valorFinal,
            'instructions'           => $instruccionesEntrega,
            'observaciones_instrucciones_generales' => $instruccionesEntrega,
            'instrucciones_entrega'  => $instruccionesEntrega,
            'shipping_type'          => $shippingType,
            'shipping_address'       => $shippingAddress,
            'shipping_contact'       => $shippingContact,
            'shipping_phone'         => $shippingPhone,
            'status'                 => 'Nueva',
            'order_type'             => 'Interna',
            'state'                  => 1,
            'request_date'           => substr($now, 0, 10),
            'fecha_de_solicitud'     => substr($now, 0, 10),
            'created'                => $now,
            'modified'               => $now,
            'created_by'             => $uid,
            'modified_by'            => $uid,
            'version'                => '3.115.0',
        ];

        $payload = array_merge($payload, $mapBool);

        if ($deliveryDateYmd !== null) {
            $payload['delivery_date']   = $deliveryDateYmd;
            $payload['fecha_de_entrega'] = $deliveryDateYmd;
        }

        $medidasCols = $this->medidasPayloadForOrdenesTable($preItem, $ordenActualByLower);
        if ($medidasCols !== []) {
            $payload = array_merge($payload, $medidasCols);
        }

        if (isset($ordenActualByLower['client_id']) && $clientId !== null && $clientId !== '') {
            $payload['client_id'] = $clientId;
        }

        if (isset($ordenActualByLower['pre_cotizacion_id'])) {
            $payload['pre_cotizacion_id'] = $preCotizacionId;
        }

        $jsonPayload = [
            'source'                => self::SOURCE_QUOTATION_PRE,
            'schema_version'        => self::SOURCE_SCHEMA_VERSION,
            'quotation_id'          => $quotationId,
            'pre_cotizacion_id'     => $preCotizacionId,
            'quotation_number'      => $this->getProp($qRow, 'quotation_number'),
            'pre_cotizacion_number'   => $this->getProp($preItem, 'number'),
            'document_mode'         => isset($preItem->document_mode) ? (string) $preItem->document_mode : 'pliego',
            'valor_final_line'      => round($valorFinal, 2),
            'pre_total_snapshot'    => round(\is_finite($preTotal) ? (float) $preTotal : 0.0, 2),
            'confirmation_id'       => $confirmation ? (int) ($confirmation->id ?? 0) : null,
            'line_detalles_snapshot'=> $this->decodeLineDetallesSnapshot($confirmation),
            'wizard_tipo_entrega'   => isset($wizard['tipo_entrega']) ? (string) $wizard['tipo_entrega'] : null,
            'ot_fecha_entrega'       => $deliveryDateYmd,
            'pre_medidas'           => isset($preItem->medidas) ? trim((string) $preItem->medidas) : '',
        ];

        $ordenSourceJson = json_encode($jsonPayload, JSON_UNESCAPED_UNICODE);
        if ($ordenSourceJson === false) {
            return ['success' => false, 'message' => 'Could not encode orden snapshot JSON'];
        }

        if (isset($ordenActualByLower['orden_source_json'])) {
            $payload['orden_source_json'] = $ordenSourceJson;
        }

        $filtered = $this->filterColumnsForOrdenesTable($payload, $ordenActualByLower);

        return [
            'success'           => true,
            'message'           => 'OK',
            'columns'           => $filtered,
            'orden_source_json' => isset($ordenActualByLower['orden_source_json']) ? $ordenSourceJson : null,
        ];
    }

    /**
     * Build only the JSON blob (for tests or storing elsewhere if column missing).
     *
     */
    public function buildOrdenSourcePayload(
        int $quotationId,
        int $preCotizacionId,
        float $valorFinal,
        float $preTotal,
        ?string $quotationNumber,
        ?string $preNumber,
        string $documentMode,
        ?int $confirmationId,
        $lineDetallesSnapshot,
        ?string $wizardTipoEntrega
    ): string {
        $data = [
            'source'                 => self::SOURCE_QUOTATION_PRE,
            'schema_version'         => self::SOURCE_SCHEMA_VERSION,
            'quotation_id'           => $quotationId,
            'pre_cotizacion_id'      => $preCotizacionId,
            'quotation_number'       => $quotationNumber,
            'pre_cotizacion_number'  => $preNumber,
            'document_mode'          => $documentMode,
            'valor_final_line'       => round($valorFinal, 2),
            'pre_total_snapshot'     => round($preTotal, 2),
            'confirmation_id'        => $confirmationId,
            'line_detalles_snapshot' => $lineDetallesSnapshot,
            'wizard_tipo_entrega'    => $wizardTipoEntrega,
        ];

        $enc = json_encode($data, JSON_UNESCAPED_UNICODE);

        return $enc === false ? '{}' : $enc;
    }

    /**
     * Same SI/NO defaults as webhook-style orders (no fabricated process detail).
     *
     * @return  array<string, string>
     */
    protected function baseProcessDefaults(): array
    {
        $no = 'NO';

        return [
            'cutting'             => $no,
            'blocking'            => $no,
            'folding'             => $no,
            'laminating'          => $no,
            'spine'               => $no,
            'gluing'              => $no,
            'numbering'           => $no,
            'sizing'              => $no,
            'stapling'            => $no,
            'die_cutting'         => $no,
            'varnish'             => $no,
            'white_print'         => $no,
            'trimming'            => $no,
            'eyelets'             => $no,
            'perforation'         => $no,
            'corte'               => $no,
            'bloqueado'           => $no,
            'doblado'             => $no,
            'laminado'            => $no,
            'lomo'                => $no,
            'pegado'              => $no,
            'numerado'            => $no,
            'sizado'              => $no,
            'engrapado'           => $no,
            'troquel'             => $no,
            'barniz'              => $no,
            'impresion_blanco'    => $no,
            'despuntado'          => $no,
            'ojetes'              => $no,
            'perforado'           => $no,
        ];
    }

    /**
     * Normalize wizard / POST date to Y-m-d for `delivery_date` / `fecha_de_entrega`, or null if empty/invalid.
     */
    protected function normalizeDeliveryDateYmd(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            $y = (int) $m[1];
            $mo = (int) $m[2];
            $d = (int) $m[3];
            if (checkdate($mo, $d, $y)) {
                return sprintf('%04d-%02d-%02d', $y, $mo, $d);
            }

            return null;
        }

        try {
            return Factory::getDate($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Fill `dimensions` and/or `medidas_en_pulgadas` from pre-cotización `medidas` when those columns exist.
     *
     * @param   array<string,string>  $ordenActualByLower  lowercase column name → actual DB name
     *
     * @return  array<string,string>
     */
    protected function medidasPayloadForOrdenesTable(object $preItem, array $ordenActualByLower): array
    {
        $raw = isset($preItem->medidas) ? trim((string) $preItem->medidas) : '';
        if ($raw === '') {
            return [];
        }

        $out = [];
        if (isset($ordenActualByLower['dimensions'])) {
            $field = $ordenActualByLower['dimensions'];
            $out[$field] = $this->truncateUtf8($raw, 255);
        }

        if (isset($ordenActualByLower['medidas_en_pulgadas'])) {
            $field = $ordenActualByLower['medidas_en_pulgadas'];
            $out[$field] = $this->truncateUtf8($raw, 100);
        }

        return $out;
    }

    protected function truncateUtf8(string $s, int $maxLen): string
    {
        if ($maxLen < 1) {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (\mb_strlen($s, 'UTF-8') <= $maxLen) {
                return $s;
            }

            return \mb_substr($s, 0, $maxLen, 'UTF-8');
        }

        if (\strlen($s) <= $maxLen) {
            return $s;
        }

        return \substr($s, 0, $maxLen);
    }

    protected function wizardToFormLikeArray(array $wizard): array
    {
        $tipo = isset($wizard['tipo_entrega']) ? (string) $wizard['tipo_entrega'] : '';

        if ($tipo === 'recoger') {
            $tipoNormalized = 'Recoge en oficina';
        } elseif ($tipo === 'domicilio') {
            $tipoNormalized = 'Entrega a domicilio';
        } else {
            $tipoNormalized = $tipo !== '' ? $tipo : 'Entrega a domicilio';
        }

        $address = trim((string) ($wizard['delivery_address'] ?? $wizard['shipping_address'] ?? ''));

        return [
            'tipo_entrega'           => $tipoNormalized,
            'direccion_entrega'      => $address,
            'contacto_nombre'        => trim((string) ($wizard['contact_person_name'] ?? $wizard['contacto_nombre'] ?? '')),
            'contacto_telefono'      => trim((string) ($wizard['contact_person_phone'] ?? $wizard['contacto_telefono'] ?? '')),
            'instrucciones_entrega'  => trim((string) ($wizard['instrucciones_entrega'] ?? '')),
        ];
    }

    protected function deriveShippingType(array $formLike): string
    {
        return (string) ($formLike['tipo_entrega'] ?? 'Entrega a domicilio');
    }

    protected function deriveShippingAddress(array $formLike): string
    {
        $shippingType = $this->deriveShippingType($formLike);

        if ($shippingType === 'Recoge en oficina') {
            return 'Recoge en oficina';
        }

        return (string) ($formLike['direccion_entrega'] ?? '');
    }

    protected function deriveShippingContact(array $formLike): ?string
    {
        if ($this->deriveShippingType($formLike) === 'Recoge en oficina') {
            return null;
        }

        $n = trim((string) ($formLike['contacto_nombre'] ?? ''));

        return $n !== '' ? $n : null;
    }

    protected function deriveShippingPhone(array $formLike): ?string
    {
        if ($this->deriveShippingType($formLike) === 'Recoge en oficina') {
            return null;
        }

        $p = trim((string) ($formLike['contacto_telefono'] ?? ''));

        return $p !== '' ? $p : null;
    }

    /**
     * Work order description for display: the confirmed cotización line text only
     * (#__ordenproduccion_quotation_items.descripcion), not the PRE header text.
     * Cotización/PRE numbers and document_mode remain in orden_source_json.
     *
     * @param   object  $quotation  Quotation header (unused; kept for callers)
     * @param   object  $preItem    PRE row (unused; kept for callers)
     * @param   object  $itemRow    Quotation line for this pre_cotizacion_id
     *
     * @return  string  Trimmed quotation line descripcion
     */
    protected function composeWorkDescription(object $quotation, object $preItem, object $itemRow): string
    {
        return trim((string) ($this->getProp($itemRow, 'descripcion') ?? ''));
    }

    protected function loadQuotation(int $quotationId): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');

        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    protected function loadQuotationItemForPre(int $quotationId, int $preCotizacionId): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('pre_cotizacion_id') . ' = ' . $preCotizacionId);

        $this->db->setQuery($q);

        return $this->db->loadObject() ?: null;
    }

    protected function loadLatestConfirmation(int $quotationId, int $preCotizacionId): ?object
    {
        $tbl = $this->db->replacePrefix('#__ordenproduccion_pre_cotizacion_confirmation');
        try {
            $tables = $this->db->getTableList();
            if (!\in_array($tbl, $tables, true)) {
                return null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_pre_cotizacion_confirmation'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('pre_cotizacion_id') . ' = ' . $preCotizacionId)
            ->order($this->db->quoteName('id') . ' DESC');

        $this->db->setQuery($q, 0, 1);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * @param   object|null  $confirmation
     * @return  array|object
     */
    protected function decodeLineDetallesSnapshot($confirmation)
    {
        if (!$confirmation) {
            return new \stdClass();
        }

        $raw = $this->getProp($confirmation, 'line_detalles_json');
        if ($raw === null || $raw === '') {
            return new \stdClass();
        }

        $decoded = json_decode((string) $raw, true);

        return \is_array($decoded) ? $decoded : new \stdClass();
    }

    /**
     * @param   array<string,mixed>  $data
     * @param   array<string,string>  $ordenActualByLower  map lowercase column name → actual DB column name
     * @return  array<string,mixed>
     */
    protected function filterColumnsForOrdenesTable(array $data, array $ordenActualByLower): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $lk = strtolower((string) $key);
            if (isset($ordenActualByLower[$lk])) {
                $out[$ordenActualByLower[$lk]] = $value;
            }
        }

        return $out;
    }

    protected function getProp(object $obj, string $key)
    {
        foreach ([$key, strtolower($key), strtoupper($key)] as $k) {
            if (isset($obj->{$k})) {
                return $obj->{$k};
            }
        }

        $arr = (array) $obj;
        foreach ($arr as $ak => $av) {
            if (strtolower((string) $ak) === strtolower($key)) {
                return $av;
            }
        }

        return null;
    }

    /**
     * Uses the same counter and format as webhooks and admin numeración
     * (`SettingsModel::getNextOrderNumber`, `#__ordenproduccion_settings`).
     *
     * @since  3.115.8
     */
    protected function generateNextOrderNumber(): string
    {
        try {
            $settingsModel = new SettingsModel();
            $number        = $settingsModel->getNextOrderNumber();

            if (\is_string($number) && trim($number) !== '') {
                return trim($number);
            }
        } catch (\Throwable $e) {
            // Fall through to timestamp-based fallback (matches SettingsModel catch style).
        }

        return 'ORD-' . date('YmdHis');
    }
}
