<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;

/**
 * Fetches USD reference exchange rate from Banco de Guatemala public SOAP service.
 *
 * @see https://www.banguat.gob.gt/tipo_cambio
 * @see https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx
 *
 * @since  3.119.172
 */
class BanguatTipoCambioHelper
{
    private const SOAP_URL = 'https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx';

    private const SOAP_NS = 'http://www.banguat.gob.gt/variables/ws/';

    /** USD currency id in BANGUAT Variables / TipoCambioRango. */
    private const USD_MONEDA_ID = '2';

    /** @var array<string, float> */
    private static array $cache = [];

    /**
     * Reference USD rate (GTQ per 1 USD) for an issue date (Y-m-d).
     *
     * Uses TipoCambioDia/referencia when the date is today in Guatemala;
     * otherwise TipoCambioRango venta for USD on that date.
     */
    public function getUsdReferenciaForDate(string $dateYmd): ?float
    {
        $dateYmd = trim($dateYmd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }

        if (isset(self::$cache[$dateYmd])) {
            return self::$cache[$dateYmd];
        }

        $todayGt = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala')))->format('Y-m-d');
        $rate    = ($dateYmd === $todayGt)
            ? $this->fetchTipoCambioDiaReferencia()
            : $this->fetchTipoCambioRangoUsdVenta($dateYmd);

        if ($rate !== null && $rate > 0) {
            self::$cache[$dateYmd] = $rate;
        }

        return $rate;
    }

    private function fetchTipoCambioDiaReferencia(): ?float
    {
        $body = $this->soapPost('TipoCambioDia', '<TipoCambioDia xmlns="' . self::SOAP_NS . '" />');
        if ($body === '') {
            return null;
        }

        if (preg_match('/<referencia>([0-9.]+)<\/referencia>/i', $body, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function fetchTipoCambioRangoUsdVenta(string $dateYmd): ?float
    {
        $dmY = $this->ymdToDmY($dateYmd);
        if ($dmY === '') {
            return null;
        }

        $payload = '<TipoCambioRango xmlns="' . self::SOAP_NS . '">'
            . '<fechainit>' . htmlspecialchars($dmY, ENT_XML1) . '</fechainit>'
            . '<fechafin>' . htmlspecialchars($dmY, ENT_XML1) . '</fechafin>'
            . '</TipoCambioRango>';

        $body = $this->soapPost('TipoCambioRango', $payload);
        if ($body === '') {
            return null;
        }

        if (preg_match_all(
            '/<Var>\s*<moneda>' . self::USD_MONEDA_ID . '<\/moneda>\s*<fecha>[^<]*<\/fecha>\s*<venta>([0-9.]+)<\/venta>/i',
            $body,
            $matches,
            PREG_SET_ORDER
        )) {
            return (float) $matches[0][1];
        }

        if (preg_match('/<venta>([0-9.]+)<\/venta>/i', $body, $m)) {
            return (float) $m[1];
        }

        return null;
    }

    private function soapPost(string $action, string $innerBody): string
    {
        $envelope = '<?xml version="1.0" encoding="utf-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $innerBody . '</soap:Body></soap:Envelope>';

        try {
            $http = HttpFactory::getHttp();
            $resp = $http->post(
                self::SOAP_URL,
                $envelope,
                [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '"' . self::SOAP_NS . $action . '"',
                ],
                15
            );
            if ((int) ($resp->code ?? 0) !== 200) {
                return '';
            }

            return trim((string) ($resp->body ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function ymdToDmY(string $dateYmd): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd, new \DateTimeZone('America/Guatemala'));
        if (!$dt) {
            return '';
        }

        return $dt->format('d/m/Y');
    }
}
