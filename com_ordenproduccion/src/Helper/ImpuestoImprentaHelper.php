<?php
/**
 * Impuesto de imprenta (timbre de prensa): cotización-only surcharge line.
 *
 * @package     com_ordenproduccion
 * @since       3.119.249
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * @since  3.119.249
 */
final class ImpuestoImprentaHelper
{
    /** Stored in quotation_items.descripcion to mark synthetic impuesto lines. */
    public const LINE_DESC_PREFIX = 'IMPUESTO_IMPRENTA:';

    /** @var bool|null */
    private static $columnReady;

    private function __construct()
    {
    }

    /**
     * Ensure legacy `#__ordenproduccion_pre_cotizacion.impuesto_imprenta` column exists (cleared on cotización save).
     */
    public static function ensureColumn(?DatabaseInterface $db = null): bool
    {
        if (self::$columnReady === true) {
            return true;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $cols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
            $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
            if (isset($cols['impuesto_imprenta'])) {
                return self::$columnReady = true;
            }

            $table = $db->replacePrefix('#__ordenproduccion_pre_cotizacion');
            $db->setQuery(
                'ALTER TABLE ' . $db->quoteName($table)
                . ' ADD COLUMN ' . $db->quoteName('impuesto_imprenta')
                . ' DECIMAL(12,2) NULL DEFAULT NULL'
                . " COMMENT 'Legacy; cleared — impuesto lives on cotización only'"
            );
            $db->execute();

            return self::$columnReady = true;
        } catch (\Throwable $e) {
            try {
                $cols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
                $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
                if (isset($cols['impuesto_imprenta'])) {
                    return self::$columnReady = true;
                }
            } catch (\Throwable $e2) {
            }

            self::$columnReady = false;

            return false;
        }
    }

    /** @var string[] */
    private const DEFAULT_KEYWORDS = ['volante', 'volantes', 'afiche', 'afiches'];

    public static function getDefaultKeywords(): array
    {
        return self::DEFAULT_KEYWORDS;
    }

    public static function normalizeKeywordsFromInput($raw): array
    {
        $items = [];

        if (\is_array($raw)) {
            $items = $raw;
        } elseif (\is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }

            if ($trimmed[0] === '[') {
                $decoded = json_decode($trimmed, true);
                if (\is_array($decoded)) {
                    $items = $decoded;
                }
            } else {
                $items = preg_split('/\r\n|\r|\n|,/', $trimmed) ?: [];
            }
        }

        $normalized = [];
        $seen       = [];

        foreach ($items as $item) {
            $keyword = trim(preg_replace('/\s+/u', ' ', (string) $item) ?? '');
            if ($keyword === '') {
                continue;
            }

            $key = mb_strtolower($keyword, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key]   = true;
            $normalized[] = $keyword;
        }

        return $normalized;
    }

    public static function parseKeywordsParam($raw): array
    {
        return self::normalizeKeywordsFromInput($raw);
    }

    public static function getConfiguredKeywords(): array
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $raw    = $params->get('impuesto_imprenta_palabras', null);

        if ($raw === null || $raw === '') {
            return self::getDefaultKeywords();
        }

        return self::parseKeywordsParam($raw);
    }

    public static function descriptionMatches(string $description): bool
    {
        $description = trim($description);
        if ($description === '') {
            return false;
        }

        foreach (self::getConfiguredKeywords() as $keyword) {
            if (self::descriptionContainsKeyword($description, $keyword)) {
                return true;
            }
        }

        return false;
    }

    public static function descriptionMatchesForQuotationLine(
        string $lineDescription,
        string $preCotDescription = '',
        string $preCotLineText = ''
    ): bool {
        if (self::descriptionMatches($lineDescription)) {
            return true;
        }

        $preCotDescription = trim($preCotDescription);
        if ($preCotDescription !== '' && self::descriptionMatches($preCotDescription)) {
            return true;
        }

        $preCotLineText = trim($preCotLineText);

        return $preCotLineText !== '' && self::descriptionMatches($preCotLineText);
    }

    public static function getPreCotizacionLineMatchingText(int $preCotizacionId, ?DatabaseInterface $db = null): string
    {
        if ($preCotizacionId < 1) {
            return '';
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $cols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion_line', false);
            $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
            if ($cols === []) {
                return '';
            }

            $select = [$db->quoteName('id')];
            if (isset($cols['vendor_descripcion'])) {
                $select[] = $db->quoteName('vendor_descripcion');
            }
            if (isset($cols['calculation_breakdown'])) {
                $select[] = $db->quoteName('calculation_breakdown');
            }

            $query = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('pre_cotizacion_id') . ' = ' . (int) $preCotizacionId)
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $db->setQuery($query);
            $rows  = $db->loadObjectList() ?: [];
            $parts = [];

            foreach ($rows as $row) {
                if (isset($row->vendor_descripcion)) {
                    $vd = trim((string) $row->vendor_descripcion);
                    if ($vd !== '') {
                        $parts[] = $vd;
                    }
                }
                if (!empty($row->calculation_breakdown)) {
                    $breakdown = json_decode((string) $row->calculation_breakdown, true);
                    if (\is_array($breakdown)) {
                        foreach ($breakdown as $entry) {
                            if (!\is_array($entry)) {
                                continue;
                            }
                            foreach (['label', 'detail'] as $key) {
                                if (!empty($entry[$key])) {
                                    $parts[] = trim((string) $entry[$key]);
                                }
                            }
                        }
                    }
                }
            }

            return trim(implode(' ', $parts));
        } catch (\Throwable $e) {
            return '';
        }
    }

    public static function getMinimumValorBaseForPreCot(int $preCotizacionId, float $minimumValorFinal): float
    {
        return round(max(0.0, $minimumValorFinal), 2);
    }

    public static function getPreCotizacionDescriptionsByIds(array $preIds, ?DatabaseInterface $db = null): array
    {
        $ids = [];
        foreach ($preIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('descripcion')])
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_keys($ids)) . ')');
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];
            $map  = [];

            foreach ($rows as $row) {
                $map[(int) $row->id] = trim((string) ($row->descripcion ?? ''));
            }

            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param   array<int|string>  $preIds
     *
     * @return  array<int, string>  id => number (e.g. PRE-01121)
     */
    public static function getPreCotizacionNumbersByIds(array $preIds, ?DatabaseInterface $db = null): array
    {
        $ids = [];
        foreach ($preIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('number')])
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', array_keys($ids)) . ')');
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];
            $map  = [];

            foreach ($rows as $row) {
                $id  = (int) $row->id;
                $num = trim((string) ($row->number ?? ''));
                $map[$id] = $num !== '' ? $num : ('PRE-' . $id);
            }

            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function getPreCotizacionNumberById(int $preCotizacionId, ?DatabaseInterface $db = null): string
    {
        if ($preCotizacionId < 1) {
            return '';
        }

        $map = self::getPreCotizacionNumbersByIds([$preCotizacionId], $db);

        return $map[$preCotizacionId] ?? ('PRE-' . $preCotizacionId);
    }

    public static function extractValorBaseForForm(
        float $lineTotalFinal,
        float $storedImpuesto,
        string $description,
        string $preCotDescription = '',
        string $preCotLineText = ''
    ): float {
        return round(max(0.0, $lineTotalFinal), 2);
    }

    private static function descriptionContainsKeyword(string $description, string $keyword): bool
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return false;
        }

        if (preg_match('/\s/u', $keyword)) {
            return mb_stripos($description, $keyword) !== false;
        }

        $escaped = preg_quote($keyword, '/');

        return (bool) preg_match('/(?<![\p{L}\p{N}_])' . $escaped . '(?![\p{L}\p{N}_])/iu', $description);
    }

    public static function getParamPercent(): float
    {
        $pct = (float) ComponentHelper::getParams('com_ordenproduccion')->get('impuesto_imprenta', 0);

        return max(0.0, min(100.0, $pct));
    }

    /**
     * Configured label for the cotización impuesto line (Parámetros → Etiqueta de Impuesto de imprenta).
     */
    public static function getParamLabel(): string
    {
        $raw = trim((string) ComponentHelper::getParams('com_ordenproduccion')->get('impuesto_imprenta_etiqueta', ''));
        if ($raw !== '') {
            return $raw;
        }

        $label = Text::_('COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA');

        return ($label === 'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA') ? 'Impuesto de imprenta' : $label;
    }

    public static function normalizeParamLabelFromInput($raw): string
    {
        $label = trim(strip_tags((string) $raw));

        if ($label === '') {
            return '';
        }

        if (mb_strlen($label, 'UTF-8') > 255) {
            $label = mb_substr($label, 0, 255, 'UTF-8');
        }

        return $label;
    }

    /** @deprecated Pre-cotización no longer stores impuesto; always 0 for new logic. */
    public static function getStoredAmount(int $preCotizacionId, ?DatabaseInterface $db = null): float
    {
        return 0.0;
    }

    public static function buildImpuestoLineDescription(int $forPreCotizacionId): string
    {
        return self::LINE_DESC_PREFIX . (int) $forPreCotizacionId;
    }

    public static function parseImpuestoLineForPreId(string $descripcion): int
    {
        $descripcion = trim($descripcion);
        if ($descripcion === '' || !str_starts_with($descripcion, self::LINE_DESC_PREFIX)) {
            return 0;
        }

        return max(0, (int) substr($descripcion, \strlen(self::LINE_DESC_PREFIX)));
    }

    /**
     * @param   object|array<string, mixed>  $item
     */
    public static function isImpuestoLineItem($item): bool
    {
        if (\is_array($item)) {
            if (!empty($item['is_impuesto_line'])) {
                return true;
            }
            $desc = (string) ($item['descripcion'] ?? '');
        } else {
            if (!empty($item->is_impuesto_line)) {
                return true;
            }
            $desc = (string) ($item->descripcion ?? '');
        }

        return self::parseImpuestoLineForPreId($desc) > 0;
    }

    /**
     * Timbre de prensa / impuesto cotización lines must not create work orders.
     *
     * @param   object|array<string, mixed>  $item
     */
    public static function isOrdenTrabajoEligibleQuotationItem($item): bool
    {
        return !self::isImpuestoLineItem($item);
    }

    /**
     * Pre-cotización ids linked by product lines only (excludes impuesto marker rows).
     *
     * @return  int[]
     */
    public static function getOrdenTrabajoEligiblePreCotizacionIds(int $quotationId, ?DatabaseInterface $db = null): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return [];
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
            $qiCols = \is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
            if (!isset($qiCols['pre_cotizacion_id'])) {
                return [];
            }

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotation_items'))
                ->where($db->quoteName('quotation_id') . ' = ' . $quotationId)
                ->where($db->quoteName('pre_cotizacion_id') . ' IS NOT NULL')
                ->where($db->quoteName('pre_cotizacion_id') . ' > 0');
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];
            $ids  = [];

            foreach ($rows as $row) {
                if (!self::isOrdenTrabajoEligibleQuotationItem($row)) {
                    continue;
                }
                $pid = (int) ($row->pre_cotizacion_id ?? 0);
                if ($pid > 0) {
                    $ids[$pid] = true;
                }
            }

            return array_keys($ids);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function isPreCotizacionEligibleForOrdenTrabajo(int $quotationId, int $preCotizacionId, ?DatabaseInterface $db = null): bool
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($quotationId < 1 || $preCotizacionId < 1) {
            return false;
        }

        $eligible = self::getOrdenTrabajoEligiblePreCotizacionIds($quotationId, $db);

        return \in_array($preCotizacionId, $eligible, true);
    }

    /**
     * Human-readable label for display (cotización view / edit preview).
     */
    public static function getImpuestoLineDisplayLabel(int $forPreCotizacionId, string $preNumber = ''): string
    {
        $label = self::getParamLabel();
        $preNumber = trim($preNumber);
        if ($preNumber === '' && $forPreCotizacionId > 0) {
            $preNumber = self::getPreCotizacionNumberById($forPreCotizacionId);
        }

        return $preNumber !== '' ? ($label . ' — ' . $preNumber) : $label;
    }

    /**
     * Description for cotización line tables / PDF (resolves impuesto marker rows).
     *
     * @param   object|array<string, mixed>  $item
     */
    public static function getQuotationItemDisplayDescription($item, ?DatabaseInterface $db = null): string
    {
        $desc = \is_array($item)
            ? (string) ($item['descripcion'] ?? '')
            : (string) ($item->descripcion ?? '');

        if (!self::isImpuestoLineItem($item)) {
            return $desc;
        }

        $forPreId = self::parseImpuestoLineForPreId($desc);
        if ($forPreId < 1) {
            return $desc;
        }

        $preNum = \is_array($item)
            ? trim((string) ($item['pre_cotizacion_number'] ?? ''))
            : trim((string) ($item->pre_cotizacion_number ?? ''));

        return self::getImpuestoLineDisplayLabel($forPreId, $preNum);
    }

    /**
     * Codigo / PRE column for cotización line tables / PDF.
     *
     * @param   object|array<string, mixed>  $item
     */
    public static function getQuotationItemDisplayCodigo($item, ?DatabaseInterface $db = null): string
    {
        if (self::isImpuestoLineItem($item)) {
            return '-';
        }

        $preNum = \is_array($item)
            ? trim((string) ($item['pre_cotizacion_number'] ?? ''))
            : trim((string) ($item->pre_cotizacion_number ?? ''));

        if ($preNum !== '') {
            return $preNum;
        }

        $preId = \is_array($item)
            ? (int) ($item['pre_cotizacion_id'] ?? 0)
            : (int) ($item->pre_cotizacion_id ?? 0);

        return $preId > 0 ? ('PRE-' . $preId) : '-';
    }

    /**
     * Replace impuesto marker rows with configured label and linked PRE number for UI/PDF/FEL.
     *
     * @param   array<int, object>  $items
     *
     * @return  array<int, object>
     */
    public static function enrichQuotationItemsForDisplay(array $items, ?DatabaseInterface $db = null): array
    {
        if ($items === []) {
            return $items;
        }

        $impuestoPreIds = [];
        foreach ($items as $item) {
            if (!self::isImpuestoLineItem($item)) {
                continue;
            }
            $forPreId = self::parseImpuestoLineForPreId((string) ($item->descripcion ?? ''));
            if ($forPreId > 0) {
                $impuestoPreIds[$forPreId] = true;
            }
        }

        $preNumberById = $impuestoPreIds !== []
            ? self::getPreCotizacionNumbersByIds(array_keys($impuestoPreIds), $db)
            : [];

        foreach ($items as $item) {
            if (!self::isImpuestoLineItem($item)) {
                continue;
            }
            $forPreId = self::parseImpuestoLineForPreId((string) ($item->descripcion ?? ''));
            if ($forPreId < 1) {
                continue;
            }
            $preNum = $preNumberById[$forPreId] ?? self::getPreCotizacionNumberById($forPreId, $db);
            $item->is_impuesto_line       = true;
            $item->impuesto_for_pre_id    = $forPreId;
            $item->pre_cotizacion_number  = $preNum;
            $item->descripcion            = self::getImpuestoLineDisplayLabel($forPreId, $preNum);
        }

        return $items;
    }

    public static function computeImpuestoAmount(
        float $lineValue,
        string $lineDescription,
        string $preCotDescription = '',
        string $preCotLineText = ''
    ): float {
        $lineValue = round(max(0.0, $lineValue), 2);
        $pct       = self::getParamPercent();

        if ($pct <= 0 || !self::descriptionMatchesForQuotationLine($lineDescription, $preCotDescription, $preCotLineText)) {
            return 0.0;
        }

        return round($lineValue * ($pct / 100.0), 2);
    }

    /**
     * Cotización line value is stored as-is; impuesto is a separate quotation_items row.
     *
     * @return  array{valor_base: float, impuesto: float|null, valor_final: float}
     */
    public static function resolveLineValue(
        float $formValor,
        string $description,
        string $preCotDescription = '',
        string $preCotLineText = ''
    ): array {
        $formValor = round(max(0.0, $formValor), 2);
        $impuesto  = self::computeImpuestoAmount($formValor, $description, $preCotDescription, $preCotLineText);

        return [
            'valor_base'  => $formValor,
            'impuesto'    => $impuesto > 0 ? $impuesto : null,
            'valor_final' => $formValor,
        ];
    }

    /**
     * Append synthetic impuesto lines after each matching pre-cot cotización line.
     *
     * @param   array<int, array<string, mixed>>  $lineItems
     * @param   array<int, string>                $preDescMap
     * @param   array<int, string>                $preLineTextMap
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function appendImpuestoLineItems(array $lineItems, array $preDescMap, array $preLineTextMap): array
    {
        $result = [];
        $order  = 0;

        foreach ($lineItems as $item) {
            if (!empty($item['is_impuesto_line'])) {
                continue;
            }

            $item['line_order'] = $order++;
            $result[]           = $item;

            $preId = isset($item['pre_cotizacion_id']) ? (int) $item['pre_cotizacion_id'] : 0;
            if ($preId < 1) {
                continue;
            }

            $lineValue = (float) ($item['valor_base'] ?? $item['subtotal'] ?? 0);
            $lineDesc  = (string) ($item['descripcion'] ?? '');
            $impuesto  = self::computeImpuestoAmount(
                $lineValue,
                $lineDesc,
                $preDescMap[$preId] ?? '',
                $preLineTextMap[$preId] ?? ''
            );

            if ($impuesto <= 0) {
                continue;
            }

            $result[] = [
                'line_order'         => $order++,
                'cantidad'           => 1.0,
                'descripcion'        => self::buildImpuestoLineDescription($preId),
                'valor_unitario'     => $impuesto,
                'subtotal'           => $impuesto,
                'valor_final'        => $impuesto,
                'pre_cotizacion_id'  => null,
                'line_images_json'   => '[]',
                'is_impuesto_line'   => true,
                'impuesto_for_pre_id' => $preId,
            ];
        }

        return $result;
    }

    /**
     * Persist margen_adicional on pre-cotización only (impuesto is cotización-only).
     */
    public static function syncPreCotizacionFromQuotationLine(
        int $preCotizacionId,
        float $valorBase,
        float $preTotal,
        ?float $impuestoAmount = null,
        ?DatabaseInterface $db = null
    ): void {
        if ($preCotizacionId < 1) {
            return;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        self::clearPreCotizacionImpuesto($preCotizacionId, $db);

        try {
            $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
            $pcCols = \is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
        } catch (\Throwable $e) {
            return;
        }

        $hasMargen   = isset($pcCols['margen_adicional']);
        $hasComision = isset($pcCols['comision_margen_adicional']);

        if (!$hasMargen) {
            return;
        }

        try {
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . (int) $preCotizacionId);

            if ($valorBase > $preTotal) {
                $margenAdicional = round($valorBase - $preTotal, 2);
                $paramPct       = (float) ComponentHelper::getParams('com_ordenproduccion')->get('comision_margen_adicional', 0);
                $comisionMa     = round($margenAdicional * $paramPct / 100, 2);
                $q->set($db->quoteName('margen_adicional') . ' = ' . (float) $margenAdicional);
                if ($hasComision) {
                    $q->set($db->quoteName('comision_margen_adicional') . ' = ' . (float) $comisionMa);
                }
            } else {
                $q->set($db->quoteName('margen_adicional') . ' = NULL');
                if ($hasComision) {
                    $q->set($db->quoteName('comision_margen_adicional') . ' = NULL');
                }
            }

            $db->setQuery($q)->execute();
        } catch (\Throwable $e) {
        }
    }

    public static function clearPreCotizacionImpuesto(int $preCotizacionId, ?DatabaseInterface $db = null): void
    {
        if ($preCotizacionId < 1 || !self::ensureColumn($db)) {
            return;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->set($db->quoteName('impuesto_imprenta') . ' = NULL')
                ->where($db->quoteName('id') . ' = ' . (int) $preCotizacionId);
            $db->setQuery($q)->execute();
        } catch (\Throwable $e) {
        }
    }

    /**
     * Resolve pre-cotización id linked to an orden de trabajo row.
     *
     * @param   object|array<string, mixed>  $order
     */
    public static function resolvePreCotizacionIdForOrder($order): int
    {
        if (\is_array($order)) {
            $preId = (int) ($order['pre_cotizacion_id'] ?? 0);
            if ($preId > 0) {
                return $preId;
            }
            $json = (string) ($order['orden_source_json'] ?? '');
        } else {
            $preId = (int) ($order->pre_cotizacion_id ?? 0);
            if ($preId > 0) {
                return $preId;
            }
            $json = (string) ($order->orden_source_json ?? '');
        }

        if ($json !== '') {
            $data = json_decode($json, true);
            if (\is_array($data)) {
                return max(0, (int) ($data['pre_cotizacion_id'] ?? 0));
            }
        }

        return 0;
    }

    /**
     * Resolve cotización id for an orden de trabajo (JSON snapshot, then DB lookups).
     *
     * @param   object|array<string, mixed>  $order
     */
    public static function resolveQuotationIdForOrder($order, ?DatabaseInterface $db = null): int
    {
        $json = \is_array($order) ? (string) ($order['orden_source_json'] ?? '') : (string) ($order->orden_source_json ?? '');
        if ($json !== '') {
            $data = json_decode($json, true);
            if (\is_array($data) && !empty($data['quotation_id'])) {
                return (int) $data['quotation_id'];
            }
        }

        $preId = self::resolvePreCotizacionIdForOrder($order);
        if ($preId < 1) {
            return 0;
        }

        $map = self::resolveQuotationIdsForPreIds([$preId], $db);

        return (int) ($map[$preId] ?? 0);
    }

    /**
     * Timbre de prensa amount for a PRE on a cotización (0 when no line exists).
     */
    public static function getImpuestoAmountForPreCotizacion(int $quotationId, int $preCotizacionId, ?DatabaseInterface $db = null): float
    {
        if ($quotationId < 1 || $preCotizacionId < 1) {
            return 0.0;
        }

        $map = self::loadImpuestoAmountMap($db, [$quotationId]);

        return (float) ($map[$quotationId . ':' . $preCotizacionId] ?? 0.0);
    }

    /**
     * Full amount a payment should cover for an OT: product invoice_value + linked timbre de prensa.
     *
     * @param   object|array<string, mixed>  $order
     */
    public static function getOrderPaymentCoverageTotal($order, ?DatabaseInterface $db = null): float
    {
        $enriched = self::enrichOrdersWithPaymentCoverage([$order], $db);
        $row      = $enriched[0] ?? $order;

        if (\is_array($row)) {
            return round((float) ($row['coverage_total'] ?? $row['invoice_value'] ?? 0), 2);
        }

        return round((float) ($row->coverage_total ?? $row->invoice_value ?? 0), 2);
    }

    /**
     * Attach coverage_total, impuesto_imprenta_amount and adjusted remaining_balance to order rows.
     *
     * @param   array<int, object|array<string, mixed>>  $orders
     *
     * @return  array<int, object|array<string, mixed>>
     */
    public static function enrichOrdersWithPaymentCoverage(array $orders, ?DatabaseInterface $db = null): array
    {
        if ($orders === []) {
            return $orders;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        $preIdsNeedingQuotation = [];
        $orderMeta              = [];

        foreach ($orders as $idx => $order) {
            $preId = self::resolvePreCotizacionIdForOrder($order);
            $qId   = 0;
            $json  = \is_array($order) ? (string) ($order['orden_source_json'] ?? '') : (string) ($order->orden_source_json ?? '');
            if ($json !== '') {
                $data = json_decode($json, true);
                if (\is_array($data) && !empty($data['quotation_id'])) {
                    $qId = (int) $data['quotation_id'];
                }
            }
            if ($preId > 0 && $qId < 1) {
                $preIdsNeedingQuotation[$preId] = true;
            }
            $orderMeta[$idx] = ['pre_id' => $preId, 'q_id' => $qId];
        }

        $preToQuotation = self::resolveQuotationIdsForPreIds(array_keys($preIdsNeedingQuotation), $db);

        $quotationIds = [];
        foreach ($orderMeta as $idx => $meta) {
            if ($meta['q_id'] < 1 && $meta['pre_id'] > 0) {
                $orderMeta[$idx]['q_id'] = (int) ($preToQuotation[$meta['pre_id']] ?? 0);
            }
            if ($orderMeta[$idx]['q_id'] > 0) {
                $quotationIds[$orderMeta[$idx]['q_id']] = true;
            }
        }

        $impuestoMap = self::loadImpuestoAmountMap($db, array_keys($quotationIds));

        foreach ($orders as $idx => $order) {
            $invoiceValue = round((float) (\is_array($order)
                ? ($order['invoice_value'] ?? 0)
                : ($order->invoice_value ?? 0)), 2);
            $meta     = $orderMeta[$idx];
            $impuesto = 0.0;
            if ($meta['pre_id'] > 0 && $meta['q_id'] > 0) {
                $impuesto = (float) ($impuestoMap[$meta['q_id'] . ':' . $meta['pre_id']] ?? 0.0);
            }
            $coverage = round($invoiceValue + $impuesto, 2);

            if (\is_array($order)) {
                $order['impuesto_imprenta_amount'] = $impuesto;
                $order['coverage_total']           = $coverage;
                if (isset($order['total_paid'])) {
                    $order['remaining_balance'] = max(0.0, round($coverage - (float) $order['total_paid'], 2));
                }
                $orders[$idx] = $order;
            } else {
                $order->impuesto_imprenta_amount = $impuesto;
                $order->coverage_total           = $coverage;
                if (isset($order->total_paid)) {
                    $order->remaining_balance = max(0.0, round($coverage - (float) $order->total_paid, 2));
                }
            }
        }

        return $orders;
    }

    /**
     * @param   int[]  $preIds
     *
     * @return  array<int, int>  pre_cotizacion_id => quotation_id
     */
    private static function resolveQuotationIdsForPreIds(array $preIds, ?DatabaseInterface $db = null): array
    {
        $preIds = array_values(array_unique(array_filter(array_map('intval', $preIds))));
        if ($preIds === []) {
            return [];
        }

        $db  = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $map = [];

        try {
            $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
            $pcCols = \is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
            if (isset($pcCols['quotation_id'])) {
                $q = $db->getQuery(true)
                    ->select([
                        $db->quoteName('id'),
                        $db->quoteName('quotation_id'),
                    ])
                    ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', $preIds) . ')');
                $db->setQuery($q);
                foreach ($db->loadObjectList() ?: [] as $row) {
                    $pid = (int) ($row->id ?? 0);
                    $qid = (int) ($row->quotation_id ?? 0);
                    if ($pid > 0 && $qid > 0) {
                        $map[$pid] = $qid;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $missing = array_values(array_diff($preIds, array_keys($map)));
        if ($missing === []) {
            return $map;
        }

        try {
            $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
            $qiCols = \is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
            if (!isset($qiCols['pre_cotizacion_id'], $qiCols['quotation_id'])) {
                return $map;
            }

            $q = $db->getQuery(true)
                ->select([
                    $db->quoteName('pre_cotizacion_id'),
                    $db->quoteName('quotation_id'),
                ])
                ->from($db->quoteName('#__ordenproduccion_quotation_items'))
                ->where($db->quoteName('pre_cotizacion_id') . ' IN (' . implode(',', $missing) . ')')
                ->group($db->quoteName('pre_cotizacion_id') . ', ' . $db->quoteName('quotation_id'));
            $db->setQuery($q);
            foreach ($db->loadObjectList() ?: [] as $row) {
                $pid = (int) ($row->pre_cotizacion_id ?? 0);
                $qid = (int) ($row->quotation_id ?? 0);
                if ($pid > 0 && $qid > 0 && !isset($map[$pid])) {
                    $map[$pid] = $qid;
                }
            }
        } catch (\Throwable $e) {
        }

        return $map;
    }

    /**
     * @param   int[]  $quotationIds
     *
     * @return  array<string, float>  "{quotation_id}:{pre_id}" => amount
     */
    private static function loadImpuestoAmountMap(?DatabaseInterface $db, array $quotationIds): array
    {
        $quotationIds = array_values(array_unique(array_filter(array_map('intval', $quotationIds))));
        if ($quotationIds === []) {
            return [];
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
            $qiCols = \is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
            if (!isset($qiCols['descripcion'], $qiCols['quotation_id'])) {
                return [];
            }
            $amountCol = isset($qiCols['valor_final']) ? 'valor_final' : (isset($qiCols['subtotal']) ? 'subtotal' : null);
            if ($amountCol === null) {
                return [];
            }

            $q = $db->getQuery(true)
                ->select([
                    $db->quoteName('quotation_id'),
                    $db->quoteName('descripcion'),
                    'COALESCE(' . $db->quoteName($amountCol) . ', 0) AS amount',
                ])
                ->from($db->quoteName('#__ordenproduccion_quotation_items'))
                ->where($db->quoteName('quotation_id') . ' IN (' . implode(',', $quotationIds) . ')')
                ->where($db->quoteName('descripcion') . ' LIKE ' . $db->quote(self::LINE_DESC_PREFIX . '%'));
            $db->setQuery($q);

            $map = [];
            foreach ($db->loadObjectList() ?: [] as $row) {
                $preId = self::parseImpuestoLineForPreId((string) ($row->descripcion ?? ''));
                if ($preId < 1) {
                    continue;
                }
                $key       = (int) ($row->quotation_id ?? 0) . ':' . $preId;
                $map[$key] = round((float) ($row->amount ?? 0), 2);
            }

            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
