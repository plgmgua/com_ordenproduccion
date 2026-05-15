<?php

/**
 * Detects mismatches between the active FEL ambiente (test|prod) and Digifact certify URL hostnames.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.60
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Guards against issuing with production mode while URLs still target Digifact QA (testnuc…) or the inverse.
 */
final class CertificadorDigifactAmbienteHelper
{
    /**
     * Every configured NUC certification base URL (FACT, NIT transform).
     *
     * @param   array<string, string>  $creds  Stored certificador url_cert_cf / url_cert_nit (optional)
     *
     * @return  list<string>
     */
    public static function collectConfiguredCertificarBaseUrls(array $creds): array
    {
        $out = [];
        foreach (['url_cert_cf', 'url_cert_nit'] as $k) {
            $u = trim((string) ($creds[$k] ?? ''));
            if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL)) {
                $out[] = $u;
            }
        }

        return $out;
    }

    /**
     * First valid certification host (same family as {@see FelInvoiceIssuanceService::buildDigifactCertificarRequestUrl}).
     *
     * @param   array<string, string>  $creds  url_cert_* (trimmed by caller OK)
     */
    public static function resolveCertificarBaseHost(array $creds): string
    {
        foreach (self::collectConfiguredCertificarBaseUrls($creds) as $base) {
            $parts = parse_url($base);
            if ($parts !== false && !empty($parts['host'])) {
                return strtolower((string) $parts['host']);
            }
        }

        return '';
    }

    /**
     * Common Digifact Guatemala QA host pattern (transform/nuc_json on test stacks).
     */
    public static function gtHostnameLooksLikeDigifactTest(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }
        if (str_contains($host, 'testnuc')) {
            return true;
        }
        if (preg_match('/^test[0-9a-z.-]*\.digifact\.com$/', $host) === 1) {
            return true;
        }

        return str_contains($host, 'digifact')
            && (str_contains($host, 'staging') || str_contains($host, 'sandbox'));
    }

    /**
     * Typical Guatemala production NUC API host (narrow — avoids blocking uncommon but valid QA hostnames).
     */
    public static function gtHostnameLooksLikeDigifactProdNucGt(string $host): bool
    {
        $h = strtolower(trim($host));

        return $h === 'nucgt.digifact.com' || $h === 'www.nucgt.digifact.com';
    }

    /**
     * Violation Joomla language constant (message key) if hostname disagrees with selected modo for NUC POST.
     */
    public static function nucCertifyHostViolatesModo(string $host, string $modo): ?string
    {
        $modo = $modo === 'prod' ? 'prod' : 'test';
        if ($host === '') {
            return null;
        }
        if ($modo === 'prod' && self::gtHostnameLooksLikeDigifactTest($host)) {
            return 'COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_URL_MODO_PROD_USES_TEST_HOST';
        }
        if ($modo === 'test' && self::gtHostnameLooksLikeDigifactProdNucGt($host)) {
            return 'COM_ORDENPRODUCCION_CERTIFICADOR_DIGIFACT_URL_MODO_TEST_USES_PROD_HOST';
        }

        return null;
    }

    /**
     * Message key from stored/pending cert URLs for the given modo, or null if OK / no URL.
     *
     * @param   array<string, string>  $creds  Same shape as getCertificadorFactSettings* row
     */
    public static function nucCertifyCredsViolateModo(array $creds, string $modo): ?string
    {
        $modo = $modo === 'prod' ? 'prod' : 'test';
        foreach (self::collectConfiguredCertificarBaseUrls($creds) as $url) {
            $parts = parse_url($url);
            if ($parts === false || empty($parts['host'])) {
                continue;
            }
            $host = strtolower((string) $parts['host']);
            $v    = self::nucCertifyHostViolatesModo($host, $modo);
            if ($v !== null) {
                return $v;
            }
        }

        return null;
    }
}
