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

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Import parsed MT-940 files into database tables.
 *
 * @since  3.119.149
 */
class Mt940ImportHelper
{
    /**
     * @param   string       $content         File body
     * @param   string       $filename        Original filename
     * @param   array<int>   $allowedBankIds  MT940 settings bank account ids
     * @param   string       $emailUid        Optional IMAP UID
     * @param   string       $sender          Optional email sender
     * @param   string       $subject         Optional email subject
     *
     * @return  array{success: bool, message: string, import_log_id?: int, transactions_count?: int, bank_account_id?: int, skipped?: bool}
     *
     * @since   3.119.149
     */
    public static function importFileContent(
        string $content,
        string $filename,
        array $allowedBankIds,
        string $emailUid = '',
        string $sender = '',
        string $subject = ''
    ): array {
        $filename = \trim($filename);
        if ($filename === '') {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_IMPORT_MISSING_FILENAME'];
        }

        if (!self::tablesAvailable()) {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'];
        }

        $parsed = Mt940ParserHelper::parse($content);
        $acctNo = (string) ($parsed['account_number'] ?? '');
        if ($acctNo === '') {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_IMPORT_NO_ACCOUNT_NUMBER'];
        }

        $bankAccountId = self::resolveBankAccountId($acctNo, $allowedBankIds);
        if ($bankAccountId < 1) {
            return [
                'success' => false,
                'message' => 'COM_ORDENPRODUCCION_MT940_IMPORT_ACCOUNT_NOT_CONFIGURED',
            ];
        }

        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $now = Factory::getDate()->toSql();

        if (self::importLogExists($filename)) {
            return [
                'success'  => true,
                'message'  => 'COM_ORDENPRODUCCION_MT940_IMPORT_ALREADY_IMPORTED',
                'skipped'  => true,
            ];
        }

        $importLogId = self::insertImportLog([
            'email_uid'           => $emailUid,
            'sender'              => $sender,
            'subject'             => $subject,
            'filename'            => $filename,
            'bank_account_id'     => $bankAccountId,
            'account_number'      => $acctNo,
            'status'              => 'imported',
            'transactions_count'  => 0,
            'message'             => '',
            'imported_at'         => $now,
        ]);

        $statementDate = $parsed['statement_date'] ?? null;
        $currency      = (string) ($parsed['currency'] ?? 'GTQ');
        $inserted      = 0;

        foreach ($parsed['transactions'] as $tx) {
            if (self::transactionExists($bankAccountId, $filename, $tx)) {
                continue;
            }

            $row = (object) [
                'bank_account_id'   => $bankAccountId,
                'account_number'    => $acctNo,
                'source_email_uid'  => $emailUid !== '' ? $emailUid : null,
                'source_filename'   => $filename,
                'import_log_id'     => $importLogId,
                'statement_date'    => $statementDate,
                'transaction_date'  => $tx['transaction_date'] ?? null,
                'value_date'        => $tx['value_date'] ?? null,
                'reference'         => $tx['reference'] ?? '',
                'amount'            => (float) ($tx['amount'] ?? 0),
                'currency'          => $tx['currency'] ?? $currency,
                'debit_credit'      => $tx['debit_credit'] ?? '',
                'description'       => $tx['description'] ?? '',
                'raw_line'          => $tx['raw_line'] ?? '',
                'imported_at'       => $now,
            ];

            $db->insertObject('#__ordenproduccion_mt940_transactions', $row, 'id');
            $inserted++;
        }

        self::updateImportLogCount($importLogId, $inserted);

        return [
            'success'             => true,
            'message'             => 'COM_ORDENPRODUCCION_MT940_IMPORT_OK',
            'import_log_id'       => $importLogId,
            'transactions_count'  => $inserted,
            'bank_account_id'     => $bankAccountId,
        ];
    }

    /**
     * @return  bool
     *
     * @since   3.119.149
     */
    public static function tablesAvailable(): bool
    {
        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $table = $db->replacePrefix('#__ordenproduccion_mt940_transactions');

            return \in_array($table, $db->getTableList(), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param   string       $accountNumber
     * @param   array<int>   $allowedBankIds
     *
     * @return  int
     *
     * @since   3.119.149
     */
    public static function resolveBankAccountId(string $accountNumber, array $allowedBankIds): int
    {
        $accountNumber = Mt940ParserHelper::normalizeAccountNumber($accountNumber);
        if ($accountNumber === '') {
            return 0;
        }

        $allowedBankIds = \array_values(\array_unique(\array_filter(\array_map('intval', $allowedBankIds), static function ($v) {
            return $v > 0;
        })));

        if ($allowedBankIds === []) {
            return 0;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if (!self::bankAccountsHaveAccountNumberColumn($db)) {
            return 0;
        }

        $candidates = self::accountNumberMatchCandidates($accountNumber);
        $query      = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ordenproduccion_bank_accounts'))
            ->where($db->quoteName('id') . ' IN (' . \implode(',', $allowedBankIds) . ')')
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('account_number') . ' IN (' . \implode(',', \array_map([$db, 'quote'], $candidates)) . ')');

        $db->setQuery($query, 0, 1);
        $id = (int) $db->loadResult();

        return $id > 0 ? $id : 0;
    }

    /**
     * @param   string  $accountNumber
     *
     * @return  array<int, string>
     *
     * @since   3.119.149
     */
    private static function accountNumberMatchCandidates(string $accountNumber): array
    {
        $norm   = Mt940ParserHelper::normalizeAccountNumber($accountNumber);
        $strip  = \ltrim($norm, '0');
        $cands  = \array_unique(\array_filter([$norm, $strip !== '' ? $strip : null, $strip !== '' ? \str_pad($strip, \strlen($norm), '0', \STR_PAD_LEFT) : null]));

        return \array_values($cands);
    }

    /**
     * @param   DatabaseInterface  $db
     *
     * @return  bool
     *
     * @since   3.119.149
     */
    private static function bankAccountsHaveAccountNumberColumn(DatabaseInterface $db): bool
    {
        try {
            $cols = $db->getTableColumns('#__ordenproduccion_bank_accounts', false);

            return isset($cols['account_number']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param   string  $filename
     *
     * @return  bool
     *
     * @since   3.119.149
     */
    private static function importLogExists(string $filename): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->where($db->quoteName('filename') . ' = ' . $db->quote($filename))
            ->where($db->quoteName('status') . ' = ' . $db->quote('imported'));

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * @param   array<string, mixed>  $data
     *
     * @return  int
     *
     * @since   3.119.149
     */
    private static function insertImportLog(array $data): int
    {
        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $obj = (object) $data;
        $db->insertObject('#__ordenproduccion_mt940_import_log', $obj, 'id');

        return (int) ($obj->id ?? 0);
    }

    /**
     * @param   int  $importLogId
     * @param   int  $count
     *
     * @return  void
     *
     * @since   3.119.149
     */
    private static function updateImportLogCount(int $importLogId, int $count): void
    {
        if ($importLogId < 1) {
            return;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->set($db->quoteName('transactions_count') . ' = ' . (int) $count)
            ->where($db->quoteName('id') . ' = ' . $importLogId);
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * @param   int                  $bankAccountId
     * @param   string               $filename
     * @param   array<string, mixed>  $tx
     *
     * @return  bool
     *
     * @since   3.119.149
     */
    private static function transactionExists(int $bankAccountId, string $filename, array $tx): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_mt940_transactions'))
            ->where($db->quoteName('bank_account_id') . ' = ' . $bankAccountId)
            ->where($db->quoteName('source_filename') . ' = ' . $db->quote($filename))
            ->where($db->quoteName('reference') . ' = ' . $db->quote((string) ($tx['reference'] ?? '')))
            ->where($db->quoteName('amount') . ' = ' . (float) ($tx['amount'] ?? 0))
            ->where($db->quoteName('debit_credit') . ' = ' . $db->quote((string) ($tx['debit_credit'] ?? '')));

        if (!empty($tx['transaction_date'])) {
            $query->where($db->quoteName('transaction_date') . ' = ' . $db->quote((string) $tx['transaction_date']));
        }

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }
}
