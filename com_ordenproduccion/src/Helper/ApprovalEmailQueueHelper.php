<?php
/**
 * Telegram (GrimpsaBot) notifications for approval workflows — assignment and outcome.
 * Templates are stored in workflow rows: email_body_assign, email_body_decided (legacy column names).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.102.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;

/**
 * Queues Telegram DMs to approvers and submitters when Telegram is enabled and chat_id is known.
 */
class ApprovalEmailQueueHelper
{
    /**
     * Whether approval Telegram notifications may be sent.
     */
    protected static function isTelegramDispatchReady(DatabaseInterface $db): bool
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        if ((int) $params->get('telegram_enabled', 0) !== 1) {
            return false;
        }
        if (trim((string) $params->get('telegram_bot_token', '')) === '') {
            return false;
        }

        return TelegramQueueHelper::telegramQueueTableExists($db);
    }

    /**
     * @return  object|null  Request row with workflow_id, entity_*, submitter_id, etc.
     */
    protected static function loadRequestRow(DatabaseInterface $db, int $requestId): ?object
    {
        if ($requestId < 1) {
            return null;
        }
        $q = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_approval_requests'))
            ->where($db->quoteName('id') . ' = ' . $requestId)
            ->setLimit(1);
        $db->setQuery($q);
        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * @return  object|null  Workflow row
     */
    protected static function loadWorkflowRow(DatabaseInterface $db, int $workflowId): ?object
    {
        if ($workflowId < 1) {
            return null;
        }
        $q = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_approval_workflows'))
            ->where($db->quoteName('id') . ' = ' . $workflowId)
            ->setLimit(1);
        $db->setQuery($q);
        $row = $db->loadObject();

        return $row ?: null;
    }

    /**
     * Human-facing entity reference for Telegram templates.
     * For solicitud_descuento, solicitud_cotizacion and creacion_orden_trabajo, entity_id is the pre-cotización PK: use stored number (e.g. PRE-00072).
     *
     * @since  3.109.68
     */
    protected static function formatEntityIdForTelegram(DatabaseInterface $db, object $request): string
    {
        $pk = (int) ($request->entity_id ?? 0);
        if ($pk < 1) {
            return '0';
        }

        $type = (string) ($request->entity_type ?? '');
        if ($type === ApprovalWorkflowService::ENTITY_PAYMENT_PROOF) {
            return 'PA-' . str_pad((string) $pk, 5, '0', STR_PAD_LEFT);
        }

        if ($type === ApprovalWorkflowService::ENTITY_ORDEN_COMPRA) {
            try {
                $q = $db->getQuery(true)
                    ->select($db->quoteName('number'))
                    ->from($db->quoteName('#__ordenproduccion_orden_compra'))
                    ->where($db->quoteName('id') . ' = ' . $pk)
                    ->setLimit(1);
                $db->setQuery($q);
                $num = trim((string) $db->loadResult());
                if ($num !== '') {
                    return $num;
                }
            } catch (\Throwable $e) {
            }

            return 'ORC-' . str_pad((string) $pk, 5, '0', STR_PAD_LEFT);
        }

        if (
            $type !== ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO
            && $type !== ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION
            && $type !== ApprovalWorkflowService::ENTITY_CREACION_ORDEN_TRABAJO
        ) {
            return (string) $pk;
        }

        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName('number'))
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' = ' . $pk)
                ->setLimit(1);
            $db->setQuery($q);
            $num = trim((string) $db->loadResult());
            if ($num !== '') {
                return $num;
            }
        } catch (\Throwable $e) {
        }

        return 'PRE-' . str_pad((string) $pk, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Quotación fields for Telegram: display number + client label (Creación OT, etc.).
     *
     * @return  array{number: string, client_name: string}
     *
     * @since  3.115.59
     */
    protected static function loadQuotationTelegramExtras(DatabaseInterface $db, int $quotationId): array
    {
        $out = [
            'number'      => '',
            'client_name' => '',
        ];

        if ($quotationId < 1) {
            return $out;
        }

        $fallbackNum = 'COT-' . str_pad((string) $quotationId, 5, '0', STR_PAD_LEFT);

        try {
            $q = $db->getQuery(true)
                ->select([
                    $db->quoteName('quotation_number'),
                    $db->quoteName('client_name'),
                ])
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . $quotationId)
                ->setLimit(1);
            $db->setQuery($q);
            $row = $db->loadObject();

            if ($row === null) {
                $out['number'] = $fallbackNum;

                return $out;
            }

            $num = trim((string) ($row->quotation_number ?? ''));
            $out['number']      = $num !== '' ? $num : $fallbackNum;
            $out['client_name'] = trim((string) ($row->client_name ?? ''));
        } catch (\Throwable $e) {
            $out['number'] = $fallbackNum;
        }

        return $out;
    }

    /**
     * Quotación reference for Telegram (e.g. COT-00123).
     *
     * @since  3.115.58
     */
    protected static function formatCotizacionNumberForTelegram(DatabaseInterface $db, int $quotationId): string
    {
        return self::loadQuotationTelegramExtras($db, $quotationId)['number'];
    }

    /**
     * Orden de trabajo reference for Telegram (uses stored numeración).
     *
     * @since  3.115.58
     */
    protected static function formatOrdenTrabajoNumberForTelegram(DatabaseInterface $db, int $ordenId): string
    {
        if ($ordenId < 1) {
            return '';
        }

        try {
            $cols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
            $select = [$db->quoteName('orden_de_trabajo')];
            if (isset($cols['order_number'])) {
                $select[] = $db->quoteName('order_number');
            }

            $q = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . $ordenId)
                ->setLimit(1);
            $db->setQuery($q);
            $row = $db->loadObject();
            if ($row !== null) {
                $a = trim((string) ($row->orden_de_trabajo ?? ''));
                if ($a !== '') {
                    return $a;
                }
                if (isset($row->order_number)) {
                    $b = trim((string) ($row->order_number ?? ''));
                    if ($b !== '') {
                        return $b;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return 'ORD-' . str_pad((string) $ordenId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Format DB datetime for Telegram templates (site/user timezone when possible).
     */
    protected static function formatSqlDatetimeForTemplate(?string $sql): string
    {
        if ($sql === null || trim($sql) === '' || $sql === '0000-00-00 00:00:00') {
            return '';
        }

        try {
            return Factory::getDate($sql)->format(Text::_('DATE_FORMAT_LC4'));
        } catch (\Throwable $e) {
            return trim($sql);
        }
    }

    /**
     * Extra placeholders for payment_proof workflows (client + proof dates).
     *
     * @return array<string, string>
     */
    protected static function buildPaymentProofTemplateVars(DatabaseInterface $db, object $request): array
    {
        $proofId = (int) ($request->entity_id ?? 0);
        if ($proofId < 1) {
            return [
                'proof_id'         => '0',
                'proof_number'     => '',
                'client_name'      => '',
                'client_id'        => '',
                'proof_created'    => '',
                'request_created'  => '',
                'payment_amount'   => '0.00',
                'currency'         => 'Q',
            ];
        }

        $proofNumber = 'PA-' . str_pad((string) $proofId, 5, '0', STR_PAD_LEFT);

        $empty = [
            'proof_id'         => (string) $proofId,
            'proof_number'     => $proofNumber,
            'client_name'      => '',
            'client_id'        => '',
            'proof_created'    => '',
            'request_created'  => '',
            'payment_amount'   => '0.00',
            'currency'         => 'Q',
        ];

        try {
            $ppCols = $db->getTableColumns('#__ordenproduccion_payment_proofs', false);
            $ppCols = is_array($ppCols) ? array_change_key_case($ppCols, CASE_LOWER) : [];
            $select = [
                $db->quoteName('pp.created'),
                $db->quoteName('pp.payment_amount'),
            ];
            if (isset($ppCols['currency'])) {
                $select[] = $db->quoteName('pp.currency');
            }

            $q = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
                ->where('pp.' . $db->quoteName('id') . ' = ' . $proofId)
                ->setLimit(1);
            $db->setQuery($q);
            $proofRow = $db->loadObject();
            if ($proofRow !== null) {
                if (isset($proofRow->created)) {
                    $empty['proof_created'] = self::formatSqlDatetimeForTemplate((string) $proofRow->created);
                }
                $amt = isset($proofRow->payment_amount) ? (float) $proofRow->payment_amount : 0.0;
                $empty['payment_amount'] = number_format($amt, 2, '.', '');
                if (isset($proofRow->currency)) {
                    $cur = trim((string) $proofRow->currency);
                    if ($cur !== '') {
                        $empty['currency'] = $cur;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        if (isset($request->created) && (string) $request->created !== '') {
            $empty['request_created'] = self::formatSqlDatetimeForTemplate((string) $request->created);
        }

        try {
            $orderCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
            $orderCols = is_array($orderCols) ? array_change_key_case($orderCols, CASE_LOWER) : [];
            $hasClientName = isset($orderCols['client_name']);
            $clientExpr    = $hasClientName ? 'o.client_name' : 'o.nombre_del_cliente';
            $select        = [$clientExpr . ' AS client_name'];
            if (isset($orderCols['client_id'])) {
                $select[] = 'o.client_id';
            } else {
                $select[] = $db->quote('') . ' AS ' . $db->quoteName('client_id');
            }

            $q2 = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__ordenproduccion_payment_orders', 'po'))
                ->innerJoin(
                    $db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.id = po.order_id'
                )
                ->where('po.' . $db->quoteName('payment_proof_id') . ' = ' . $proofId)
                ->order('po.' . $db->quoteName('id') . ' ASC')
                ->setLimit(1);
            $db->setQuery($q2);
            $row = $db->loadObject();
            if ($row !== null) {
                $empty['client_name'] = trim((string) ($row->client_name ?? ''));
                if (isset($row->client_id)) {
                    $empty['client_id'] = trim((string) $row->client_id);
                }
            }
        } catch (\Throwable $e) {
        }

        return $empty;
    }

    /**
     * @param   array<string, string>  $base
     *
     * @return  array<string, string>
     */
    protected static function buildBaseTemplateVars(DatabaseInterface $db, object $request, ?object $workflow, User $recipient, array $base = []): array
    {
        $app      = Factory::getApplication();
        $siteName = (string) $app->get('sitename', '');

        $rel = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones', false);
        $root = rtrim(Uri::root(), '/');
        $url  = $root . '/' . ltrim((string) $rel, '/');

        $vars = array_merge([
            'request_id'   => (string) (int) ($request->id ?? 0),
            'entity_id'    => self::formatEntityIdForTelegram($db, $request),
            'entity_type'  => (string) ($request->entity_type ?? ''),
            'workflow_name' => $workflow ? (string) ($workflow->name ?? '') : '',
            'workflow_description' => $workflow ? (string) ($workflow->description ?? '') : '',
            'recipient_name' => trim((string) $recipient->name),
            'recipient_username' => trim((string) $recipient->username),
            'recipient_id' => (string) (int) $recipient->id,
            'submitter_id' => (string) (int) ($request->submitter_id ?? 0),
            'site_name'    => $siteName,
            'approval_url' => $url,
        ], $base);

        $submitterId = (int) ($request->submitter_id ?? 0);
        if ($submitterId > 0) {
            try {
                $sub = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($submitterId);
                if (!$sub->guest) {
                    $vars['submitter_name']     = trim((string) $sub->name);
                    $vars['submitter_username'] = trim((string) $sub->username);
                }
            } catch (\Throwable $e) {
            }
        }
        if (!isset($vars['submitter_name'])) {
            $vars['submitter_name'] = '';
        }
        if (!isset($vars['submitter_username'])) {
            $vars['submitter_username'] = '';
        }

        if ((string) ($request->entity_type ?? '') === ApprovalWorkflowService::ENTITY_PAYMENT_PROOF) {
            $vars = array_merge($vars, self::buildPaymentProofTemplateVars($db, $request));
        }

        $etLink = (string) ($request->entity_type ?? '');
        if (
            $etLink === ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO
            || $etLink === ApprovalWorkflowService::ENTITY_SOLICITUD_COTIZACION
            || $etLink === ApprovalWorkflowService::ENTITY_CREACION_ORDEN_TRABAJO
        ) {
            $precotPkUrl = (int) ($request->entity_id ?? 0);
            if ($precotPkUrl > 0) {
                $precotDocRel = Route::_('index.php?option=com_ordenproduccion&view=cotizador&layout=document&id=' . $precotPkUrl, false);
                $precotAbs    = $root . '/' . ltrim((string) $precotDocRel, '/');
                $vars['approval_url']         = $precotAbs;
                $vars['precot_document_url']    = $precotAbs;
            }
        }

        if ($etLink === ApprovalWorkflowService::ENTITY_CREACION_ORDEN_TRABAJO) {
            $preNumFormatted                 = self::formatEntityIdForTelegram($db, $request);
            $vars['pre_cotizacion_number']   = $preNumFormatted;
            $vars['precot_number']           = $preNumFormatted;
            $vars['cotizacion_number']       = '';
            $vars['cotizacion_client_name']  = '';
            $vars['quotation_id']            = '';
            $vars['orden_trabajo_id']        = '';
            $vars['orden_trabajo_number']    = '';
            $vars['fecha_entrega']           = '';
            $vars['fecha_entrega_solicitada'] = '';
            $metaRawCre                      = isset($request->metadata) ? trim((string) $request->metadata) : '';
            $metaArrCre                      = null;

            if ($metaRawCre !== '') {
                try {
                    $metaArrCre = json_decode($metaRawCre, true);
                } catch (\Throwable $e) {
                    $metaArrCre = null;
                }
            }

            if (is_array($metaArrCre)) {
                $qidCre = (int) ($metaArrCre['quotation_id'] ?? 0);
                if ($qidCre > 0) {
                    $vars['quotation_id']           = (string) $qidCre;
                    $qExtras                        = self::loadQuotationTelegramExtras($db, $qidCre);
                    $vars['cotizacion_number']      = $qExtras['number'];
                    $vars['cotizacion_client_name'] = $qExtras['client_name'];
                }

                $creacionOtId = (int) ($metaArrCre['creacion_ot_orden_id'] ?? 0);
                $creacionOtNu = trim((string) ($metaArrCre['creacion_ot_orden_number'] ?? ''));
                if ($creacionOtId > 0) {
                    $vars['orden_trabajo_id'] = (string) $creacionOtId;
                }

                $vars['orden_trabajo_number'] = $creacionOtNu !== ''
                    ? $creacionOtNu
                    : ($creacionOtId > 0 ? self::formatOrdenTrabajoNumberForTelegram($db, $creacionOtId) : '');

                if (isset($metaArrCre['wizard']) && is_array($metaArrCre['wizard'])) {
                    $feCre = trim((string) ($metaArrCre['wizard']['ot_fecha_entrega'] ?? ''));
                    if ($feCre !== '') {
                        try {
                            $lbl = Factory::getDate($feCre . ' 12:00:00')->format(Text::_('DATE_FORMAT_LC4'));
                        } catch (\Throwable $e) {
                            $lbl = $feCre;
                        }
                        $vars['fecha_entrega']           = $lbl;
                        $vars['fecha_entrega_solicitada'] = $lbl;
                    }
                }
            }
        }

        /*
         * Step-assignment Telegram/email templates may use {actor_name} for “who triggered this request”.
         * Outcome notifications pass actor_* in $base (the approver who decided). Assign notifications do not;
         * use the submitter (e.g. orden de compra creator) so placeholders are not left literal.
         */
        if (!array_key_exists('actor_name', $base)) {
            $vars['actor_name']     = $vars['submitter_name'] ?? '';
            $vars['actor_username'] = $vars['submitter_username'] ?? '';
            $vars['actor_id']       = $vars['submitter_id'] ?? '0';
        }

        return $vars;
    }

    /**
     * Notify approver(s) via Telegram when a step is assigned.
     */
    public static function notifyAssign(int $requestId, User $approver, int $entityId, string $entityType): void
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if (!self::isTelegramDispatchReady($db)) {
            return;
        }

        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

        $req = self::loadRequestRow($db, $requestId);
        if ($req === null) {
            return;
        }

        $wf = self::loadWorkflowRow($db, (int) ($req->workflow_id ?? 0));
        $template = $wf ? trim((string) ($wf->email_body_assign ?? '')) : '';
        if ($template === '') {
            $template = Text::_('COM_ORDENPRODUCCION_APPROVAL_TELEGRAM_ASSIGN_DEFAULT');
        }

        $vars = self::buildBaseTemplateVars($db, $req, $wf, $approver, [
            'approver_name'     => trim((string) $approver->name),
            'approver_username' => trim((string) $approver->username),
            'approver_id'       => (string) (int) $approver->id,
        ]);

        $text = TelegramNotificationHelper::replaceTemplatePlaceholders($template, $vars);
        if (trim($text) === '') {
            return;
        }

        $chatId = TelegramNotificationHelper::getChatIdForUser((int) $approver->id);
        if ($chatId === null) {
            return;
        }

        TelegramQueueHelper::enqueue($chatId, $text);

        $params = ComponentHelper::getParams('com_ordenproduccion');
        TelegramNotificationHelper::sendToAdministracionBroadcastChannel(
            $params,
            $text,
            TelegramNotificationHelper::EVENT_APPROVAL_WORKFLOW
        );
    }

    /**
     * Notify the request submitter when the request is fully approved or rejected.
     *
     * @param   string  $outcome  approved|rejected
     */
    public static function notifySubmitterOutcome(int $requestId, string $outcome, int $actorUserId, string $comments): void
    {
        $outcome = strtolower($outcome);
        if (!in_array($outcome, ['approved', 'rejected'], true)) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        if (!self::isTelegramDispatchReady($db)) {
            return;
        }

        TelegramNotificationHelper::ensureTelegramLanguageLoaded();

        $req = self::loadRequestRow($db, $requestId);
        if ($req === null) {
            return;
        }

        $submitterId = (int) ($req->submitter_id ?? 0);
        if ($submitterId < 1) {
            return;
        }

        try {
            $submitter = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($submitterId);
        } catch (\Throwable $e) {
            return;
        }
        if ($submitter->guest) {
            return;
        }

        $wf = self::loadWorkflowRow($db, (int) ($req->workflow_id ?? 0));
        $template = $wf ? trim((string) ($wf->email_body_decided ?? '')) : '';
        if ($template === '') {
            $template = Text::_('COM_ORDENPRODUCCION_APPROVAL_TELEGRAM_OUTCOME_DEFAULT');
        }

        $actorName = '';
        $actorUser = '';
        if ($actorUserId > 0) {
            try {
                $actor = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($actorUserId);
                if (!$actor->guest) {
                    $actorName = trim((string) $actor->name);
                    $actorUser = trim((string) $actor->username);
                }
            } catch (\Throwable $e) {
            }
        }

        $decisionLabel = $outcome === 'approved'
            ? Text::_('COM_ORDENPRODUCCION_APPROVAL_TELEGRAM_DECISION_APPROVED')
            : Text::_('COM_ORDENPRODUCCION_APPROVAL_TELEGRAM_DECISION_REJECTED');

        $vars = self::buildBaseTemplateVars($db, $req, $wf, $submitter, [
            'decision'        => $outcome,
            'decision_label'  => $decisionLabel,
            'comments'        => $comments,
            'actor_id'        => (string) $actorUserId,
            'actor_name'      => $actorName,
            'actor_username'  => $actorUser,
        ]);

        $text = TelegramNotificationHelper::replaceTemplatePlaceholders($template, $vars);
        if (trim($text) === '') {
            return;
        }

        $chatId = TelegramNotificationHelper::getChatIdForUser($submitterId);
        if ($chatId === null) {
            return;
        }

        TelegramQueueHelper::enqueue($chatId, $text);

        $params = ComponentHelper::getParams('com_ordenproduccion');
        TelegramNotificationHelper::sendToAdministracionBroadcastChannel(
            $params,
            $text,
            TelegramNotificationHelper::EVENT_APPROVAL_WORKFLOW
        );
    }
}
