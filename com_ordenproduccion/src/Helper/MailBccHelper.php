<?php
/**
 * Single-recipient addressing for transactional mail (one queued send per address).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailHelper;

/**
 * @since  3.113.81
 */
final class MailBccHelper
{
    /**
     * Add exactly one To recipient (validated). Callers send one mail per address.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    public static function applySingleRecipient(Mail $mailer, string $address): void
    {
        $addr = trim((string) $address);
        if ($addr === '' || !MailHelper::isEmailAddress($addr)) {
            throw new \RuntimeException('Invalid recipient email address.');
        }
        $mailer->addRecipient($addr);
    }
}
