<?php
/**
 * Blink payment gateway configuration (env vars override component params).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\Registry\Registry;

/**
 * Resolves Blink / Pay Bi credentials for server-side gateway calls only.
 *
 * @since  3.119.129
 */
class BlinkGatewayConfigHelper
{
    public const DEFAULT_BASE_URL = 'http://blink.grupoimpre.com:3000';

    /**
     * @return  Registry
     */
    public static function getParams(): Registry
    {
        return ComponentHelper::getParams('com_ordenproduccion');
    }

    /**
     * @return  bool
     */
    public static function isEnabled(): bool
    {
        $env = getenv('BLINK_ENABLED');
        if ($env !== false && $env !== '') {
            return \in_array(strtolower((string) $env), ['1', 'true', 'yes', 'on'], true);
        }

        return (int) self::getParams()->get('blink_enabled', 0) === 1;
    }

    /**
     * @return  string
     */
    public static function getBaseUrl(): string
    {
        $url = self::resolveString('BLINK_BASE_URL', 'blink_base_url', self::DEFAULT_BASE_URL);

        return rtrim($url, '/');
    }

    /**
     * @return  string
     */
    public static function getApiKey(): string
    {
        return self::resolveSecret('BLINK_API_KEY', 'blink_api_key');
    }

    /**
     * @return  string
     */
    public static function getPayBiUsuario(): string
    {
        return self::resolveSecret('PAYBI_USUARIO', 'blink_paybi_usuario');
    }

    /**
     * @return  string
     */
    public static function getPayBiClave(): string
    {
        return self::resolveSecret('PAYBI_CLAVE', 'blink_paybi_clave');
    }

    /**
     * @return  array{enabled: bool, base_url: string, api_key: string, usuario: string, clave: string, configured: bool}
     */
    public static function getSnapshot(): array
    {
        $baseUrl = self::getBaseUrl();
        $apiKey  = self::getApiKey();
        $usuario = self::getPayBiUsuario();
        $clave   = self::getPayBiClave();
        $enabled = self::isEnabled();

        return [
            'enabled'    => $enabled,
            'base_url'   => $baseUrl,
            'api_key'    => $apiKey,
            'usuario'    => $usuario,
            'clave'      => $clave,
            'configured' => $enabled
                && $baseUrl !== ''
                && $apiKey !== ''
                && $usuario !== ''
                && $clave !== '',
        ];
    }

    /**
     * Map installment months to Blink / Pay Bi VC code.
     *
     * @param   int  $cuotas  0 or 1 = single payment
     *
     * @return  string
     */
    public static function cuotasToInstallmentCode(int $cuotas): string
    {
        if ($cuotas <= 1) {
            return 'VC00';
        }

        $map = [
            2  => 'VC02',
            3  => 'VC03',
            6  => 'VC06',
            10 => 'VC10',
            12 => 'VC12',
            15 => 'VC15',
            18 => 'VC18',
            24 => 'VC24',
            36 => 'VC36',
        ];

        return $map[$cuotas] ?? 'VC00';
    }

    /**
     * Sanitize installments string (comma-separated VC codes).
     *
     * @param   string  $raw
     *
     * @return  string
     */
    public static function normalizeInstallments(string $raw): string
    {
        $allowed = ['VC00', 'VC02', 'VC03', 'VC06', 'VC10', 'VC12', 'VC15', 'VC18', 'VC24', 'VC36'];
        $parts   = preg_split('/\s*,\s*/', strtoupper(trim($raw)), -1, PREG_SPLIT_NO_EMPTY);
        $out     = [];

        if (\is_array($parts)) {
            foreach ($parts as $p) {
                if (\in_array($p, $allowed, true) && !\in_array($p, $out, true)) {
                    $out[] = $p;
                }
            }
        }

        return $out !== [] ? implode(',', $out) : 'VC00';
    }

    /**
     * @param   string  $envName
     * @param   string  $paramName
     * @param   string  $default
     *
     * @return  string
     */
    private static function resolveString(string $envName, string $paramName, string $default = ''): string
    {
        $env = getenv($envName);
        if ($env !== false && trim((string) $env) !== '') {
            return trim((string) $env);
        }

        $val = trim((string) self::getParams()->get($paramName, $default));

        return $val !== '' ? $val : $default;
    }

    /**
     * @param   string  $envName
     * @param   string  $paramName
     *
     * @return  string
     */
    private static function resolveSecret(string $envName, string $paramName): string
    {
        $env = getenv($envName);
        if ($env !== false && (string) $env !== '') {
            return (string) $env;
        }

        return trim((string) self::getParams()->get($paramName, ''));
    }
}
