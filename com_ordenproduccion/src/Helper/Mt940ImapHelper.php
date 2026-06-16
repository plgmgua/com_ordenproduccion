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
use Joomla\CMS\Language\Text;

/**
 * IMAP connectivity for MT-940 bank statement emails.
 *
 * @since  3.119.146
 */
class Mt940ImapHelper
{
    /**
     * Default sender filter for Banco Industrial MT-940 notifications.
     */
    public const DEFAULT_SENDER_EMAIL = 'confirmacionbisf@corporacionbi.gt';

    /**
     * @param   array<string, mixed>  $settings
     *
     * @return  array{success: bool, message: string, mailbox_total?: int, sender_total?: int, mailbox?: string, driver?: string, imap_error?: string}
     *
     * @since   3.119.146
     */
    public static function testConnection(array $settings): array
    {
        $host       = \trim((string) ($settings['imap_host'] ?? ''));
        $port       = \max(1, (int) ($settings['imap_port'] ?? 993));
        $encryption = self::normalizeEncryption((string) ($settings['imap_encryption'] ?? 'ssl'));
        $username   = \trim((string) ($settings['imap_username'] ?? ''));
        $password   = (string) ($settings['imap_password'] ?? '');
        $sender     = \trim((string) ($settings['sender_email'] ?? self::DEFAULT_SENDER_EMAIL));

        if ($host === '' || $username === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'COM_ORDENPRODUCCION_MT940_IMAP_MISSING_FIELDS',
            ];
        }

        if (\function_exists('imap_open')) {
            return self::testConnectionWithExtension($host, $port, $encryption, $username, $password, $sender);
        }

        return Mt940SocketImapClient::testConnection($settings);
    }

    /**
     * Default IMAP connect/read timeout (seconds).
     *
     * @since  3.119.168
     */
    public static function getConnectTimeoutSeconds(): int
    {
        $timeout = (int) \ini_get('default_socket_timeout');
        if ($timeout < 1) {
            $timeout = 60;
        }

        return \max(30, \min(120, $timeout));
    }

    /**
     * @param   string  $host
     * @param   int     $port
     * @param   string  $encryption
     * @param   string  $username
     * @param   string  $password
     * @param   string  $sender
     *
     * @return  array{success: bool, message: string, mailbox_total?: int, sender_total?: int, mailbox?: string, driver?: string, imap_error?: string}
     *
     * @since   3.119.147
     */
    private static function testConnectionWithExtension(
        string $host,
        int $port,
        string $encryption,
        string $username,
        string $password,
        string $sender
    ): array {
        $mailbox = self::buildMailboxString($host, $port, $encryption);
        $imap    = @\imap_open($mailbox, $username, $password, \OP_READONLY, 1);

        if ($imap === false) {
            $err = \trim((string) \imap_last_error());

            return [
                'success'    => false,
                'message'    => 'COM_ORDENPRODUCCION_MT940_IMAP_CONNECT_FAIL',
                'imap_error' => $err,
                'mailbox'    => $mailbox,
                'driver'     => 'imap',
            ];
        }

        $total = 0;
        $check = @\imap_check($imap);
        if ($check && isset($check->Nmsgs)) {
            $total = (int) $check->Nmsgs;
        }

        $senderTotal = 0;
        if ($sender !== '') {
            $criteria = 'FROM "' . self::escapeImapSearchValue($sender) . '"';
            $matches  = @\imap_search($imap, $criteria, \SE_UID);
            if (\is_array($matches)) {
                $senderTotal = \count($matches);
            }
        }

        @\imap_close($imap);

        return [
            'success'       => true,
            'message'       => 'COM_ORDENPRODUCCION_MT940_IMAP_CONNECT_OK',
            'mailbox_total' => $total,
            'sender_total'  => $senderTotal,
            'mailbox'       => $mailbox,
            'driver'        => 'imap',
        ];
    }

    /**
     * @param   string  $host
     * @param   int     $port
     * @param   string  $encryption  ssl|tls|none
     *
     * @return  string  e.g. {imap.example.com:993/imap/ssl}INBOX
     *
     * @since   3.119.146
     */
    public static function buildMailboxString(string $host, int $port, string $encryption = 'ssl'): string
    {
        $encryption = self::normalizeEncryption($encryption);
        $flags      = '/imap';
        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        } else {
            $flags .= '/notls';
        }

        return '{' . $host . ':' . $port . $flags . '}INBOX';
    }

    /**
     * @param   string  $value
     *
     * @return  string
     *
     * @since   3.119.146
     */
    public static function normalizeEncryption(string $value): string
    {
        $value = strtolower(trim($value));

        if (\in_array($value, ['tls', 'starttls'], true)) {
            return 'tls';
        }

        if ($value === 'none' || $value === 'notls' || $value === '') {
            return 'none';
        }

        return 'ssl';
    }

    /**
     * @param   string  $email
     *
     * @return  string
     *
     * @since   3.119.146
     */
    private static function escapeImapSearchValue(string $email): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $email);
    }

    /**
     * Translate MT-940 IMAP helper result into a user-facing message.
     *
     * @param   array<string, mixed>  $result
     * @param   array<string, mixed>  $settings
     *
     * @return  string
     *
     * @since   3.119.168
     */
    public static function formatResultMessage(array $result, array $settings = []): string
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $msgKey = (string) ($result['message'] ?? '');
        $msg    = $msgKey !== '' && \strpos($msgKey, 'COM_ORDENPRODUCCION_') === 0 ? Text::_($msgKey) : $msgKey;
        $imapErr = \trim((string) ($result['imap_error'] ?? ''));

        if ($imapErr !== '') {
            $host = \trim((string) ($settings['imap_host'] ?? ''));
            $port = \max(1, (int) ($settings['imap_port'] ?? 993));
            if (\stripos($imapErr, 'timed out') !== false && $host !== '') {
                $msg = Text::sprintf(
                    'COM_ORDENPRODUCCION_MT940_IMAP_TIMEOUT_DETAIL',
                    $msg,
                    $host,
                    $port,
                    $imapErr
                );
            } else {
                $msg = \trim($msg . ' ' . $imapErr);
            }
        }

        return \trim($msg);
    }

    /**
     * @param   array<string, mixed>  $result
     * @param   array<string, mixed>  $settings
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.168
     */
    public static function buildFailurePayload(array $result, array $settings = []): array
    {
        $host = \trim((string) ($settings['imap_host'] ?? ''));
        $port = \max(1, (int) ($settings['imap_port'] ?? 993));
        $enc  = self::normalizeEncryption((string) ($settings['imap_encryption'] ?? 'ssl'));
        $payload = [
            'driver'      => (string) ($result['driver'] ?? ''),
            'imap_host'   => $host,
            'imap_port'   => $port,
            'encryption'  => $enc,
            'imap_error'  => (string) ($result['imap_error'] ?? ''),
        ];

        if ($host !== '') {
            $payload['tcp_probe'] = Mt940DiagnosticHelper::probeTcp($host, $port, $enc, 15);
        }

        return $payload;
    }
}
