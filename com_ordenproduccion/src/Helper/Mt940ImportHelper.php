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
     * @return  array{success: bool, message: string, import_log_id?: int, transactions_count?: int, bank_account_id?: int, skipped?: bool, duplicate_reason?: string}
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
        $filename    = self::normalizeFilename($filename);
        $contentHash = self::contentHash($content);
        $emailUid    = \trim($emailUid);

        if ($filename === '') {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_IMPORT_MISSING_FILENAME'];
        }

        if (!self::tablesAvailable()) {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'];
        }

        $dup = self::findDuplicateImport($filename, $emailUid, $contentHash);
        if ($dup !== null) {
            $refreshed = self::refreshDuplicateImportMetadata($content, $filename, $allowedBankIds);

            return [
                'success'           => true,
                'message'           => $refreshed
                    ? 'COM_ORDENPRODUCCION_MT940_IMPORT_METADATA_REFRESHED'
                    : 'COM_ORDENPRODUCCION_MT940_IMPORT_ALREADY_IMPORTED',
                'skipped'           => true,
                'duplicate_reason'  => $dup,
                'metadata_refreshed' => $refreshed,
            ];
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

        try {
            $db->transactionStart();

            $dup = self::findDuplicateImport($filename, $emailUid, $contentHash);
            if ($dup !== null) {
                $db->transactionRollback();
                $refreshed = self::refreshDuplicateImportMetadata($content, $filename, $allowedBankIds);

                return [
                    'success'            => true,
                    'message'            => $refreshed
                        ? 'COM_ORDENPRODUCCION_MT940_IMPORT_METADATA_REFRESHED'
                        : 'COM_ORDENPRODUCCION_MT940_IMPORT_ALREADY_IMPORTED',
                    'skipped'            => true,
                    'duplicate_reason'   => $dup,
                    'metadata_refreshed' => $refreshed,
                ];
            }

            $currency = (string) ($parsed['currency'] ?? 'GTQ');

            $importLogId = self::insertImportLog([
                'email_uid'                  => $emailUid !== '' ? $emailUid : null,
                'sender'                     => $sender,
                'subject'                    => $subject,
                'filename'                   => $filename,
                'content_hash'               => $contentHash,
                'bank_account_id'            => $bankAccountId,
                'account_number'             => $acctNo,
                'statement_reference'        => (string) ($parsed['statement_reference'] ?? ''),
                'statement_date'             => $parsed['statement_date'] ?? null,
                'statement_sequence'         => (string) ($parsed['statement_sequence'] ?? ''),
                'currency'                   => $currency,
                'opening_balance'            => $parsed['opening_balance'] ?? null,
                'closing_balance'            => $parsed['closing_balance'] ?? null,
                'closing_available_balance'  => $parsed['closing_available_balance'] ?? null,
                'status'                     => 'imported',
                'transactions_count'         => 0,
                'message'                    => '',
                'imported_at'                => $now,
            ]);

            $statementDate = $parsed['statement_date'] ?? null;
            $inserted      = 0;

            foreach ($parsed['transactions'] as $tx) {
                $fingerprint = self::buildTxFingerprint($bankAccountId, $acctNo, $tx);
                if (self::txFingerprintExists($fingerprint)) {
                    continue;
                }

                $txCurrency = (string) ($tx['currency'] ?? $currency);
                if ($txCurrency === '' || \strlen($txCurrency) !== 3 || !\preg_match('/^[A-Z]{3}$/', $txCurrency)) {
                    $txCurrency = $currency !== '' ? $currency : 'GTQ';
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
                    'transaction_code'  => $tx['transaction_code'] ?? '',
                    'amount'            => (float) ($tx['amount'] ?? 0),
                    'currency'          => $txCurrency,
                    'debit_credit'      => $tx['debit_credit'] ?? '',
                    'description'       => $tx['description'] ?? '',
                    'raw_line'          => $tx['raw_line'] ?? '',
                    'tx_fingerprint'    => $fingerprint,
                    'imported_at'       => $now,
                ];

                if (!self::transactionsHaveTransactionCodeColumn()) {
                    unset($row->transaction_code);
                }

                try {
                    $db->insertObject('#__ordenproduccion_mt940_transactions', $row, 'id');
                    $inserted++;
                } catch (\Throwable $e) {
                    if (self::isDuplicateKeyError($e)) {
                        continue;
                    }
                    throw $e;
                }
            }

            self::updateImportLogCount($importLogId, $inserted);
            $db->transactionCommit();

            return [
                'success'            => true,
                'message'            => 'COM_ORDENPRODUCCION_MT940_IMPORT_OK',
                'import_log_id'      => $importLogId,
                'transactions_count' => $inserted,
                'bank_account_id'    => $bankAccountId,
            ];
        } catch (\Throwable $e) {
            $db->transactionRollback();

            if (self::isDuplicateKeyError($e)) {
                return [
                    'success'          => true,
                    'message'          => 'COM_ORDENPRODUCCION_MT940_IMPORT_ALREADY_IMPORTED',
                    'skipped'          => true,
                    'duplicate_reason' => 'duplicate_key',
                ];
            }

            throw $e;
        }
    }

    /**
     * @param   string  $filename
     *
     * @return  string
     *
     * @since   3.119.150
     */
    public static function normalizeFilename(string $filename): string
    {
        $filename = \trim(\str_replace('\\', '/', $filename));
        $base     = \basename($filename);

        return $base !== '' ? $base : $filename;
    }

    /**
     * @param   string  $content
     *
     * @return  string
     *
     * @since   3.119.150
     */
    public static function contentHash(string $content): string
    {
        $normalized = \str_replace(["\r\n", "\r"], "\n", $content);

        return \hash('sha256', $normalized);
    }

    /**
     * @param   int                  $bankAccountId
     * @param   string               $accountNumber
     * @param   array<string, mixed>  $tx
     *
     * @return  string
     *
     * @since   3.119.150
     */
    public static function buildTxFingerprint(int $bankAccountId, string $accountNumber, array $tx): string
    {
        $parts = [
            (string) $bankAccountId,
            Mt940ParserHelper::normalizeAccountNumber($accountNumber),
            (string) ($tx['transaction_date'] ?? ''),
            (string) ($tx['reference'] ?? ''),
            \number_format((float) ($tx['amount'] ?? 0), 2, '.', ''),
            (string) ($tx['debit_credit'] ?? ''),
            \trim((string) ($tx['description'] ?? '')),
        ];

        return \hash('sha256', \implode('|', $parts));
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
     * Delete all MT-940 transactions and import log rows (for a fresh mailbox re-import).
     *
     * @return  array{
     *   success: bool,
     *   message: string,
     *   transactions_deleted?: int,
     *   import_logs_deleted?: int
     * }
     *
     * @since   3.119.153
     */
    public static function clearAllImportedData(): array
    {
        if (!self::tablesAvailable()) {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $db->transactionStart();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_mt940_transactions'));
            $db->setQuery($query);
            $db->execute();
            $txDeleted = (int) $db->getAffectedRows();

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__ordenproduccion_mt940_import_log'));
            $db->setQuery($query);
            $db->execute();
            $logDeleted = (int) $db->getAffectedRows();

            $db->transactionCommit();

            return [
                'success'              => true,
                'message'              => 'COM_ORDENPRODUCCION_MT940_CLEAR_OK',
                'transactions_deleted' => $txDeleted,
                'import_logs_deleted'  => $logDeleted,
            ];
        } catch (\Throwable $e) {
            $db->transactionRollback();

            throw $e;
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
     * @param   string  $filename
     * @param   string  $emailUid
     * @param   string  $contentHash
     *
     * @return  ?string  duplicate reason code
     *
     * @since   3.119.150
     */
    private static function findDuplicateImport(string $filename, string $emailUid, string $contentHash): ?string
    {
        if (self::importLogExistsByFilename($filename)) {
            return 'filename';
        }

        if ($contentHash !== '' && self::importLogExistsByContentHash($contentHash)) {
            return 'content_hash';
        }

        return null;
    }

    /**
     * @param   string  $accountNumber
     *
     * @return  array<int, string>
     */
    private static function accountNumberMatchCandidates(string $accountNumber): array
    {
        $norm  = Mt940ParserHelper::normalizeAccountNumber($accountNumber);
        $strip = \ltrim($norm, '0');
        $cands = \array_unique(\array_filter([
            $norm,
            $strip !== '' ? $strip : null,
            $strip !== '' && $norm !== '' ? \str_pad($strip, \strlen($norm), '0', \STR_PAD_LEFT) : null,
        ]));

        return \array_values($cands);
    }

    /**
     * @param   DatabaseInterface  $db
     *
     * @return  bool
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
     * @param   string  $emailUid
     *
     * @return  bool
     */
    private static function importLogExistsByEmailUid(string $emailUid): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->where($db->quoteName('email_uid') . ' = ' . $db->quote($emailUid))
            ->where($db->quoteName('status') . ' = ' . $db->quote('imported'));

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * @param   string  $filename
     *
     * @return  bool
     */
    private static function importLogExistsByFilename(string $filename): bool
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->where('LOWER(' . $db->quoteName('filename') . ') = ' . $db->quote(\strtolower($filename)))
            ->where($db->quoteName('status') . ' = ' . $db->quote('imported'));

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * @param   string  $contentHash
     *
     * @return  bool
     */
    private static function importLogExistsByContentHash(string $contentHash): bool
    {
        if (!self::importLogHasContentHashColumn()) {
            return false;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->where($db->quoteName('content_hash') . ' = ' . $db->quote($contentHash))
            ->where($db->quoteName('status') . ' = ' . $db->quote('imported'));

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * @param   string  $fingerprint
     *
     * @return  bool
     */
    private static function txFingerprintExists(string $fingerprint): bool
    {
        if ($fingerprint === '') {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        if (self::transactionsHaveFingerprintColumn()) {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_mt940_transactions'))
                ->where($db->quoteName('tx_fingerprint') . ' = ' . $db->quote($fingerprint));
            $db->setQuery($query);

            return (int) $db->loadResult() > 0;
        }

        return false;
    }

    /**
     * @return  bool
     */
    private static function importLogHasContentHashColumn(): bool
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $cols = $db->getTableColumns('#__ordenproduccion_mt940_import_log', false);

            return isset($cols['content_hash']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return  bool
     */
    private static function transactionsHaveFingerprintColumn(): bool
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $cols = $db->getTableColumns('#__ordenproduccion_mt940_transactions', false);

            return isset($cols['tx_fingerprint']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * When a file was already imported (e.g. before 3.119.151), backfill statement metadata on re-upload.
     *
     * @param   string      $content
     * @param   string      $filename
     * @param   array<int>  $allowedBankIds
     *
     * @return  bool
     *
     * @since   3.119.152
     */
    private static function refreshDuplicateImportMetadata(string $content, string $filename, array $allowedBankIds): bool
    {
        if (!self::importLogHasStatementMetadataColumns()) {
            return false;
        }

        $parsed = Mt940ParserHelper::parse($content);
        $acctNo = (string) ($parsed['account_number'] ?? '');
        if ($acctNo === '') {
            return false;
        }

        $bankAccountId = self::resolveBankAccountId($acctNo, $allowedBankIds);
        if ($bankAccountId < 1) {
            return false;
        }

        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->where('LOWER(' . $db->quoteName('filename') . ') = ' . $db->quote(\strtolower($filename)))
            ->where($db->quoteName('status') . ' = ' . $db->quote('imported'))
            ->order($db->quoteName('id') . ' DESC');
        $db->setQuery($query, 0, 1);
        $importLogId = (int) $db->loadResult();

        if ($importLogId < 1) {
            return false;
        }

        $currency = (string) ($parsed['currency'] ?? 'GTQ');
        $opening  = $parsed['opening_balance'] ?? null;
        $closing  = $parsed['closing_balance'] ?? null;
        $avail    = $parsed['closing_available_balance'] ?? null;
        $stmtDate = $parsed['statement_date'] ?? null;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_mt940_import_log'))
            ->set($db->quoteName('bank_account_id') . ' = ' . $bankAccountId)
            ->set($db->quoteName('account_number') . ' = ' . $db->quote($acctNo))
            ->set($db->quoteName('statement_reference') . ' = ' . $db->quote((string) ($parsed['statement_reference'] ?? '')))
            ->set($db->quoteName('statement_date') . ' = ' . ($stmtDate ? $db->quote($stmtDate) : 'NULL'))
            ->set($db->quoteName('statement_sequence') . ' = ' . $db->quote((string) ($parsed['statement_sequence'] ?? '')))
            ->set($db->quoteName('currency') . ' = ' . $db->quote($currency))
            ->set($db->quoteName('opening_balance') . ' = ' . ($opening !== null ? $db->quote((string) (float) $opening) : 'NULL'))
            ->set($db->quoteName('closing_balance') . ' = ' . ($closing !== null ? $db->quote((string) (float) $closing) : 'NULL'))
            ->set($db->quoteName('closing_available_balance') . ' = ' . ($avail !== null ? $db->quote((string) (float) $avail) : 'NULL'))
            ->where($db->quoteName('id') . ' = ' . $importLogId);

        if (self::importLogHasContentHashColumn()) {
            $query->set($db->quoteName('content_hash') . ' = ' . $db->quote(self::contentHash($content)));
        }

        $db->setQuery($query);
        $db->execute();

        return true;
    }

    /**
     * @return  bool
     */
    private static function importLogHasStatementMetadataColumns(): bool
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $cols = $db->getTableColumns('#__ordenproduccion_mt940_import_log', false);

            return isset($cols['statement_reference']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return  bool
     */
    private static function transactionsHaveTransactionCodeColumn(): bool
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $cols = $db->getTableColumns('#__ordenproduccion_mt940_transactions', false);

            return isset($cols['transaction_code']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param   array<string, mixed>  $data
     *
     * @return  int
     */
    private static function insertImportLog(array $data): int
    {
        $db  = Factory::getContainer()->get(DatabaseInterface::class);
        $obj = (object) $data;

        if (!self::importLogHasContentHashColumn()) {
            unset($obj->content_hash);
        }

        if (!self::importLogHasStatementMetadataColumns()) {
            foreach ([
                'statement_reference',
                'statement_date',
                'statement_sequence',
                'currency',
                'opening_balance',
                'closing_balance',
                'closing_available_balance',
            ] as $col) {
                unset($obj->$col);
            }
        }

        $db->insertObject('#__ordenproduccion_mt940_import_log', $obj, 'id');

        return (int) ($obj->id ?? 0);
    }

    /**
     * @param   int  $importLogId
     * @param   int  $count
     *
     * @return  void
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
     * @param   \Throwable  $e
     *
     * @return  bool
     */
    private static function isDuplicateKeyError(\Throwable $e): bool
    {
        $msg = \strtolower($e->getMessage());

        return \strpos($msg, 'duplicate') !== false
            || \strpos($msg, '1062') !== false
            || \strpos($msg, 'unique') !== false;
    }
}
