<?php
/**
 * Email queue for approval workflow notifications (assign / decided).
 * Sending is implemented in a later iteration; rows are stored for cron/processing.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.102.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;

/**
 * Enqueues notification emails for approvers and requesters.
 */
class ApprovalEmailQueueHelper
{
    /**
     * Queue a single email row.
     *
     * @param   int     $requestId   Approval request id
     * @param   string  $event       assign|decided
     * @param   int     $toUserId    Recipient user id (0 = only email)
     * @param   string  $toEmail     Fallback email
     * @param   string  $subject     Subject line
     * @param   string  $body        Body (plain/HTML as stored)
     *
     * @return  void
     */
    public static function enqueue(
        int $requestId,
        string $event,
        int $toUserId,
        string $toEmail,
        string $subject,
        string $body
    ): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ordenproduccion_approval_email_queue'))
            ->columns([
                $db->quoteName('request_id'),
                $db->quoteName('event'),
                $db->quoteName('to_user_id'),
                $db->quoteName('to_email'),
                $db->quoteName('subject'),
                $db->quoteName('body'),
                $db->quoteName('status'),
                $db->quoteName('created'),
            ])
            ->values(implode(',', [
                (string) $requestId,
                $db->quote($event),
                $toUserId > 0 ? (string) $toUserId : 'NULL',
                $db->quote($toEmail),
                $db->quote($subject),
                $db->quote($body),
                $db->quote('pending'),
                $db->quote(Factory::getDate()->toSql()),
            ]));

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Build minimal assign notification for an approver (templates wired later).
     *
     * @param   int   $requestId  Request id
     * @param   User  $approver   Approver user
     * @param   int   $entityId   Entity id
     * @param   string $entityType Entity type string
     *
     * @return  void
     */
    public static function notifyAssign(int $requestId, User $approver, int $entityId, string $entityType): void
    {
        $email = trim((string) $approver->email);
        if ($email === '') {
            return;
        }

        $subject = 'Aprobación pendiente — ' . $entityType . ' #' . $entityId;
        $body    = 'Tiene una solicitud de aprobación pendiente (solicitud #' . $requestId . ').';

        self::enqueue($requestId, 'assign', (int) $approver->id, $email, $subject, $body);
    }
}
