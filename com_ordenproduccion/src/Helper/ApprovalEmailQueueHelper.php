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
     * For solicitud_descuento, entity_id is the pre-cotización PK: use stored number (e.g. PRE-00072).
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
        if ($type !== ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO) {
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
