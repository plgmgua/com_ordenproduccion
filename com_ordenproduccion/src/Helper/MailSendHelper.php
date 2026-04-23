<?php
/**
 * Ensures Joomla mailer results are not treated as success when send() returns false.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\Mail;

/**
 * @since  3.113.69
 */
final class MailSendHelper
{
    /**
     * Call the mailer send operation and fail loudly if it returns false (can happen when
     * Joomla/PHPMailer has exception throwing disabled or after SMTP retry).
     *
     * @return  array<string, mixed>  Transport/recipient snapshot for outbound log meta (mail_diag).
     *
     * @throws  \RuntimeException
     *
     * @since   3.113.69
     */
    public static function sendChecked(Mail $mailer): array
    {
        $diag = self::snapshotMailDiagnostics($mailer);
        $result = $mailer->send();

        $info = trim((string) ($mailer->ErrorInfo ?? ''));
        if ($info !== '') {
            $diag['mailer_error_info'] = $info;
        }

        if (($diag['mail_transport'] ?? '') === 'smtp' && method_exists($mailer, 'getSMTPInstance')) {
            try {
                $smtp = $mailer->getSMTPInstance();
                if ($smtp !== null && method_exists($smtp, 'getLastReply')) {
                    $lr = trim((string) $smtp->getLastReply());
                    if ($lr !== '') {
                        $diag['smtp_last_reply'] = substr($lr, 0, 500);
                    }
                }
            } catch (\Throwable $ignore) {
            }
        }

        if ($result === true) {
            return $diag;
        }

        Log::add(
            'Mail send() returned false. ' . ($info !== '' ? $info : '(no ErrorInfo)'),
            Log::WARNING,
            'com_ordenproduccion'
        );

        throw new \RuntimeException(
            $info !== '' ? $info : 'Mail send returned false; check Joomla Global Configuration → Mail (SMTP recommended).'
        );
    }

    /**
     * Captures mailer state before send() (PHPMailer may clear address lists after send).
     *
     * @return  array<string, mixed>
     *
     * @since   3.113.75
     */
    private static function snapshotMailDiagnostics(Mail $mailer): array
    {
        $to  = method_exists($mailer, 'getToAddresses') ? (array) $mailer->getToAddresses() : [];
        $cc  = method_exists($mailer, 'getCcAddresses') ? (array) $mailer->getCcAddresses() : [];
        $bcc = method_exists($mailer, 'getBccAddresses') ? (array) $mailer->getBccAddresses() : [];

        $transport = (string) ($mailer->Mailer ?? '');
        $hints     = [];

        if ($transport === 'mail') {
            $hints[] = 'php_mail_transport_may_drop_or_delay_bcc_use_smtp';
        }
        if ($bcc === [] && $to !== []) {
            $hints[] = 'zero_bcc_check_mailfrom_equals_only_recipient';
        }

        return [
            'mail_transport'    => $transport,
            'recipient_to_n'    => count($to),
            'recipient_cc_n'    => count($cc),
            'recipient_bcc_n'   => count($bcc),
            'delivery_hints'    => $hints,
        ];
    }
}
