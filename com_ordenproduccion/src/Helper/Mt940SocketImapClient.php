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
 * Minimal IMAP client over PHP streams (no ext-imap required).
 *
 * @since  3.119.147
 */
class Mt940SocketImapClient
{
    /**
     * @var    resource|null
     */
    private $socket = null;

    /**
     * @var    int
     */
    private $tagNum = 0;

    /**
     * @param   string  $host
     * @param   int     $port
     * @param   string  $encryption  ssl|tls|none
     *
     * @return  void
     *
     * @since   3.119.147
     */
    public function connect(string $host, int $port, string $encryption): void
    {
        if (!\function_exists('stream_socket_client')) {
            throw new \RuntimeException('COM_ORDENPRODUCCION_MT940_IMAP_SOCKET_UNAVAILABLE');
        }

        $encryption = Mt940ImapHelper::normalizeEncryption($encryption);
        $errno      = 0;
        $errstr     = '';
        $timeout    = 30;

        $context = \stream_context_create([
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ],
        ]);

        if ($encryption === 'ssl') {
            if (!\extension_loaded('openssl')) {
                throw new \RuntimeException('COM_ORDENPRODUCCION_MT940_IMAP_OPENSSL_MISSING');
            }
            $remote = 'ssl://' . $host . ':' . $port;
            $this->socket = @\stream_socket_client($remote, $errno, $errstr, $timeout, \STREAM_CLIENT_CONNECT, $context);
        } else {
            $remote = 'tcp://' . $host . ':' . $port;
            $this->socket = @\stream_socket_client($remote, $errno, $errstr, $timeout, \STREAM_CLIENT_CONNECT, $context);
        }

        if ($this->socket === false) {
            $msg = $errstr !== '' ? $errstr : ('Connection failed (errno ' . $errno . ')');
            throw new \RuntimeException($msg);
        }

        \stream_set_timeout($this->socket, $timeout);
        $this->readLine();

        if ($encryption === 'tls') {
            if (!\extension_loaded('openssl')) {
                throw new \RuntimeException('COM_ORDENPRODUCCION_MT940_IMAP_OPENSSL_MISSING');
            }
            $resp = $this->command('STARTTLS');
            if (!$this->responseOk($resp)) {
                throw new \RuntimeException($this->responseMessage($resp) ?: 'STARTTLS failed');
            }

            $crypto = \STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (\defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto |= \STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }

            if (!@\stream_socket_enable_crypto($this->socket, true, $crypto)) {
                throw new \RuntimeException('STARTTLS negotiation failed');
            }
        }
    }

    /**
     * @param   string  $user
     * @param   string  $pass
     *
     * @return  void
     *
     * @since   3.119.147
     */
    public function login(string $user, string $pass): void
    {
        $resp = $this->command('LOGIN ' . self::quote($user) . ' ' . self::quote($pass));
        if (!$this->responseOk($resp)) {
            throw new \RuntimeException($this->responseMessage($resp) ?: 'Login failed');
        }
    }

    /**
     * @return  int
     *
     * @since   3.119.147
     */
    public function selectInbox(): int
    {
        $resp = $this->command('SELECT INBOX');
        if (\preg_match('/^\*\s+(\d+)\s+EXISTS/im', $resp, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    /**
     * @param   string       $sender
     * @param   string|null  $imapOnDate  e.g. 19-May-2026
     *
     * @return  array<int>
     *
     * @since   3.119.150
     */
    public function uidSearchFromSender(string $sender, ?string $imapOnDate = null): array
    {
        $sender = \trim($sender);
        if ($sender === '') {
            return [];
        }

        $cmd = 'UID SEARCH FROM ' . self::quote($sender);
        if ($imapOnDate !== null && $imapOnDate !== '') {
            $cmd .= ' ON ' . self::quote($imapOnDate);
        }

        $resp = $this->command($cmd);
        if (\preg_match('/^\*\s+SEARCH\s*(.*)$/im', $resp, $m)) {
            $ids = \trim((string) ($m[1] ?? ''));
            if ($ids === '') {
                return [];
            }

            return \array_values(\array_filter(\array_map('intval', \preg_split('/\s+/', $ids, -1, \PREG_SPLIT_NO_EMPTY))));
        }

        return [];
    }

    /**
     * @param   int  $uid
     *
     * @return  string
     *
     * @since   3.119.150
     */
    public function fetchUidRfc822(int $uid): string
    {
        if ($uid < 1 || !$this->socket) {
            return '';
        }

        $tag = $this->nextTag();
        \fwrite($this->socket, $tag . ' UID FETCH ' . $uid . ' (BODY.PEEK[])' . "\r\n");

        return $this->readFetchBody($tag);
    }

    /**
     * @param   string  $tag
     *
     * @return  string
     *
     * @since   3.119.150
     */
    private function readFetchBody(string $tag): string
    {
        $body = '';
        while (!\feof($this->socket)) {
            $line = $this->readLine();
            if ($line === '') {
                break;
            }

            if (\preg_match('/\{(\d+)\}\s*$/', $line, $m)) {
                $size = (int) $m[1];
                if ($size > 0) {
                    $body .= $this->readBytes($size);
                    $this->readLine();
                }
                continue;
            }

            if (\strpos($line, $tag . ' ') === 0) {
                break;
            }
        }

        return $body;
    }

    /**
     * @param   int  $bytes
     *
     * @return  string
     *
     * @since   3.119.150
     */
    private function readBytes(int $bytes): string
    {
        if (!$this->socket || $bytes < 1) {
            return '';
        }

        $buf = '';
        while (\strlen($buf) < $bytes && !\feof($this->socket)) {
            $chunk = @\fread($this->socket, $bytes - \strlen($buf));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $buf .= $chunk;
        }

        return $buf;
    }

    /**
     * @param   string  $sender
     *
     * @return  int
     *
     * @since   3.119.147
     */
    public function searchFromSender(string $sender): int
    {
        $sender = \trim($sender);
        if ($sender === '') {
            return 0;
        }

        $resp = $this->command('SEARCH FROM ' . self::quote($sender));
        if (\preg_match('/^\*\s+SEARCH\s*(.*)$/im', $resp, $m)) {
            $ids = \trim((string) ($m[1] ?? ''));
            if ($ids === '') {
                return 0;
            }

            return \count(\preg_split('/\s+/', $ids, -1, \PREG_SPLIT_NO_EMPTY));
        }

        return 0;
    }

    /**
     * @return  void
     *
     * @since   3.119.147
     */
    public function close(): void
    {
        if ($this->socket) {
            try {
                $this->command('LOGOUT');
            } catch (\Throwable $e) {
                // ignore logout errors
            }
            @\fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * @param   array<string, mixed>  $settings
     *
     * @return  array{success: bool, message: string, mailbox_total?: int, sender_total?: int, mailbox?: string, driver?: string}
     *
     * @since   3.119.147
     */
    public static function testConnection(array $settings): array
    {
        $host       = \trim((string) ($settings['imap_host'] ?? ''));
        $port       = \max(1, (int) ($settings['imap_port'] ?? 993));
        $encryption = Mt940ImapHelper::normalizeEncryption((string) ($settings['imap_encryption'] ?? 'ssl'));
        $username   = \trim((string) ($settings['imap_username'] ?? ''));
        $password   = (string) ($settings['imap_password'] ?? '');
        $sender     = \trim((string) ($settings['sender_email'] ?? Mt940ImapHelper::DEFAULT_SENDER_EMAIL));

        if ($host === '' || $username === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'COM_ORDENPRODUCCION_MT940_IMAP_MISSING_FIELDS',
            ];
        }

        $mailbox = Mt940ImapHelper::buildMailboxString($host, $port, $encryption);
        $client  = new self();

        try {
            $client->connect($host, $port, $encryption);
            $client->login($username, $password);
            $total       = $client->selectInbox();
            $senderTotal = $client->searchFromSender($sender);

            return [
                'success'       => true,
                'message'       => 'COM_ORDENPRODUCCION_MT940_IMAP_CONNECT_OK',
                'mailbox_total' => $total,
                'sender_total'  => $senderTotal,
                'mailbox'       => $mailbox,
                'driver'        => 'socket',
            ];
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (\strpos($msg, 'COM_ORDENPRODUCCION_') === 0) {
                return [
                    'success' => false,
                    'message' => $msg,
                    'mailbox' => $mailbox,
                    'driver'  => 'socket',
                ];
            }

            return [
                'success'    => false,
                'message'    => 'COM_ORDENPRODUCCION_MT940_IMAP_CONNECT_FAIL',
                'imap_error' => $msg,
                'mailbox'    => $mailbox,
                'driver'     => 'socket',
            ];
        } finally {
            $client->close();
        }
    }

    /**
     * @param   string  $cmd
     *
     * @return  string
     *
     * @since   3.119.147
     */
    private function command(string $cmd): string
    {
        if (!$this->socket) {
            throw new \RuntimeException('Not connected');
        }

        $tag = $this->nextTag();
        \fwrite($this->socket, $tag . ' ' . $cmd . "\r\n");

        return $this->readUntilTagged($tag);
    }

    /**
     * @param   string  $tag
     *
     * @return  string
     *
     * @since   3.119.147
     */
    private function readUntilTagged(string $tag): string
    {
        $buf = '';
        while (!\feof($this->socket)) {
            $line = $this->readLine();
            if ($line === '') {
                break;
            }
            $buf .= $line . "\n";
            if (\strpos($line, $tag . ' ') === 0) {
                break;
            }
        }

        return $buf;
    }

    /**
     * @return  string
     *
     * @since   3.119.147
     */
    private function readLine(): string
    {
        if (!$this->socket) {
            return '';
        }

        $line = @\fgets($this->socket);

        return $line === false ? '' : \rtrim($line, "\r\n");
    }

    /**
     * @return  string
     *
     * @since   3.119.147
     */
    private function nextTag(): string
    {
        return 'A' . \str_pad((string) (++$this->tagNum), 4, '0', \STR_PAD_LEFT);
    }

    /**
     * @param   string  $value
     *
     * @return  string
     *
     * @since   3.119.147
     */
    private static function quote(string $value): string
    {
        return '"' . \str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    /**
     * @param   string  $resp
     *
     * @return  bool
     *
     * @since   3.119.147
     */
    private function responseOk(string $resp): bool
    {
        return (bool) \preg_match('/\sOK\b/i', $resp);
    }

    /**
     * @param   string  $resp
     *
     * @return  string
     *
     * @since   3.119.147
     */
    private function responseMessage(string $resp): string
    {
        if (\preg_match('/\s(?:NO|BAD)\s+(.+)$/im', $resp, $m)) {
            return \trim((string) ($m[1] ?? ''));
        }

        return '';
    }
}
