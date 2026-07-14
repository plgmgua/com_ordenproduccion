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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

/**
 * Diagnostics log for MT-940 payment matching cron.
 *
 * @since  3.119.228
 */
class Mt940PaymentMatchLogHelper
{
    public const STATUS_MATCHED   = 'matched';
    public const STATUS_AMBIGUOUS = 'ambiguous';
    public const STATUS_NO_MATCH  = 'no_match';
    public const STATUS_SKIPPED   = 'skipped';
    public const STATUS_ERROR     = 'error';
    public const STATUS_INFO      = 'info';

    /**
     * @return bool
     *
     * @since  3.119.228
     */
    public static function tableAvailable(): bool
    {
        try {
            $db     = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $prefix = $db->getPrefix();
            $tables = $db->setQuery('SHOW TABLES LIKE ' . $db->quote($prefix . 'ordenproduccion_payment_mt940_match_log'))->loadColumn();

            return !empty($tables);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param   int     $proofId
     * @param   int     $lineId
     * @param   string  $status
     * @param   string  $message
     *
     * @return  void
     *
     * @since   3.119.228
     */
    public static function log(int $proofId, int $lineId, string $status, string $message = ''): void
    {
        if (!self::tableAvailable()) {
            return;
        }

        try {
            $db  = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $obj = (object) [
                'payment_proof_id'      => max(0, $proofId),
                'payment_proof_line_id' => $lineId > 0 ? $lineId : null,
                'status'                => $status,
                'message'               => $message,
                'created'               => Factory::getDate()->toSql(),
            ];
            $db->insertObject('#__ordenproduccion_payment_mt940_match_log', $obj);
        } catch (\Throwable $e) {
            // Non-fatal
        }
    }

    /**
     * Whether MT-940 cron-based payment verification is active.
     *
     * @return  bool
     *
     * @since   3.119.228
     */
    public static function isMt940VerificationEnabled(): bool
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');

        return (int) $params->get('approval_workflow_payment_proof', 0) === 1
            && (int) $params->get('payment_proof_mt940_verification', 1) === 1;
    }

    /**
     * Legacy flow: approval on save without MT-940 matching.
     *
     * @return  bool
     *
     * @since   3.119.228
     */
    public static function isLegacyPaymentProofApprovalOnSave(): bool
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');

        return (int) $params->get('approval_workflow_payment_proof', 0) === 1
            && (int) $params->get('payment_proof_mt940_verification', 1) === 0;
    }
}
