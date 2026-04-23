<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Persists outbound transactional email attempts for Control de ventas auditing.
 *
 * @since  3.113.39
 */
final class OutboundEmailLogHelper
{
    public const CONTEXT_VENDOR_QUOTE_REQUEST = 'vendor_quote_request';

    public const CONTEXT_PAYMENTPROOF_MISMATCH = 'paymentproof_mismatch';

    /** @since  3.113.63 */
    public const CONTEXT_ORDENCOMPRA_APPROVED = 'ordencompra_approved';

    /** @var int  Max chars per body field before JSON encode (UTF-8 safe). */
    private const MAX_BODY_FIELD_CHARS = 400000;

    /** @var int  Soft cap for encoded JSON length (below MEDIUMTEXT max). */
    private const MAX_JSON_CHARS = 12582912;

    /**
     * Record one outbound email attempt (success or failure).
     *
     * @param   string  $contextType   Short machine key (see CONTEXT_* constants).
     * @param   int     $userId        Joomla user id of the actor (0 if unknown).
     * @param   string  $toEmail       Recipient(s); may be comma-separated for multi-recipient sends.
     * @param   string  $subject       Email subject.
     * @param   bool    $success       Whether transport reported success.
     * @param   string  $errorMessage  Empty on success; otherwise error detail.
     * @param   array   $meta          Extra JSON-safe context (ids, etc.). Optional keys: body_html, body_text (stored for admin preview; size-capped).
     *
     * @return  void
     *
     * @since   3.113.39
     */
    public static function log(
        string $contextType,
        int $userId,
        string $toEmail,
        string $subject,
        bool $success,
        string $errorMessage = '',
        array $meta = []
    ): void {
        $meta = self::prepareMetaForStorage($meta);

        try {
            $jsonMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            $jsonMeta = '{}';
        }

        if (strlen($jsonMeta) > self::MAX_JSON_CHARS) {
            unset($meta['body_html'], $meta['body_text']);
            $meta['storage_truncated'] = true;

            try {
                $jsonMeta = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            } catch (\Throwable $e) {
                $jsonMeta = '{"storage_truncated":true}';
            }
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $created = Factory::getDate()->toSql();
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_outbound_email_log'))
                ->columns([
                    $db->quoteName('context_type'),
                    $db->quoteName('status'),
                    $db->quoteName('user_id'),
                    $db->quoteName('to_email'),
                    $db->quoteName('subject'),
                    $db->quoteName('error_message'),
                    $db->quoteName('meta'),
                    $db->quoteName('created'),
                ])
                ->values(implode(',', [
                    $db->quote(substr($contextType, 0, 64)),
                    $success ? '1' : '0',
                    (string) max(0, $userId),
                    $db->quote(substr($toEmail, 0, 512)),
                    $db->quote(substr($subject, 0, 512)),
                    $db->quote(substr($errorMessage, 0, 2000)),
                    $db->quote($jsonMeta),
                    $db->quote($created),
                ]));
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Never break the main request if logging fails (e.g. table not migrated yet).
        }
    }

    /**
     * Truncate large body fields so JSON fits DB; keeps ids and small keys intact.
     *
     * @param   array<string, mixed>  $meta
     *
     * @return  array<string, mixed>
     */
    private static function prepareMetaForStorage(array $meta): array
    {
        foreach (['body_html', 'body_text'] as $key) {
            if (!isset($meta[$key]) || !is_string($meta[$key])) {
                continue;
            }

            $str = $meta[$key];
            $len = function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str);

            if ($len > self::MAX_BODY_FIELD_CHARS) {
                $meta[$key] = function_exists('mb_substr')
                    ? mb_substr($str, 0, self::MAX_BODY_FIELD_CHARS, 'UTF-8')
                    : substr($str, 0, self::MAX_BODY_FIELD_CHARS);
                $meta[$key . '_truncated'] = true;
            }
        }

        return $meta;
    }

    /**
     * @return  bool
     *
     * @since   3.113.39
     */
    public static function isTableAvailable(): bool
    {
        static $cached = null;

        if ($cached !== null) {
            return $cached;
        }

        try {
            $db     = Factory::getContainer()->get(DatabaseInterface::class);
            $prefix = $db->getPrefix();
            $table  = $prefix . 'ordenproduccion_outbound_email_log';
            $db->setQuery('SHOW TABLES LIKE ' . $db->quote($table));
            $cached = (bool) $db->loadResult();
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * Paginated list for Administración → Correos enviados tab.
     *
     * @param   int       $limitstart    Offset.
     * @param   int       $limit         Page size.
     * @param   int|null  $filterUserId  If set, only rows for this user (Ventas: own sends).
     *
     * @return  array{rows: array<int, object>, total: int}
     *
     * @since   3.113.39
     */
    public static function getListForAdministracion(int $limitstart, int $limit, ?int $filterUserId): array
    {
        $rows  = [];
        $total = 0;

        if (!self::isTableAvailable()) {
            return ['rows' => $rows, 'total' => $total];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $countQ = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_outbound_email_log', 'l'));

            if ($filterUserId !== null && $filterUserId > 0) {
                $countQ->where($db->quoteName('l.user_id') . ' = ' . (int) $filterUserId);
            }

            $db->setQuery($countQ);
            $total = (int) $db->loadResult();

            $listQ = $db->getQuery(true)
                ->select([
                    $db->quoteName('l') . '.*',
                    $db->quoteName('u.name', 'actor_name'),
                    $db->quoteName('u.username', 'actor_username'),
                ])
                ->from($db->quoteName('#__ordenproduccion_outbound_email_log', 'l'))
                ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('l.user_id'))
                ->order($db->quoteName('l.created') . ' DESC')
                ->order($db->quoteName('l.id') . ' DESC');

            if ($filterUserId !== null && $filterUserId > 0) {
                $listQ->where($db->quoteName('l.user_id') . ' = ' . (int) $filterUserId);
            }

            $limit = max(1, min(100, $limit));
            $db->setQuery($listQ, $limitstart, $limit);
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            $rows  = [];
            $total = 0;
        }

        return ['rows' => $rows, 'total' => $total];
    }
}
