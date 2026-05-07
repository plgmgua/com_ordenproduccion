<?php

/**
 * Digifact-style SHARED NIT info (GET + raw JWT in Authorization header).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.11
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Digifact NUC SHARED GET: query params COUNTRY, TAXID, USERNAME, DATA1, DATA2.
 * Authorization header is the raw JWT (no "Bearer " prefix), matching Digifact tooling.
 */
class CertificadorFactNitLookupHelper
{
    /**
     * Strip optional "Bearer " and whitespace (Digifact samples use raw JWT only).
     */
    protected static function normalizeAuthorizationToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (stripos($token, 'Bearer ') === 0) {
            $token = trim(substr($token, 7));
        }

        return $token;
    }

    /**
     * NUC expects TAXID zero-padded (e.g. 114441782 → 000114441782).
     */
    protected static function normalizeTaxIdForDigifactQuery(string $taxId): string
    {
        $taxId = trim($taxId);
        if ($taxId === '') {
            return '';
        }
        $digits = preg_replace('/\D/', '', $taxId) ?? '';
        if ($digits === '') {
            return $taxId;
        }
        if (\strlen($digits) >= 12) {
            return $digits;
        }

        return str_pad($digits, 12, '0', STR_PAD_LEFT);
    }

    /**
     * Printable curl (GET) matching the PHP request (for debugging).
     */
    protected static function formatCurlDebugCommand(string $url, string $rawJwt, bool $revealFullToken): string
    {
        $auth = $revealFullToken ? $rawJwt : (strlen($rawJwt) > 36
            ? substr($rawJwt, 0, 16) . '…REDACTED…' . substr($rawJwt, -12)
            : 'REDACTED');

        return 'curl --location ' . escapeshellarg($url)
            . " \\\n--header " . escapeshellarg('Authorization: ' . $auth)
            . " \\\n--header " . escapeshellarg('Accept: application/json, text/plain, */*');
    }

    /**
     * Fetch taxpayer info for a client NIT (RTU-style response).
     *
     * @param   bool  $revealTokenInDebug  When true, curl debug lines include full JWT (POST digifact_debug=1).
     *
     * @return  array<string, mixed>
     */
    public static function fetchNitInfo(
        string $clientNit,
        string $sharedUrl,
        string $emissorTaxId,
        string $apiUsername,
        string $bearerToken,
        int $timeoutSec = 45,
        bool $revealTokenInDebug = false
    ): array {
        $empty = [
            'ok'          => false,
            'http_code'   => 0,
            'error'       => '',
            'name'        => '',
            'nit'         => '',
            'street'      => '',
            'city'        => '',
            'raw_excerpt' => '',
            'debug'       => ['attempts' => []],
        ];

        $sharedUrl     = trim($sharedUrl);
        $emissorTaxId  = self::normalizeTaxIdForDigifactQuery(trim($emissorTaxId));
        $apiUsername   = trim($apiUsername);
        $bearerToken   = self::normalizeAuthorizationToken(trim($bearerToken));
        $clientNit     = trim($clientNit);

        if ($sharedUrl === '' || !filter_var($sharedUrl, FILTER_VALIDATE_URL)) {
            $empty['error'] = 'invalid_shared_url';

            return $empty;
        }
        if ($emissorTaxId === '' || $apiUsername === '') {
            $empty['error'] = 'missing_certificador_tax_or_user';

            return $empty;
        }
        if ($bearerToken === '') {
            $empty['error'] = 'missing_bearer_token';

            return $empty;
        }
        if ($clientNit === '') {
            $empty['error'] = 'missing_client_nit';

            return $empty;
        }

        $cleanNit = preg_replace('/[^\dA-Za-z\-]/', '', $clientNit) ?? '';
        if ($cleanNit === '') {
            $empty['error'] = 'invalid_client_nit';

            return $empty;
        }

        $parts = parse_url($sharedUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            $empty['error'] = 'invalid_shared_url';

            return $empty;
        }

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        $queryParams['COUNTRY']  = 'GT';
        $queryParams['TAXID']    = $emissorTaxId;
        $queryParams['USERNAME'] = $apiUsername;
        $queryParams['DATA2']    = 'NIT|' . $cleanNit;

        if (!\function_exists('curl_init')) {
            $empty['error'] = 'curl_required';

            return $empty;
        }

        // Try SHARED_GETINFONITcom first (Digifact NUC); optional .com suffix second (some gateways differ).
        $data1Variants = ['SHARED_GETINFONITcom', 'SHARED_GETINFONIT.com'];
        $decoded       = null;
        $rawBody       = '';
        $httpCode      = 0;
        $mime          = '';
        $excerpt       = '';
        $debugAttempts = [];

        foreach ($data1Variants as $data1Val) {
            $queryParams['DATA1'] = $data1Val;
            $builtQuery           = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
            $path                 = isset($parts['path']) ? $parts['path'] : '/';
            $url                  = $parts['scheme'] . '://' . $parts['host']
                . (isset($parts['port']) ? ':' . $parts['port'] : '')
                . $path
                . '?' . $builtQuery;

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                \CURLOPT_HTTPGET         => true,
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_FOLLOWLOCATION => true,
                \CURLOPT_CONNECTTIMEOUT => 15,
                \CURLOPT_TIMEOUT        => max(5, min(120, $timeoutSec)),
                \CURLOPT_SSL_VERIFYPEER => true,
                \CURLOPT_SSL_VERIFYHOST => 2,
                \CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . $bearerToken,
                    'Accept: application/json, text/plain, */*',
                ],
            ]);
            $rawBody  = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $curlErr  = (string) curl_error($ch);
            $mime     = (string) curl_getinfo($ch, \CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            $debugAttempts[] = [
                'DATA1'        => $data1Val,
                'url'          => $url,
                'http_code'    => $httpCode,
                'content_type' => trim($mime),
                'query_params' => $queryParams,
                'curl'         => self::formatCurlDebugCommand($url, $bearerToken, $revealTokenInDebug),
            ];

            if ($rawBody === false) {
                $empty['http_code'] = $httpCode;
                $empty['error']     = $curlErr !== '' ? $curlErr : 'curl_exec_failed';
                $empty['debug']     = [
                    'attempts' => $debugAttempts,
                    'note'     => $revealTokenInDebug ? '' : 'POST digifact_debug=1 with the verify request to show the full JWT in curl.',
                ];

                return $empty;
            }

            $rawBody = (string) $rawBody;
            $excerpt = self::excerptBody($rawBody);
            $decoded = self::decodeJsonResponseBody($rawBody);

            if (\is_array($decoded)) {
                break;
            }
        }

        $debugBlock = [
            'attempts' => $debugAttempts,
            'note'     => $revealTokenInDebug ? '' : 'POST digifact_debug=1 with the verify request to show the full JWT in curl.',
        ];

        if (!\is_array($decoded)) {
            $empty['http_code']   = $httpCode;
            $empty['raw_excerpt'] = $excerpt;
            $mimeTrim             = trim($mime);
            $extra                = $mimeTrim !== '' ? ('; ' . $mimeTrim) : '';
            $empty['error']       = sprintf(
                'invalid_json (HTTP %d%s): %s',
                $httpCode,
                $extra,
                $excerpt
            );
            $empty['debug']       = $debugBlock;

            return $empty;
        }

        $req0 = null;
        if (isset($decoded['REQUEST_DATA'][0])) {
            $req0 = $decoded['REQUEST_DATA'][0];
        } elseif (isset($decoded['Request_Data'][0])) {
            $req0 = $decoded['Request_Data'][0];
        }

        $respOk = false;
        if (\is_array($req0)) {
            $respOk = (int) ($req0['Respuesta'] ?? 0) === 1 && (int) ($req0['Codigo'] ?? 0) === 1;
        }

        $row = null;
        if (isset($decoded['RESPONSE'][0])) {
            $row = $decoded['RESPONSE'][0];
        } elseif (isset($decoded['Response'][0])) {
            $row = $decoded['Response'][0];
        }

        if (!$respOk || !\is_array($row)) {
            $empty['http_code']   = $httpCode;
            $empty['raw_excerpt'] = $excerpt;
            $msg                  = '';
            if (\is_array($req0)) {
                $msg = trim((string) ($req0['Mensaje'] ?? ''));
                if ($msg === '') {
                    $msg = trim((string) ($req0['Descripcion'] ?? ''));
                }
            }
            $empty['error'] = $msg !== '' ? $msg : ($httpCode >= 400 ? 'http_' . $httpCode : 'nit_lookup_failed');
            $empty['debug'] = $debugBlock;

            return $empty;
        }

        $nombre = trim((string) ($row['NOMBRE'] ?? $row['Nombre'] ?? ''));
        $nitOut = trim((string) ($row['NIT'] ?? $row['Nit'] ?? $cleanNit));
        $dir    = trim((string) ($row['Direccion'] ?? $row['DIRECCION'] ?? ''));
        $dept   = trim((string) ($row['DEPARTAMENTO'] ?? $row['Departamento'] ?? ''));
        $muni   = trim((string) ($row['MUNICIPIO'] ?? $row['Municipio'] ?? ''));
        $city   = trim(trim($dept) . ' ' . trim($muni));

        return [
            'ok'          => true,
            'http_code'   => $httpCode,
            'error'       => '',
            'name'        => $nombre,
            'nit'         => $nitOut,
            'street'      => $dir,
            'city'        => $city,
            'raw_excerpt' => '',
            'debug'       => $debugBlock,
        ];
    }

    /**
     * Decode API body: trim/BOM, optional UTF-8 fix, extract embedded JSON object.
     *
     * @return  array<string, mixed>|null
     */
    protected static function decodeJsonResponseBody(string $rawBody): ?array
    {
        $rawBody = trim($rawBody);
        if ($rawBody === '') {
            return null;
        }
        if (strpos($rawBody, "\xEF\xBB\xBF") === 0) {
            $rawBody = substr($rawBody, 3);
        }

        $flags = 0;
        if (\defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= \JSON_INVALID_UTF8_SUBSTITUTE;
        }

        $decoded = json_decode($rawBody, true, 512, $flags);
        if (\is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($rawBody, '{');
        if ($start !== false) {
            $slice = substr($rawBody, $start);
            $decoded = json_decode($slice, true, 512, $flags);
            if (\is_array($decoded)) {
                return $decoded;
            }

            $depth = 0;
            $len   = \strlen($slice);
            $end   = -1;
            for ($i = 0; $i < $len; $i++) {
                $c = $slice[$i];
                if ($c === '{') {
                    $depth++;
                } elseif ($c === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $i;
                        break;
                    }
                }
            }
            if ($end >= 0) {
                $json = substr($slice, 0, $end + 1);
                $decoded = json_decode($json, true, 512, $flags);
                if (\is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    protected static function excerptBody(string $raw, int $max = 400): string
    {
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw) ?? '';
        if (\function_exists('mb_substr')) {
            return mb_substr($raw, 0, $max, 'UTF-8');
        }

        return substr($raw, 0, $max);
    }
}
