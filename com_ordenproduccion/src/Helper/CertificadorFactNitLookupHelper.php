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
 * NIT: DATA1 SHARED_GETINFONIT*, DATA2 NIT|….
 * CUI: DATA1 SHARED_GETINFOCUI*, DATA2 CUI|….
 *
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
     * Ensure the URL path targets Digifact SHARED GET. Users often paste the NUC transform URL
     * (/api/v2/transform/nuc_json); that route is not valid for SHARED_GETINFONIT.
     *
     * Guatemala NUC production (and test stacks on *nucgt.digifact.com) use a site prefix:
     * `/gt.com.apinuc/api/SHARED` — not `/api/Shared`. Flat `/api/Shared` returns 404 there.
     *
     * Other regions / hosts keep the historical `/api/Shared` rewrite after stripping transform paths.
     */
    protected static function normalizeDigifactSharedUrlParts(array $parts): array
    {
        $path = isset($parts['path']) ? $parts['path'] : '/';
        $pn   = strtolower(str_replace('\\', '/', $path));
        $host = strtolower((string) ($parts['host'] ?? ''));

        $isDigifactGtNucHost = strpos($host, 'nucgt.digifact.com') !== false;
        $gtApinucSharedPath  = '/gt.com.apinuc/api/SHARED';

        if ($isDigifactGtNucHost) {
            $hasApinucShared = ($pn !== '' && strpos($pn, 'gt.com.apinuc') !== false && strpos($pn, 'shared') !== false);
            if ($hasApinucShared) {
                return $parts;
            }

            $fromTransform = strpos($pn, 'nuc_json') !== false
                || strpos($pn, '/api/v2/transform') !== false
                || strpos($pn, 'transform/nuc') !== false;
            $legacyFlatShared = $pn === '/api/shared'; // strtolower of /api/Shared
            $rootish          = $pn === '' || $pn === '/';

            if ($fromTransform || $legacyFlatShared || $rootish) {
                $parts['path'] = $gtApinucSharedPath;
            }

            return $parts;
        }

        // Other hosts: keep flat /api/Shared when rewriting off transform URLs
        if ($pn !== '' && strpos($pn, '/api/shared') !== false) {
            return $parts;
        }
        if (strpos($pn, 'nuc_json') !== false
            || strpos($pn, '/api/v2/transform') !== false
            || strpos($pn, 'transform/nuc') !== false) {
            $parts['path'] = '/api/Shared';
        }

        return $parts;
    }

    /**
     * Query keys from the transform endpoint are not valid on /api/Shared for NIT lookup.
     *
     * @param  array<string, mixed>  $queryParams
     */
    protected static function stripInvalidDigifactSharedNitKeys(array &$queryParams): void
    {
        foreach ([
            'FORMAT', 'format', 'TYPE', 'type', 'LAYOUT', 'layout', 'METHOD', 'method',
        ] as $k) {
            unset($queryParams[$k]);
        }
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
     * @return  array<string, mixed>
     */
    protected static function emptyLookupResult(): array
    {
        return [
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
    }

    /**
     * GET Shared until JSON parses; sets DATA1 per variant; DATA2 must already be on $queryParams.
     *
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>|null  $digifactLogContext  environment (test|prod), operation (e.g. shared_nit), quotation_id?
     * @return  array{decoded: array<string, mixed>|null, http_code: int, excerpt: string, mime: string, debug_block: array<string, mixed>, early: array<string, mixed>|null}
     */
    protected static function digifactSharedFetchUntilJsonParsed(
        array $parts,
        array $queryParams,
        array $data1Variants,
        string $bearerToken,
        int $timeoutSec,
        bool $revealTokenInDebug,
        ?array $digifactLogContext = null
    ): array {
        $decoded       = null;
        $rawBody       = '';
        $httpCode      = 0;
        $mime          = '';
        $excerpt       = '';
        $debugAttempts = [];

        foreach ($data1Variants as $data1Val) {
            $attemptT0            = microtime(true);
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

            if ($digifactLogContext !== null) {
                $dur = (int) round((microtime(true) - $attemptT0) * 1000);
                $env = (($digifactLogContext['environment'] ?? 'test') === 'prod') ? 'prod' : 'test';
                $op  = (string) ($digifactLogContext['operation'] ?? 'shared_lookup');
                $quo = isset($digifactLogContext['quotation_id']) ? (int) $digifactLogContext['quotation_id'] : 0;
                CertificadorDigifactLogHelper::record([
                    'environment'          => $env,
                    'operation'            => $op,
                    'request_method'       => 'GET',
                    'request_url'          => $url,
                    'request_headers_json' => json_encode(
                        ['Authorization' => '***REDACTED***', 'Accept' => 'application/json, text/plain, */*'],
                        JSON_UNESCAPED_UNICODE
                    ),
                    'request_body'         => CertificadorDigifactLogHelper::formatSharedRequestBodyForLog($queryParams),
                    'response_http_code'  => $httpCode,
                    'response_body'        => $rawBody !== false ? (string) $rawBody : '',
                    'client_error'         => ($rawBody === false && $curlErr !== '') ? $curlErr : '',
                    'duration_ms'          => $dur,
                    'quotation_id'         => $quo > 0 ? $quo : null,
                ]);
            }

            $debugAttempts[] = [
                'DATA1'        => $data1Val,
                'url'          => $url,
                'http_code'    => $httpCode,
                'content_type' => trim($mime),
                'query_params' => $queryParams,
                'curl'         => self::formatCurlDebugCommand($url, $bearerToken, $revealTokenInDebug),
            ];

            if ($rawBody === false) {
                $e = self::emptyLookupResult();
                $e['http_code'] = $httpCode;
                $e['error']     = $curlErr !== '' ? $curlErr : 'curl_exec_failed';
                $e['debug']     = [
                    'attempts' => $debugAttempts,
                    'note'     => $revealTokenInDebug ? '' : 'POST digifact_debug=1 with the verify request to show the full JWT in curl.',
                ];

                return [
                    'decoded'     => null,
                    'http_code'   => $httpCode,
                    'excerpt'     => '',
                    'mime'        => $mime,
                    'debug_block' => $e['debug'],
                    'early'       => $e,
                ];
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

        return [
            'decoded'     => \is_array($decoded) ? $decoded : null,
            'http_code'   => $httpCode,
            'excerpt'     => $excerpt,
            'mime'        => $mime,
            'debug_block' => $debugBlock,
            'early'       => null,
        ];
    }

    /**
     * Map Digifact REQUEST_DATA / RESPONSE to our flat fields (nit key holds vat/NIT/CUI for forms).
     *
     * @param  'nit'|'cui'  $mode
     */
    protected static function interpretDigifactSharedResponse(
        ?array $decoded,
        int $httpCode,
        string $excerpt,
        string $mime,
        array $debugBlock,
        string $mode,
        string $cleanFallbackId
    ): array {
        $empty = self::emptyLookupResult();

        if (!\is_array($decoded)) {
            $mimeTrim = trim($mime);
            $extra    = $mimeTrim !== '' ? ('; ' . $mimeTrim) : '';
            $empty['http_code']   = $httpCode;
            $empty['raw_excerpt'] = $excerpt;
            $empty['error']       = sprintf(
                'invalid_json (HTTP %d%s): %s',
                $httpCode,
                $extra,
                $excerpt
            );
            $empty['debug'] = $debugBlock;

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
            $empty['error'] = $msg !== '' ? $msg : ($httpCode >= 400 ? 'http_' . $httpCode : 'shared_lookup_failed');
            $empty['debug'] = $debugBlock;

            return $empty;
        }

        $nombre = trim((string) ($row['NOMBRE'] ?? $row['Nombre'] ?? ''));

        if ($mode === 'cui') {
            $idOut = trim((string) ($row['CUI'] ?? $row['Cui'] ?? $cleanFallbackId));

            return [
                'ok'          => true,
                'http_code'   => $httpCode,
                'error'       => '',
                'name'        => $nombre,
                'nit'         => $idOut,
                'street'      => '',
                'city'        => '',
                'raw_excerpt' => '',
                'debug'       => $debugBlock,
            ];
        }

        $nitOut = trim((string) ($row['NIT'] ?? $row['Nit'] ?? $cleanFallbackId));
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
     * Shared setup: parse URL, query base (COUNTRY, TAXID, USERNAME, DATA2).
     *
     * @param  array<string, mixed>  $queryParams  Output base query (without DATA1).
     *
     * @return  array{0: array<string, mixed>|null, 1: array<string, mixed>}  [parts|null on fail, empty on fail]
     */
    protected static function buildDigifactSharedQueryBase(string $sharedUrl, string $emissorTaxId, string $apiUsername, string $data2Value): array
    {
        $sharedUrl    = trim($sharedUrl);
        $emissorTaxId = self::normalizeTaxIdForDigifactQuery(trim($emissorTaxId));
        $apiUsername  = trim($apiUsername);
        $empty        = self::emptyLookupResult();

        if ($sharedUrl === '' || !filter_var($sharedUrl, FILTER_VALIDATE_URL)) {
            $empty['error'] = 'invalid_shared_url';

            return [null, $empty];
        }
        if ($emissorTaxId === '' || $apiUsername === '') {
            $empty['error'] = 'missing_certificador_tax_or_user';

            return [null, $empty];
        }

        $parts = parse_url($sharedUrl);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            $empty['error'] = 'invalid_shared_url';

            return [null, $empty];
        }

        $parts = self::normalizeDigifactSharedUrlParts($parts);

        $queryParams = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $queryParams);
        }

        self::stripInvalidDigifactSharedNitKeys($queryParams);
        $queryParams['COUNTRY']  = 'GT';
        $queryParams['TAXID']    = $emissorTaxId;
        $queryParams['USERNAME'] = $apiUsername;
        $queryParams['DATA2']    = $data2Value;

        return [$parts, $queryParams];
    }

    /**
     * Fetch taxpayer info for a client NIT (RTU-style response).
     *
     * @param   bool  $revealTokenInDebug  When true, curl debug lines include full JWT (POST digifact_debug=1).
     * @param   array<string, mixed>|null  $digifactLogContext  Optional audit log (environment, operation, quotation_id).
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
        bool $revealTokenInDebug = false,
        ?array $digifactLogContext = null
    ): array {
        $bearerToken = self::normalizeAuthorizationToken(trim($bearerToken));
        $clientNit   = trim($clientNit);

        if ($bearerToken === '') {
            $e = self::emptyLookupResult();
            $e['error'] = 'missing_bearer_token';

            return $e;
        }
        if ($clientNit === '') {
            $e = self::emptyLookupResult();
            $e['error'] = 'missing_client_nit';

            return $e;
        }

        $cleanNit = preg_replace('/[^\dA-Za-z\-]/', '', $clientNit) ?? '';
        if ($cleanNit === '') {
            $e = self::emptyLookupResult();
            $e['error'] = 'invalid_client_nit';

            return $e;
        }

        [$parts, $baseOrFail] = self::buildDigifactSharedQueryBase($sharedUrl, $emissorTaxId, $apiUsername, 'NIT|' . $cleanNit);
        if ($parts === null) {
            return $baseOrFail;
        }
        $queryParams = $baseOrFail;

        if (!\function_exists('curl_init')) {
            $e = self::emptyLookupResult();
            $e['error'] = 'curl_required';

            return $e;
        }

        $data1Variants = ['SHARED_GETINFONITcom', 'SHARED_GETINFONIT.com'];
        $fetch         = self::digifactSharedFetchUntilJsonParsed(
            $parts,
            $queryParams,
            $data1Variants,
            $bearerToken,
            $timeoutSec,
            $revealTokenInDebug,
            $digifactLogContext
        );

        if ($fetch['early'] !== null) {
            return $fetch['early'];
        }

        return self::interpretDigifactSharedResponse(
            $fetch['decoded'],
            $fetch['http_code'],
            $fetch['excerpt'],
            $fetch['mime'],
            $fetch['debug_block'],
            'nit',
            $cleanNit
        );
    }

    /**
     * Fetch person info by CUI (SHARED_GETINFOCUI; response has NOMBRE, CUI, no address).
     *
     * @param   bool  $revealTokenInDebug  When true, curl debug lines include full JWT (POST digifact_debug=1).
     * @param   array<string, mixed>|null  $digifactLogContext  Optional audit log (environment, operation, quotation_id).
     *
     * @return  array<string, mixed>  Same shape as fetchNitInfo; nit key holds CUI for form vat.
     */
    public static function fetchCuiInfo(
        string $clientCui,
        string $sharedUrl,
        string $emissorTaxId,
        string $apiUsername,
        string $bearerToken,
        int $timeoutSec = 45,
        bool $revealTokenInDebug = false,
        ?array $digifactLogContext = null
    ): array {
        $bearerToken = self::normalizeAuthorizationToken(trim($bearerToken));
        $clientCui   = trim($clientCui);

        if ($bearerToken === '') {
            $e = self::emptyLookupResult();
            $e['error'] = 'missing_bearer_token';

            return $e;
        }
        if ($clientCui === '') {
            $e = self::emptyLookupResult();
            $e['error'] = 'missing_client_cui';

            return $e;
        }

        $cleanCui = preg_replace('/\D/', '', $clientCui) ?? '';
        if ($cleanCui === '') {
            $e = self::emptyLookupResult();
            $e['error'] = 'invalid_client_cui';

            return $e;
        }

        [$parts, $baseOrFail] = self::buildDigifactSharedQueryBase($sharedUrl, $emissorTaxId, $apiUsername, 'CUI|' . $cleanCui);
        if ($parts === null) {
            return $baseOrFail;
        }
        $queryParams = $baseOrFail;

        if (!\function_exists('curl_init')) {
            $e = self::emptyLookupResult();
            $e['error'] = 'curl_required';

            return $e;
        }

        $data1Variants = ['SHARED_GETINFOCUI', 'SHARED_GETINFOCUI.com'];
        $fetch         = self::digifactSharedFetchUntilJsonParsed(
            $parts,
            $queryParams,
            $data1Variants,
            $bearerToken,
            $timeoutSec,
            $revealTokenInDebug,
            $digifactLogContext
        );

        if ($fetch['early'] !== null) {
            return $fetch['early'];
        }

        return self::interpretDigifactSharedResponse(
            $fetch['decoded'],
            $fetch['http_code'],
            $fetch['excerpt'],
            $fetch['mime'],
            $fetch['debug_block'],
            'cui',
            $cleanCui
        );
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
