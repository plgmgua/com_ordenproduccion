<?php

/**
 * Digifact-style SHARED NIT info (GET + Bearer), for pre-filling cliente RTU data.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.118.11
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * GET {url_cert_nit} with COUNTRY, TAXID, USERNAME, DATA1, DATA2 query params.
 */
class CertificadorFactNitLookupHelper
{
    /**
     * Fetch taxpayer info for a client NIT (RTU-style response).
     *
     * @return  array{
     *   ok: bool,
     *   http_code: int,
     *   error: string,
     *   name: string,
     *   nit: string,
     *   street: string,
     *   city: string,
     *   raw_excerpt: string
     * }
     */
    public static function fetchNitInfo(
        string $clientNit,
        string $sharedUrl,
        string $emissorTaxId,
        string $apiUsername,
        string $bearerToken,
        int $timeoutSec = 45
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
        ];

        $sharedUrl     = trim($sharedUrl);
        $emissorTaxId  = trim($emissorTaxId);
        $apiUsername   = trim($apiUsername);
        $bearerToken   = trim($bearerToken);
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
        $queryParams['DATA1']    = 'SHARED_GETINFONITcom';
        $queryParams['DATA2']    = 'NIT|' . $cleanNit;

        $builtQuery = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $path       = isset($parts['path']) ? $parts['path'] : '/';
        $url        = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path
            . '?' . $builtQuery;

        if (!\function_exists('curl_init')) {
            $empty['error'] = 'curl_required';

            return $empty;
        }

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
                'Authorization: Bearer ' . $bearerToken,
                'Accept: application/json',
            ],
        ]);
        $rawBody  = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        $curlErr  = (string) curl_error($ch);
        curl_close($ch);

        if ($rawBody === false) {
            $empty['http_code'] = $httpCode;
            $empty['error']     = $curlErr !== '' ? $curlErr : 'curl_exec_failed';

            return $empty;
        }

        $rawBody = (string) $rawBody;
        $excerpt = self::excerptBody($rawBody);
        $decoded = json_decode($rawBody, true);

        if (!\is_array($decoded)) {
            $empty['http_code']   = $httpCode;
            $empty['raw_excerpt'] = $excerpt;
            $empty['error']       = 'invalid_json';

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
        ];
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
