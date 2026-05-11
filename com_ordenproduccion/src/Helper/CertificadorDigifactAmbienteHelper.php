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
     * Host picked for NUC certification (same precedence as FelInvoiceIssuanceService::buildDigifactCertificarRequestUrl).
     *
     * @param   array<string, string>  $creds  url_cert_cf, url_cert_nit (trimmed by caller OK)
     */
    public static function resolveCertificarBaseHost(array $creds): string
    {
        $base = trim((string) ($creds['url_cert_cf'] ?? ''));
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            $base = trim((string) ($creds['url_cert_nit'] ?? ''));
        }
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            return '';
        }
        $parts = parse_url($base);
        if ($parts === false || empty($parts['host'])) {
            return '';
        }

        return strtolower((string) $parts['host']);
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
        return strtolower(trim($host)) === 'nucgt.digifact.com';
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
     * Message key from stored/pending cert CF / NIT URLs for the given modo, or null if OK / URL missing.
     *
     * @param   array<string, string>  $creds  Same shape as getCertificadorFactSettings* row
     */
    public static function nucCertifyCredsViolateModo(array $creds, string $modo): ?string
    {
        $host = self::resolveCertificarBaseHost($creds);

        return self::nucCertifyHostViolatesModo($host, $modo);
    }
}
