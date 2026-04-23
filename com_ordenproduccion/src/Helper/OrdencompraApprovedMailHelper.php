<?php
/**
 * Email to the purchase-order requester when an ORC is approved (optional BCC to vendor).
 * Subject/body templates: workflow row (orden_compra) or language defaults; placeholders like Telegram.
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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
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

        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

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
                $db->quoteName('workflow_id'),
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
                self::fallbackSubjectLine($ordenCompraId, $header),
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
                self::fallbackSubjectLine($ordenCompraId, $header),
                'Invalid requester email'
            );

            return;
        }

        $wfRow = null;
        if ($req && (int) ($req->workflow_id ?? 0) > 0) {
            $wfId = (int) $req->workflow_id;
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_approval_workflows'))
                    ->where($db->quoteName('id') . ' = ' . $wfId)
                    ->setLimit(1)
            );
            $wfRow = $db->loadObject();
        }

        $vendorEmailResolved = self::resolveVendorContactEmail($header, $app);
        $vars                = self::buildTemplateVars($req, $wfRow, $submitter, $header, $ordenCompraId, $vendorEmailResolved);

        $wfSubj = $wfRow && isset($wfRow->email_ordencompra_approved_subject)
            ? trim((string) $wfRow->email_ordencompra_approved_subject) : '';
        $wfBodyRaw = $wfRow && isset($wfRow->email_ordencompra_approved_body)
            ? (string) $wfRow->email_ordencompra_approved_body : '';

        $subjectTpl = $wfSubj !== ''
            ? $wfSubj
            : Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_TPL_SUBJECT_DEFAULT');
        $bodyTpl = trim($wfBodyRaw) !== ''
            ? $wfBodyRaw
            : Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_TPL_BODY_DEFAULT');

        $subject = TelegramNotificationHelper::replaceTemplatePlaceholders($subjectTpl, $vars);
        $subject = trim(html_entity_decode(strip_tags($subject), ENT_QUOTES, 'UTF-8'));
        if ($subject === '') {
            $subject = self::fallbackSubjectLine($ordenCompraId, $header);
        }

        $bodyVars = self::escapeVarsForHtmlEmail($vars);
        $body     = TelegramNotificationHelper::replaceTemplatePlaceholders($bodyTpl, $bodyVars);
        if (trim($body) === '') {
            $body = TelegramNotificationHelper::replaceTemplatePlaceholders(
                Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_TPL_BODY_DEFAULT'),
                $bodyVars
            );
        }

        $ccVendor = (int) ($header->approve_email_cc_vendor ?? 0) === 1;

        $orcNumber = trim((string) ($header->number ?? ''));
        if ($orcNumber === '') {
            $orcNumber = (string) $ordenCompraId;
        }

        $relPdf = trim((string) ($header->approved_pdf_path ?? ''));
        $absPdf = '';
        if ($relPdf !== '' && strpos($relPdf, '..') === false) {
            $absPdf = JPATH_ROOT . '/' . str_replace('\\', '/', ltrim($relPdf, '/'));
            if (!is_file($absPdf)) {
                $absPdf = '';
            }
        }

        $bccList = [$toEmail];
        if ($ccVendor && $vendorEmailResolved !== '' && MailHelper::isEmailAddress($vendorEmailResolved)) {
            $bccList[] = $vendorEmailResolved;
        }
        $toSummary = 'BCC: ' . implode(', ', $bccList);

        $mailer = null;

        try {
            $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(true);
            MailBccHelper::applySiteToWithBcc($mailer, $bccList);
            if ($absPdf !== '') {
                $attachName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $orcNumber) . '.pdf';
                $mailer->addAttachment($absPdf, $attachName);
            }
            $mailDiag = MailSendHelper::sendChecked($mailer);

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
                    'vendor_email'            => $vendorEmailResolved,
                    'had_pdf_attachment'      => $absPdf !== '' ? 1 : 0,
                    'body_html'               => $body,
                    'mail_diag'               => $mailDiag,
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
                    'body_html'               => $body,
                ]
            );
        }
    }

    /**
     * @param   object|null  $req  approval_requests row
     *
     * @return  array<string, string>
     */
    private static function buildTemplateVars(?object $req, ?object $workflow, User $recipient, object $header, int $ordenCompraId, string $vendorEmail): array
    {
        $app      = Factory::getApplication();
        $siteName = (string) $app->get('sitename', '');

        $relAdmin = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones', false);
        $root     = rtrim(Uri::root(), '/');
        $approvalUrl = $root . '/' . ltrim((string) $relAdmin, '/');

        $relOc = Route::_('index.php?option=com_ordenproduccion&view=ordencompra&id=' . $ordenCompraId, false);
        $ordenCompraUrl = $root . '/' . ltrim((string) $relOc, '/');

        $orcNumber = trim((string) ($header->number ?? ''));
        if ($orcNumber === '') {
            $orcNumber = (string) $ordenCompraId;
        }

        $precotNum = trim((string) ($header->precot_number ?? ''));
        $proveedorName = '';
        $snap          = isset($header->proveedor_snapshot) ? trim((string) $header->proveedor_snapshot) : '';
        if ($snap !== '') {
            $d = json_decode($snap, true);
            if (is_array($d)) {
                $proveedorName = trim((string) ($d['name'] ?? ''));
            }
        }

        $curr = trim((string) ($header->currency ?? 'Q'));
        $tot  = (float) ($header->total_amount ?? 0);

        $vars = [
            'orc_number'        => $orcNumber,
            'entity_id'         => $orcNumber,
            'precot_number'     => $precotNum,
            'proveedor_name'    => $proveedorName,
            'vendor_email'      => $vendorEmail,
            'total_amount'      => number_format($tot, 2, '.', ''),
            'currency'          => $curr,
            'request_id'        => $req ? (string) (int) ($req->id ?? 0) : '0',
            'entity_type'       => ApprovalWorkflowService::ENTITY_ORDEN_COMPRA,
            'workflow_name'     => $workflow ? trim((string) ($workflow->name ?? '')) : '',
            'workflow_description' => $workflow ? trim((string) ($workflow->description ?? '')) : '',
            'recipient_name'    => trim((string) $recipient->name),
            'recipient_username' => trim((string) $recipient->username),
            'recipient_id'      => (string) (int) $recipient->id,
            'submitter_id'      => $req ? (string) (int) ($req->submitter_id ?? 0) : (string) (int) ($header->created_by ?? 0),
            'submitter_name'    => '',
            'submitter_username' => '',
            'site_name'         => $siteName,
            'approval_url'      => $approvalUrl,
            'orden_compra_url'  => $ordenCompraUrl,
            'actor_name'        => '',
            'actor_username'    => '',
            'actor_id'          => '0',
        ];

        $sid = (int) $vars['submitter_id'];
        if ($sid > 0) {
            try {
                $sub = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($sid);
                if (!$sub->guest) {
                    $vars['submitter_name']     = trim((string) $sub->name);
                    $vars['submitter_username'] = trim((string) $sub->username);
                }
            } catch (\Throwable $e) {
            }
        }

        $vars['actor_name']     = $vars['submitter_name'];
        $vars['actor_username'] = $vars['submitter_username'];
        $vars['actor_id']       = $vars['submitter_id'];

        return $vars;
    }

    /**
     * @param   array<string, string>  $vars
     *
     * @return  array<string, string>
     */
    private static function escapeVarsForHtmlEmail(array $vars): array
    {
        $out = [];
        foreach ($vars as $k => $v) {
            $out[$k] = htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        }

        return $out;
    }

    private static function fallbackSubjectLine(int $ordenCompraId, object $header): string
    {
        $orc = trim((string) ($header->number ?? ''));
        if ($orc === '') {
            $orc = '#' . $ordenCompraId;
        }

        return TelegramNotificationHelper::replaceTemplatePlaceholders(
            Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_APPROVED_EMAIL_TPL_SUBJECT_DEFAULT'),
            ['orc_number' => $orc]
        );
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
