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

    /**
     * True when description contains volante/volantes or afiche/afiches.
     */
    public static function descriptionMatches(string $description): bool
    {
        $description = trim($description);
        if ($description === '') {
            return false;
        }

        // Unicode-aware boundaries (PHP \b is ASCII-only even with /u).
        return (bool) preg_match('/(?<![\p{L}\p{N}_])(volantes?|afiches?)(?![\p{L}\p{N}_])/iu', $description);
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
     * Resolve pre-tax base, impuesto amount, and final line value (idempotent on re-save).
     *
     * @param   float  $minimumValor  Minimum allowed pre-tax valor final (pre-cot total / TC min).
     *
     * @return  array{valor_base: float, impuesto: float|null, valor_final: float}
     */
    public static function resolveLineValue(
        float $formValor,
        string $description,
        float $previousImpuesto = 0.0,
        float $minimumValor = 0.0
    ): array {
        $formValor        = round(max(0.0, $formValor), 2);
        $previousImpuesto = round(max(0.0, $previousImpuesto), 2);
        $minimumValor     = round(max(0.0, $minimumValor), 2);
        $valorBase        = $formValor;
        $pct              = self::getParamPercent();

        if ($previousImpuesto > 0 && $formValor >= $previousImpuesto) {
            $valorBase = round($formValor - $previousImpuesto, 2);
        } elseif (
            $previousImpuesto <= 0
            && $pct > 0
            && $minimumValor > 0
            && self::descriptionMatches($description)
            && self::formValorLooksTaxInclusive($formValor, $pct, $minimumValor)
        ) {
            // Recovery when tax was applied to the cotización but not stored on pre-cot (missing column).
            $valorBase = round($formValor / (1.0 + $pct / 100.0), 2);
        }

        if ($pct > 0 && self::descriptionMatches($description)) {
            $impuesto   = round($valorBase * ($pct / 100.0), 2);
            $valorFinal = round($valorBase + $impuesto, 2);

            return [
                'valor_base'  => $valorBase,
                'impuesto'    => $impuesto,
                'valor_final' => $valorFinal,
            ];
        }

        return [
            'valor_base'  => $valorBase,
            'impuesto'    => null,
            'valor_final' => $valorBase,
        ];
    }

    /**
     * True when form value is higher than min×(1+%) and equals base+tax for that %.
     */
    private static function formValorLooksTaxInclusive(float $formValor, float $pct, float $minimumValor): bool
    {
        if ($pct <= 0 || $formValor <= 0 || $minimumValor <= 0) {
            return false;
        }

        $minWithTax = round($minimumValor * (1.0 + $pct / 100.0), 2);
        if ($formValor <= $minWithTax + 0.005) {
            return false;
        }

        $base    = round($formValor / (1.0 + $pct / 100.0), 2);
        $tax     = round($base * ($pct / 100.0), 2);
        $rebuilt = round($base + $tax, 2);

        return $base + 0.001 >= $minimumValor && abs($rebuilt - $formValor) <= 0.02;
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
