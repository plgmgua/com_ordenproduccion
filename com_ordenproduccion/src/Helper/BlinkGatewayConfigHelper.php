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
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Resolves Blink / Pay Bi credentials for server-side gateway calls only.
 *
 * @since  3.119.129
 */
class BlinkGatewayConfigHelper
{
    public const DEFAULT_BASE_URL = 'http://blink.grupoimpre.com:3000';

    private const SECRET_CONFIG_KEYS = [
        'blink_api_key',
        'blink_paybi_clave',
        'blink_paybi_key',
        'blink_webhook_secret',
    ];

    public const DEFAULT_SOCIAL_NETWORK_CODE = '1621282737059942b';

    public const DEFAULT_SOCIAL_NETWORK_LABEL = 'Botón de Pago';

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

        return (int) self::getFreshParams()->get('blink_enabled', 0) === 1;
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
        return self::resolveSecret(
            ['BLINK_GATEWAY_API_KEY', 'BLINK_API_KEY', 'GATEWAY_API_KEY'],
            'blink_api_key'
        );
    }

    /**
     * Safe diagnostics for troubleshooting 401 (no full secret exposed).
     *
     * @return  array{configured: bool, length: int, suffix: string}
     */
    public static function getApiKeyDiagnostics(): array
    {
        $key = self::getApiKey();

        return [
            'configured' => $key !== '',
            'length'     => \strlen($key),
            'suffix'     => $key !== '' ? \substr($key, -4) : '',
        ];
    }

    /**
     * Load component language strings (site + admin paths).
     *
     * @return  void
     */
    public static function loadLanguage(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR);
    }

    /**
     * @return  string
     */
    public static function getPayBiUsuario(): string
    {
        return self::resolveSecret(['PAYBI_USUARIO'], 'blink_paybi_usuario');
    }

    /**
     * @return  string
     */
    public static function getPayBiClave(): string
    {
        return self::resolveSecret(['PAYBI_CLAVE'], 'blink_paybi_clave');
    }

    /**
     * EBI Pay Bi API key (40-char hex), sent as credentials.key when set.
     *
     * @return  string
     *
     * @since   3.119.204
     */
    public static function getPayBiKey(): string
    {
        return self::resolveSecret(['PAYBI_KEY'], 'blink_paybi_key');
    }

    /**
     * Pay Bi checkout channel code for link creation (omit from payload when default).
     *
     * @return  string  Pay Bi network code; empty = use Blink default (1621282737059942b).
     *
     * @since   3.119.204
     */
    public static function getSocialNetworkCode(): string
    {
        $code = self::resolveString('PAYBI_SOCIAL_NETWORK_CODE', 'blink_social_network_code', '');

        if ($code === '' || $code === self::DEFAULT_SOCIAL_NETWORK_CODE || $code === self::DEFAULT_SOCIAL_NETWORK_LABEL) {
            return '';
        }

        return $code;
    }

    /**
     * HMAC secret for Blink log.created webhook (X-Blink-Signature).
     *
     * @return  string
     *
     * @since   3.119.208
     */
    public static function getWebhookSecret(): string
    {
        return self::resolveSecret(['BLINK_WEBHOOK_SECRET'], 'blink_webhook_secret');
    }

    /**
     * Public HTTPS origin for Blink inbound webhooks (no trailing slash).
     *
     * @return  string
     *
     * @since   3.119.208
     */
    public static function getLogWebhookPublicRoot(): string
    {
        $raw = self::resolveString('BLINK_WEBHOOK_PUBLIC_BASE', 'blink_webhook_public_base', '');
        if ($raw === '') {
            return rtrim(Uri::root(), '/');
        }

        $raw = rtrim($raw, '/');
        $p   = @parse_url($raw);
        if (!\is_array($p) || empty($p['scheme']) || empty($p['host'])) {
            return rtrim(Uri::root(), '/');
        }
        if (strtolower((string) $p['scheme']) !== 'https') {
            return rtrim(Uri::root(), '/');
        }

        $host = (string) $p['host'];
        if ($host === '' || preg_match('/[<>\s]/u', $host)) {
            return rtrim(Uri::root(), '/');
        }

        $root = 'https://' . $host;
        if (!empty($p['port']) && (int) $p['port'] > 0 && (int) $p['port'] !== 443) {
            $root .= ':' . (int) $p['port'];
        }
        if (!empty($p['path']) && $p['path'] !== '/') {
            $root .= rtrim((string) $p['path'], '/');
        }

        return $root;
    }

    /**
     * Full POST URL for Blink log.created webhook subscription.
     *
     * @return  string
     *
     * @since   3.119.208
     */
    public static function getLogWebhookPublicUrl(): string
    {
        return self::getLogWebhookPublicRoot()
            . '/index.php?option=com_ordenproduccion&controller=blink&task=logWebhook&format=raw';
    }

    /**
     * Truncate Pay Bi title (max 100 chars).
     *
     * @param   string  $title
     *
     * @return  string
     *
     * @since   3.119.205
     */
    public static function truncatePaymentTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return '';
        }

        if (\strlen($title) > 100) {
            return \substr($title, 0, 97) . '...';
        }

        return $title;
    }

    /**
     * Truncate Pay Bi description (max 500 chars).
     *
     * @param   string  $description
     *
     * @return  string
     *
     * @since   3.119.205
     */
    public static function truncatePaymentDescription(string $description): string
    {
        $description = trim($description);
        if ($description === '') {
            return '';
        }

        if (\strlen($description) > 500) {
            return \substr($description, 0, 497) . '...';
        }

        return $description;
    }

    /**
     * @return  array{
     *   enabled: bool,
     *   base_url: string,
     *   api_key: string,
     *   api_key_set: bool,
     *   usuario: string,
     *   clave_set: bool,
     *   paybi_key_set: bool,
     *   social_network_code: string,
     *   webhook_secret_set: bool,
     *   webhook_url: string,
     *   credentials_configured: bool,
     *   configured: bool
     * }
     */
    public static function getSnapshot(): array
    {
        $baseUrl = self::getBaseUrl();
        $apiKey  = self::getApiKey();
        $usuario = self::getPayBiUsuario();
        $clave   = self::getPayBiClave();
        $payBiKey = self::getPayBiKey();
        $socialCode = self::getSocialNetworkCode();
        $webhookSecret = self::getWebhookSecret();
        $enabled = self::isEnabled();

        $credentialsConfigured = $baseUrl !== ''
            && $apiKey !== ''
            && $usuario !== ''
            && $clave !== '';

        return [
            'enabled'                => $enabled,
            'base_url'               => $baseUrl,
            'api_key'                => $apiKey,
            'api_key_set'            => $apiKey !== '',
            'usuario'                => $usuario,
            'clave_set'              => $clave !== '',
            'paybi_key_set'          => $payBiKey !== '',
            'social_network_code'    => $socialCode !== '' ? $socialCode : self::DEFAULT_SOCIAL_NETWORK_CODE,
            'social_network_label'   => self::DEFAULT_SOCIAL_NETWORK_LABEL,
            'webhook_secret_set'     => $webhookSecret !== '',
            'webhook_url'            => self::getLogWebhookPublicUrl(),
            'credentials_configured' => $credentialsConfigured,
            'configured'             => $enabled && $credentialsConfigured,
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

        $val = trim((string) self::getFreshParams()->get($paramName, $default));

        return $val !== '' ? $val : $default;
    }

    /**
     * @param   string|array<int, string>  $envNames
     * @param   string                     $paramName
     *
     * @return  string
     */
    private static function resolveSecret($envNames, string $paramName): string
    {
        foreach ((array) $envNames as $envName) {
            $env = getenv($envName);
            if ($env !== false && trim((string) $env) !== '') {
                return trim((string) $env);
            }
        }

        $val = trim((string) self::getFreshParams()->get($paramName, ''));
        if ($val !== '') {
            return $val;
        }

        if (\in_array($paramName, self::SECRET_CONFIG_KEYS, true)) {
            return self::loadConfigTableValue($paramName);
        }

        return '';
    }

    /**
     * Read component params directly from #__extensions (avoids stale ComponentHelper cache).
     *
     * @return  Registry
     */
    private static function getFreshParams(): Registry
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
            );

            return new Registry((string) $db->loadResult());
        } catch (\Throwable $e) {
            return self::getParams();
        }
    }

    /**
     * Fallback for secrets mirrored by BlinkConfigSaveHelper.
     *
     * @param   string  $settingKey
     *
     * @return  string
     */
    private static function loadConfigTableValue(string $settingKey): string
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('setting_value'))
                    ->from($db->quoteName('#__ordenproduccion_config'))
                    ->where($db->quoteName('setting_key') . ' = ' . $db->quote($settingKey))
            );

            return trim((string) $db->loadResult());
        } catch (\Throwable $e) {
            return '';
        }
    }
}
