<?php
/**
 * Impuesto de imprenta: % param applied to cotización lines mentioning volante/afiche.
 *
 * @package     com_ordenproduccion
 * @since       3.119.249
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * @since  3.119.249
 */
final class ImpuestoImprentaHelper
{
    /** @var bool|null */
    private static $columnReady;

    private function __construct()
    {
    }

    /**
     * Ensure `#__ordenproduccion_pre_cotizacion.impuesto_imprenta` exists (migration may not have run yet).
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
                . " COMMENT 'Impuesto de imprenta (volante/afiche) amount from param %'"
            );
            $db->execute();

            return self::$columnReady = true;
        } catch (\Throwable $e) {
            // Re-check: concurrent create or column already present under another error.
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

    /**
     * Default keywords when the param has never been saved.
     *
     * @return  string[]
     */
    public static function getDefaultKeywords(): array
    {
        return self::DEFAULT_KEYWORDS;
    }

    /**
     * Normalize keywords from JSON POST input or stored param value.
     *
     * @param   mixed  $raw  JSON array string, array, or comma-separated text
     *
     * @return  string[]
     */
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

            $seen[$key]       = true;
            $normalized[]     = $keyword;
        }

        return $normalized;
    }

    /**
     * Parse stored component param into keyword list.
     *
     * @return  string[]
     */
    public static function parseKeywordsParam($raw): array
    {
        return self::normalizeKeywordsFromInput($raw);
    }

    /**
     * Keywords/phrases from Parámetros; defaults when param was never saved.
     *
     * @return  string[]
     */
    public static function getConfiguredKeywords(): array
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $raw    = $params->get('impuesto_imprenta_palabras', null);

        if ($raw === null || $raw === '') {
            return self::getDefaultKeywords();
        }

        return self::parseKeywordsParam($raw);
    }

    /**
     * True when description matches any configured keyword or phrase.
     */
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

    /**
     * Match cotización line description, pre-cotización header, and/or line text (vendor lines, etc.).
     */
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

    /**
     * Concatenated line-level text from a pre-cotización (vendor_descripcion, etc.) for keyword matching.
     */
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
            $rows = $db->loadObjectList() ?: [];
            $parts  = [];

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

    /**
     * Minimum valor base for a cotización line (excludes impuesto already baked into total_con_tarjeta).
     */
    public static function getMinimumValorBaseForPreCot(int $preCotizacionId, float $minimumValorFinal): float
    {
        $minimumValorFinal = round(max(0.0, $minimumValorFinal), 2);
        $storedImpuesto    = self::getStoredAmount($preCotizacionId);

        if ($storedImpuesto > 0) {
            return round(max(0.0, $minimumValorFinal - $storedImpuesto), 2);
        }

        return $minimumValorFinal;
    }

    /**
     * Pre-cotización header descriptions keyed by id (for cotización save / form display).
     *
     * @param   int[]  $preIds
     *
     * @return  array<int, string>
     */
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
     * Valor base to show in the cotización edit form (input is pre-tax; impuesto is added on save).
     */
    public static function extractValorBaseForForm(
        float $lineTotalFinal,
        float $storedImpuesto,
        string $description,
        string $preCotDescription = '',
        string $preCotLineText = ''
    ): float {
        $lineTotalFinal = round(max(0.0, $lineTotalFinal), 2);
        $storedImpuesto = round(max(0.0, $storedImpuesto), 2);

        if ($storedImpuesto > 0) {
            return round(max(0.0, $lineTotalFinal - $storedImpuesto), 2);
        }

        $pct = self::getParamPercent();
        if ($pct > 0 && self::descriptionMatchesForQuotationLine($description, $preCotDescription, $preCotLineText)) {
            return round($lineTotalFinal / (1.0 + $pct / 100.0), 2);
        }

        return $lineTotalFinal;
    }

    /**
     * Phrases: case-insensitive substring. Single words: Unicode word boundaries.
     */
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

    /**
     * Configured percentage (0–100). 0 disables the tax.
     */
    public static function getParamPercent(): float
    {
        $pct = (float) ComponentHelper::getParams('com_ordenproduccion')->get('impuesto_imprenta', 0);

        return max(0.0, min(100.0, $pct));
    }

    /**
     * Stored amount on pre-cotización (0 if none / column missing).
     */
    public static function getStoredAmount(int $preCotizacionId, ?DatabaseInterface $db = null): float
    {
        if ($preCotizacionId < 1) {
            return 0.0;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        if (!self::ensureColumn($db)) {
            return 0.0;
        }

        try {
            $query = $db->getQuery(true)
                ->select($db->quoteName('impuesto_imprenta'))
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . (int) $preCotizacionId);
            $db->setQuery($query);
            $raw = $db->loadResult();
            if ($raw === null || $raw === '') {
                return 0.0;
            }

            return round((float) $raw, 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Resolve pre-tax base, impuesto amount, and final line value.
     *
     * The cotización form posts `value` as valor base (pre-impuesto). Impuesto is computed here
     * and added to valor_final on save.
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
        $pct       = self::getParamPercent();

        if ($pct > 0 && self::descriptionMatchesForQuotationLine($description, $preCotDescription, $preCotLineText)) {
            $valorBase  = $formValor;
            $impuesto   = round($valorBase * ($pct / 100.0), 2);
            $valorFinal = round($valorBase + $impuesto, 2);

            return [
                'valor_base'  => $valorBase,
                'impuesto'    => $impuesto,
                'valor_final' => $valorFinal,
            ];
        }

        return [
            'valor_base'  => $formValor,
            'impuesto'    => null,
            'valor_final' => $formValor,
        ];
    }

    /**
     * Persist margen_adicional / comisión / impuesto_imprenta for a quotation line's pre-cotización.
     *
     * Margen adicional is based on valor_base (before impuesto). Impuesto is stored separately.
     *
     * @param   float|null  $impuestoAmount  null clears the column
     */
    public static function syncPreCotizacionFromQuotationLine(
        int $preCotizacionId,
        float $valorBase,
        float $preTotal,
        ?float $impuestoAmount,
        ?DatabaseInterface $db = null
    ): void {
        if ($preCotizacionId < 1) {
            return;
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        self::ensureColumn($db);

        try {
            $pcCols = $db->getTableColumns('#__ordenproduccion_pre_cotizacion', false);
            $pcCols = \is_array($pcCols) ? array_change_key_case($pcCols, CASE_LOWER) : [];
        } catch (\Throwable $e) {
            return;
        }

        $hasMargen    = isset($pcCols['margen_adicional']);
        $hasComision  = isset($pcCols['comision_margen_adicional']);
        $hasImpuesto  = isset($pcCols['impuesto_imprenta']);

        if (!$hasMargen && !$hasImpuesto) {
            return;
        }

        try {
            $q = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . (int) $preCotizacionId);

            if ($hasMargen) {
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
            }

            if ($hasImpuesto) {
                if ($impuestoAmount !== null && $impuestoAmount > 0) {
                    $q->set($db->quoteName('impuesto_imprenta') . ' = ' . (float) round($impuestoAmount, 2));
                } else {
                    $q->set($db->quoteName('impuesto_imprenta') . ' = NULL');
                }
            }

            $db->setQuery($q)->execute();
        } catch (\Throwable $e) {
            // Columns may not exist until migration runs.
        }
    }
}
