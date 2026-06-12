<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Parse SWIFT MT940 text files (Banco Industrial format).
 *
 * @since  3.119.149
 */
class Mt940ParserHelper
{
    /**
     * @param   string  $content  Raw .TXT file body
     *
     * @return  array{
     *   account_number: string,
     *   statement_reference: string,
     *   statement_date: ?string,
     *   statement_sequence: string,
     *   currency: string,
     *   opening_balance: ?float,
     *   closing_balance: ?float,
     *   closing_available_balance: ?float,
     *   transactions: array<int, array{
     *     transaction_date: ?string,
     *     value_date: ?string,
     *     debit_credit: string,
     *     amount: float,
     *     currency: string,
     *     transaction_code: string,
     *     reference: string,
     *     description: string,
     *     raw_line: string
     *   }>
     * }
     *
     * @since  3.119.149
     */
    public static function parse(string $content): array
    {
        $out = [
            'account_number'              => '',
            'statement_reference'         => '',
            'statement_date'              => null,
            'statement_sequence'          => '',
            'currency'                    => 'GTQ',
            'opening_balance'             => null,
            'closing_balance'             => null,
            'closing_available_balance'   => null,
            'transactions'                => [],
        ];

        $lines = \preg_split('/\R/', $content) ?: [];
        $pendingTx = null;

        foreach ($lines as $line) {
            $line = \trim($line);
            if ($line === '' || $line === '-') {
                continue;
            }

            if (\strpos($line, ':20:') === 0) {
                $out['statement_reference'] = \trim(\substr($line, 4));
                $out['statement_date']      = self::parseStatementDateFromTag20($out['statement_reference']);

                continue;
            }

            if (\strpos($line, ':25:') === 0) {
                $out['account_number'] = self::normalizeAccountNumber(\trim(\substr($line, 4)));

                continue;
            }

            if (\strpos($line, ':28C:') === 0) {
                $out['statement_sequence'] = \trim(\substr($line, 5));

                continue;
            }

            if (\strpos($line, ':60F:') === 0) {
                $bal = self::parseBalanceLine(\substr($line, 5));
                if ($bal !== null) {
                    $out['opening_balance'] = $bal['amount'];
                    if ($bal['currency'] !== '') {
                        $out['currency'] = $bal['currency'];
                    }
                }

                continue;
            }

            if (\strpos($line, ':62F:') === 0) {
                $bal = self::parseBalanceLine(\substr($line, 5));
                if ($bal !== null) {
                    $out['closing_balance'] = $bal['amount'];
                    if ($bal['currency'] !== '') {
                        $out['currency'] = $bal['currency'];
                    }
                }

                continue;
            }

            if (\strpos($line, ':64:') === 0) {
                $bal = self::parseBalanceLine(\substr($line, 4));
                if ($bal !== null) {
                    $out['closing_available_balance'] = $bal['amount'];
                    if ($bal['currency'] !== '' && $out['currency'] === 'GTQ') {
                        $out['currency'] = $bal['currency'];
                    }
                }

                continue;
            }

            if (\strpos($line, ':61:') === 0) {
                if ($pendingTx !== null) {
                    $out['transactions'][] = $pendingTx;
                }
                $pendingTx = self::parseTransactionLine(\substr($line, 4), $out['currency']);

                continue;
            }

            if (\strpos($line, ':86:') === 0 && $pendingTx !== null) {
                $desc = \trim(\substr($line, 4));
                $pendingTx['description'] = $desc !== '' ? $desc : $pendingTx['description'];
            }
        }

        if ($pendingTx !== null) {
            $out['transactions'][] = $pendingTx;
        }

        return $out;
    }

    /**
     * @param   string  $value
     *
     * @return  string
     *
     * @since   3.119.149
     */
    public static function normalizeAccountNumber(string $value): string
    {
        return \trim(\preg_replace('/\s+/', '', $value) ?? '');
    }

    /**
     * @param   string  $ref  :20: value e.g. 11/06/20260000
     *
     * @return  ?string  Y-m-d
     *
     * @since   3.119.149
     */
    private static function parseStatementDateFromTag20(string $ref): ?string
    {
        if (\preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $ref, $m)) {
            return \sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /**
     * @param   string  $raw  e.g. C260611GTQ60399,53
     *
     * @return  ?array{currency: string, amount: float}
     *
     * @since   3.119.149
     */
    private static function parseBalanceLine(string $raw): ?array
    {
        $raw = \trim($raw);
        if ($raw === '') {
            return null;
        }

        if (!\preg_match('/^[CD](\d{6})([A-Z]{3})([\d,]+)$/i', $raw, $m)) {
            return null;
        }

        $sign = \strtoupper($raw[0]) === 'D' ? -1.0 : 1.0;

        return [
            'currency' => \strtoupper($m[2]),
            'amount'   => \round($sign * self::parseEuropeanAmount($m[3]), 2),
        ];
    }

    /**
     * @param   string  $raw      e.g. 260611D467,61NMSC00012105
     * @param   string  $currency Default currency from statement
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.149
     */
    private static function parseTransactionLine(string $raw, string $currency): array
    {
        $tx = [
            'transaction_date' => null,
            'value_date'       => null,
            'debit_credit'     => '',
            'amount'           => 0.0,
            'currency'         => $currency !== '' ? $currency : 'GTQ',
            'transaction_code' => '',
            'reference'        => '',
            'description'      => '',
            'raw_line'         => ':61:' . $raw,
        ];

        if (!\preg_match('/^(\d{6})([CD])([\d,]+)(.*)$/i', $raw, $m)) {
            return $tx;
        }

        $tx['transaction_date'] = self::yyMmDdToSql($m[1]);
        $tx['value_date']       = $tx['transaction_date'];
        $tx['debit_credit']     = \strtoupper($m[2]) === 'D' ? 'D' : 'C';
        $tx['amount']           = \round(self::parseEuropeanAmount($m[3]), 2);

        $tail = \trim((string) ($m[4] ?? ''));
        if ($tail !== '') {
            if (\preg_match('/^([A-Z]{4})(\d+)$/i', $tail, $tm)) {
                $tx['transaction_code'] = \strtoupper($tm[1]);
                $tx['reference']        = $tm[2];
            } elseif (\preg_match('/^([A-Z]{3})(\d+)$/i', $tail, $tm)) {
                $tx['transaction_code'] = \strtoupper($tm[1]);
                $tx['reference']        = $tm[2];
            } elseif (\preg_match('/(\d{5,})$/', $tail, $rm)) {
                $tx['reference'] = $rm[1];
            } else {
                $tx['reference'] = $tail;
            }
        }

        return $tx;
    }

    /**
     * @param   string  $yyMmDd  e.g. 260611
     *
     * @return  ?string
     *
     * @since   3.119.149
     */
    private static function yyMmDdToSql(string $yyMmDd): ?string
    {
        if (!\preg_match('/^(\d{2})(\d{2})(\d{2})$/', $yyMmDd, $m)) {
            return null;
        }

        return \sprintf('20%02d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }

    /**
     * @param   string  $value  e.g. 467,61 or 60399,53
     *
     * @return  float
     *
     * @since   3.119.149
     */
    private static function parseEuropeanAmount(string $value): float
    {
        $value = \str_replace(',', '.', \trim($value));

        return (float) $value;
    }
}
