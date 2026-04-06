<?php
/**
 * Audit log for internal approval workflow (Option B).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.102.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Writes rows to #__ordenproduccion_approval_audit_log.
 */
class ApprovalAuditHelper
{
    /**
     * @param   int         $requestId   Request id
     * @param   string      $action      Short action code (e.g. created, approved, rejected, step_advanced)
     * @param   int         $userId      Acting user
     * @param   string|null $oldStatus   Previous request status
     * @param   string|null $newStatus   New request status
     * @param   string      $comments    Optional comment
     *
     * @return  void
     */
    public static function log(
        int $requestId,
        string $action,
        int $userId,
        ?string $oldStatus,
        ?string $newStatus,
        string $comments = ''
    ): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $ip = '';

        try {
            $ip = (string) Factory::getApplication()->input->server->get('REMOTE_ADDR', '', 'string');
        } catch (\Throwable $e) {
            $ip = '';
        }

        if (strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ordenproduccion_approval_audit_log'))
            ->columns([
                $db->quoteName('request_id'),
                $db->quoteName('action'),
                $db->quoteName('user_id'),
                $db->quoteName('old_status'),
                $db->quoteName('new_status'),
                $db->quoteName('comments'),
                $db->quoteName('ip_address'),
                $db->quoteName('created'),
            ])
            ->values(implode(',', [
                (string) (int) $requestId,
                $db->quote($action),
                (string) (int) $userId,
                $oldStatus === null ? 'NULL' : $db->quote($oldStatus),
                $newStatus === null ? 'NULL' : $db->quote($newStatus),
                $db->quote($comments),
                $db->quote($ip),
                $db->quote(Factory::getDate()->toSql()),
            ]));

        $db->setQuery($query);
        $db->execute();
    }
}
