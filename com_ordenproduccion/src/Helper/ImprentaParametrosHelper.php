<?php
/**
 * Imprenta (printing) percentage parameters required for pre-cotización pricing.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.140
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

class ImprentaParametrosHelper
{
    /**
     * Component param keys => language label keys.
     *
     * @return  array<string, string>
     */
    public static function getRequiredParamDefinitions(): array
    {
        return [
            'margen_ganancia'          => 'COM_ORDENPRODUCCION_PARAM_MARGEN_GANANCIA',
            'iva'                      => 'COM_ORDENPRODUCCION_PARAM_IVA',
            'isr'                      => 'COM_ORDENPRODUCCION_PARAM_ISR',
            'comision_venta'           => 'COM_ORDENPRODUCCION_PARAM_COMISION_VENTA',
            'comision_margen_adicional' => 'COM_ORDENPRODUCCION_PARAM_COMISION_MARGEN_ADICIONAL',
        ];
    }

    /**
     * @return  array<string, float>
     */
    public static function getValues(?Registry $params = null): array
    {
        $params = $params ?? ComponentHelper::getParams('com_ordenproduccion');
        $values = [];
        foreach (array_keys(self::getRequiredParamDefinitions()) as $key) {
            $values[$key] = (float) $params->get($key, 0);
        }

        return $values;
    }

    /**
     * True when every required percentage is greater than zero.
     */
    public static function areConfiguredForPreCotizacion(?Registry $params = null): bool
    {
        return self::getMissingParamKeys($params) === [];
    }

    /**
     * Param keys that are missing (<= 0).
     *
     * @return  list<string>
     */
    public static function getMissingParamKeys(?Registry $params = null): array
    {
        $missing = [];
        foreach (self::getValues($params) as $key => $value) {
            if ($value <= 0) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    /**
     * Human-readable labels for missing params (translated when possible).
     *
     * @return  list<string>
     */
    public static function getMissingParamLabels(?Registry $params = null): array
    {
        $labels = [];
        foreach (self::getMissingParamKeys($params) as $key) {
            $labels[] = self::getParamLabel($key);
        }

        return $labels;
    }

    /**
     * Comma-separated missing labels for messages.
     */
    public static function getMissingParamLabelsCsv(?Registry $params = null): string
    {
        return implode(', ', self::getMissingParamLabels($params));
    }

    /**
     * Warning for admins on Parámetros screen or when saving zeros.
     */
    public static function getAdminWarningMessage(?Registry $params = null): string
    {
        $csv = self::getMissingParamLabelsCsv($params);
        $msg = Text::sprintf('COM_ORDENPRODUCCION_PARAMETROS_MISSING_ADMIN', $csv);
        if ($msg === 'COM_ORDENPRODUCCION_PARAMETROS_MISSING_ADMIN' || $csv === '') {
            return 'Algunos parámetros de imprenta están en 0 o vacíos: ' . $csv
                . '. Revise Administración de Imprenta → Parámetros. Mientras tanto no se pueden crear nuevas pre-cotizaciones.';
        }

        return $msg;
    }

    /**
     * Warning shown when a user tries to create a pre-cotización while params are invalid.
     */
    public static function getPreCotizacionBlockedMessage(?Registry $params = null): string
    {
        $csv = self::getMissingParamLabelsCsv($params);
        $msg = Text::sprintf('COM_ORDENPRODUCCION_PRE_COTIZACION_BLOCKED_PARAMETROS', $csv);
        if ($msg === 'COM_ORDENPRODUCCION_PRE_COTIZACION_BLOCKED_PARAMETROS' || $csv === '') {
            return 'No se puede crear la pre-cotización: los parámetros de imprenta no están configurados (' . $csv . ').';
        }

        return $msg;
    }

    private static function getParamLabel(string $key): string
    {
        $defs = self::getRequiredParamDefinitions();
        $langKey = $defs[$key] ?? $key;
        $label   = Text::_($langKey);

        return ($label === $langKey) ? $key : $label;
    }
}
