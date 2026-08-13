<?php
/**
 * Payment proof currency (GTQ stored on saldo; USD lines via bank account + BANGUAT).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @since  3.119.290
 */
class PaymentProofCurrencyHelper
{
    public const CURRENCY_GTQ = 'GTQ';

    public const CURRENCY_USD = 'USD';

    /**
     * @param   string  $currency
     *
     * @return  string  GTQ|USD
     */
    public static function normalizeCurrency(string $currency): string
    {
        return strtoupper(trim($currency)) === self::CURRENCY_USD ? self::CURRENCY_USD : self::CURRENCY_GTQ;
    }

    /**
     * @param   string  $currency
     *
     * @return  string  Display prefix (Q. or USD)
     */
    public static function currencyPrefix(string $currency): string
    {
        return self::normalizeCurrency($currency) === self::CURRENCY_USD ? '$' : 'Q.';
    }

    /**
     * Resolve display/save currency for a stored payment line (column or bank account fallback).
     *
     * @param   object       $line
     * @param   array<int|string, string>  $bankAccountCurrencies  bank_account_id => GTQ|USD
     *
     * @return  string  GTQ|USD
     */
    public static function resolveLineCurrency(object $line, array $bankAccountCurrencies = []): string
    {
        if (isset($line->currency) && trim((string) $line->currency) !== '') {
            $stored = self::normalizeCurrency((string) $line->currency);
            if ($stored === self::CURRENCY_USD) {
                return self::CURRENCY_USD;
            }
        }

        $baId = (int) ($line->bank_account_id ?? 0);
        if ($baId > 0) {
            $mapped = $bankAccountCurrencies[$baId] ?? $bankAccountCurrencies[(string) $baId] ?? '';
            if (self::normalizeCurrency((string) $mapped) === self::CURRENCY_USD) {
                return self::CURRENCY_USD;
            }
        }

        return self::CURRENCY_GTQ;
    }

    /**
     * @param   float  $usd
     * @param   float  $rate  GTQ per 1 USD
     *
     * @return  float
     */
    public static function usdToGtq(float $usd, float $rate): float
    {
        if ($rate <= 0) {
            return round($usd, 2);
        }

        return round($usd * $rate, 2);
    }

    /**
     * Resolve currency from a bank account row.
     *
     * @param   object|null  $bankAccount
     *
     * @return  string
     */
    public static function currencyFromBankAccount(?object $bankAccount): string
    {
        if (!$bankAccount || !isset($bankAccount->currency)) {
            return self::CURRENCY_GTQ;
        }

        return self::normalizeCurrency((string) $bankAccount->currency);
    }

    /**
     * Enrich a payment line with currency, exchange_rate, and amount_gtq.
     *
     * @param   array        $line         payment_type, bank_account_id, document_date, amount, exchange_rate?
     * @param   object|null  $bankAccount  Row from #__ordenproduccion_bank_accounts
     *
     * @return  array
     *
     * @throws  \RuntimeException  When USD line has no usable BANGUAT rate
     */
    public static function enrichPaymentLine(array $line, ?object $bankAccount): array
    {
        $amount   = round((float) ($line['amount'] ?? 0), 2);
        $currency = self::currencyFromBankAccount($bankAccount);

        $exchangeRate = null;
        $amountGtq    = $amount;

        if ($currency === self::CURRENCY_USD) {
            $postedRate = isset($line['exchange_rate']) ? (float) $line['exchange_rate'] : 0.0;
            $docDate    = trim((string) ($line['document_date'] ?? ''));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $docDate)) {
                $docDate = (new \DateTimeImmutable('now', new \DateTimeZone('America/Guatemala')))->format('Y-m-d');
            }

            if ($postedRate > 0.000001) {
                $exchangeRate = round($postedRate, 6);
            } else {
                $helper  = new BanguatTipoCambioHelper();
                $fetched = $helper->getUsdReferenciaForDate($docDate);
                if ($fetched === null || $fetched <= 0) {
                    throw new \RuntimeException(
                        Text::sprintf('COM_ORDENPRODUCCION_PAYMENT_PROOF_USD_RATE_UNAVAILABLE', $docDate)
                    );
                }
                $exchangeRate = round((float) $fetched, 6);
            }

            $amountGtq = self::usdToGtq($amount, $exchangeRate);
        }

        $line['currency']      = $currency;
        $line['exchange_rate'] = $exchangeRate;
        $line['amount_gtq']    = $amountGtq;

        return $line;
    }

    /**
     * GTQ amount used for saldo (prefers stored amount_gtq).
     *
     * @param   object  $line
     *
     * @return  float
     */
    public static function lineAmountGtq(object $line): float
    {
        if (isset($line->amount_gtq) && $line->amount_gtq !== null && $line->amount_gtq !== '') {
            return round((float) $line->amount_gtq, 2);
        }

        return round((float) ($line->amount ?? 0), 2);
    }

    /**
     * Human-readable line amount for lists.
     *
     * @param   object  $line
     *
     * @return  string
     */
    public static function formatLineAmountDisplay(object $line, array $bankAccountCurrencies = []): string
    {
        $amount   = round((float) ($line->amount ?? 0), 2);
        $currency = self::resolveLineCurrency($line, $bankAccountCurrencies);

        if ($currency === self::CURRENCY_USD) {
            $gtq  = self::lineAmountGtq($line);
            $rate = isset($line->exchange_rate) ? (float) $line->exchange_rate : 0.0;
            $base = '$ ' . number_format($amount, 2, '.', ',');
            if ($rate > 0 && abs($gtq - $amount) > 0.009) {
                return $base . ' (Q ' . number_format($gtq, 2, '.', ',') . ' @ ' . number_format($rate, 4, '.', '') . ')';
            }

            return $base;
        }

        return 'Q ' . number_format($amount, 2, '.', ',');
    }

    /**
     * Amount with currency symbol only (no GTQ conversion suffix).
     *
     * @param   object  $line
     * @param   array<int|string, string>  $bankAccountCurrencies
     *
     * @return  string
     */
    public static function formatLineAmountPlain(object $line, array $bankAccountCurrencies = []): string
    {
        $amount   = round((float) ($line->amount ?? 0), 2);
        $currency = self::resolveLineCurrency($line, $bankAccountCurrencies);

        return self::formatAmount($amount, $currency);
    }

    /**
     * Format a numeric amount with GTQ/USD prefix (payment proof / MT-940 match UI).
     *
     * @param   float   $amount
     * @param   string  $currency  GTQ|USD
     */
    public static function formatAmount(float $amount, string $currency): string
    {
        $amount = round($amount, 2);

        if (self::normalizeCurrency($currency) === self::CURRENCY_USD) {
            return '$ ' . number_format($amount, 2, '.', ',');
        }

        return 'Q ' . number_format($amount, 2, '.', ',');
    }

    /**
     * Financiero MT-940 movements list style (USD code prefix, not $).
     */
    public static function formatMt940MovementAmount(float $amount, string $currency): string
    {
        $amount = round(abs($amount), 2);

        if (self::normalizeCurrency($currency) === self::CURRENCY_USD) {
            return 'USD ' . number_format($amount, 2, '.', ',');
        }

        return 'Q ' . number_format($amount, 2, '.', ',');
    }
}
