<?php
/**
 * Places real recipients in BCC and uses the site mail address as the visible To (SMTP-safe).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailHelper;

/**
 * @since  3.113.74
 */
final class MailBccHelper
{
    /**
     * Set To = Global Configuration mailfrom; add each distinct valid address as BCC
     * (skips BCC when same as mailfrom to avoid duplicate delivery).
     *
     * @param   array<int, string>  $bccAddresses
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public static function applySiteToWithBcc(Mail $mailer, array $bccAddresses): void
    {
        $app = Factory::getApplication();
        $from = trim((string) $app->get('mailfrom', ''));
        if ($from === '' || !MailHelper::isEmailAddress($from)) {
            throw new \RuntimeException('Invalid site mailfrom (Global Configuration → Mail).');
        }
        $fromName = trim((string) $app->get('fromname', ''));

        $seen = [];
        $list = [];
        foreach ($bccAddresses as $addr) {
            $addr = trim((string) $addr);
            if ($addr === '' || !MailHelper::isEmailAddress($addr)) {
                continue;
            }
            $key = strtolower($addr);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $list[] = $addr;
        }
        if ($list === []) {
            throw new \RuntimeException('No valid recipient addresses for BCC.');
        }

        $mailer->addRecipient($from, $fromName);
        foreach ($list as $addr) {
            if (strcasecmp($addr, $from) !== 0) {
                $mailer->addBcc($addr);
            }
        }
    }
}
