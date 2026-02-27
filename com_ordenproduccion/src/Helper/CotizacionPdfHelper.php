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
 * User profile fields use Joomla custom field names: numero-de-celular, puesto-laboral, departamento, telefono, agente-de-ventas.
 */
class CotizacionPdfHelper
{
    /** Placeholder: número de cotización */
    public const PLACEHOLDER_NUMERO_COTIZACION = '{NUMERO_COTIZACION}';

    /** Placeholder: nombre del agente de ventas (usuario conectado o sales_agent_name en contexto) */
    public const PLACEHOLDER_AGENTE_VENTAS = '{AGENTE_VENTAS}';

    /** Placeholder: campo perfil agente-de-ventas */
    public const PLACEHOLDER_AGENTE_DE_VENTAS_CAMPO = '{AGENTE_DE_VENTAS_CAMPO}';

    /** Placeholder: número de celular (campo perfil numero-de-celular) */
    public const PLACEHOLDER_CELULAR = '{CELULAR}';

    /** Placeholder: puesto laboral (campo perfil puesto-laboral) */
    public const PLACEHOLDER_PUESTO = '{PUESTO}';

    /** Placeholder: departamento (campo perfil departamento) */
    public const PLACEHOLDER_DEPARTAMENTO = '{DEPARTAMENTO}';

    /** Placeholder: teléfono (campo perfil telefono) */
    public const PLACEHOLDER_TELEFONO = '{TELEFONO}';

    /** Joomla custom field names for user profile (Users: Fields). */
    private const USER_FIELD_CELULAR = 'numero-de-celular';
    private const USER_FIELD_PUESTO = 'puesto-laboral';
    private const USER_FIELD_DEPARTAMENTO = 'departamento';
    private const USER_FIELD_TELEFONO = 'telefono';
    private const USER_FIELD_AGENTE_DE_VENTAS = 'agente-de-ventas';

    /**
     * Get placeholder keys and their language labels for the Ajustes UI.
     *
     * @return  array  [ 'placeholder' => 'Label constant or text', ... ]
     */
    public static function getPlaceholdersForUi()
    {
        return [
            self::PLACEHOLDER_NUMERO_COTIZACION   => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_NUMERO_COTIZACION',
            self::PLACEHOLDER_AGENTE_VENTAS       => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_AGENTE_VENTAS',
            self::PLACEHOLDER_AGENTE_DE_VENTAS_CAMPO => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_AGENTE_DE_VENTAS_CAMPO',
            self::PLACEHOLDER_CELULAR             => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_CELULAR',
            self::PLACEHOLDER_PUESTO              => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_PUESTO',
            self::PLACEHOLDER_DEPARTAMENTO       => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_DEPARTAMENTO',
            self::PLACEHOLDER_TELEFONO           => 'COM_ORDENPRODUCCION_COTIZACION_PDF_VAR_TELEFONO',
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

        $agenteDeVentasCampo = $user ? self::getUserCustomField($user, self::USER_FIELD_AGENTE_DE_VENTAS) : '';
        $celular     = $user ? self::getUserCustomField($user, self::USER_FIELD_CELULAR) : '';
        $puesto      = $user ? self::getUserCustomField($user, self::USER_FIELD_PUESTO) : '';
        $departamento = $user ? self::getUserCustomField($user, self::USER_FIELD_DEPARTAMENTO) : '';
        $telefono    = $user ? self::getUserCustomField($user, self::USER_FIELD_TELEFONO) : '';

        $replacements = [
            self::PLACEHOLDER_NUMERO_COTIZACION   => $numeroCotizacion,
            self::PLACEHOLDER_AGENTE_VENTAS       => $agenteVentas,
            self::PLACEHOLDER_AGENTE_DE_VENTAS_CAMPO => $agenteDeVentasCampo,
            self::PLACEHOLDER_CELULAR             => $celular,
            self::PLACEHOLDER_PUESTO              => $puesto,
            self::PLACEHOLDER_DEPARTAMENTO        => $departamento,
            self::PLACEHOLDER_TELEFONO            => $telefono,
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
     * Use the field "name" as in Users: Fields (e.g. numero-de-celular, puesto-laboral).
     *
     * @param   User    $user
     * @param   string  $fieldName  Field name (e.g. numero-de-celular, puesto-laboral)
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
