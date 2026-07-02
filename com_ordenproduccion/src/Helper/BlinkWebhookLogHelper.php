<?php
/**
 * Persist Blink gateway log.created webhook payloads.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Stores full Blink exchange log entries from webhook `payload.data`.
 *
 * @since  3.119.208
 */
class BlinkWebhookLogHelper
{
    public const PAGE_SIZE = 25;

    /**
     * @param   \Joomla\Database\DatabaseInterface  $db
     *
     * @return  bool
     */
    public static function tableExists(DatabaseInterface $db): bool
    {
        static $ok;

        if ($ok !== null) {
            return $ok;
        }

        try {
            $prefix = $db->getPrefix();
            $tables = $db->getTableList();
            $name   = $prefix . 'ordenproduccion_blink_exchange_logs';
            $ok     = \in_array($name, $tables, true);
        } catch (\Throwable $e) {
            $ok = false;
        }

        return $ok;
    }

    /**
     * Insert one exchange log row from webhook payload.data. Never throws.
     *
     * @param   array<string,mixed>  $logData  Full log entry object
     * @param   string               $event    Event name (e.g. log.created)
     *
     * @return  int  Insert id, or 0 on failure
     */
    public static function storeLogEntry(array $logData, string $event = 'log.created'): int
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!self::tableExists($db)) {
            return 0;
        }

        $json = json_encode($logData, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return 0;
        }

        $blinkLogId = self::pickString($logData, ['id', '_id', 'logId', 'log_id']);
        $requestId  = self::pickString($logData, ['requestId', 'request_id']);
        $referenceId = self::pickString($logData, ['referenceId', 'reference_id']);
        $operation  = self::pickString($logData, ['gatewayOperation', 'gateway_operation', 'operation']);

        $row = (object) [
            'blink_log_id'       => self::truncate($blinkLogId, 64),
            'request_id'         => self::truncate($requestId, 64),
            'reference_id'       => self::truncate($referenceId, 100),
            'gateway_operation'  => self::truncate($operation, 64),
            'event_type'         => self::truncate($event !== '' ? $event : 'log.created', 32),
            'log_data_json'      => $json,
            'received'           => Factory::getDate()->toSql(),
        ];

        try {
            $db->insertObject('#__ordenproduccion_blink_exchange_logs', $row, 'id');

            return (int) ($row->id ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return  int
     */
    public static function countEntries(): int
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return 0;
        }

        if (!self::tableExists($db)) {
            return 0;
        }

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_blink_exchange_logs'))
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @param   int  $limit
     * @param   int  $offset
     *
     * @return  array<int,object>
     */
    public static function getRecentEntries(int $limit = self::PAGE_SIZE, int $offset = 0): array
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
        } catch (\Throwable $e) {
            return [];
        }

        if (!self::tableExists($db)) {
            return [];
        }

        $limit  = max(1, min(200, $limit));
        $offset = max(0, $offset);

        try {
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_blink_exchange_logs'))
                    ->order($db->quoteName('received') . ' DESC')
                    ->setLimit($limit, $offset)
            );

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param   array<string,mixed>  $data
     * @param   array<int,string>    $keys
     *
     * @return  string
     */
    private static function pickString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (!isset($data[$key])) {
                continue;
            }
            $val = trim((string) $data[$key]);
            if ($val !== '') {
                return $val;
            }
        }

        return '';
    }

    /**
     * @param   string  $value
     * @param   int     $max
     *
     * @return  string|null
     */
    private static function truncate(string $value, int $max): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (\strlen($value) > $max) {
            return \substr($value, 0, $max);
        }

        return $value;
    }
}
