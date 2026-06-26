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
use Joomla\CMS\Input\Input;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

/**
 * Records authenticated user session / device data for Control de Ventas → User Audit.
 *
 * @since  3.119.111
 */
final class UserSessionAuditHelper
{
    /** @var int  Minimum seconds between DB writes for the same session row. */
    private const THROTTLE_SECONDS = 60;

    /**
     * Whether this request should be recorded (skip binary downloads and raw responses).
     */
    public static function shouldRecordFromInput(Input $input): bool
    {
        if (strtolower($input->getCmd('format', 'html')) === 'raw') {
            return false;
        }

        $task = strtolower($input->getCmd('task', ''));

        if ($task !== '' && (str_contains($task, 'download') || str_contains($task, 'export'))) {
            return false;
        }

        return true;
    }

    /**
     * Capture current request context into the audit table (upsert by user + session id).
     */
    public static function recordFromRequest(Input $input): void
    {
        if (!self::shouldRecordFromInput($input) || !self::isTableAvailable()) {
            return;
        }

        try {
            $app     = Factory::getApplication();
            $user    = Factory::getUser();
            $session = $app->getSession();

            if ($user->guest || (int) $user->id < 1) {
                return;
            }

            $sessionId = trim((string) $session->getId());
            if ($sessionId === '') {
                return;
            }

            $userId    = (int) $user->id;
            $now       = Factory::getDate()->toSql();
            $ua        = self::clientUserAgent();
            $parsed    = self::parseUserAgent($ua);
            $ip        = self::clientIp();
            $viewName  = substr(trim($input->getCmd('view', '')), 0, 64);
            $taskName  = substr(trim($input->getCmd('task', '')), 0, 128);
            $requestUri = self::truncate(trim((string) ($_SERVER['REQUEST_URI'] ?? '')), 512);
            $referer    = self::truncate(trim((string) ($_SERVER['HTTP_REFERER'] ?? '')), 512);
            $acceptLang = self::truncate(trim((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 255);

            $meta = self::buildMetaJson($user, $session, $input);

            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $findQ = $db->getQuery(true)
                ->select([
                    $db->quoteName('id'),
                    $db->quoteName('last_seen'),
                    $db->quoteName('hit_count'),
                ])
                ->from($db->quoteName('#__ordenproduccion_user_session_audit'))
                ->where($db->quoteName('user_id') . ' = ' . $userId)
                ->where($db->quoteName('session_id') . ' = ' . $db->quote($sessionId))
                ->order($db->quoteName('id') . ' DESC');
            $db->setQuery($findQ, 0, 1);
            $existing = $db->loadObject();

            if ($existing !== null) {
                $lastSeenTs = strtotime((string) ($existing->last_seen ?? ''));
                $nowTs      = strtotime($now);
                if ($lastSeenTs !== false && $nowTs !== false && ($nowTs - $lastSeenTs) < self::THROTTLE_SECONDS) {
                    return;
                }

                $update = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_user_session_audit'))
                    ->set($db->quoteName('ip_address') . ' = ' . $db->quote($ip))
                    ->set($db->quoteName('user_agent') . ' = ' . $db->quote($ua))
                    ->set($db->quoteName('browser') . ' = ' . $db->quote($parsed['browser']))
                    ->set($db->quoteName('platform') . ' = ' . $db->quote($parsed['platform']))
                    ->set($db->quoteName('device_type') . ' = ' . $db->quote($parsed['device_type']))
                    ->set($db->quoteName('accept_language') . ' = ' . $db->quote($acceptLang))
                    ->set($db->quoteName('request_uri') . ' = ' . $db->quote($requestUri))
                    ->set($db->quoteName('view_name') . ' = ' . $db->quote($viewName))
                    ->set($db->quoteName('task_name') . ' = ' . $db->quote($taskName))
                    ->set($db->quoteName('referer') . ' = ' . $db->quote($referer))
                    ->set($db->quoteName('meta') . ' = ' . $db->quote($meta))
                    ->set($db->quoteName('last_seen') . ' = ' . $db->quote($now))
                    ->set($db->quoteName('hit_count') . ' = ' . $db->quoteName('hit_count') . ' + 1')
                    ->where($db->quoteName('id') . ' = ' . (int) $existing->id);
                $db->setQuery($update);
                $db->execute();

                return;
            }

            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__ordenproduccion_user_session_audit'))
                ->columns([
                    $db->quoteName('user_id'),
                    $db->quoteName('session_id'),
                    $db->quoteName('ip_address'),
                    $db->quoteName('user_agent'),
                    $db->quoteName('browser'),
                    $db->quoteName('platform'),
                    $db->quoteName('device_type'),
                    $db->quoteName('accept_language'),
                    $db->quoteName('request_uri'),
                    $db->quoteName('view_name'),
                    $db->quoteName('task_name'),
                    $db->quoteName('referer'),
                    $db->quoteName('meta'),
                    $db->quoteName('first_seen'),
                    $db->quoteName('last_seen'),
                    $db->quoteName('hit_count'),
                ])
                ->values(implode(',', [
                    (string) $userId,
                    $db->quote($sessionId),
                    $db->quote($ip),
                    $db->quote($ua),
                    $db->quote($parsed['browser']),
                    $db->quote($parsed['platform']),
                    $db->quote($parsed['device_type']),
                    $db->quote($acceptLang),
                    $db->quote($requestUri),
                    $db->quote($viewName),
                    $db->quote($taskName),
                    $db->quote($referer),
                    $db->quote($meta),
                    $db->quote($now),
                    $db->quote($now),
                    '1',
                ]));
            $db->setQuery($insert);
            $db->execute();
        } catch (\Throwable $e) {
            Log::add(
                'UserSessionAuditHelper: could not persist row — ' . $e->getMessage(),
                Log::WARNING,
                'com_ordenproduccion'
            );
        }
    }

    /**
     * @return  bool
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
            $table  = $prefix . 'ordenproduccion_user_session_audit';
            $db->setQuery('SHOW TABLES LIKE ' . $db->quote($table));
            $cached = (bool) $db->loadResult();
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * Paginated list for Control de Ventas → User Audit tab.
     *
     * @param   array{user_id?:int,ip?:string,date_from?:string,date_to?:string}  $filters
     *
     * @return  array{rows: array<int, object>, total: int}
     */
    public static function getListForAdministracion(int $limitstart, int $limit, array $filters = []): array
    {
        $rows  = [];
        $total = 0;

        if (!self::isTableAvailable()) {
            return ['rows' => $rows, 'total' => $total];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            $applyFilters = static function ($query) use ($db, $filters) {
                if (!empty($filters['user_id']) && (int) $filters['user_id'] > 0) {
                    $query->where($db->quoteName('a.user_id') . ' = ' . (int) $filters['user_id']);
                }
                $ip = trim((string) ($filters['ip'] ?? ''));
                if ($ip !== '') {
                    $query->where($db->quoteName('a.ip_address') . ' LIKE ' . $db->quote('%' . $ip . '%'));
                }
                $from = trim((string) ($filters['date_from'] ?? ''));
                if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                    $query->where($db->quoteName('a.last_seen') . ' >= ' . $db->quote($from . ' 00:00:00'));
                }
                $to = trim((string) ($filters['date_to'] ?? ''));
                if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                    $query->where($db->quoteName('a.last_seen') . ' <= ' . $db->quote($to . ' 23:59:59'));
                }

                return $query;
            };

            $countQ = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_user_session_audit', 'a'));
            $applyFilters($countQ);
            $db->setQuery($countQ);
            $total = (int) $db->loadResult();

            $listQ = $db->getQuery(true)
                ->select([
                    $db->quoteName('a') . '.*',
                    $db->quoteName('u.name', 'user_name'),
                    $db->quoteName('u.username', 'user_username'),
                    $db->quoteName('u.email', 'user_email'),
                ])
                ->from($db->quoteName('#__ordenproduccion_user_session_audit', 'a'))
                ->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'))
                ->order($db->quoteName('a.last_seen') . ' DESC')
                ->order($db->quoteName('a.id') . ' DESC');
            $applyFilters($listQ);

            $limit = max(1, min(100, $limit));
            $db->setQuery($listQ, $limitstart, $limit);
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            $rows  = [];
            $total = 0;
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Active Joomla users for filter dropdown.
     *
     * @return  array<int, string>  id => "Name (username)"
     */
    public static function getUserFilterOptions(): array
    {
        if (!self::isTableAvailable()) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $q  = $db->getQuery(true)
                ->select([
                    $db->quoteName('u.id'),
                    $db->quoteName('u.name'),
                    $db->quoteName('u.username'),
                ])
                ->from($db->quoteName('#__ordenproduccion_user_session_audit', 'a'))
                ->join('INNER', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'))
                ->group($db->quoteName('u.id'))
                ->order($db->quoteName('u.name') . ' ASC');
            $db->setQuery($q);
            $rows = $db->loadObjectList() ?: [];
            $out  = [];
            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);
                if ($id < 1) {
                    continue;
                }
                $name = trim((string) ($row->name ?? ''));
                $user = trim((string) ($row->username ?? ''));
                $label = $name !== '' ? $name : ('#' . $id);
                if ($user !== '') {
                    $label .= ' (' . $user . ')';
                }
                $out[$id] = $label;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return  string
     */
    public static function clientIp(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $xff   = trim((string) ($parts[0] ?? ''));
            if ($xff !== '') {
                $ip = $xff;
            }
        }

        return self::truncate($ip, 64);
    }

    /**
     * @return  string
     */
    public static function clientUserAgent(): string
    {
        return self::truncate(trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), 512);
    }

    /**
     * @return  array{browser:string,platform:string,device_type:string}
     */
    public static function parseUserAgent(string $ua): array
    {
        $device = 'desktop';
        if ($ua === '') {
            return ['browser' => 'Unknown', 'platform' => 'Unknown', 'device_type' => 'unknown'];
        }

        if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit/i', $ua)) {
            $device = 'bot';
        } elseif (preg_match('/ipad|tablet|playbook|silk(?!.*mobile)/i', $ua)) {
            $device = 'tablet';
        } elseif (preg_match('/mobile|android|iphone|ipod|blackberry|opera mini|iemobile/i', $ua)) {
            $device = 'mobile';
        }

        $browser = 'Unknown';
        if (preg_match('/Edg\/(\d+)/', $ua, $m)) {
            $browser = 'Edge ' . $m[1];
        } elseif (preg_match('/OPR\/(\d+)/', $ua, $m)) {
            $browser = 'Opera ' . $m[1];
        } elseif (preg_match('/Chrome\/(\d+)/', $ua, $m) && !preg_match('/Edg\//', $ua)) {
            $browser = 'Chrome ' . $m[1];
        } elseif (preg_match('/Firefox\/(\d+)/', $ua, $m)) {
            $browser = 'Firefox ' . $m[1];
        } elseif (preg_match('/Version\/(\d+).*Safari/', $ua, $m) && !preg_match('/Chrome/', $ua)) {
            $browser = 'Safari ' . $m[1];
        }

        $platform = 'Unknown';
        if (preg_match('/Windows NT/i', $ua)) {
            $platform = 'Windows';
        } elseif (preg_match('/Android/i', $ua)) {
            $platform = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) {
            $platform = 'iOS';
        } elseif (preg_match('/Mac OS X|Macintosh/i', $ua)) {
            $platform = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $platform = 'Linux';
        }

        return [
            'browser'     => self::truncate($browser, 128),
            'platform'    => self::truncate($platform, 128),
            'device_type' => $device,
        ];
    }

    /**
     * @param   \Joomla\CMS\User\User                    $user
     * @param   \Joomla\CMS\Session\SessionInterface     $session
     */
    private static function buildMetaJson($user, $session, Input $input): string
    {
        $groupIds = $user->getAuthorisedGroups();
        $groupTitles = [];
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            if ($groupIds !== []) {
                $gq = $db->getQuery(true)
                    ->select([$db->quoteName('id'), $db->quoteName('title')])
                    ->from($db->quoteName('#__usergroups'))
                    ->where($db->quoteName('id') . ' IN (' . implode(',', array_map('intval', $groupIds)) . ')');
                $db->setQuery($gq);
                foreach ($db->loadObjectList() ?: [] as $g) {
                    $groupTitles[(int) $g->id] = (string) ($g->title ?? '');
                }
            }
        } catch (\Throwable $e) {
            // Non-blocking
        }

        $meta = [
            'username'              => (string) $user->username,
            'email'                 => (string) $user->email,
            'display_name'          => (string) $user->name,
            'group_ids'             => array_values(array_map('intval', $groupIds)),
            'group_titles'          => $groupTitles,
            'is_super_user'         => AccessHelper::isSuperUser(),
            'session_cookie_name'   => method_exists($session, 'getName') ? (string) $session->getName() : '',
            'request_method'        => strtoupper(trim((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'))),
            'http_accept'           => self::truncate(trim((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 512),
            'http_accept_encoding'  => self::truncate(trim((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')), 255),
            'http_connection'       => self::truncate(trim((string) ($_SERVER['HTTP_CONNECTION'] ?? '')), 64),
            'http_sec_ch_ua'        => self::truncate(trim((string) ($_SERVER['HTTP_SEC_CH_UA'] ?? '')), 255),
            'http_sec_ch_ua_mobile' => self::truncate(trim((string) ($_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '')), 32),
            'http_sec_ch_ua_platform' => self::truncate(trim((string) ($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? '')), 64),
            'http_host'             => self::truncate(trim((string) ($_SERVER['HTTP_HOST'] ?? '')), 255),
            'controller'            => trim($input->getCmd('controller', '')),
            'option'                => trim($input->getCmd('option', '')),
            'layout'                => trim($input->getCmd('layout', '')),
            'format'                => trim($input->getCmd('format', '')),
        ];

        $impersonationMeta = UserImpersonationHelper::getAuditMeta();
        if ($impersonationMeta !== []) {
            $meta['impersonation'] = $impersonationMeta;
        }

        try {
            return json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return '{}';
        }
    }

    private static function truncate(string $s, int $max): string
    {
        if ($max < 1 || $s === '') {
            return $s;
        }
        if (function_exists('mb_strlen') && mb_strlen($s, 'UTF-8') > $max) {
            return mb_substr($s, 0, $max, 'UTF-8');
        }
        if (strlen($s) > $max) {
            return substr($s, 0, $max);
        }

        return $s;
    }
}
