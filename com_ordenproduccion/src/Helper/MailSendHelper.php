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
     * @return  void
     *
     * @throws  \RuntimeException
     *
     * @since   3.113.69
     */
    public static function sendChecked(Mail $mailer): void
    {
        $result = $mailer->send();

        if ($result === true) {
            return;
        }

        $info = trim((string) ($mailer->ErrorInfo ?? ''));

        Log::add(
            'Mail send() returned false. ' . ($info !== '' ? $info : '(no ErrorInfo)'),
            Log::WARNING,
            'com_ordenproduccion'
        );

        throw new \RuntimeException(
            $info !== '' ? $info : 'Mail send returned false; check Joomla Global Configuration → Mail (SMTP recommended).'
        );
    }
}
