<?php

/**
 * Grimpsa bot — Telegram settings (frontend).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @since       3.105.0
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramQueueHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('bootstrap.collapse', '.collapse', []);
HTMLHelper::_('bootstrap.alert', '.alert', []);

$canAdmin = !empty($this->canManageBotSettings);
$tableOk  = !empty($this->telegramTableOk);
$form     = $this->form;

$truncateQueueBody = static function (string $text, int $max = 200): string {
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    if (\function_exists('mb_strlen') && \function_exists('mb_substr')) {
        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return mb_substr($text, 0, $max) . '…';
    }
    if (\strlen($text) <= $max) {
        return $text;
    }

    return substr($text, 0, $max) . '…';
};

$queueUrl = static function (int $tgQp, int $tgQs): string {
    return Route::_('index.php?option=com_ordenproduccion&view=grimpsabot&tg_qp=' . $tgQp . '&tg_qs=' . $tgQs, false) . '#grimpsabot-pane-queue';
};

$webhookEndpointUrl = rtrim(Uri::root(), '/') . '/index.php?option=com_ordenproduccion&controller=telegram&task=webhook&format=raw';

?>
<div class="com-ordenproduccion-grimpsabot container py-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TITLE'); ?></h1>
    <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_INTRO'); ?></p>

    <?php if (!$tableOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TABLE_MISSING'); ?></div>
    <?php endif; ?>

    <?php if ($canAdmin && $form) : ?>
        <div class="card mb-4">
            <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SETTINGS_CARD_HEADER'); ?></div>
            <div class="card-body">
                <form action="<?php echo Route::_('index.php'); ?>" method="post" name="grimpsabot-config" id="grimpsabot-config" class="form-validate">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.saveconfig" />
                    <?php echo HTMLHelper::_('form.token'); ?>

                    <ul class="nav nav-tabs mb-3" id="grimpsabotTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" id="grimpsabot-tab-bot" href="#grimpsabot-pane-bot" role="tab" aria-controls="grimpsabot-pane-bot" aria-selected="true">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_BOT'); ?>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="grimpsabot-tab-broadcast" href="#grimpsabot-pane-broadcast" role="tab" aria-controls="grimpsabot-pane-broadcast" aria-selected="false">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_BROADCAST'); ?>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="grimpsabot-tab-webhook" href="#grimpsabot-pane-webhook" role="tab" aria-controls="grimpsabot-pane-webhook" aria-selected="false">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_WEBHOOK'); ?>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" id="grimpsabot-tab-queue" href="#grimpsabot-pane-queue" role="tab" aria-controls="grimpsabot-pane-queue" aria-selected="false">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_QUEUE'); ?>
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content" id="grimpsabotTabsContent">
                        <div class="tab-pane fade show active" id="grimpsabot-pane-bot" role="tabpanel" aria-labelledby="grimpsabot-tab-bot" tabindex="0">
                            <?php foreach ($form->getFieldset('telegram') as $field) : ?>
                                <div class="mb-3">
                                    <?php echo $field->label; ?>
                                    <?php echo $field->input; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="border rounded p-3 mb-3 bg-light">
                                <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BOT_TAB_SETWEBHOOK_HELP'); ?></p>
                                <button type="submit" form="grimpsabot-webhook-register" class="btn btn-warning btn-sm"
                                    onclick="(function(){var h=document.getElementById('grimpsabot-webhook-return');if(h){h.value='bot';}return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_CONFIRM')); ?>);})();">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_BTN'); ?>
                                </button>
                            </div>
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_MSG_HELP_INVOICE'))); ?></p>
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_MSG_HELP_ENVIO'))); ?></p>
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_MSG_HELP_PAYMENT_PROOF_ENTERED'))); ?></p>
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_MSG_HELP_PAYMENT_PROOF_VERIFIED'))); ?></p>
                        </div>
                        <div class="tab-pane fade" id="grimpsabot-pane-broadcast" role="tabpanel" aria-labelledby="grimpsabot-tab-broadcast" tabindex="0">
                            <?php foreach ($form->getFieldset('broadcast') as $field) : ?>
                                <div class="mb-3">
                                    <?php echo $field->label; ?>
                                    <?php echo $field->input; ?>
                                </div>
                            <?php endforeach; ?>
                            <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BROADCAST_CRON_HELP'); ?></p>
                            <?php if (!empty($this->telegramCronCrontabLine)) : ?>
                                <pre class="small mb-2 font-monospace bg-light p-2 rounded border" style="white-space: pre-wrap; word-break: break-all;"><?php echo $this->escape($this->telegramCronCrontabLine); ?></pre>
                            <?php endif; ?>
                            <?php if (empty($this->telegramCronCrontabKeyConfigured)) : ?>
                                <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BROADCAST_CRON_SAVE_KEY_FIRST'); ?></p>
                            <?php endif; ?>
                            <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BROADCAST_TEST_BELOW'); ?></p>
                        </div>
                        <div class="tab-pane fade" id="grimpsabot-pane-webhook" role="tabpanel" aria-labelledby="grimpsabot-tab-webhook" tabindex="0">
                            <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_INTRO'); ?></p>
                            <div class="mb-3">
                                <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_URL_LABEL'); ?></label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace" readonly value="<?php echo htmlspecialchars($webhookEndpointUrl, ENT_QUOTES, 'UTF-8'); ?>" id="grimpsabot-webhook-url" />
                                    <button type="button" class="btn btn-outline-secondary" onclick="(function(){var el=document.getElementById('grimpsabot-webhook-url');if(!el)return;el.select();try{document.execCommand('copy');}catch(e){}})();"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_COPY'); ?></button>
                                </div>
                            </div>
                            <?php foreach ($form->getFieldset('webhook') as $field) : ?>
                                <div class="mb-3">
                                    <?php echo $field->label; ?>
                                    <?php echo $field->input; ?>
                                </div>
                            <?php endforeach; ?>
                            <p class="small text-muted mb-2"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_REGISTER_HELP'))); ?></p>
                            <button type="submit" form="grimpsabot-webhook-register" class="btn btn-warning btn-sm"
                                onclick="(function(){var h=document.getElementById('grimpsabot-webhook-return');if(h){h.value='webhook';}return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_CONFIRM')); ?>);})();">
                                <?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_BTN'); ?>
                            </button>
                            <span class="small text-muted ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_SET_BTN_HINT'); ?></span>
                        </div>
                        <div class="tab-pane fade" id="grimpsabot-pane-queue" role="tabpanel" aria-labelledby="grimpsabot-tab-queue" tabindex="0">
                            <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_INTRO'); ?></p>
                            <?php if (empty($this->telegramQueueTableOk)) : ?>
                                <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_TABLE_MISSING'); ?></div>
                            <?php endif; ?>
                            <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PENDING_HEADING'); ?>
                                <?php if (!empty($this->telegramQueueTableOk)) : ?>
                                    <span class="badge bg-secondary"><?php echo (int) $this->telegramQueuePendingTotal; ?></span>
                                    <?php if ((int) $this->telegramQueuePendingTotal > 0) : ?>
                                        <span class="small text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PER_PAGE', (int) TelegramQueueHelper::QUEUE_PAGE_SIZE); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h3>
                            <?php if (!empty($this->telegramQueueTableOk) && (int) $this->telegramQueuePendingTotal === 0) : ?>
                                <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PENDING_EMPTY'); ?></p>
                            <?php elseif (!empty($this->telegramQueueTableOk)) : ?>
                                <div class="table-responsive mb-4">
                                    <table class="table table-sm table-striped table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_CHAT_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_CREATED'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_ATTEMPTS'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_LAST_TRY'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_LAST_ERROR'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_BODY'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($this->telegramQueuePending as $row) : ?>
                                                <tr>
                                                    <td><?php echo (int) ($row->id ?? 0); ?></td>
                                                    <td><code class="small"><?php echo $this->escape((string) ($row->chat_id ?? '')); ?></code></td>
                                                    <td class="small"><?php echo $this->escape((string) ($row->created ?? '')); ?></td>
                                                    <td><?php echo (int) ($row->attempts ?? 0); ?></td>
                                                    <td class="small"><?php echo $this->escape((string) ($row->last_try ?? '')); ?></td>
                                                    <td class="small text-break"><?php echo $this->escape((string) ($row->last_error ?? '')); ?></td>
                                                    <td class="small"><span title="<?php echo $this->escape((string) ($row->body ?? '')); ?>"><?php echo $this->escape($truncateQueueBody((string) ($row->body ?? ''))); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ((int) $this->telegramQueuePendingTotalPages > 1) : ?>
                                    <nav class="mb-4" aria-label="<?php echo $this->escape(Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PENDING_HEADING')); ?>">
                                        <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
                                            <li class="page-item<?php echo (int) $this->telegramQueuePendingPage <= 1 ? ' disabled' : ''; ?>">
                                                <?php if ((int) $this->telegramQueuePendingPage > 1) : ?>
                                                    <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage - 1, (int) $this->telegramQueueSentPage); ?>"><?php echo Text::_('JPREVIOUS'); ?></a>
                                                <?php else : ?>
                                                    <span class="page-link"><?php echo Text::_('JPREVIOUS'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <?php
                                            $pEnd = (int) $this->telegramQueuePendingTotalPages;
                                            $pCur = (int) $this->telegramQueuePendingPage;
                                            if ($pEnd <= 15) :
                                                for ($p = 1; $p <= $pEnd; $p++) :
                                                    $isActive = $p === $pCur;
                                                    ?>
                                                    <li class="page-item<?php echo $isActive ? ' active' : ''; ?>">
                                                        <?php if (!$isActive) : ?>
                                                            <a class="page-link" href="<?php echo $queueUrl($p, (int) $this->telegramQueueSentPage); ?>"><?php echo $p; ?></a>
                                                        <?php else : ?>
                                                            <span class="page-link"><?php echo $p; ?></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php
                                                endfor;
                                            else :
                                                ?>
                                                <li class="page-item disabled"><span class="page-link"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PAGE_OF', $pCur, $pEnd); ?></span></li>
                                            <?php endif; ?>
                                            <li class="page-item<?php echo $pCur >= $pEnd ? ' disabled' : ''; ?>">
                                                <?php if ($pCur < $pEnd) : ?>
                                                    <a class="page-link" href="<?php echo $queueUrl($pCur + 1, (int) $this->telegramQueueSentPage); ?>"><?php echo Text::_('JNEXT'); ?></a>
                                                <?php else : ?>
                                                    <span class="page-link"><?php echo Text::_('JNEXT'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>

                            <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_HEADING'); ?>
                                <?php if (!empty($this->telegramSentLogTableOk)) : ?>
                                    <span class="badge bg-secondary"><?php echo (int) $this->telegramQueueSentTotal; ?></span>
                                    <?php if ((int) $this->telegramQueueSentTotal > 0) : ?>
                                        <span class="small text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PER_PAGE', (int) TelegramQueueHelper::QUEUE_PAGE_SIZE); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </h3>
                            <?php if (empty($this->telegramSentLogTableOk)) : ?>
                                <div class="alert alert-info small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_LOG_MISSING'); ?></div>
                            <?php elseif ((int) $this->telegramQueueSentTotal === 0) : ?>
                                <p class="text-muted small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_EMPTY'); ?></p>
                            <?php else : ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_CHAT_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_QUEUED'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_SENT_AT'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_QUEUE_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_COL_BODY'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($this->telegramQueueSent as $row) : ?>
                                                <tr>
                                                    <td><?php echo (int) ($row->id ?? 0); ?></td>
                                                    <td><code class="small"><?php echo $this->escape((string) ($row->chat_id ?? '')); ?></code></td>
                                                    <td class="small"><?php echo $this->escape((string) ($row->queued_created ?? '')); ?></td>
                                                    <td class="small"><?php echo $this->escape((string) ($row->sent_at ?? '')); ?></td>
                                                    <td><?php echo (int) ($row->source_queue_id ?? 0); ?></td>
                                                    <td class="small"><span title="<?php echo $this->escape((string) ($row->body ?? '')); ?>"><?php echo $this->escape($truncateQueueBody((string) ($row->body ?? ''))); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ((int) $this->telegramQueueSentTotalPages > 1) : ?>
                                    <nav class="mb-0" aria-label="<?php echo $this->escape(Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_HEADING')); ?>">
                                        <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
                                            <li class="page-item<?php echo (int) $this->telegramQueueSentPage <= 1 ? ' disabled' : ''; ?>">
                                                <?php if ((int) $this->telegramQueueSentPage > 1) : ?>
                                                    <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage, (int) $this->telegramQueueSentPage - 1); ?>"><?php echo Text::_('JPREVIOUS'); ?></a>
                                                <?php else : ?>
                                                    <span class="page-link"><?php echo Text::_('JPREVIOUS'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <?php
                                            $sEnd = (int) $this->telegramQueueSentTotalPages;
                                            $sCur = (int) $this->telegramQueueSentPage;
                                            if ($sEnd <= 15) :
                                                for ($s = 1; $s <= $sEnd; $s++) :
                                                    $sActive = $s === $sCur;
                                                    ?>
                                                    <li class="page-item<?php echo $sActive ? ' active' : ''; ?>">
                                                        <?php if (!$sActive) : ?>
                                                            <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage, $s); ?>"><?php echo $s; ?></a>
                                                        <?php else : ?>
                                                            <span class="page-link"><?php echo $s; ?></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php
                                                endfor;
                                            else :
                                                ?>
                                                <li class="page-item disabled"><span class="page-link"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PAGE_OF', $sCur, $sEnd); ?></span></li>
                                            <?php endif; ?>
                                            <li class="page-item<?php echo $sCur >= $sEnd ? ' disabled' : ''; ?>">
                                                <?php if ($sCur < $sEnd) : ?>
                                                    <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage, $sCur + 1); ?>"><?php echo Text::_('JNEXT'); ?></a>
                                                <?php else : ?>
                                                    <span class="page-link"><?php echo Text::_('JNEXT'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3 border-top pt-3">
                        <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
                    </div>
                </form>
                <?php if (!empty($this->telegramSetWebhookDebug)) : ?>
                    <div class="mt-3 border rounded p-3 bg-light" id="grimpsabot-setwebhook-debug">
                        <div class="fw-semibold mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_DEBUG_TITLE'); ?></div>
                        <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_DEBUG_HELP'); ?></p>
                        <textarea readonly class="form-control font-monospace small" rows="16" spellcheck="false"><?php echo $this->escape((string) $this->telegramSetWebhookDebug); ?></textarea>
                    </div>
                <?php endif; ?>
                <form id="grimpsabot-webhook-register" action="<?php echo Route::_('index.php'); ?>" method="post" class="d-none" aria-hidden="true">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.setTelegramWebhook" />
                    <input type="hidden" name="grimpsabot_webhook_return" id="grimpsabot-webhook-return" value="webhook" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
                <form action="<?php echo Route::_('index.php'); ?>" method="post" class="mt-3 border-top pt-3">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.sendtestbroadcast" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST_BROADCAST'); ?></button>
                    <span class="small text-muted ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST_BROADCAST_HINT'); ?></span>
                </form>
            </div>
        </div>
    <?php elseif (!$canAdmin) : ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_ADMIN_ONLY_CONFIG'); ?></p>
    <?php endif; ?>

    <?php if ($tableOk) : ?>
        <div class="card mb-4">
            <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_FIELDSET_PROFILE'); ?></div>
            <div class="card-body">
                <p><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_CHAT_HELP'); ?></p>
                <form action="<?php echo Route::_('index.php'); ?>" method="post" name="grimpsabot-profile" id="grimpsabot-profile">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.saveprofile" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label" for="telegram_chat_id"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_CHAT_ID_LABEL'); ?></label>
                            <input type="text" class="form-control" name="telegram_chat_id" id="telegram_chat_id"
                                value="<?php echo $this->escape((string) ($this->myChatId ?? '')); ?>"
                                autocomplete="off" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_CHAT_ID_HINT'); ?>" />
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-secondary"><?php echo Text::_('JSAVE'); ?></button>
                        </div>
                    </div>
                </form>

                <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TEST_EVENTS_INTRO'); ?></p>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <form action="<?php echo Route::_('index.php'); ?>" method="post" class="d-inline">
                        <input type="hidden" name="option" value="com_ordenproduccion" />
                        <input type="hidden" name="task" value="grimpsabot.sendtestevent" />
                        <input type="hidden" name="telegram_test_event" value="invoice" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST_INVOICE'); ?></button>
                    </form>
                    <form action="<?php echo Route::_('index.php'); ?>" method="post" class="d-inline">
                        <input type="hidden" name="option" value="com_ordenproduccion" />
                        <input type="hidden" name="task" value="grimpsabot.sendtestevent" />
                        <input type="hidden" name="telegram_test_event" value="envio" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST_ENVIO'); ?></button>
                    </form>
                    <form action="<?php echo Route::_('index.php'); ?>" method="post" class="d-inline">
                        <input type="hidden" name="option" value="com_ordenproduccion" />
                        <input type="hidden" name="task" value="grimpsabot.sendtestevent" />
                        <input type="hidden" name="telegram_test_event" value="proof_entered" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST_PAYMENT_PROOF_ENTERED'); ?></button>
                    </form>
                    <form action="<?php echo Route::_('index.php'); ?>" method="post" class="d-inline">
                        <input type="hidden" name="option" value="com_ordenproduccion" />
                        <input type="hidden" name="task" value="grimpsabot.sendtestevent" />
                        <input type="hidden" name="telegram_test_event" value="proof_verified" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST_PAYMENT_PROOF_VERIFIED'); ?></button>
                    </form>
                </div>
                <form action="<?php echo Route::_('index.php'); ?>" method="post" class="mt-1">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.sendtest" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST'); ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if ($canAdmin && $form) : ?>
<script>
(function () {
    var tabBar = document.getElementById('grimpsabotTabs');
    var panesWrap = document.getElementById('grimpsabotTabsContent');
    if (!tabBar || !panesWrap) {
        return;
    }
    tabBar.addEventListener('click', function (e) {
        var a = e.target && e.target.closest ? e.target.closest('a[href^="#grimpsabot-pane-"]') : null;
        if (!a || !tabBar.contains(a)) {
            return;
        }
        e.preventDefault();
        var id = a.getAttribute('href');
        if (!id || id.charAt(0) !== '#') {
            return;
        }
        var pane = document.querySelector(id);
        if (!pane || !panesWrap.contains(pane)) {
            return;
        }
        tabBar.querySelectorAll('.nav-link').forEach(function (el) {
            var on = el === a;
            el.classList.toggle('active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panesWrap.querySelectorAll('.tab-pane').forEach(function (el) {
            var on = el === pane;
            el.classList.toggle('show', on);
            el.classList.toggle('active', on);
        });
    });
    var h = (window.location.hash || '').trim();
    if (h && h.indexOf('#grimpsabot-pane-') === 0) {
        var link0 = tabBar.querySelector('a[href="' + h + '"]');
        if (link0) {
            link0.click();
        }
    }
    var dbg = document.getElementById('grimpsabot-setwebhook-debug');
    if (dbg) {
        dbg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
})();
</script>
<?php endif; ?>
