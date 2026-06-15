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

/**
 * MT-940 import run log (cron and manual executions).
 *
 * @since  3.119.160
 */
class Mt940RunLogHelper
{
    public const TRIGGER_CRON          = 'cron';
    public const TRIGGER_MANUAL_MAILBOX = 'manual_mailbox';
    public const TRIGGER_MANUAL_FILE   = 'manual_file';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAIL    = 'fail';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * @return  bool
     *
     * @since   3.119.160
     */
    public static function tableAvailable(): bool
    {
        try {
            $db     = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'ordenproduccion_mt940_run_log'))->loadColumn();

            return !empty($tables);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param   string               $triggerType
     * @param   string               $status
     * @param   array<string, mixed>  $stats
     * @param   string               $message
     * @param   int                  $httpStatus
     * @param   int                  $createdBy
     * @param   array<string, mixed>|null  $details
     *
     * @return  void
     *
     * @since   3.119.160
     */
    public static function recordRun(
        string $triggerType,
        string $status,
        array $stats = [],
        string $message = '',
        int $httpStatus = 200,
        int $createdBy = 0,
        ?array $details = null
    ): void {
        if (!self::tableAvailable()) {
            return;
        }

        try {
            $db  = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $obj = (object) [
                'trigger_type'          => self::normalizeTriggerType($triggerType),
                'status'                => self::normalizeStatus($status),
                'emails_scanned'        => max(0, (int) ($stats['emails_scanned'] ?? 0)),
                'files_imported'        => max(0, (int) ($stats['files_imported'] ?? 0)),
                'files_skipped'         => max(0, (int) ($stats['files_skipped'] ?? 0)),
                'transactions_imported' => max(0, (int) ($stats['transactions_imported'] ?? 0)),
                'message'               => \mb_substr(\trim($message), 0, 65000),
                'details_json'          => $details !== null && $details !== []
                    ? \json_encode($details, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES)
                    : null,
                'http_status'           => max(0, min(999, $httpStatus)),
                'created_by'            => max(0, $createdBy),
                'ran_at'                => Factory::getDate()->toSql(),
            ];

            $db->insertObject('#__ordenproduccion_mt940_run_log', $obj, 'id');
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param   string               $triggerType
     * @param   array<string, mixed>  $result  Mt940MailboxImportHelper result
     * @param   string               $message   Human-readable summary
     * @param   int                  $httpStatus
     * @param   int                  $createdBy
     *
     * @return  void
     *
     * @since   3.119.160
     */
    public static function recordMailboxImport(
        string $triggerType,
        array $result,
        string $message = '',
        int $httpStatus = 200,
        int $createdBy = 0
    ): void {
        $status = !empty($result['success']) ? self::STATUS_SUCCESS : self::STATUS_FAIL;
        $details = [];
        if (!empty($result['details']) && \is_array($result['details'])) {
            $details['details'] = $result['details'];
        }
        if (!empty($result['driver'])) {
            $details['driver'] = (string) $result['driver'];
        }
        if (!empty($result['imap_error'])) {
            $details['imap_error'] = (string) $result['imap_error'];
        }

        self::recordRun(
            $triggerType,
            $status,
            [
                'emails_scanned'        => (int) ($result['emails_scanned'] ?? 0),
                'files_imported'        => (int) ($result['files_imported'] ?? 0),
                'files_skipped'         => (int) ($result['files_skipped'] ?? 0),
                'transactions_imported' => (int) ($result['transactions_imported'] ?? 0),
            ],
            $message,
            $httpStatus,
            $createdBy,
            $details !== [] ? $details : null
        );
    }

    /**
     * @param   array<string, mixed>  $result  Mt940ImportHelper::importFileContent result
     * @param   string               $filename
     * @param   string               $message
     * @param   int                  $createdBy
     *
     * @return  void
     *
     * @since   3.119.160
     */
    public static function recordFileImport(array $result, string $filename, string $message = '', int $createdBy = 0): void
    {
        $skipped = !empty($result['skipped']);
        $ok      = !empty($result['success']);
        $status  = $ok ? self::STATUS_SUCCESS : self::STATUS_FAIL;

        self::recordRun(
            self::TRIGGER_MANUAL_FILE,
            $status,
            [
                'emails_scanned'        => 0,
                'files_imported'        => ($ok && !$skipped) ? 1 : 0,
                'files_skipped'         => $skipped ? 1 : 0,
                'transactions_imported' => (int) ($result['transactions_count'] ?? 0),
            ],
            $message,
            $ok ? 200 : 500,
            $createdBy,
            [
                'filename'         => $filename,
                'bank_account_id'  => (int) ($result['bank_account_id'] ?? 0),
                'duplicate_reason' => (string) ($result['duplicate_reason'] ?? ''),
            ]
        );
    }

    /**
     * @param   int  $limit
     * @param   int  $start
     *
     * @return  array{rows: array<int, object>, total: int}
     *
     * @since   3.119.160
     */
    public static function getRunLogList(int $limit, int $start): array
    {
        if (!self::tableAvailable()) {
            return ['rows' => [], 'total' => 0];
        }

        $limit = max(5, min(200, $limit));
        $start = max(0, $start);

        try {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

            $countQ = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_mt940_run_log'));
            $db->setQuery($countQ);
            $total = (int) $db->loadResult();

            $listQ = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_mt940_run_log'))
                ->order($db->quoteName('ran_at') . ' DESC, ' . $db->quoteName('id') . ' DESC');
            $db->setQuery($listQ, $start, $limit);
            $rows = $db->loadObjectList() ?: [];

            return ['rows' => $rows, 'total' => $total];
        } catch (\Throwable $e) {
            return ['rows' => [], 'total' => 0];
        }
    }

    private static function normalizeTriggerType(string $triggerType): string
    {
        $allowed = [self::TRIGGER_CRON, self::TRIGGER_MANUAL_MAILBOX, self::TRIGGER_MANUAL_FILE];

        return \in_array($triggerType, $allowed, true) ? $triggerType : self::TRIGGER_CRON;
    }

    private static function normalizeStatus(string $status): string
    {
        $allowed = [self::STATUS_SUCCESS, self::STATUS_FAIL, self::STATUS_SKIPPED];

        return \in_array($status, $allowed, true) ? $status : self::STATUS_FAIL;
    }
}
