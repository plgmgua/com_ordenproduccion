<?php
/**
 * Mock FELplex "Crear DTE Síncrono" engine (legacy): build JSON from cotización, optionally simulate certification
 * when Digifact URLs/token are unset; otherwise {@see FelInvoiceIssuanceService::executeDigifactCertificationForInvoice()}
 * persists real Digifact payloads and artifacts.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorDigifactAmbienteHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorDigifactLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\FelXmlHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\FpdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\OdooHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * FEL invoice issuance service (Digifact when configured; else mock simulation).
 *
 * @since  3.101.50
 */
class FelInvoiceIssuanceService
{
    /** @var string Time (24h) for scheduled FEL run on the chosen billing date (site timezone) */
    public const SCHEDULED_ISSUE_HOUR = '08:00:00';

    /** @var DatabaseInterface */
    protected $db;

    /** Guatemala IVA rate (12%) for price breakdown when totals are tax-inclusive */
    public const IVA_RATE = 0.12;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Active certifier environment from site config (test = prueba, prod = producción).
     *
     * @return  string  test|prod
     *
     * @since   3.118.5
     */
    public function getActiveCertificadorModo(): string
    {
        try {
            $model = new AdministracionModel();

            return $model->getCertificadorFactModo();
        } catch (\Throwable $e) {
            return 'test';
        }
    }

    /**
     * Full credential block for the active modo (includes clave). For server-side / API use only.
     *
     * @return  array<string, string>
     *
     * @since   3.118.6
     */
    public function getActiveCertificadorCredentials(): array
    {
        try {
            $model = new AdministracionModel();

            return $model->getCertificadorFactSettingsForActiveModo();
        } catch (\Throwable $e) {
            return [
                'url_autenticacion' => '',
                'url_info'          => '',
                'url_cert_cf'       => '',
                'url_cert_nit'      => '',
                'url_cert_cui'      => '',
                'branch_code'       => '',
                'branch_name'       => '',
                'branch_address'    => '',
                'branch_city'       => '',
                'branch_district'   => '',
                'branch_state'      => '',
                'branch_country'    => '',
                'nit'               => '',
                'usuario'           => '',
                'clave'             => '',
            ];
        }
    }

    /**
     * Non-empty bearer JWT for active modo when stored and not expired (does not auto-refresh).
     *
     * @since 3.118.8
     */
    public function getActiveCertificadorBearerToken(): string
    {
        try {
            $model = new AdministracionModel();
            $env = $model->getCertificadorFactModo();
            if ($model->isCertificadorBearerTokenExpiredForEnv($env)) {
                return '';
            }
            $b = $model->getCertificadorBearerTokenBundleForEnv($env);

            return trim((string) ($b['token'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * JWT for Digifact HTTP: refresh via {@see AdministracionModel::maintainCertificadorBearerTokens()} when Joomla
     * marks it expired, then return the stored token (even when Digifact accepts it despite bad expira_* metadata).
     *
     * @since 3.118.77
     */
    protected function resolveBearerJwtForDigifactPost(): string
    {
        try {
            $model = new AdministracionModel();
            $env = $model->getCertificadorFactModo();
            if ($model->isCertificadorBearerTokenExpiredForEnv($env)) {
                $model->maintainCertificadorBearerTokens(false);
            }
            $b = $model->getCertificadorBearerTokenBundleForEnv($env);

            return trim((string) ($b['token'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Whether issuance columns exist (migration 3.101.50 applied).
     */
    public function isEngineAvailable(): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);

        return \is_array($cols) && isset($cols['fel_issue_status']);
    }

    /**
     * Whether quotation_id exists on invoices (3.101.47+).
     */
    public function hasQuotationIdColumn(): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);

        return \is_array($cols) && isset($cols['quotation_id']);
    }

    /**
     * Whether fel_scheduled_at exists (migration 3.101.51).
     */
    public function hasFelScheduledAtColumn(): bool
    {
        return $this->hasColumn('fel_scheduled_at');
    }

    /**
     * Load active invoice linked to quotation, if any.
     */
    public function getInvoiceByQuotationId(int $quotationId): ?object
    {
        if ($quotationId < 1 || !$this->hasQuotationIdColumn()) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');

        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Create pending invoice row for quotation (queue step 1).
     *
     * @return  int  New invoice id or 0 on failure
     */
    public function createPendingInvoiceFromQuotation(int $quotationId, int $userId): int
    {
        if (!$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return 0;
        }

        if ($quotationId < 1) {
            return 0;
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing) {
            return (int) $existing->id;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $quotation = $this->db->loadObject();
        if (!$quotation) {
            return 0;
        }

        $lines = $this->loadQuotationLines($quotationId);
        $invoiceNumber = $this->generateNextInvoiceNumber();
        $now = Factory::getDate()->toSql();
        $total = (float) ($quotation->total_amount ?? 0);
        $lineItemsJson = json_encode($this->buildLineItemsForStorage($lines));

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => '',
            'client_name'        => $quotation->client_name ?? '',
            'client_nit'         => $quotation->client_nit ?? null,
            'sales_agent'        => $quotation->sales_agent ?? null,
            'request_date'       => !empty($quotation->quote_date) ? $quotation->quote_date : null,
            'delivery_date'      => null,
            'invoice_date'       => $now,
            'invoice_amount'     => $total,
            'currency'           => $quotation->currency ?? 'Q',
            'work_description'   => $this->summarizeLines($lines),
            'material'           => null,
            'dimensions'         => null,
            'print_color'        => null,
            'line_items'         => $lineItemsJson,
            'quotation_file'     => null,
            'extraction_status'  => 'manual',
            'status'             => 'draft',
            'notes'              => 'FEL mock queue (cotización)',
            'state'              => 1,
            'version'            => '3.101.51',
            'created'            => $now,
            'created_by'         => $userId,
            'quotation_id'       => $quotationId,
            'invoice_source'     => 'cotizacion_fel',
            'fel_issue_status'   => 'pending',
            'fel_issue_error'    => null,
            'fel_request_json'   => null,
            'fel_response_json'  => null,
            'fel_local_pdf_path' => null,
            'fel_local_xml_path' => null,
            'felplex_uuid'       => null,
        ];

        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        $filtered = [];
        foreach ($row as $k => $v) {
            if (isset($cols[strtolower($k)])) {
                $filtered[$k] = $v;
            }
        }

        $o = (object) $filtered;
        $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');
        $newId = (int) $o->id;
        if ($newId > 0) {
            try {
                TelegramNotificationHelper::notifyInvoiceCreated($newId);
            } catch (\Throwable $e) {
            }
        }

        return $newId;
    }

    /**
     * Brief Odoo partner snippet (credit limit + payment terms) for FEL invoice notes.
     *
     * @param   object  $quotation  Quotation row with optional client_id (Odoo res.partner).
     *
     * @return  string  Empty or "Límite crédito Odoo: … · …" style fragment
     */
    private function buildOdooFinanceNoteFragment(object $quotation): string
    {
        if (!isset($quotation->client_id)) {
            return '';
        }

        $partnerId = (int) $quotation->client_id;

        if ($partnerId < 1) {
            return '';
        }

        try {
            $helper = new OdooHelper();
            $info   = $helper->getPartnerSalesAccountingInfo($partnerId);
        } catch (\Throwable $e) {
            return '';
        }

        $parts = [];

        if ($info['credit_limit'] !== null && (float) $info['credit_limit'] >= 0) {
            $parts[] = 'Límite crédito Odoo: ' . number_format((float) $info['credit_limit'], 2, '.', '');
        }

        $ptn = isset($info['payment_term_name']) ? trim((string) $info['payment_term_name']) : '';

        if ($ptn !== '') {
            $tid = $info['payment_term_id'] !== null ? (int) $info['payment_term_id'] : 0;
            $parts[] = $tid > 0
                ? sprintf('Términos pago Odoo: %s (#%d)', $ptn, $tid)
                : ('Términos pago Odoo: ' . $ptn);
        }

        return $parts === [] ? '' : implode(' · ', $parts);
    }

    /**
     * Build UTC SQL datetime for 08:00 on the given local date (Joomla site offset).
     *
     * @param   string  $dateYmd  Y-m-d
     *
     * @return  string|null  SQL datetime or null if invalid
     */
    public function buildScheduledAtSql(string $dateYmd): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }

        $offset = Factory::getApplication()->get('offset');
        if ($offset === null || $offset === '') {
            $offset = 'UTC';
        }

        try {
            $date = Factory::getDate($dateYmd . ' ' . self::SCHEDULED_ISSUE_HOUR, $offset);
        } catch (\Throwable $e) {
            return null;
        }

        return $date->toSql();
    }

    /**
     * Queue or update invoice row: issue FEL at fel_scheduled_at (08:00 local on billing date).
     *
     * @param   string  $billingDateYmd  Y-m-d from quotation facturación
     *
     * @return  int  Invoice id or 0
     */
    public function scheduleOrUpdateInvoiceFromQuotation(int $quotationId, int $userId, string $billingDateYmd): int
    {
        if (!$this->isEngineAvailable() || !$this->hasQuotationIdColumn() || !$this->hasFelScheduledAtColumn()) {
            return 0;
        }

        if ($quotationId < 1) {
            return 0;
        }

        $scheduledSql = $this->buildScheduledAtSql($billingDateYmd);
        if ($scheduledSql === null) {
            return 0;
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing && (string) ($existing->fel_issue_status ?? '') === 'completed') {
            return (int) $existing->id;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $quotation = $this->db->loadObject();
        if (!$quotation) {
            return 0;
        }

        $felNoteBase  = 'FEL scheduled queue (cotización, 08:00)';
        $odooNoteFrag = $this->buildOdooFinanceNoteFragment($quotation);
        $invoiceNotes = $odooNoteFrag !== '' ? $felNoteBase . ' | ' . $odooNoteFrag : $felNoteBase;

        $lines = $this->loadQuotationLines($quotationId);
        $now     = Factory::getDate()->toSql();
        $total   = (float) ($quotation->total_amount ?? 0);
        $lineItemsJson = json_encode($this->buildLineItemsForStorage($lines));

        if ($existing) {
            $invoiceId = (int) $existing->id;
            $update    = [
                'fel_issue_status' => 'scheduled',
                'fel_scheduled_at' => $scheduledSql,
                'fel_issue_error'  => null,
                'invoice_amount'   => $total,
                'line_items'       => $lineItemsJson,
                'work_description' => $this->summarizeLines($lines),
                'notes'            => $invoiceNotes,
                'modified'         => $now,
                'modified_by'      => $userId,
            ];
            $update = $this->filterToExistingColumns($update);
            $this->updateInvoiceFields($invoiceId, $update);

            return $invoiceId;
        }

        $invoiceNumber = $this->generateNextInvoiceNumber();

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => '',
            'client_name'        => $quotation->client_name ?? '',
            'client_nit'         => $quotation->client_nit ?? null,
            'sales_agent'        => $quotation->sales_agent ?? null,
            'request_date'       => !empty($quotation->quote_date) ? $quotation->quote_date : null,
            'delivery_date'      => null,
            'invoice_date'       => $now,
            'invoice_amount'     => $total,
            'currency'           => $quotation->currency ?? 'Q',
            'work_description'   => $this->summarizeLines($lines),
            'material'           => null,
            'dimensions'         => null,
            'print_color'        => null,
            'line_items'         => $lineItemsJson,
            'quotation_file'     => null,
            'extraction_status'  => 'manual',
            'status'             => 'draft',
            'notes'              => $invoiceNotes,
            'state'              => 1,
            'version'            => '3.101.51',
            'created'            => $now,
            'created_by'         => $userId,
            'quotation_id'       => $quotationId,
            'invoice_source'     => 'cotizacion_fel',
            'fel_issue_status'   => 'scheduled',
            'fel_issue_error'    => null,
            'fel_request_json'   => null,
            'fel_response_json'  => null,
            'fel_local_pdf_path' => null,
            'fel_local_xml_path' => null,
            'felplex_uuid'       => null,
            'fel_scheduled_at'   => $scheduledSql,
        ];

        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        $filtered = [];
        foreach ($row as $k => $v) {
            if (isset($cols[strtolower($k)])) {
                $filtered[$k] = $v;
            }
        }

        $o = (object) $filtered;
        $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');
        $newId = (int) $o->id;
        if ($newId > 0) {
            try {
                TelegramNotificationHelper::notifyInvoiceCreated($newId);
            } catch (\Throwable $e) {
            }
        }

        return $newId;
    }

    /**
     * Process invoices whose scheduled time has passed (e.g. called from Control de ventas → cola).
     *
     * @return  int  Number of invoices that completed successfully
     */
    public function processDueScheduledInvoices(int $limit = 30): int
    {
        if (!$this->isEngineAvailable() || !$this->hasFelScheduledAtColumn()) {
            return 0;
        }

        $db = $this->db;
        $now = Factory::getDate()->toSql();
        $q   = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ordenproduccion_invoices'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('fel_issue_status') . ' = ' . $db->quote('scheduled'))
            ->where($db->quoteName('fel_scheduled_at') . ' IS NOT NULL')
            ->where($db->quoteName('fel_scheduled_at') . ' <= ' . $db->quote($now))
            ->order($db->quoteName('fel_scheduled_at') . ' ASC')
            ->setLimit($limit);
        $db->setQuery($q);
        $ids = $db->loadColumn() ?: [];
        $ok  = 0;

        foreach ($ids as $id) {
            $r = $this->processInvoice((int) $id, false);
            if (!empty($r['success'])) {
                $ok++;
            }
        }

        return $ok;
    }

    /**
     * Run FEL for a pending/processing invoice (queue step 2): Digifact NUC POST when URLs, bearer token, and modo/ambiente
     * are valid; otherwise the legacy FELplex mock (local XML/PDF).
     *
     * @param   bool  $forceScheduled  If true, run even when fel_issue_status is scheduled and fel_scheduled_at is in the future.
     */
    public function processInvoice(int $invoiceId, bool $forceScheduled = false): array
    {
        if ($invoiceId < 1 || !$this->isEngineAvailable()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $inv = $this->loadInvoice($invoiceId);
        if (!$inv) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }

        $status = (string) ($inv->fel_issue_status ?? 'none');
        if ($status === 'completed') {
            return ['success' => true, 'message' => 'Already completed', 'invoice_id' => $invoiceId];
        }

        if ($status === 'failed') {
            $this->updateInvoiceFields($invoiceId, [
                'fel_issue_status'   => 'pending',
                'fel_issue_error'    => null,
                'fel_response_json'  => null,
            ]);
            $inv = $this->loadInvoice($invoiceId);
            $status = (string) ($inv->fel_issue_status ?? 'pending');
        }

        if ($status === 'processing' && !empty($inv->fel_response_json)) {
            return ['success' => true, 'message' => 'Already completed', 'invoice_id' => $invoiceId];
        }

        if ($status === 'scheduled' && !$forceScheduled) {
            $schedRaw = $inv->fel_scheduled_at ?? null;
            if ($schedRaw !== null && $schedRaw !== '') {
                $due = Factory::getDate($schedRaw);
                $now = Factory::getDate('now');
                if ($due->getTimestamp() > $now->getTimestamp()) {
                    return ['success' => false, 'message' => 'Scheduled for later'];
                }
            }
        }

        $quotationId = (int) ($inv->quotation_id ?? 0);
        if ($quotationId < 1) {
            $this->markFailed($invoiceId, 'Missing quotation_id');

            return ['success' => false, 'message' => 'Missing quotation'];
        }

        $this->setStatus($invoiceId, 'processing');

        try {
            if ($this->isDigifactIssuanceConfiguredForQueue()) {
                $built = $this->buildDigifactNucDirectPayloadForQuotation($quotationId);
                if (!$built['success']) {
                    throw new \RuntimeException((string) ($built['message'] ?? 'Digifact payload invalid'));
                }
                $actingUserId = $this->resolveFelIssuanceActorUserId($inv);
                if ($actingUserId < 1) {
                    throw new \RuntimeException('Missing user context for FEL issuance (invoice created_by)');
                }

                /** @var array<string, mixed> $nucPayload */
                $nucPayload = $built['payload'];

                return $this->executeDigifactCertificationForInvoice(
                    $invoiceId,
                    $quotationId,
                    $nucPayload,
                    $actingUserId
                );
            }

            $quotation = $this->loadQuotation($quotationId);
            if (!$quotation) {
                throw new \RuntimeException('Quotation not found');
            }

            $lines = $this->loadQuotationLines($quotationId);
            $payload = $this->buildFelplexPayload($quotation, $lines, $inv);

            $requestJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $this->updateInvoiceFields($invoiceId, ['fel_request_json' => $requestJson]);

            $response = $this->mockFelplexResponse($payload);

            $responseJson = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $paths = $this->writeLocalArtifacts($invoiceId, $quotation, $lines, $response, $payload);

            $sat = $response['sat'] ?? [];
            $auth = isset($sat['authorization']) ? (string) $sat['authorization'] : '';
            $uuid = isset($response['uuid']) ? (string) $response['uuid'] : '';

            $update = [
                'fel_response_json'      => $responseJson,
                'felplex_uuid'           => $uuid,
                'fel_autorizacion_uuid'  => $auth !== '' ? $auth : null,
                'fel_tipo_dte'           => 'FACT',
                'fel_fecha_emision'      => Factory::getDate()->toSql(),
                'fel_receptor_id'        => $this->digitsOnly($quotation->client_nit ?? ''),
                'fel_receptor_nombre'    => $quotation->client_name ?? '',
                'fel_receptor_direccion' => $quotation->client_address ?? null,
                'fel_moneda'             => 'GTQ',
                'fel_issue_status'       => 'completed',
                'fel_issue_error'        => null,
                'fel_scheduled_at'       => null,
                'fel_local_pdf_path'     => $paths['pdf'],
                'fel_local_xml_path'     => $paths['xml'],
                'status'                 => 'created',
                'modified'               => Factory::getDate()->toSql(),
                'modified_by'            => Factory::getUser()->id,
            ];

            $update = $this->filterToExistingColumns($update);
            $this->updateInvoiceFields($invoiceId, $update);

            return [
                'success'    => true,
                'message'    => 'OK',
                'invoice_id' => $invoiceId,
                'uuid'       => $uuid,
            ];
        } catch (\Throwable $e) {
            $this->markFailed($invoiceId, $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build POST body like FELplex "Crear DTE Síncrono".
     */
    public function buildFelplexPayload(object $quotation, array $lines, object $invoice): array
    {
        $total = round((float) ($quotation->total_amount ?? 0), 2);
        $base = $total > 0 ? round($total / (1 + self::IVA_RATE), 2) : 0.0;
        $tax = round($total - $base, 2);

        $items = [];
        foreach ($lines as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $r     = $this->resolveQuotationLineTotals($row);
            $qty   = $r['qty'];
            $price = $r['unit_price'];
            $desc = isset($row->descripcion) ? trim((string) $row->descripcion) : 'Item';
            if ($desc === '') {
                $desc = 'Item';
            }

            $items[] = [
                'qty'                   => (string) $qty,
                'type'                  => 'B',
                'price'                 => $price,
                'description'           => $desc,
                'without_iva'           => 0,
                'discount'              => 0,
                'is_discount_percentage' => 0,
                'taxes'                 => [
                    'quantity'        => null,
                    'tax_code'        => null,
                    'full_name'       => null,
                    'short_name'      => null,
                    'tax_amount'      => null,
                    'taxable_amount'  => null,
                ],
            ];
        }

        if ($items === []) {
            $items[] = [
                'qty'                   => '1',
                'type'                  => 'B',
                'price'                 => $total > 0 ? $total : 0,
                'description'           => $quotation->quotation_number ?? 'Cotización',
                'without_iva'           => 0,
                'discount'              => 0,
                'is_discount_percentage' => 0,
                'taxes'                 => [
                    'quantity' => null, 'tax_code' => null, 'full_name' => null,
                    'short_name' => null, 'tax_amount' => null, 'taxable_amount' => null,
                ],
            ];
        }

        $nit = $this->digitsOnly($quotation->client_nit ?? '');
        $addr = $this->splitAddress((string) ($quotation->client_address ?? ''));

        $mail = Factory::getApplication()->get('mailfrom') ?: 'noreply@localhost';

        return [
            'type'            => 'FACT',
            'currency'        => 'GTQ',
            'datetime_issue'  => Factory::getDate()->format('Y-m-d\TH:i:s'),
            'external_id'     => $quotation->quotation_number ?? ('COT-' . ((int) $quotation->id)),
            'items'           => $items,
            'total'           => $total,
            'total_tax'       => $tax,
            'emails'          => [['email' => $mail]],
            'to_cf'           => 0,
            'to'              => [
                'tax_code_type' => 'NIT',
                'tax_code'      => $nit,
                'tax_name'      => $quotation->client_name ?? '',
                'address'       => $addr,
            ],
            'exempt_phrase'   => null,
            'custom_fields'   => [
                ['name' => 'Cotización', 'value' => (string) ($quotation->quotation_number ?? '')],
                ['name' => 'Factura interna', 'value' => (string) ($invoice->invoice_number ?? '')],
            ],
        ];
    }

    /**
     * Simulated successful FELplex response (same keys as Postman example).
     */
    public function mockFelplexResponse(array $payload): array
    {
        $uuid = $this->randomUuid();
        $serie = strtoupper(substr(str_replace('-', '', $this->randomUuid()), 0, 8));
        $auth = $this->randomUuid();
        $no = random_int(1000000000, 9999999999);
        $base = Uri::root();

        return [
            'valid'        => true,
            'uuid'         => $uuid,
            'sat'          => [
                'serie'               => $serie,
                'no'                  => $no,
                'authorization'       => $auth,
                'certification_date'  => Factory::getDate()->format('Y-m-d\TH:i:s'),
            ],
            'certifier'    => [
                'name'      => 'MOCK CERTIFIER (FELplex emulator)',
                'tax_code'  => '00000000',
            ],
            'errors'       => [],
            'invoice_url'  => $base . 'media/com_ordenproduccion/fel_issued/' . $uuid . '/invoice.pdf',
            'invoice_xml'  => $base . 'media/com_ordenproduccion/fel_issued/' . $uuid . '/invoice.xml',
        ];
    }

    /**
     * @return  array{pdf: string, xml: string} Relative web paths under JPATH_ROOT
     */
    protected function writeLocalArtifacts(
        int $invoiceId,
        object $quotation,
        array $lines,
        array $response,
        array $payload
    ): array {
        $uuid = (string) ($response['uuid'] ?? $this->randomUuid());
        $relDir = 'media/com_ordenproduccion/fel_issued/' . $invoiceId . '/' . $uuid;
        $absDir = JPATH_ROOT . '/' . $relDir;

        if (!Folder::create($absDir)) {
            throw new \RuntimeException('Cannot create directory: ' . $absDir);
        }

        $xml = $this->buildMockXml($quotation, $lines, $response, $payload);
        $xmlPath = $absDir . '/invoice.xml';
        if (file_put_contents($xmlPath, $xml) === false) {
            throw new \RuntimeException('Cannot write XML');
        }

        $pdfPath = $absDir . '/invoice.pdf';
        $this->buildMockPdf($pdfPath, $quotation, $lines, $response);

        return [
            'pdf' => $relDir . '/invoice.pdf',
            'xml' => $relDir . '/invoice.xml',
        ];
    }

    protected function buildMockXml(object $quotation, array $lines, array $response, array $payload): string
    {
        $sat = $response['sat'] ?? [];
        $esc = static function ($s) {
            return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        $linesXml = '';
        foreach ($lines as $row) {
            $d = isset($row->descripcion) ? $row->descripcion : '';
            $sub = isset($row->subtotal) ? (float) $row->subtotal : 0;
            $linesXml .= '<Linea descripcion="' . $esc($d) . '" total="' . $esc((string) $sub) . "\"/>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n<MockDTE xmlns=\"urn:mock:felplex\" factura=\"FACT\" moneda=\"GTQ\">"
            . "\n  <Emisor>MOCK — not a legal SAT document</Emisor>"
            . "\n  <Receptor nit=\"" . $esc($this->digitsOnly($quotation->client_nit ?? '')) . "\">"
            . $esc($quotation->client_name ?? '') . '</Receptor>'
            . "\n  <Totales total=\"" . $esc((string) ($payload['total'] ?? '')) . "\" iva=\"" . $esc((string) ($payload['total_tax'] ?? '')) . "\"/>"
            . "\n  <Autorizacion uuid=\"" . $esc($response['uuid'] ?? '') . "\" serie=\"" . $esc($sat['serie'] ?? '') . "\" no=\"" . $esc((string) ($sat['no'] ?? '')) . "\" auth=\"" . $esc($sat['authorization'] ?? '') . "\"/>"
            . "\n  <Lineas>\n" . $linesXml . '  </Lineas>'
            . "\n</MockDTE>\n";
    }

    protected function buildMockPdf(string $absPath, object $quotation, array $lines, array $response): void
    {
        if (!FpdfHelper::register()) {
            $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);

            return;
        }

        $enc = static function (string $s): string {
            return CotizacionPdfHelper::encodeTextForFpdf($s);
        };

        @\ob_start();
        try {
            // Same setup as CotizacionController cotización PDF (Letter mm, Arial, Latin-1 via helper).
            $pdf = new \FPDF('P', 'mm', [215.9, 279.4]);
            $pdf->AddPage();
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);

            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, $enc('Factura electrónica (MOCK / pruebas)'), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $enc('No documento legal — emulador FELplex'), 0, 1);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, $enc('Cliente: ' . ($quotation->client_name ?? '')), 0, 1);
            $pdf->Cell(0, 6, $enc('NIT: ' . ($quotation->client_nit ?? '')), 0, 1);
            $pdf->Cell(0, 6, $enc('Cotización: ' . ($quotation->quotation_number ?? '')), 0, 1);
            $sat = $response['sat'] ?? [];
            $pdf->Cell(0, 6, $enc('UUID: ' . ($response['uuid'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, $enc('Autorización: ' . ($sat['authorization'] ?? '')), 0, 1);
            $pdf->Ln(4);

            $currency = (string) ($quotation->currency ?? 'Q');
            $colDesc   = 102;
            $colCant   = 16;
            $colUnit   = 28;
            $colSub    = 28;
            $lineH     = 6;

            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell($colCant, $lineH, $enc('Cant.'), 1, 0, 'L');
            $pdf->Cell($colDesc, $lineH, $enc('Descripción'), 1, 0, 'L');
            $pdf->Cell($colUnit, $lineH, $enc('P. unit.'), 1, 0, 'R');
            $pdf->Cell($colSub, $lineH, $enc('Subtotal'), 1, 1, 'R');
            $pdf->SetFont('Arial', '', 9);

            foreach ($lines as $row) {
                if (!\is_object($row)) {
                    continue;
                }
                $r    = $this->resolveQuotationLineTotals($row);
                $rawDesc = \trim((string) ($row->descripcion ?? ''));
                if (\function_exists('mb_substr')) {
                    $rawDesc = \mb_substr($rawDesc, 0, 4000, 'UTF-8');
                } else {
                    $rawDesc = \substr($rawDesc, 0, 4000);
                }
                $desc = $enc($rawDesc);
                $qty  = $r['qty'];
                $qtyStr = (\abs($qty - \round($qty)) < 0.0001)
                    ? (string) (int) \round($qty)
                    : \number_format($qty, 2, '.', '');
                $unitStr = $currency . ' ' . \number_format($r['unit_price'], 4);
                $subStr  = $currency . ' ' . \number_format($r['line_total'], 2);

                $rowX = $pdf->GetX();
                $rowY = $pdf->GetY();

                $pdf->SetXY($rowX + $colCant, $rowY);
                $pdf->MultiCell($colDesc, $lineH, $desc, 1, 'L');
                $newY = $pdf->GetY();
                $rowH = \max($lineH, $newY - $rowY);

                $pdf->SetXY($rowX, $rowY);
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell($colCant, $rowH, $qtyStr, 1, 0, 'C');

                $pdf->SetXY($rowX + $colCant + $colDesc, $rowY);
                $pdf->Cell($colUnit, $rowH, $unitStr, 1, 0, 'R');
                $pdf->Cell($colSub, $rowH, $subStr, 1, 1, 'R');
                $pdf->SetXY($rowX, $newY);
            }

            $total = \round((float) ($quotation->total_amount ?? 0), 2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($colCant + $colDesc + $colUnit, $lineH, $enc('Total:'), 1, 0, 'R');
            $pdf->Cell($colSub, $lineH, $currency . ' ' . \number_format($total, 2), 1, 1, 'R');

            $pdf->Output('F', $absPath);
        } catch (\Throwable $e) {
            @\ob_end_clean();
            $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);

            return;
        }
        @\ob_end_clean();

        $head = @\file_get_contents($absPath, false, null, 0, 5);
        $len  = (int) @\filesize($absPath);
        if ($head !== '%PDF-' || $len < 64) {
            $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);
        }
    }

    /**
     * Single-page PDF without external libs (Helvetica, ASCII-safe text).
     *
     * @param   list<object>  $lines  Quotation line rows
     */
    protected function writeMinimalValidFelMockPdf(string $absPath, object $quotation, array $lines, array $response): void
    {
        $ascii = static function (string $s): string {
            $s = (string) \preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
            if (\function_exists('iconv')) {
                $t = @\iconv('UTF-8', 'ASCII//TRANSLIT', $s);
                if ($t !== false) {
                    $s = $t;
                }
            }

            return (string) \preg_replace('/[^\x20-\x7E]/', '?', $s);
        };
        $esc = static function (string $s): string {
            return \str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        };

        $textLines = [
            'Factura electronica (MOCK / pruebas)',
            'No documento legal',
            'Cliente: ' . $ascii((string) ($quotation->client_name ?? '')),
            'NIT: ' . $ascii((string) ($quotation->client_nit ?? '')),
            'Cotizacion: ' . $ascii((string) ($quotation->quotation_number ?? '')),
            'UUID: ' . $ascii((string) ($response['uuid'] ?? '')),
        ];
        $sat = $response['sat'] ?? [];
        $textLines[] = 'Autorizacion: ' . $ascii((string) ($sat['authorization'] ?? ''));

        foreach ($lines as $row) {
            if (\count($textLines) >= 32) {
                break;
            }
            $d = isset($row->descripcion) ? $ascii(\trim((string) $row->descripcion)) : '';
            if ($d !== '') {
                $textLines[] = '- ' . \substr($d, 0, 120);
            }
        }

        $stream = "BT\n/F1 10 Tf\n";
        foreach ($textLines as $i => $l) {
            $line = \preg_replace('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F]+/', ' ', \substr($l, 0, 220));
            if ($i === 0) {
                $stream .= \sprintf("50 750 Td (%s) Tj\n", $esc($line));
            } else {
                $stream .= \sprintf("0 -14 Td (%s) Tj\n", $esc($line));
            }
        }
        $stream .= 'ET';

        $streamBody = $stream . "\n";
        $streamLen  = \strlen($streamBody);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        $offsets[1] = \strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = \strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        $offsets[3] = \strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        $offsets[4] = \strlen($pdf);
        $pdf .= '4 0 obj' . "\n<< /Length {$streamLen} >>\nstream\n{$streamBody}endstream\nendobj\n";

        $offsets[5] = \strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $xrefPos = \strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= \sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        if (\file_put_contents($absPath, $pdf) === false) {
            throw new \RuntimeException('Cannot write mock PDF');
        }
    }

    protected function summarizeLines(array $lines): string
    {
        $parts = [];
        foreach ($lines as $row) {
            if (!empty($row->descripcion)) {
                $parts[] = trim((string) $row->descripcion);
            }
        }

        return implode('; ', \array_slice($parts, 0, 5));
    }

    /**
     * @return  list<object>
     */
    protected function loadQuotationLines(int $quotationId): array
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($q);
        $rows = $this->db->loadObjectList();

        return \is_array($rows) ? $rows : [];
    }

    protected function loadQuotation(int $id): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $id)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    protected function loadInvoice(int $id): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('id') . ' = ' . $id)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Resolve quantity, line total, and unit price from a quotation line row.
     * Prefer valor_final (pre-cot override) over subtotal; derive missing totals from valor_unitario * qty.
     *
     * @return  array{qty: float, line_total: float, unit_price: float}
     */
    protected function resolveQuotationLineTotals(object $row): array
    {
        $qty = isset($row->cantidad) ? (float) $row->cantidad : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $sub = isset($row->subtotal) ? (float) $row->subtotal : 0.0;
        $vf  = isset($row->valor_final) ? (float) $row->valor_final : 0.0;
        $vu  = isset($row->valor_unitario) ? (float) $row->valor_unitario : 0.0;

        $lineTotal = ($vf > 0.00001) ? $vf : $sub;
        if ($lineTotal <= 0.00001 && $vu > 0) {
            $lineTotal = round($vu * $qty, 2);
        }
        if ($lineTotal <= 0.00001 && $sub > 0) {
            $lineTotal = $sub;
        }

        $unit = $vu > 0.00001 ? round($vu, 4) : ($qty > 0 ? round($lineTotal / $qty, 4) : 0.0);

        return [
            'qty'         => $qty,
            'line_total'  => $lineTotal,
            'unit_price'  => $unit,
        ];
    }

    /**
     * JSON stored on invoices.line_items: includes precio_unitario for invoice PDF/detail (template key).
     */
    protected function buildLineItemsForStorage(array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $r = $this->resolveQuotationLineTotals($row);
            $out[] = [
                'cantidad'          => $r['qty'],
                'descripcion'       => $row->descripcion ?? '',
                'subtotal'          => $r['line_total'],
                'valor_unitario'    => $r['unit_price'],
                'precio_unitario'   => $r['unit_price'],
            ];
        }

        return $out;
    }

    protected function generateNextInvoiceNumber(): string
    {
        $q = $this->db->getQuery(true)
            ->select('MAX(CAST(SUBSTRING(' . $this->db->quoteName('invoice_number') . ', 5) AS UNSIGNED))')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('invoice_number') . ' LIKE ' . $this->db->quote('FAC-%'));
        $this->db->setQuery($q);
        $max = (int) $this->db->loadResult();

        return 'FAC-' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    protected function setStatus(int $invoiceId, string $status): void
    {
        $this->updateInvoiceFields($invoiceId, [
            'fel_issue_status' => $status,
            'modified'         => Factory::getDate()->toSql(),
        ]);
    }

    protected function markFailed(int $invoiceId, string $msg): void
    {
        $this->updateInvoiceFields($invoiceId, [
            'fel_issue_status' => 'failed',
            'fel_issue_error'  => $msg,
            'modified'         => Factory::getDate()->toSql(),
        ]);
    }

    /**
     * Merge Digifact SAT Certificacion fields into invoice fel_extra (Serie, Número DTE, certificador block).
     *
     * @param  array<string, mixed>  $certificacionMeta  From interpretDigifactCertificarResponse certificacion_meta
     *
     * @since  3.118.52
     */
    protected function mergeInvoiceFelExtraWithCertificacionMeta(int $invoiceId, array $certificacionMeta): ?string
    {
        if ($invoiceId < 1 || !$this->hasColumn('fel_extra')) {
            return null;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('fel_extra'))
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('id') . ' = ' . $invoiceId)
        );
        $prev = $this->db->loadResult();
        $felExtra = [];
        if (\is_string($prev) && $prev !== '') {
            $decoded = json_decode($prev, true);
            if (\is_array($decoded)) {
                $felExtra = $decoded;
            }
        }

        $serie  = trim((string) ($certificacionMeta['autorizacion_serie'] ?? ''));
        $numDte = trim((string) ($certificacionMeta['autorizacion_numero_dte'] ?? ''));
        $touched = false;
        if ($serie !== '') {
            $felExtra['autorizacion_serie'] = $serie;
            $touched = true;
        }
        if ($numDte !== '') {
            $felExtra['autorizacion_numero_dte'] = $numDte;
            $touched = true;
        }

        $certIn = $certificacionMeta['certificacion'] ?? [];
        if (\is_array($certIn)) {
            $certFiltered = array_filter([
                'nit_certificador'         => trim((string) ($certIn['nit_certificador'] ?? '')),
                'nombre_certificador'      => trim((string) ($certIn['nombre_certificador'] ?? '')),
                'fecha_hora_certificacion' => trim((string) ($certIn['fecha_hora_certificacion'] ?? '')),
            ]);
            if ($certFiltered !== []) {
                $prevCert = \is_array($felExtra['certificacion'] ?? null) ? $felExtra['certificacion'] : [];
                $felExtra['certificacion'] = array_merge($prevCert, $certFiltered);
                $touched = true;
            }
        }

        if (!$touched && ($prev === null || trim((string) $prev) === '')) {
            return null;
        }

        $json = json_encode($felExtra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json !== false ? $json : null;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function updateInvoiceFields(int $invoiceId, array $fields): void
    {
        if ($invoiceId < 1 || $fields === []) {
            return;
        }

        $fields = $this->filterToExistingColumns($fields);
        if ($fields === []) {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_invoices'));

        foreach ($fields as $k => $v) {
            $q->set($this->db->quoteName($k) . ' = ' . ($v === null ? 'NULL' : $this->db->quote($v)));
        }

        $q->where($this->db->quoteName('id') . ' = ' . $invoiceId);
        $this->db->setQuery($q);
        $this->db->execute();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function filterToExistingColumns(array $fields): array
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        if (!\is_array($cols)) {
            return [];
        }
        $cols = array_change_key_case($cols, CASE_LOWER);
        $out = [];
        foreach ($fields as $k => $v) {
            if (isset($cols[strtolower($k)])) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    protected function hasColumn(string $name): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        if (!\is_array($cols)) {
            return false;
        }
        $cols = array_change_key_case($cols, CASE_LOWER);

        return isset($cols[strtolower($name)]);
    }

    protected function digitsOnly(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }

    protected function splitAddress(string $full): array
    {
        $full = trim($full);
        if ($full === '') {
            return [
                'street'  => 'Guatemala',
                'city'    => 'Guatemala',
                'state'   => 'GU',
                'zip'     => '01001',
                'country' => 'GT',
            ];
        }

        return [
            'street'  => $full,
            'city'    => 'Guatemala',
            'state'   => 'GU',
            'zip'     => '01001',
            'country' => 'GT',
        ];
    }

    /**
     * TAXID in Digifact query string: up to 12 digits, zero-padded (see {@see CertificadorFactNitLookupHelper}).
     */
    public static function padIssuerTaxIdForDigifactUrl(string $nit): string
    {
        $d = preg_replace('/\D/', '', $nit) ?? '';
        if ($d === '') {
            return '';
        }
        if (\strlen($d) >= 12) {
            return $d;
        }

        return str_pad($d, 12, '0', STR_PAD_LEFT);
    }

    /**
     * NUC JSON body for Digifact transform (GT FACT): Buyer + Items from cotización; Seller name/email from site;
     * Seller.BranchInfo from certificador branch_* keys (active modo) with legacy defaults when empty.
     * AdditionalDocumentInfo: compact AdditionalInfo entry with @Name Cotizacion and #text = trimmed quotation_number, or COT-{id} if blank (Xml-to-JSON style keys for Digifact NUC).
     * Line amounts are IVA-inclusive; TaxableAmount = lineTotal/1.12, IVA Amount = lineTotal − TaxableAmount (12%).
     *
     * @param   list<object>  $lines  From {@see loadQuotationLines()}
     *
     * @return  array<string, mixed>
     *
     * @since   3.118.27
     */
    public function buildDigifactNucJsonPayload(object $quotation, array $lines, array $creds): array
    {
        $addrParts = $this->splitAddress((string) ($quotation->client_address ?? ''));
        $buyerStreet = trim((string) ($quotation->client_address ?? '')) !== ''
            ? trim((string) $quotation->client_address) : $addrParts['street'];
        $buyerNit = trim((string) ($quotation->client_nit ?? ''));
        $buyerName = trim((string) ($quotation->client_name ?? ''));
        if ($buyerName === '') {
            $buyerName = 'Cliente';
        }

        $issued = Factory::getDate('now')->format('c');

        $items   = [];
        $grand   = 0.0;
        $totalIva = 0.0;
        $lineNum = 1;
        foreach ($lines as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $r         = $this->resolveQuotationLineTotals($row);
            $lineTotal = round((float) $r['line_total'], 2);
            if ($lineTotal <= 0.000001) {
                continue;
            }
            $grand += $lineTotal;
            $taxable = $lineTotal / (1 + self::IVA_RATE);
            $ivaLine = $lineTotal - $taxable;
            $totalIva += $ivaLine;

            $desc = isset($row->descripcion) ? trim((string) $row->descripcion) : '';
            if ($desc === '') {
                $desc = 'Item';
            }

            $items[] = [
                'Number'         => (string) $lineNum,
                'Codes'          => null,
                'Type'           => 'Bien',
                'Description'    => $desc,
                'Qty'            => sprintf('%.6f', $r['qty']),
                'UnitOfMeasure'  => 'UNI',
                'Price'          => sprintf('%.6f', $r['unit_price']),
                'Discounts'      => null,
                'Taxes'          => [
                    'Tax' => [
                        [
                            'Code'          => '1',
                            'Description'   => 'IVA',
                            'TaxableAmount' => sprintf('%.6f', $taxable),
                            'Amount'        => sprintf('%.6f', $ivaLine),
                        ],
                    ],
                ],
                'Totals' => [
                    'TotalItem' => sprintf('%.6f', $lineTotal),
                ],
            ];
            $lineNum++;
        }

        if ($items === []) {
            $t = round((float) ($quotation->total_amount ?? 0), 2);
            if ($t > 0) {
                $taxable = $t / (1 + self::IVA_RATE);
                $ivaLine = $t - $taxable;
                $grand    = $t;
                $totalIva = $ivaLine;
                $items[] = [
                    'Number'        => '1',
                    'Codes'         => null,
                    'Type'          => 'Bien',
                    'Description'   => (string) ($quotation->quotation_number ?? 'Cotizacion'),
                    'Qty'            => '1.000000',
                    'UnitOfMeasure' => 'UNI',
                    'Price'          => sprintf('%.6f', $t),
                    'Discounts'      => null,
                    'Taxes'          => [
                        'Tax' => [
                            [
                                'Code'          => '1',
                                'Description'   => 'IVA',
                                'TaxableAmount' => sprintf('%.6f', $taxable),
                                'Amount'        => sprintf('%.6f', $ivaLine),
                            ],
                        ],
                    ],
                    'Totals' => ['TotalItem' => sprintf('%.6f', $t)],
                ];
            }
        }

        $app  = Factory::getApplication();
        $site = trim((string) $app->get('sitename')) ?: 'Emisor';
        $mail = trim((string) $app->get('mailfrom')) ?: 'noreply@localhost';
        $nitSellerDigits = $this->digitsOnly($creds['nit'] ?? '');
        $nitSellerJson   = $nitSellerDigits !== '' ? ltrim($nitSellerDigits, '0') ?: $nitSellerDigits : '000000000';

        $branchCode = trim((string) ($creds['branch_code'] ?? ''));
        if ($branchCode === '') {
            $branchCode = '1';
        }
        $branchName = trim((string) ($creds['branch_name'] ?? ''));
        if ($branchName === '') {
            $branchName = $site;
        }
        $branchAddr = trim((string) ($creds['branch_address'] ?? ''));
        if ($branchAddr === '') {
            $branchAddr = 'Ciudad';
        }
        $branchCity = trim((string) ($creds['branch_city'] ?? ''));
        if ($branchCity === '') {
            $branchCity = '01001';
        }
        $branchDistrict = trim((string) ($creds['branch_district'] ?? ''));
        if ($branchDistrict === '') {
            $branchDistrict = 'Guatemala';
        }
        $branchState = trim((string) ($creds['branch_state'] ?? ''));
        if ($branchState === '') {
            $branchState = 'Guatemala';
        }
        $branchCountry = strtoupper(trim((string) ($creds['branch_country'] ?? '')));
        if ($branchCountry === '') {
            $branchCountry = 'GT';
        }

        $cotRef = trim((string) ($quotation->quotation_number ?? ''));
        if ($cotRef === '') {
            $cotRef = 'COT-' . (int) ($quotation->id ?? 0);
        }

        return [
            'Version'     => '1.00',
            'CountryCode' => 'GT',
            'Header'      => [
                'DocType'        => 'FACT',
                'IssuedDateTime' => $issued,
                'Currency'       => 'GTQ',
            ],
            'Seller' => [
                'TaxID'               => $nitSellerJson,
                'TaxIDAdditionalInfo' => [
                    ['Name' => 'AfiliacionIVA', 'Data' => null, 'Value' => 'GEN'],
                ],
                'Name'    => $site,
                'Contact' => [
                    'EmailList' => ['Email' => [$mail]],
                ],
                'AdditionlInfo' => [
                    ['Name' => 'TipoFrase', 'Data' => '1', 'Value' => '1'],
                    ['Name' => 'Escenario', 'Data' => '1', 'Value' => '2'],
                ],
                'BranchInfo' => [
                    'Code' => $branchCode,
                    'Name' => $branchName,
                    'AddressInfo' => [
                        'Address'  => $branchAddr,
                        'City'     => $branchCity,
                        'District' => $branchDistrict,
                        'State'    => $branchState,
                        'Country'  => $branchCountry,
                    ],
                ],
            ],
            'Buyer' => [
                'TaxID'       => $buyerNit !== '' ? $buyerNit : 'CF',
                'Name'        => $buyerName,
                'AddressInfo' => [
                    'Address'  => $buyerStreet,
                    'City'     => '01010',
                    'District' => 'GUATEMALA',
                    'State'    => 'GUATEMALA',
                    'Country'  => 'GT',
                ],
            ],
            'ThirdParties' => null,
            'Items'        => $items,
            'Totals'       => [
                'TotalTaxes' => [
                    'TotalTax' => [
                        [
                            'Description' => 'IVA',
                            'Amount'      => sprintf('%.6f', $totalIva),
                        ],
                    ],
                ],
                'GrandTotal' => [
                    'InvoiceTotal' => sprintf('%.6f', $grand),
                ],
            ],
            'AdditionalDocumentInfo' => [
                'AdditionalInfo' => [
                    [
                        '@Name' => 'Cotizacion',
                        '#text' => $cotRef,
                    ],
                ],
            ],
        ];
    }

    /**
     * Merge TAXID (padded), USERNAME, FORMAT into certificación URL from config.
     * Uses **URL certificación / FACT** (`url_cert_cf`) when set; if empty or invalid, falls back to **URL certificación NIT**
     * (Digifact `nuc_json` is typically stored there — no duplicate field required).
     */
    public function buildDigifactCertificarRequestUrl(array $creds): string
    {
        $base = trim((string) ($creds['url_cert_cf'] ?? ''));
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            $base = trim((string) ($creds['url_cert_nit'] ?? ''));
        }
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            return '';
        }
        $taxPadded = self::padIssuerTaxIdForDigifactUrl((string) ($creds['nit'] ?? ''));
        $user      = trim((string) ($creds['usuario'] ?? ''));
        if ($taxPadded === '' || $user === '') {
            return '';
        }
        $parts = parse_url($base);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $q = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
        }
        $q['TAXID']    = $taxPadded;
        $q['USERNAME'] = $user;
        if (!isset($q['FORMAT']) || trim((string) $q['FORMAT']) === '') {
            $q['FORMAT'] = 'XML';
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';

        return $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path . '?' . http_build_query($q);
    }

    /**
     * POST JSON to Digifact NUC transform; Authorization must be raw JWT (no "Bearer ").
     *
     * @param   array<string, mixed>|null  $digifactLogContext  When not null, full request/response bodies are stored in the Digifact log table
     *                                                        (Authorization redacted in headers JSON only).
     *
     * @return  array{http_code:int, body:string, error:string}
     */
    public function postDigifactCertificarJson(string $requestUrl, string $jsonBody, string $bearerJwt, ?array $digifactLogContext = null): array
    {
        $bearerJwt = trim($bearerJwt);
        if ($requestUrl === '' || $bearerJwt === '') {
            return ['http_code' => 0, 'body' => '', 'error' => 'missing_url_or_token'];
        }
        $t0 = microtime(true);
        try {
            $http     = HttpFactory::getHttp();
            $response = $http->post(
                $requestUrl,
                $jsonBody,
                [
                    'Authorization' => $bearerJwt,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json, text/xml, application/xml, */*',
                ],
                120
            );
            $code = (int) ($response->code ?? 0);

            $out = [
                'http_code' => $code,
                'body'      => (string) ($response->body ?? ''),
                'error'     => '',
            ];
        } catch (\Throwable $e) {
            $out = ['http_code' => 0, 'body' => '', 'error' => $e->getMessage()];
        }

        if ($digifactLogContext !== null) {
            $duration = (int) round((microtime(true) - $t0) * 1000);
            $env      = (($digifactLogContext['environment'] ?? 'test') === 'prod') ? 'prod' : 'test';
            $inv      = isset($digifactLogContext['invoice_id']) ? (int) $digifactLogContext['invoice_id'] : 0;
            $quo      = isset($digifactLogContext['quotation_id']) ? (int) $digifactLogContext['quotation_id'] : 0;
            CertificadorDigifactLogHelper::record([
                'environment'          => $env,
                'operation'            => (string) ($digifactLogContext['operation'] ?? 'certify_nuc'),
                'request_method'       => 'POST',
                'request_url'          => $requestUrl,
                'request_headers_json' => json_encode(
                    [
                        'Authorization' => '***REDACTED***',
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json, text/xml, application/xml, */*',
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
                'request_body'         => $jsonBody,
                'response_http_code'  => (int) $out['http_code'],
                'response_body'        => (string) $out['body'],
                'client_error'         => (string) $out['error'],
                'duration_ms'          => $duration,
                'invoice_id'           => $inv > 0 ? $inv : null,
                'quotation_id'         => $quo > 0 ? $quo : null,
            ]);
        }

        return $out;
    }

    /**
     * @return  array{uuid?: string, autorizacion?: string}
     */
    public function parseDigifactCertificarResponseBody(string $body): array
    {
        $body = trim($body);
        $out  = [];
        if ($body === '') {
            return $out;
        }
        $j = json_decode($body, true);
        if (\is_array($j)) {
            foreach (['uuid', 'UUID', 'Uuid'] as $k) {
                if (!empty($j[$k]) && \is_string($j[$k])) {
                    $out['uuid'] = $j[$k];
                    break;
                }
            }
            if (!isset($out['uuid']) && !empty($j['response']) && \is_string($j['response'])) {
                $j2 = json_decode($j['response'], true);
                if (\is_array($j2) && !empty($j2['uuid'])) {
                    $out['uuid'] = (string) $j2['uuid'];
                }
            }
            if (!isset($out['uuid']) && isset($j['Sat']['Uuid']) && \is_string($j['Sat']['Uuid'])) {
                $out['uuid'] = $j['Sat']['Uuid'];
            }
        }
        if (strpos(ltrim($body), '<') === 0) {
            $satUuid = $this->extractSatAutorizacionUuidFromFelXml($body);
            if ($satUuid !== '') {
                $out['uuid'] = $satUuid;
            }
        }
        if (!isset($out['uuid']) && preg_match('/uuid["\']?\s*[:=]\s*["\']([A-F0-9a-f\-]{30,80})/i', $body, $m)) {
            $out['uuid'] = $m[1];
        }
        if (preg_match('/Autorizacion[_a-z]*["\']?\s*[:=]\s*["\']([A-F0-9a-f\-]{20,})/i', $body, $m2)) {
            $out['autorizacion'] = $m2[1];
        }

        return $out;
    }

    /**
     * SAT-certified FEL XML: authorization UUID is the text inside NumeroAutorizacion (same as SAT export filename).
     *
     * @since 3.118.35
     */
    public function extractSatAutorizacionUuidFromFelXml(string $xml): string
    {
        $xml = trim($xml);
        if ($xml === '' || strpos(ltrim($xml), '<') !== 0) {
            return '';
        }
        if (preg_match('/<(?:[\w.-]+:)?NumeroAutorizacion\b[^>]*>\s*([A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12})\s*</us', $xml, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Safe file base name for FEL artifacts (SAT style: uppercase UUID with hyphens).
     *
     * @since 3.118.35
     */
    public function sanitizeFelArtifactBasename(string $name, string $fallback): string
    {
        $name = trim($name);
        if ($name === '') {
            $name = $fallback;
        }
        $name = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $name);
        $name = trim((string) $name, '.-_');

        return substr($name !== '' ? $name : $fallback, 0, 180);
    }

    /**
     * Build Digifact NUC JSON payload for preview/issue (no HTTP POST, no invoice row). Validates cert URL + quotation lines.
     *
     * @return  array{success:bool, message?:string, payload?:array<string, mixed>, payload_json?:string}
     *
     * @since   3.118.34
     */
    public function buildDigifactNucDirectPayloadForQuotation(int $quotationId): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1 || !$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $creds = $this->getActiveCertificadorCredentials();
        if ($this->buildDigifactCertificarRequestUrl($creds) === '') {
            return ['success' => false, 'message' => 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).'];
        }

        $ambErr = CertificadorDigifactAmbienteHelper::nucCertifyCredsViolateModo($creds, $this->getActiveCertificadorModo());
        if ($ambErr !== null) {
            return ['success' => false, 'message' => Text::_($ambErr)];
        }

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing && (string) ($existing->fel_issue_status ?? '') === 'completed') {
            return ['success' => false, 'message' => 'Invoice already completed for this quotation.'];
        }

        $lines   = $this->loadQuotationLines($quotationId);
        $payload = $this->buildDigifactNucJsonPayload($quotation, $lines, $creds);
        if (($payload['Items'] ?? []) === []) {
            return ['success' => false, 'message' => 'No billable lines'];
        }

        $pretty = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
        if ($pretty === false) {
            return ['success' => false, 'message' => 'JSON encode failed'];
        }

        return ['success' => true, 'payload' => $payload, 'payload_json' => $pretty];
    }

    /**
     * Build a pseudo-invoice object + cotización reference from a Digifact NUC JSON payload (for HTML preview only).
     *
     * @param   array<string, mixed>  $payload  From {@see buildDigifactNucJsonPayload()}
     *
     * @return  \stdClass  Item shaped like an FEL invoice row for {@see tmpl/invoice/preview_digifact_fragment.php}
     *
     * @since   3.118.42
     */
    public function buildInvoicePreviewItemFromNucPayload(array $payload): \stdClass
    {
        $seller = \is_array($payload['Seller'] ?? null) ? $payload['Seller'] : [];
        $buyer  = \is_array($payload['Buyer'] ?? null) ? $payload['Buyer'] : [];
        $branch = \is_array($seller['BranchInfo'] ?? null) ? $seller['BranchInfo'] : [];
        $bAddr  = \is_array($branch['AddressInfo'] ?? null) ? $branch['AddressInfo'] : [];
        $rAddr  = \is_array($buyer['AddressInfo'] ?? null) ? $buyer['AddressInfo'] : [];

        $felExtra = [];
        if ($bAddr !== []) {
            $felExtra['emisor_direccion'] = [
                'direccion'     => (string) ($bAddr['Address'] ?? ''),
                'codigo_postal' => (string) ($bAddr['City'] ?? ''),
                'municipio'     => (string) ($bAddr['District'] ?? ''),
                'departamento'  => (string) ($bAddr['State'] ?? ''),
                'pais'          => (string) ($bAddr['Country'] ?? ''),
            ];
        }

        $lineItems = [];
        $items = $payload['Items'] ?? [];
        if (\is_array($items)) {
            foreach ($items as $idx => $it) {
                if (!\is_array($it)) {
                    continue;
                }
                $lineItems[] = [
                    'numero_linea'    => (int) ($it['Number'] ?? ($idx + 1)),
                    'cantidad'        => (float) ($it['Qty'] ?? 0),
                    'descripcion'     => (string) ($it['Description'] ?? ''),
                    'subtotal'        => (float) ($it['Totals']['TotalItem'] ?? 0),
                    'precio_unitario' => (float) ($it['Price'] ?? 0),
                    'valor_unitario'  => (float) ($it['Price'] ?? 0),
                ];
            }
        }

        $grand = 0.0;
        if (isset($payload['Totals']['GrandTotal']['InvoiceTotal'])) {
            $grand = (float) $payload['Totals']['GrandTotal']['InvoiceTotal'];
        }

        $issuerName = trim((string) ($branch['Name'] ?? ''));
        if ($issuerName === '') {
            $issuerName = (string) ($seller['Name'] ?? '');
        }

        $item                   = new \stdClass();
        $item->id               = 0;
        $item->invoice_source = 'fel_import';
        $item->invoice_number = '';
        $item->fel_emisor_nombre = $issuerName;
        $item->fel_emisor_nit    = (string) ($seller['TaxID'] ?? '');
        $item->fel_autorizacion_uuid = '';
        $item->fel_fecha_emision     = null;
        $item->client_nit         = (string) ($buyer['TaxID'] ?? '');
        $item->client_name        = (string) ($buyer['Name'] ?? '');
        $item->client_address     = (string) ($rAddr['Address'] ?? '');
        $item->invoice_amount     = $grand;
        $item->currency           = (($payload['Header']['Currency'] ?? '') === 'GTQ') ? 'Q' : (string) ($payload['Header']['Currency'] ?? 'Q');
        $item->fel_extra          = $felExtra !== [] ? json_encode($felExtra, JSON_UNESCAPED_UNICODE) : '';
        $item->line_items         = $lineItems;

        return $item;
    }

    /**
     * Cotización reference string from NUC AdditionalDocumentInfo (compact @Name / #text or empty).
     *
     * @param   array<string, mixed>  $payload
     *
     * @since   3.118.42
     */
    public function extractCotizacionReferenceFromNucPayload(array $payload): string
    {
        $list = $payload['AdditionalDocumentInfo']['AdditionalInfo'] ?? null;
        if (!\is_array($list)) {
            return '';
        }
        foreach ($list as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            if (isset($entry['#text'])) {
                return trim((string) $entry['#text']);
            }
        }

        return '';
    }

    /**
     * Interpret Digifact certification HTTP body: JSON envelope with base64 responseData*, raw XML, or legacy text.
     *
     * @return  array{xml:string, pdf:string, uuid:string, autorizacion:string, digifact_code:?int, digifact_msg:string, success:bool, certificacion_meta:array<string, mixed>}
     *
     * @since   3.118.34
     */
    public function interpretDigifactCertificarResponse(string $body, int $httpCode): array
    {
        $body = trim($body);
        $out  = [
            'xml'                 => '',
            'pdf'                 => '',
            'uuid'                => '',
            'autorizacion'        => '',
            'digifact_code'       => null,
            'digifact_msg'        => '',
            'success'             => false,
            'certificacion_meta'  => [
                'autorizacion_serie'       => '',
                'autorizacion_numero_dte'  => '',
                'numero_autorizacion_text' => '',
                'certificacion'            => [
                    'nit_certificador'          => '',
                    'nombre_certificador'       => '',
                    'fecha_hora_certificacion'  => '',
                ],
            ],
        ];

        if ($body === '') {
            return $out;
        }

        $j = json_decode($body, true);
        if (\is_array($j)) {
            $out['digifact_code'] = \array_key_exists('code', $j) ? (int) $j['code'] : null;
            $out['digifact_msg']  = trim((string) ($j['message'] ?? ''));
            $auth                 = trim((string) ($j['authNumber'] ?? ''));
            $out['autorizacion']  = $auth;

            foreach (['responseData1', 'responseData2', 'responseData3'] as $dataKey) {
                if (empty($j[$dataKey]) || !\is_string($j[$dataKey])) {
                    continue;
                }
                $raw = trim($j[$dataKey]);
                if ($raw === '') {
                    continue;
                }
                $decoded = base64_decode($raw, true);
                if ($decoded === false || $decoded === '') {
                    continue;
                }
                $trimDec = ltrim($decoded);
                if ($out['xml'] === '' && $trimDec !== '' && strpos($trimDec, '<') === 0) {
                    $out['xml'] = $decoded;

                    continue;
                }
                if ($out['pdf'] === '' && strncmp($decoded, '%PDF', 4) === 0) {
                    $out['pdf'] = $decoded;
                }
            }

            if ($out['xml'] !== '') {
                $out['certificacion_meta'] = FelXmlHelper::extractCertificacionDisplayMeta($out['xml']);
                $satFileUuid = $this->extractSatAutorizacionUuidFromFelXml($out['xml']);
                if ($satFileUuid !== '') {
                    $out['uuid'] = $satFileUuid;
                }
                $parsedXml = $this->parseDigifactCertificarResponseBody($out['xml']);
                if ($out['uuid'] === '' && !empty($parsedXml['uuid'])) {
                    $out['uuid'] = $parsedXml['uuid'];
                }
                if (!empty($parsedXml['autorizacion'])) {
                    $out['autorizacion'] = $parsedXml['autorizacion'];
                }
                $metaText = trim((string) ($out['certificacion_meta']['numero_autorizacion_text'] ?? ''));
                if ($metaText !== '') {
                    if ($out['uuid'] === '') {
                        $out['uuid'] = $metaText;
                    }
                    if ($out['autorizacion'] === '') {
                        $out['autorizacion'] = $metaText;
                    }
                }
            }
            if ($out['uuid'] === '' && $auth !== '') {
                $out['uuid'] = $auth;
            }

            $codeOk     = ($out['digifact_code'] === 1);
            $hasPayload = ($out['xml'] !== '' || $out['autorizacion'] !== '');
            $out['success'] = $httpCode >= 200 && $httpCode < 300 && $codeOk && $hasPayload;

            return $out;
        }

        if (strpos(ltrim($body), '<') === 0) {
            $out['xml']         = $body;
            $out['certificacion_meta'] = FelXmlHelper::extractCertificacionDisplayMeta($body);
            $satFileUuid        = $this->extractSatAutorizacionUuidFromFelXml($body);
            $parsed             = $this->parseDigifactCertificarResponseBody($body);
            $out['uuid']        = $satFileUuid !== '' ? $satFileUuid : ($parsed['uuid'] ?? '');
            $out['autorizacion'] = $parsed['autorizacion'] ?? '';
            $metaText = trim((string) ($out['certificacion_meta']['numero_autorizacion_text'] ?? ''));
            if ($metaText !== '') {
                if ($out['uuid'] === '') {
                    $out['uuid'] = $metaText;
                }
                if ($out['autorizacion'] === '') {
                    $out['autorizacion'] = $metaText;
                }
            }
            $legacyOk           = $out['uuid'] !== '' || $out['autorizacion'] !== ''
                || (strlen($body) > 30 && stripos($body, 'dte') !== false);
            $out['success']     = $httpCode >= 200 && $httpCode < 300 && $legacyOk;

            return $out;
        }

        $parsed             = $this->parseDigifactCertificarResponseBody($body);
        $out['uuid']        = $parsed['uuid'] ?? '';
        $out['autorizacion'] = $parsed['autorizacion'] ?? '';
        $legacyOk           = $out['uuid'] !== '' || $out['autorizacion'] !== ''
            || (strlen($body) > 30 && stripos($body, 'dte') !== false);
        $out['success']     = $httpCode >= 200 && $httpCode < 300 && $legacyOk;

        return $out;
    }

    /**
     * When true, queued processing ({@see processInvoice()}) uses Digifact NUC instead of the FELplex mock.
     * Bearer token freshness is enforced in {@see executeDigifactCertificationForInvoice()} so the queue never
     * silently falls back to mock just because JWT leeway/expiry metadata disagrees with a still-valid token.
     *
     * @since   3.118.76
     */
    protected function isDigifactIssuanceConfiguredForQueue(): bool
    {
        $creds = $this->getActiveCertificadorCredentials();
        if ($this->buildDigifactCertificarRequestUrl($creds) === '') {
            return false;
        }
        if (CertificadorDigifactAmbienteHelper::nucCertifyCredsViolateModo($creds, $this->getActiveCertificadorModo()) !== null) {
            return false;
        }

        return true;
    }

    /**
     * Prefer current session user when present; otherwise invoice modified_by / created_by (scheduled / cron).
     *
     * @since   3.118.76
     */
    protected function resolveFelIssuanceActorUserId(object $invoice): int
    {
        $sessionUid = (int) Factory::getUser()->id;
        if ($sessionUid >= 1) {
            return $sessionUid;
        }
        $fb = (int) ($invoice->modified_by ?? 0);
        if ($fb >= 1) {
            return $fb;
        }
        $fb = (int) ($invoice->created_by ?? 0);

        return $fb >= 1 ? $fb : 0;
    }

    /**
     * POST NUC certification JSON for an existing invoice and persist Digifact artifacts (caller sets fel_issue_status processing).
     *
     * @param   array<string, mixed>  $payload  Built from {@see buildDigifactNucJsonPayload()}
     *
     * @return  array{success:bool, message:string, invoice_id?:int, uuid?:string}
     *
     * @since   3.118.76
     */
    protected function executeDigifactCertificationForInvoice(int $invoiceId, int $quotationId, array $payload, int $userId): array
    {
        $invoiceId = (int) $invoiceId;
        $quotationId = (int) $quotationId;
        $userId = (int) $userId;
        if ($invoiceId < 1 || $quotationId < 1 || $userId < 1) {
            return ['success' => false, 'message' => 'Invalid ids'];
        }

        $creds = $this->getActiveCertificadorCredentials();
        $url   = $this->buildDigifactCertificarRequestUrl($creds);
        if ($url === '') {
            $msg = 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }
        $bearer = $this->resolveBearerJwtForDigifactPost();
        if ($bearer === '') {
            $msg = 'Digifact bearer token missing or expired (renew in Ajustes → Certificador).';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonBody === false) {
            $msg = 'JSON encode failed';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $this->updateInvoiceFields($invoiceId, [
            'fel_request_json' => $jsonBody,
            'fel_issue_error'  => null,
        ]);

        $env = $this->getActiveCertificadorModo();
        $env = ($env === 'prod') ? 'prod' : 'test';
        $logCtx = [
            'environment'  => $env,
            'operation'    => 'certify_nuc',
            'invoice_id'   => $invoiceId,
            'quotation_id' => $quotationId,
        ];

        $httpResult = $this->postDigifactCertificarJson($url, $jsonBody, $bearer, $logCtx);
        if ($httpResult['error'] !== '') {
            $this->markFailed($invoiceId, $httpResult['error']);

            return ['success' => false, 'message' => $httpResult['error']];
        }

        $body = $httpResult['body'];
        $code = $httpResult['http_code'];
        $this->updateInvoiceFields($invoiceId, ['fel_response_json' => $body]);

        $interpret = $this->interpretDigifactCertificarResponse($body, $code);

        return $this->finalizeDigifactCertificationSuccessFromInterpret(
            $invoiceId,
            $quotationId,
            $interpret,
            $userId,
            $code,
            $body
        );
    }

    /**
     * Persist completed invoice fields from Digifact interpret result (extracted for reuse by queue/direct issue).
     *
     * @param   array{xml:string, pdf:string, uuid:string, autorizacion:string, digifact_code:?int, digifact_msg:string, success:bool, certificacion_meta:array<string, mixed>}  $interpret
     *
     * @return  array{success:bool, message:string, invoice_id:int, uuid?:string}
     *
     * @since   3.118.76
     */
    protected function finalizeDigifactCertificationSuccessFromInterpret(
        int $invoiceId,
        int $quotationId,
        array $interpret,
        int $userId,
        int $httpCode,
        string $rawBody
    ): array {
        $uuid = $interpret['uuid'];
        $auth = $interpret['autorizacion'];

        if (!$interpret['success']) {
            $errBits = ['HTTP ' . $httpCode];
            if ($interpret['digifact_msg'] !== '') {
                $errBits[] = $interpret['digifact_msg'];
            }
            if ($interpret['digifact_code'] !== null && $interpret['digifact_code'] !== 1) {
                $errBits[] = 'code ' . $interpret['digifact_code'];
            }
            $snippet = strlen($rawBody) > 400 ? substr($rawBody, 0, 400) . '…' : $rawBody;
            if ($snippet !== '' && stripos(implode(' ', $errBits), $snippet) === false) {
                $errBits[] = $snippet;
            }
            $failMsg = implode(' — ', array_filter($errBits));
            $this->markFailed($invoiceId, $failMsg);

            return ['success' => false, 'message' => 'Digifact error: ' . $failMsg];
        }

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            $msg = 'Quotation not found';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $xmlRel = null;
        $pdfRel = null;
        if ($interpret['xml'] !== '' || $interpret['pdf'] !== '') {
            $relDir = 'media/com_ordenproduccion/fel_issued/' . $invoiceId . '/digifact';
            $absDir = JPATH_ROOT . '/' . $relDir;
            if (Folder::create($absDir)) {
                $base = '';
                if ($interpret['xml'] !== '') {
                    $base = $this->extractSatAutorizacionUuidFromFelXml($interpret['xml']);
                }
                if ($base === '') {
                    $base = $interpret['uuid'] !== '' ? $interpret['uuid']
                        : ($interpret['autorizacion'] !== '' ? $interpret['autorizacion'] : ('invoice-' . $invoiceId));
                }
                $base = $this->sanitizeFelArtifactBasename($base, 'invoice-' . $invoiceId);
                if ($interpret['xml'] !== '') {
                    $xmlForDisk = $interpret['xml'];
                    $norm         = FelXmlHelper::normalizeFelXmlForImport($xmlForDisk);
                    if (!empty($norm['success']) && isset($norm['xml']) && \is_string($norm['xml']) && $norm['xml'] !== '') {
                        $xmlForDisk = $norm['xml'];
                    }
                    $xmlPath = $absDir . '/' . $base . '.xml';
                    if (file_put_contents($xmlPath, $xmlForDisk) !== false) {
                        $xmlRel = $relDir . '/' . $base . '.xml';
                    }
                }
                if ($interpret['pdf'] !== '') {
                    $pdfPath = $absDir . '/' . $base . '.pdf';
                    if (file_put_contents($pdfPath, $interpret['pdf']) !== false) {
                        $pdfRel = $relDir . '/' . $base . '.pdf';
                    }
                }
            }
        }

        $now = Factory::getDate()->toSql();
        $felplex = $uuid !== '' ? $uuid : null;
        $felAuth = $auth !== '' ? $auth : ($uuid !== '' ? $uuid : null);
        $felExtraMerged = $this->mergeInvoiceFelExtraWithCertificacionMeta(
            $invoiceId,
            \is_array($interpret['certificacion_meta'] ?? null) ? $interpret['certificacion_meta'] : []
        );
        $update = [
            'fel_issue_status'       => 'completed',
            'fel_issue_error'        => null,
            'felplex_uuid'           => $felplex,
            'fel_autorizacion_uuid'  => $felAuth,
            'fel_tipo_dte'           => 'FACT',
            'fel_fecha_emision'      => $now,
            'fel_receptor_id'        => $this->digitsOnly($quotation->client_nit ?? ''),
            'fel_receptor_nombre'    => $quotation->client_name ?? '',
            'fel_receptor_direccion' => $quotation->client_address ?? null,
            'fel_moneda'             => 'GTQ',
            'fel_scheduled_at'       => null,
            'fel_local_xml_path'     => $xmlRel,
            'fel_local_pdf_path'     => $pdfRel,
            'status'                 => 'created',
            'modified'               => $now,
            'modified_by'            => $userId,
        ];
        if ($felExtraMerged !== null) {
            $update['fel_extra'] = $felExtraMerged;
        }
        $update = $this->filterToExistingColumns($update);
        $this->updateInvoiceFields($invoiceId, $update);

        $outUuid = '';
        if ($felplex !== null && $felplex !== '') {
            $outUuid = (string) $felplex;
        }

        return [
            'success'    => true,
            'message'    => 'OK',
            'invoice_id' => $invoiceId,
            'uuid'       => $outUuid,
        ];
    }

    /**
     * Issue FEL via Digifact transform API (bypasses mock queue). Requires url_cert_cf or url_cert_nit + valid stored bearer token.
     *
     * @return  array{success:bool, message:string, invoice_id?:int}
     *
     * @since   3.118.27
     */
    public function issueDigifactNucDirectFromQuotation(int $quotationId, int $userId): array
    {
        $quotationId = (int) $quotationId;
        $userId      = (int) $userId;
        if ($quotationId < 1 || $userId < 1 || !$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $creds = $this->getActiveCertificadorCredentials();
        $url   = $this->buildDigifactCertificarRequestUrl($creds);
        if ($url === '') {
            return ['success' => false, 'message' => 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).'];
        }
        $bearer = $this->resolveBearerJwtForDigifactPost();
        if ($bearer === '') {
            return ['success' => false, 'message' => 'Digifact bearer token missing or expired (renew in Ajustes → Certificador).'];
        }

        $built = $this->buildDigifactNucDirectPayloadForQuotation($quotationId);
        if (!$built['success']) {
            return ['success' => false, 'message' => (string) ($built['message'] ?? 'Invalid payload')];
        }

        /** @var array<string, mixed> $payload */
        $payload = $built['payload'];

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        $invoiceId = $existing ? (int) $existing->id : $this->createPendingInvoiceFromQuotation($quotationId, $userId);
        if ($invoiceId < 1) {
            return ['success' => false, 'message' => 'Could not create invoice row'];
        }

        $this->setStatus($invoiceId, 'processing');

        return $this->executeDigifactCertificationForInvoice($invoiceId, $quotationId, $payload, $userId);
    }

    protected function randomUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
