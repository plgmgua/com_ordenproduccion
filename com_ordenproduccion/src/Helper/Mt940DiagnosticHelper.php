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

use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * MT-940 IMAP connectivity diagnostic (web troubleshooting + support).
 *
 * @since  3.119.168
 */
class Mt940DiagnosticHelper
{
    private int $failures = 0;
    private int $warnings = 0;

    /** @var array<int, array<string, mixed>> */
    private array $sections = [];

    /**
     * @return array<string, mixed>
     *
     * @since  3.119.168
     */
    public function run(): array
    {
        $this->failures = 0;
        $this->warnings = 0;
        $this->sections = [];

        $settings = $this->loadSettings();
        $host     = \trim((string) ($settings['imap_host'] ?? ''));
        $port     = \max(1, (int) ($settings['imap_port'] ?? 993));
        $enc      = Mt940ImapHelper::normalizeEncryption((string) ($settings['imap_encryption'] ?? 'ssl'));
        $user     = \trim((string) ($settings['imap_username'] ?? ''));
        $passSet  = \trim((string) ($settings['imap_password'] ?? '')) !== '';

        $this->addSection('PHP environment', [
            $this->check('PHP IMAP extension', \function_exists('imap_open') ? 'pass' : 'warn', \function_exists('imap_open')
                ? 'ext-imap available (preferred driver)'
                : 'ext-imap missing; socket client will be used'),
            $this->check('OpenSSL extension', \extension_loaded('openssl') ? 'pass' : 'fail', \extension_loaded('openssl')
                ? 'OpenSSL loaded'
                : 'OpenSSL required for IMAP over SSL/TLS'),
            $this->check('stream_socket_client', \function_exists('stream_socket_client') ? 'pass' : 'fail', \function_exists('stream_socket_client')
                ? 'Available'
                : 'Required for socket IMAP client'),
            $this->check('default_socket_timeout', 'info', (string) \ini_get('default_socket_timeout') . ' seconds'),
        ]);

        $this->addSection('Stored MT-940 IMAP settings', [
            $this->check('Import enabled', ($settings['enabled'] ?? '0') === '1' ? 'pass' : 'warn', ($settings['enabled'] ?? '0') === '1' ? 'Yes' : 'No'),
            $this->check('IMAP host', $host !== '' ? 'pass' : 'fail', $host !== '' ? $host : 'Not configured'),
            $this->check('IMAP port', 'info', (string) $port),
            $this->check('Encryption', 'info', $enc),
            $this->check('IMAP username', $user !== '' ? 'pass' : 'fail', $user !== '' ? $user : 'Not configured'),
            $this->check('IMAP password stored', $passSet ? 'pass' : 'fail', $passSet ? 'Yes (value hidden)' : 'Missing'),
            $this->check('Sender filter', 'info', \trim((string) ($settings['sender_email'] ?? Mt940ImapHelper::DEFAULT_SENDER_EMAIL))),
        ], [
            'mailbox_string' => $host !== '' ? Mt940ImapHelper::buildMailboxString($host, $port, $enc) : '',
            'driver_expected' => \function_exists('imap_open') ? 'imap' : 'socket',
        ]);

        if ($host === '' || $user === '' || !$passSet) {
            return $this->buildReport($settings);
        }

        $resolved = @\gethostbyname($host);
        $dnsOk    = \filter_var($host, FILTER_VALIDATE_IP) || ($resolved !== '' && $resolved !== $host);
        $this->addSection('DNS', [
            $this->check(
                'Resolve ' . $host,
                $dnsOk ? 'pass' : 'fail',
                $dnsOk ? ($resolved !== $host ? $host . ' → ' . $resolved : $host . ' (literal IP)') : 'Could not resolve host'
            ),
        ]);

        $tcp = self::probeTcp($host, $port, $enc, 15);
        $this->addSection('TCP reachability (from this server)', [
            $this->check(
                'Connect ' . ($tcp['remote'] ?? ($host . ':' . $port)),
                $tcp['ok'] ? 'pass' : 'fail',
                (string) ($tcp['detail'] ?? '')
            ),
        ], $tcp);

        $imap = Mt940ImapHelper::testConnection($settings);
        $imapStatus = !empty($imap['success']) ? 'pass' : 'fail';
        if (!$imapStatus && \strpos((string) ($imap['imap_error'] ?? ''), 'timed out') !== false) {
            $this->warnings++;
        }
        $this->addSection('Full IMAP login test', [
            $this->check(
                'Login + INBOX',
                $imapStatus,
                !empty($imap['success'])
                    ? \sprintf(
                        'OK — driver %s, INBOX messages: %d, from sender: %d',
                        (string) ($imap['driver'] ?? ''),
                        (int) ($imap['mailbox_total'] ?? 0),
                        (int) ($imap['sender_total'] ?? 0)
                    )
                    : ((string) ($imap['imap_error'] ?? '') !== '' ? (string) $imap['imap_error'] : 'Connection failed')
            ),
        ], [
            'driver'   => (string) ($imap['driver'] ?? ''),
            'mailbox'  => (string) ($imap['mailbox'] ?? ''),
            'message'  => (string) ($imap['message'] ?? ''),
        ]);

        return $this->buildReport($settings);
    }

    /**
     * Quick TCP probe used by import error payloads.
     *
     * @return array{ok: bool, remote: string, detail: string, elapsed_ms: int}
     */
    public static function probeTcp(string $host, int $port, string $encryption, int $timeoutSec = 15): array
    {
        $host       = \trim($host);
        $port       = \max(1, $port);
        $timeoutSec = \max(3, \min(60, $timeoutSec));
        $encryption = Mt940ImapHelper::normalizeEncryption($encryption);
        $remote     = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $start      = \microtime(true);

        if (!\function_exists('stream_socket_client')) {
            return [
                'ok'         => false,
                'remote'     => $remote,
                'detail'     => 'stream_socket_client unavailable',
                'elapsed_ms' => 0,
            ];
        }

        $context = \stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        $errno  = 0;
        $errstr = '';
        $sock   = @\stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $timeoutSec,
            \STREAM_CLIENT_CONNECT,
            $encryption === 'ssl' ? $context : null
        );
        $elapsedMs = (int) \round((\microtime(true) - $start) * 1000);

        if ($sock !== false) {
            @\fclose($sock);

            return [
                'ok'         => true,
                'remote'     => $remote,
                'detail'     => 'Connected in ' . $elapsedMs . ' ms',
                'elapsed_ms' => $elapsedMs,
            ];
        }

        $detail = $errstr !== '' ? $errstr : ('errno ' . $errno);
        if (\stripos($detail, 'timed out') !== false) {
            $detail .= '. Outbound connections from this web server to ' . $host . ':' . $port
                . ' may be blocked by hosting firewall or wrong host/port.';
        }

        return [
            'ok'         => false,
            'remote'     => $remote,
            'detail'     => $detail,
            'elapsed_ms' => $elapsedMs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadSettings(): array
    {
        try {
            BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_ordenproduccion/src/Model');
            /** @var AdministracionModel|null $model */
            $model = BaseDatabaseModel::getInstance('Administracion', 'Grimpsa\\Component\\Ordenproduccion\\Site\\Model');
            if ($model) {
                return $model->getMt940Settings();
            }
        } catch (\Throwable $e) {
        }

        return [];
    }

    /**
     * @param   array<string, mixed>  $settings
     *
     * @return array<string, mixed>
     */
    private function buildReport(array $settings): array
    {
        $status = $this->failures > 0 ? 'fail' : ($this->warnings > 0 ? 'warn' : 'ok');

        return [
            'meta' => [
                'status'   => $status,
                'failures' => $this->failures,
                'warnings' => $this->warnings,
                'time'     => Factory::getDate()->format('Y-m-d H:i:s T'),
            ],
            'config' => [
                'enabled'         => (string) ($settings['enabled'] ?? '0'),
                'imap_host'       => (string) ($settings['imap_host'] ?? ''),
                'imap_port'       => (string) ($settings['imap_port'] ?? '993'),
                'imap_encryption' => (string) ($settings['imap_encryption'] ?? 'ssl'),
                'imap_username'   => (string) ($settings['imap_username'] ?? ''),
                'password_set'    => \trim((string) ($settings['imap_password'] ?? '')) !== '',
                'sender_email'    => (string) ($settings['sender_email'] ?? Mt940ImapHelper::DEFAULT_SENDER_EMAIL),
            ],
            'sections' => $this->sections,
        ];
    }

    /**
     * @param   array<int, array<string, mixed>>  $checks
     * @param   array<string, mixed>              $details
     */
    private function addSection(string $title, array $checks, array $details = []): void
    {
        $this->sections[] = [
            'title'   => $title,
            'checks'  => $checks,
            'details' => $details,
        ];
    }

    /**
     * @return array{status: string, label: string, detail: string}
     */
    private function check(string $label, string $status, string $detail): array
    {
        if ($status === 'fail') {
            $this->failures++;
        } elseif ($status === 'warn') {
            $this->warnings++;
        }

        return [
            'status' => $status,
            'label'  => $label,
            'detail' => $detail,
        ];
    }
}
