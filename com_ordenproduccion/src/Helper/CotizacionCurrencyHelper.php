<?php
/**
 * Cotización display currency (GTQ stored; USD view via stored BANGUAT rate).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * @since  3.119.225
 */
class CotizacionCurrencyHelper
{
    public const DISPLAY_GTQ = 'GTQ';

    public const DISPLAY_USD = 'USD';

    /**
     * @param   object|null  $quotation
     *
     * @return  float|null
     */
    public static function getExchangeRate($quotation): ?float
    {
        if (!$quotation || !isset($quotation->exchange_rate)) {
            return null;
        }

        $rate = (float) $quotation->exchange_rate;

        return $rate > 0 ? $rate : null;
    }

    /**
     * @param   object|null  $quotation
     *
     * @return  string
     */
    public static function getExchangeRateDate($quotation): string
    {
        if (!$quotation || empty($quotation->exchange_rate_date)) {
            return '';
        }

        $raw = trim((string) $quotation->exchange_rate_date);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? $raw : '';
    }

    /**
     * @param   object|null  $quotation
     *
     * @return  bool
     */
    public static function canDisplayUsd($quotation): bool
    {
        return self::getExchangeRate($quotation) !== null;
    }

    /**
     * @param   float  $gtq
     * @param   float  $rate  GTQ per 1 USD
     *
     * @return  float
     */
    public static function gtqToUsd(float $gtq, float $rate): float
    {
        if ($rate <= 0) {
            return $gtq;
        }

        return round($gtq / $rate, 2);
    }

    /**
     * @param   float   $gtq
     * @param   float   $rate
     * @param   int     $decimals
     *
     * @return  float
     */
    public static function gtqToUsdPrecise(float $gtq, float $rate, int $decimals = 4): float
    {
        if ($rate <= 0) {
            return $gtq;
        }

        return round($gtq / $rate, $decimals);
    }

    /**
     * @param   float   $gtq
     * @param   float   $rate
     * @param   string  $displayCurrency  GTQ|USD
     * @param   int     $decimals
     *
     * @return  string
     */
    public static function formatAmount(float $gtq, float $rate, string $displayCurrency, int $decimals = 2): string
    {
        $displayCurrency = strtoupper(trim($displayCurrency));

        if ($displayCurrency === self::DISPLAY_USD && $rate > 0) {
            $usd = self::gtqToUsdPrecise($gtq, $rate, $decimals);

            return 'USD ' . number_format($usd, $decimals, '.', ',');
        }

        return 'Q ' . number_format($gtq, $decimals, '.', ',');
    }

    /**
     * Fetch BANGUAT referencia for a new quotation (quote_date or today).
     *
     * @param   string  $quoteDateYmd
     *
     * @return  array{rate: ?float, date: string}
     */
    public static function resolveRateForNewQuotation(string $quoteDateYmd): array
    {
        $quoteDateYmd = trim($quoteDateYmd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $quoteDateYmd)) {
            $quoteDateYmd = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala')))->format('Y-m-d');
        }

        $helper = new BanguatTipoCambioHelper();
        $rate   = $helper->getUsdReferenciaForDate($quoteDateYmd);

        return [
            'rate' => ($rate !== null && $rate > 0) ? $rate : null,
            'date' => $quoteDateYmd,
        ];
    }
}
