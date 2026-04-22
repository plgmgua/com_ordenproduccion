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
     * For solicitud_descuento and solicitud_cotizacion, entity_id is the pre-cotización PK: use stored number (e.g. PRE-00072).
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
    }
}
