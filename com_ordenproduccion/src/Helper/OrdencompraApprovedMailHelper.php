<?php
/**
 * Email to the purchase-order requester when an ORC is approved (optional CC to vendor).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Grimpsa\Component\Ordenproduccion\Site\Model\OrdencompraModel;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

/**
 * @since  3.113.63
 */
final class OrdencompraApprovedMailHelper
{
    /**
     * Send notification after workflow approval and PDF generation.
     *
     * @return  void
     */
    public static function sendApprovedNotification(int $ordenCompraId): void
    {
        $ordenCompraId = (int) $ordenCompraId;
        if ($ordenCompraId < 1) {
            return;
        }

        $app     = Factory::getApplication();
        $ocModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);
        if (!$ocModel instanceof OrdencompraModel || !$ocModel->hasSchema()) {
            return;
        }

        $header = $ocModel->getItemById($ordenCompraId);
        if (!$header || strtolower((string) ($header->workflow_status ?? '')) !== 'approved') {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $q  = $db->getQuery(true)
            ->select([
                $db->quoteName('submitter_id'),
                $db->quoteName('id'),
            ])
            ->from($db->quoteName('#__ordenproduccion_approval_requests'))
            ->where($db->quoteName('entity_type') . ' = ' . $db->quote(ApprovalWorkflowService::ENTITY_ORDEN_COMPRA))
            ->where($db->quoteName('entity_id') . ' = ' . $ordenCompraId)
            ->where($db->quoteName('status') . ' = ' . $db->quote('approved'))
            ->order($db->quoteName('id') . ' DESC')
            ->setLimit(1);
        $db->setQuery($q);
        $req = $db->loadObject();

        $submitterId = $req ? (int) ($req->submitter_id ?? 0) : 0;
        if ($submitterId < 1) {
            $submitterId = (int) ($header->created_by ?? 0);
        }

        if ($submitterId < 1) {
            self::logFailure(
                $ordenCompraId,
                0,
                '',
                Text::sprintf('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_SUBJECT', '#' . $ordenCompraId),
                'No submitter user id'
            );

            return;
        }

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $submitter   = $userFactory->loadUserById($submitterId);
        $toEmail     = trim((string) $submitter->email);

        if ($toEmail === '' || !MailHelper::isEmailAddress($toEmail)) {
            self::logFailure(
                $ordenCompraId,
                $submitterId,
                $toEmail,
                Text::sprintf('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_SUBJECT', '#' . $ordenCompraId),
                'Invalid requester email'
            );

            return;
        }

        $orcNumber = trim((string) ($header->number ?? ''));
        if ($orcNumber === '') {
            $orcNumber = (string) $ordenCompraId;
        }

        $subject = Text::sprintf('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_SUBJECT', $orcNumber);
        $body    = Text::sprintf('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_BODY_HTML', htmlspecialchars($orcNumber, ENT_QUOTES, 'UTF-8'));

        $ccVendor = (int) ($header->approve_email_cc_vendor ?? 0) === 1;
        $vendorEmail = self::resolveVendorContactEmail($header, $app);

        $relPdf = trim((string) ($header->approved_pdf_path ?? ''));
        $absPdf = '';
        if ($relPdf !== '' && strpos($relPdf, '..') === false) {
            $absPdf = JPATH_ROOT . '/' . str_replace('\\', '/', ltrim($relPdf, '/'));
            if (!is_file($absPdf)) {
                $absPdf = '';
            }
        }

        $toSummary = $toEmail;
        if ($ccVendor && $vendorEmail !== '' && MailHelper::isEmailAddress($vendorEmail)) {
            $toSummary .= ' (CC: ' . $vendorEmail . ')';
        }

        $mailer = null;

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(true);
            $mailer->addRecipient($toEmail);
            if ($ccVendor && $vendorEmail !== '' && MailHelper::isEmailAddress($vendorEmail)) {
                $mailer->addCc($vendorEmail);
            }
            if ($absPdf !== '') {
                $attachName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $orcNumber) . '.pdf';
                $mailer->addAttachment($absPdf, $attachName);
            }
            $mailer->send();

            OutboundEmailLogHelper::log(
                OutboundEmailLogHelper::CONTEXT_ORDENCOMPRA_APPROVED,
                $submitterId,
                $toSummary,
                $subject,
                true,
                '',
                [
                    'orden_compra_id'         => $ordenCompraId,
                    'orc_number'              => $orcNumber,
                    'approve_email_cc_vendor' => $ccVendor ? 1 : 0,
                    'vendor_email'            => $vendorEmail,
                    'had_pdf_attachment'      => $absPdf !== '' ? 1 : 0,
                ]
            );
        } catch (\Throwable $e) {
            $detail = $e->getMessage();
            if ($mailer instanceof Mail && !empty($mailer->ErrorInfo)) {
                $detail .= ' | ' . $mailer->ErrorInfo;
            }
            OutboundEmailLogHelper::log(
                OutboundEmailLogHelper::CONTEXT_ORDENCOMPRA_APPROVED,
                $submitterId,
                $toSummary,
                $subject,
                false,
                $detail,
                [
                    'orden_compra_id'         => $ordenCompraId,
                    'orc_number'              => $orcNumber,
                    'approve_email_cc_vendor' => $ccVendor ? 1 : 0,
                ]
            );
        }
    }

    private static function resolveVendorContactEmail(object $header, $app): string
    {
        $snap = isset($header->proveedor_snapshot) ? trim((string) $header->proveedor_snapshot) : '';
        if ($snap !== '') {
            $d = json_decode($snap, true);
            if (is_array($d)) {
                $e = trim((string) ($d['contact_email'] ?? ''));
                if ($e !== '' && MailHelper::isEmailAddress($e)) {
                    return $e;
                }
            }
        }

        $pid = (int) ($header->proveedor_id ?? 0);
        if ($pid < 1) {
            return '';
        }

        $admin = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Administracion', 'Site', ['ignore_request' => true]);
        if (!$admin instanceof AdministracionModel || !method_exists($admin, 'getProveedorById')) {
            return '';
        }

        $prov = $admin->getProveedorById($pid);
        if (!$prov) {
            return '';
        }

        $e = trim((string) ($prov->contact_email ?? ''));

        return ($e !== '' && MailHelper::isEmailAddress($e)) ? $e : '';
    }

    private static function logFailure(int $ocId, int $userId, string $to, string $subject, string $err): void
    {
        OutboundEmailLogHelper::log(
            OutboundEmailLogHelper::CONTEXT_ORDENCOMPRA_APPROVED,
            $userId,
            $to,
            $subject,
            false,
            $err,
            ['orden_compra_id' => $ocId]
        );
    }
}
