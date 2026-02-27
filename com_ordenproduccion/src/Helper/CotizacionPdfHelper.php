<?php
/**
 * Helper for cotización PDF template placeholders.
 * Replaces variables in Encabezado, Términos y Condiciones, Pie de página when generating the PDF.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;

/**
 * Placeholders supported in Ajustes de Cotización (PDF template).
 * Use these in Encabezado, Términos y Condiciones, Pie de página; they are replaced when generating the PDF.
 */
class CotizacionPdfHelper
{
    /** Placeholder: número de cotización */
    public const PLACEHOLDER_NUMERO_COTIZACION = '{NUMERO_COTIZACION}';

    /** Placeholder: nombre del agente de ventas (usuario conectado o asignado) */
    public const PLACEHOLDER_AGENTE_VENTAS = '{AGENTE_VENTAS}';

    /** Placeholder: número de celular (campo personalizado del perfil de usuario, nombre del campo: celular) */
    public const PLACEHOLDER_CELULAR = '{CELULAR}';

    /** Placeholder: puesto (campo personalizado del perfil de usuario, nombre del campo: puesto) */
    public const PLACEHOLDER_PUESTO = '{PUESTO}';

    /**
     * Get placeholder keys and their language labels for the Ajustes UI.
     *
     * @return  array  [ 'placeholder' => 'Label constant or text', ... ]
     */
    public static function getPlaceholdersForUi()
    {
        return [
            self::PLACEHOLDER_NUMERO_COTIZACION => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_NUMERO_COTIZACION',
            self::PLACEHOLDER_AGENTE_VENTAS    => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_AGENTE_VENTAS',
            self::PLACEHOLDER_CELULAR           => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_CELULAR',
            self::PLACEHOLDER_PUESTO            => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_PUESTO',
        ];
    }

    /**
     * Replace placeholders in HTML/text with actual values.
     *
     * @param   string       $html     Content (Encabezado, Términos o Pie) that may contain placeholders.
     * @param   array        $context  Optional. Keys: numero_cotizacion (string), user (User or int user id),
     *                                 sales_agent_name (string, overrides user name when set).
     * @return  string  Content with placeholders replaced.
     */
    public static function replacePlaceholders($html, array $context = [])
    {
        $numeroCotizacion = isset($context['numero_cotizacion']) ? (string) $context['numero_cotizacion'] : '';
        $user = self::resolveUser($context);
        $agenteVentas = isset($context['sales_agent_name']) && $context['sales_agent_name'] !== ''
            ? (string) $context['sales_agent_name']
            : ($user ? $user->get('name') : '');
        $celular = $user ? self::getUserCustomField($user, 'celular') : '';
        $puesto  = $user ? self::getUserCustomField($user, 'puesto') : '';

        $replacements = [
            self::PLACEHOLDER_NUMERO_COTIZACION => $numeroCotizacion,
            self::PLACEHOLDER_AGENTE_VENTAS    => $agenteVentas,
            self::PLACEHOLDER_CELULAR           => $celular,
            self::PLACEHOLDER_PUESTO            => $puesto,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), (string) $html);
    }

    /**
     * Resolve user from context (User object or user id).
     *
     * @param   array  $context
     * @return  User|null
     */
    private static function resolveUser(array $context)
    {
        if (isset($context['user'])) {
            $u = $context['user'];
            if ($u instanceof User) {
                return $u;
            }
            $id = (int) $u;
            if ($id > 0) {
                return Factory::getContainer()->get(\Joomla\CMS\User\UserFactoryInterface::class)->loadUserById($id);
            }
            return null;
        }
        return Factory::getUser();
    }

    /**
     * Get a custom user profile field value by field name (com_users.user custom fields).
     * Field names in Joomla are the "name" of the field (e.g. celular, puesto).
     *
     * @param   User    $user
     * @param   string  $fieldName  Field name (e.g. celular, puesto)
     * @return  string
     */
    public static function getUserCustomField(User $user, $fieldName)
    {
        $fieldName = (string) $fieldName;
        if ($fieldName === '') {
            return '';
        }
        if (!class_exists(\Joomla\Component\Fields\Administrator\Helper\FieldsHelper::class)) {
            return '';
        }
        try {
            $fields = \Joomla\Component\Fields\Administrator\Helper\FieldsHelper::getFields('com_users.user', $user, true);
            if (!is_array($fields)) {
                return '';
            }
            foreach ($fields as $field) {
                if (isset($field->name) && $field->name === $fieldName && isset($field->value)) {
                    return is_string($field->value) ? $field->value : (string) $field->value;
                }
            }
        } catch (\Throwable $e) {
            return '';
        }
        return '';
    }
}
