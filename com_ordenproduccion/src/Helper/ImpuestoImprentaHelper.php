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
        $desc = \is_array($item)
            ? (string) ($item['descripcion'] ?? '')
            : (string) ($item->descripcion ?? '');

        return self::parseImpuestoLineForPreId($desc) > 0;
    }

    /**
     * Human-readable label for display (cotización view / edit preview).
     */
    public static function getImpuestoLineDisplayLabel(int $forPreCotizacionId, string $preNumber = ''): string
    {
        $label = Text::_('COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA');
        if ($label === 'COM_ORDENPRODUCCION_PARAM_IMPUESTO_IMPRENTA') {
            $label = 'Impuesto de imprenta';
        }
        $preNumber = trim($preNumber);
        if ($preNumber === '' && $forPreCotizacionId > 0) {
            $preNumber = 'PRE-' . $forPreCotizacionId;
        }

        return $preNumber !== '' ? ($label . ' — ' . $preNumber) : $label;
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
}
