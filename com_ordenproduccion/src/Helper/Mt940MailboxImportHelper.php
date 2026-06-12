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
 * Fetch MT-940 attachments from configured IMAP mailbox and import them.
 *
 * @since  3.119.150
 */
class Mt940MailboxImportHelper
{
    /**
     * @param   array<string, mixed>  $imapSettings  host, port, encryption, username, password, sender_email
     * @param   array<int>            $allowedBankIds
     *
     * @return  array{
     *   success: bool,
     *   message: string,
     *   files_imported?: int,
     *   files_skipped?: int,
     *   transactions_imported?: int,
     *   emails_scanned?: int,
     *   details?: array<int, string>,
     *   driver?: string
     * }
     *
     * @since  3.119.150
     */
    public static function runInitialImport(array $imapSettings, array $allowedBankIds): array
    {
        if ($allowedBankIds === []) {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_IMPORT_NO_ACCOUNTS_CONFIGURED'];
        }

        if (!Mt940ImportHelper::tablesAvailable()) {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_SCHEMA_MISSING'];
        }

        $host     = \trim((string) ($imapSettings['imap_host'] ?? ''));
        $port     = \max(1, (int) ($imapSettings['imap_port'] ?? 993));
        $enc      = Mt940ImapHelper::normalizeEncryption((string) ($imapSettings['imap_encryption'] ?? 'ssl'));
        $user     = \trim((string) ($imapSettings['imap_username'] ?? ''));
        $password = (string) ($imapSettings['imap_password'] ?? '');
        $sender   = \trim((string) ($imapSettings['sender_email'] ?? Mt940ImapHelper::DEFAULT_SENDER_EMAIL));

        if ($host === '' || $user === '' || $password === '') {
            return ['success' => false, 'message' => 'COM_ORDENPRODUCCION_MT940_IMAP_MISSING_FIELDS'];
        }

        if (\function_exists('imap_open')) {
            return self::runWithImapExtension($host, $port, $enc, $user, $password, $sender, $allowedBankIds);
        }

        return self::runWithSocketClient($host, $port, $enc, $user, $password, $sender, $allowedBankIds);
    }

    /**
     * @param   array<int>  $allowedBankIds
     *
     * @return  array<string, mixed>
     */
    private static function runWithImapExtension(
        string $host,
        int $port,
        string $encryption,
        string $user,
        string $password,
        string $sender,
        array $allowedBankIds
    ): array {
        $mailbox = Mt940ImapHelper::buildMailboxString($host, $port, $encryption);
        $imap    = @\imap_open($mailbox, $user, $password, \OP_READONLY, 1);

        if ($imap === false) {
            return [
                'success'    => false,
                'message'    => 'COM_ORDENPRODUCCION_MT940_IMAP_CONNECT_FAIL',
                'imap_error' => \trim((string) \imap_last_error()),
                'driver'     => 'imap',
            ];
        }

        $summary = self::emptySummary('imap');

        try {
            $criteria = 'FROM "' . \str_replace(['\\', '"'], ['\\\\', '\\"'], $sender) . '"';
            $uids     = @\imap_search($imap, $criteria, \SE_UID);
            if (!\is_array($uids)) {
                $uids = [];
            }

            $summary['emails_scanned'] = \count($uids);

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid < 1) {
                    continue;
                }

                $overview = @\imap_fetch_overview($imap, (string) $uid, \FT_UID);
                $subject  = '';
                $from     = '';
                if (\is_array($overview) && isset($overview[0])) {
                    $subject = \trim((string) ($overview[0]->subject ?? ''));
                    $from    = \trim((string) ($overview[0]->from ?? ''));
                }

                $raw = (string) @\imap_fetchheader($imap, (string) $uid, \FT_UID);
                $raw .= (string) @\imap_body($imap, (string) $uid, \FT_UID);

                $attachments = Mt940MimeHelper::extractTextAttachments($raw);
                if ($attachments === []) {
                    $structure = @\imap_fetchstructure($imap, (string) $uid, \FT_UID);
                    if ($structure) {
                        $attachments = self::extractAttachmentsFromStructure($imap, $uid, $structure);
                    }
                }

                self::importAttachmentBatch(
                    $attachments,
                    $allowedBankIds,
                    (string) $uid,
                    $from !== '' ? $from : $sender,
                    $subject,
                    $summary
                );
            }
        } finally {
            @\imap_close($imap);
        }

        return self::finalizeSummary($summary);
    }

    /**
     * @param   array<int>  $allowedBankIds
     *
     * @return  array<string, mixed>
     */
    private static function runWithSocketClient(
        string $host,
        int $port,
        string $encryption,
        string $user,
        string $password,
        string $sender,
        array $allowedBankIds
    ): array {
        $client  = new Mt940SocketImapClient();
        $summary = self::emptySummary('socket');

        try {
            $client->connect($host, $port, $encryption);
            $client->login($user, $password);
            $client->selectInbox();
            $uids = $client->uidSearchFromSender($sender);
            $summary['emails_scanned'] = \count($uids);

            foreach ($uids as $uid) {
                $raw         = $client->fetchUidRfc822($uid);
                $attachments = Mt940MimeHelper::extractTextAttachments($raw);
                self::importAttachmentBatch(
                    $attachments,
                    $allowedBankIds,
                    (string) $uid,
                    $sender,
                    '',
                    $summary
                );
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (\strpos($msg, 'COM_ORDENPRODUCCION_') === 0) {
                return ['success' => false, 'message' => $msg, 'driver' => 'socket'];
            }

            return [
                'success'    => false,
                'message'    => 'COM_ORDENPRODUCCION_MT940_IMAP_CONNECT_FAIL',
                'imap_error' => $msg,
                'driver'     => 'socket',
            ];
        } finally {
            $client->close();
        }

        return self::finalizeSummary($summary);
    }

    /**
     * @param   array<int, array{filename: string, content: string}>  $attachments
     * @param   array<int>                                               $allowedBankIds
     * @param   array<string, int|string>                                $summary
     *
     * @return  void
     */
    private static function importAttachmentBatch(
        array $attachments,
        array $allowedBankIds,
        string $emailUid,
        string $sender,
        string $subject,
        array &$summary
    ): void {
        foreach ($attachments as $att) {
            $filename = \trim((string) ($att['filename'] ?? ''));
            $content  = (string) ($att['content'] ?? '');
            if ($filename === '' || $content === '') {
                continue;
            }

            $result = Mt940ImportHelper::importFileContent(
                $content,
                $filename,
                $allowedBankIds,
                $emailUid,
                $sender,
                $subject
            );

            if (!empty($result['skipped'])) {
                $summary['files_skipped']++;
                $summary['details'][] = $filename . ': ' . (string) ($result['duplicate_reason'] ?? 'skipped');
                continue;
            }

            if (!empty($result['success'])) {
                $summary['files_imported']++;
                $summary['transactions_imported'] += (int) ($result['transactions_count'] ?? 0);
                $summary['details'][] = $filename . ': ' . (int) ($result['transactions_count'] ?? 0) . ' tx';
            } else {
                $msg = (string) ($result['message'] ?? 'error');
                if (\strpos($msg, 'COM_ORDENPRODUCCION_') === 0) {
                    $msg = $msg;
                }
                $summary['details'][] = $filename . ': ' . $msg;
            }
        }
    }

    /**
     * @return  array<string, int|string|array<int, string>>
     */
    private static function emptySummary(string $driver): array
    {
        return [
            'success'               => true,
            'message'               => '',
            'driver'                => $driver,
            'emails_scanned'        => 0,
            'files_imported'        => 0,
            'files_skipped'         => 0,
            'transactions_imported' => 0,
            'details'               => [],
        ];
    }

    /**
     * @param   array<string, mixed>  $summary
     *
     * @return  array<string, mixed>
     */
    private static function finalizeSummary(array $summary): array
    {
        $imported = (int) ($summary['files_imported'] ?? 0);
        $skipped  = (int) ($summary['files_skipped'] ?? 0);
        $tx       = (int) ($summary['transactions_imported'] ?? 0);
        $scanned  = (int) ($summary['emails_scanned'] ?? 0);

        if ($imported === 0 && $skipped === 0 && $scanned === 0) {
            $summary['success'] = true;
            $summary['message'] = 'COM_ORDENPRODUCCION_MT940_INITIAL_IMPORT_EMPTY';

            return $summary;
        }

        $summary['success'] = true;
        $summary['message'] = 'COM_ORDENPRODUCCION_MT940_INITIAL_IMPORT_OK';

        return $summary;
    }

    /**
     * @param   resource|mixed  $imap
     *
     * @return  array<int, array{filename: string, content: string}>
     */
    private static function extractAttachmentsFromStructure($imap, int $uid, object $structure, string $partNo = ''): array
    {
        $out = [];

        if (!empty($structure->parts) && \is_array($structure->parts)) {
            foreach ($structure->parts as $idx => $sub) {
                $subPart = $partNo === '' ? (string) ($idx + 1) : $partNo . '.' . ($idx + 1);
                $out     = \array_merge($out, self::extractAttachmentsFromStructure($imap, $uid, $sub, $subPart));
            }

            return $out;
        }

        $filename = '';
        if (!empty($structure->dparameters)) {
            foreach ($structure->dparameters as $obj) {
                if (isset($obj->attribute) && \strtolower((string) $obj->attribute) === 'filename') {
                    $filename = (string) ($obj->value ?? '');
                    break;
                }
            }
        }
        if ($filename === '' && !empty($structure->parameters)) {
            foreach ($structure->parameters as $obj) {
                if (isset($obj->attribute) && \strtolower((string) $obj->attribute) === 'name') {
                    $filename = (string) ($obj->value ?? '');
                    break;
                }
            }
        }

        $body = (string) @\imap_fetchbody($imap, (string) $uid, $partNo === '' ? '1' : $partNo, \FT_UID | \FT_PEEK);
        if ($structure->encoding == 3) {
            $body = (string) \base64_decode($body, true);
        } elseif ($structure->encoding == 4) {
            $body = \quoted_printable_decode($body);
        }

        $isTxt = $filename !== '' && (bool) \preg_match('/\.txt$/i', $filename);
        if (!$isTxt && !Mt940MimeHelper::looksLikeMt940($body)) {
            return $out;
        }

        if ($filename === '') {
            $filename = 'attachment-' . \substr(\hash('sha256', $body), 0, 12) . '.txt';
        }

        $out[] = ['filename' => $filename, 'content' => $body];

        return $out;
    }
}
