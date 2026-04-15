<?php

/**
 * Grimpsa bot — Telegram settings (frontend).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @since       3.105.0
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramQueueHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramWebhookLogHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

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

$queueUrl = static function (int $tgQp, int $tgQs, int $tgWlp): string {
    return Route::_('index.php?option=com_ordenproduccion&view=grimpsabot&tg_qp=' . $tgQp . '&tg_qs=' . $tgQs . '&tg_wlp=' . $tgWlp, false) . '#grimpsabot-pane-queue';
};

$webhookLogUrl = static function (int $tgQp, int $tgQs, int $tgWlp): string {
    return Route::_('index.php?option=com_ordenproduccion&view=grimpsabot&tg_qp=' . $tgQp . '&tg_qs=' . $tgQs . '&tg_wlp=' . $tgWlp, false) . '#grimpsabot-pane-webhook-log';
};

$webhookEndpointUrl = TelegramNotificationHelper::getTelegramWebhookPublicRoot()
    . '/index.php?option=com_ordenproduccion&controller=telegram&task=webhook&format=raw';

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
                            <a class="nav-link" id="grimpsabot-tab-webhook-log" href="#grimpsabot-pane-webhook-log" role="tab" aria-controls="grimpsabot-pane-webhook-log" aria-selected="false">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_WEBHOOK_LOG'); ?>
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
                            <?php foreach ($form->getFieldset('webhook') as $field) : ?>
                                <div class="mb-3">
                                    <?php echo $field->label; ?>
                                    <?php echo $field->input; ?>
                                </div>
                            <?php endforeach; ?>
                            <div class="mb-3 border-top pt-3">
                                <label class="form-label fw-semibold" for="grimpsabot-webhook-url"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_FULL_URL_LABEL'); ?></label>
                                <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_FULL_URL_HELP'); ?></p>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control font-monospace" readonly value="<?php echo htmlspecialchars($webhookEndpointUrl, ENT_QUOTES, 'UTF-8'); ?>" id="grimpsabot-webhook-url" />
                                    <button type="button" class="btn btn-outline-secondary" onclick="(function(){var el=document.getElementById('grimpsabot-webhook-url');if(!el)return;el.select();try{document.execCommand('copy');}catch(e){}})();"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_COPY'); ?></button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="grimpsabot-webhook-generate-secret">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_GENERATE_SECRET'); ?>
                                </button>
                                <span class="small text-muted ms-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_GENERATE_SECRET_HINT'); ?></span>
                            </div>
                            <p class="small text-muted mb-2"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_REGISTER_HELP'))); ?></p>
                            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                <button type="submit" form="grimpsabot-webhook-register" class="btn btn-warning btn-sm"
                                    onclick="(function(){var h=document.getElementById('grimpsabot-webhook-return');if(h){h.value='webhook';}return window.confirm(<?php echo json_encode(Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_CONFIRM')); ?>);})();">
                                    <?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_WEBHOOK_SETUP_BTN'); ?>
                                </button>
                                <form action="<?php echo Route::_('index.php'); ?>" method="post" class="d-inline">
                                    <input type="hidden" name="option" value="com_ordenproduccion" />
                                    <input type="hidden" name="task" value="grimpsabot.telegramBotInfo" />
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_BOT_INFO_BTN'); ?></button>
                                </form>
                            </div>
                            <span class="small text-muted d-block mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_SET_BTN_HINT'); ?></span>
                            <p class="small text-muted mt-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_BOT_INFO_BTN_HELP'); ?></p>
                        </div>
                        <div class="tab-pane fade" id="grimpsabot-pane-webhook-log" role="tabpanel" aria-labelledby="grimpsabot-tab-webhook-log" tabindex="0">
                            <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_INTRO'); ?></p>
                            <?php if (empty($this->telegramWebhookLogTableOk)) : ?>
                                <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_TABLE_MISSING'); ?></div>
                            <?php elseif ((int) $this->telegramWebhookLogTotal === 0) : ?>
                                <p class="text-muted small mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_EMPTY'); ?></p>
                            <?php else : ?>
                                <p class="small text-muted mb-2">
                                    <span class="badge bg-secondary"><?php echo (int) $this->telegramWebhookLogTotal; ?></span>
                                    <?php echo Text::sprintf('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_PER_PAGE', (int) TelegramWebhookLogHelper::LOG_PAGE_SIZE); ?>
                                </p>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-striped table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_CREATED'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_IP'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_METHOD'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_BODY_LEN'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_HDR'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_HDR_VALID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_STATUS'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_OUTCOME'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_UPDATE_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_CHAT_ID'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_PREVIEW'); ?></th>
                                                <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_WEBHOOK_LOG_COL_UA'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($this->telegramWebhookLogRows as $wrow) : ?>
                                                <?php
                                                $hdrOn = !empty($wrow->secret_header_present);
                                                $hdrOk = !empty($wrow->secret_valid);
                                                ?>
                                                <tr>
                                                    <td><?php echo (int) ($wrow->id ?? 0); ?></td>
                                                    <td class="small"><?php echo $this->escape((string) ($wrow->created ?? '')); ?></td>
                                                    <td class="small"><code><?php echo $this->escape((string) ($wrow->ip ?? '')); ?></code></td>
                                                    <td><code class="small"><?php echo $this->escape((string) ($wrow->http_method ?? '')); ?></code></td>
                                                    <td><?php echo (int) ($wrow->body_length ?? 0); ?></td>
                                                    <td><?php echo $hdrOn ? Text::_('JYES') : Text::_('JNO'); ?></td>
                                                    <td><?php echo $hdrOk ? Text::_('JYES') : Text::_('JNO'); ?></td>
                                                    <td><?php echo (int) ($wrow->http_status ?? 0); ?></td>
                                                    <td class="small font-monospace text-break"><?php echo $this->escape((string) ($wrow->outcome ?? '')); ?></td>
                                                    <td><?php echo isset($wrow->update_id) ? (int) $wrow->update_id : ''; ?></td>
                                                    <td><code class="small"><?php echo $this->escape((string) ($wrow->chat_id ?? '')); ?></code></td>
                                                    <td class="small text-break"><?php echo $this->escape($truncateQueueBody((string) ($wrow->text_preview ?? ''), 120)); ?></td>
                                                    <td class="small text-break"><span title="<?php echo $this->escape((string) ($wrow->user_agent ?? '')); ?>"><?php echo $this->escape($truncateQueueBody((string) ($wrow->user_agent ?? ''), 48)); ?></span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if ((int) $this->telegramWebhookLogTotalPages > 1) : ?>
                                    <nav class="mb-0" aria-label="<?php echo $this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_WEBHOOK_LOG')); ?>">
                                        <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
                                            <li class="page-item<?php echo (int) $this->telegramWebhookLogPage <= 1 ? ' disabled' : ''; ?>">
                                                <?php if ((int) $this->telegramWebhookLogPage > 1) : ?>
                                                    <a class="page-link" href="<?php echo $webhookLogUrl((int) $this->telegramQueuePendingPage, (int) $this->telegramQueueSentPage, (int) $this->telegramWebhookLogPage - 1); ?>"><?php echo Text::_('JPREVIOUS'); ?></a>
                                                <?php else : ?>
                                                    <span class="page-link"><?php echo Text::_('JPREVIOUS'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                            <?php
                                            $wEnd = (int) $this->telegramWebhookLogTotalPages;
                                            $wCur = (int) $this->telegramWebhookLogPage;
                                            if ($wEnd <= 15) :
                                                for ($w = 1; $w <= $wEnd; $w++) :
                                                    $wActive = $w === $wCur;
                                                    ?>
                                                    <li class="page-item<?php echo $wActive ? ' active' : ''; ?>">
                                                        <?php if (!$wActive) : ?>
                                                            <a class="page-link" href="<?php echo $webhookLogUrl((int) $this->telegramQueuePendingPage, (int) $this->telegramQueueSentPage, $w); ?>"><?php echo $w; ?></a>
                                                        <?php else : ?>
                                                            <span class="page-link"><?php echo $w; ?></span>
                                                        <?php endif; ?>
                                                    </li>
                                                <?php
                                                endfor;
                                            else :
                                                ?>
                                                <li class="page-item disabled"><span class="page-link"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PAGE_OF', $wCur, $wEnd); ?></span></li>
                                            <?php endif; ?>
                                            <li class="page-item<?php echo $wCur >= $wEnd ? ' disabled' : ''; ?>">
                                                <?php if ($wCur < $wEnd) : ?>
                                                    <a class="page-link" href="<?php echo $webhookLogUrl((int) $this->telegramQueuePendingPage, (int) $this->telegramQueueSentPage, $wCur + 1); ?>"><?php echo Text::_('JNEXT'); ?></a>
                                                <?php else : ?>
                                                    <span class="page-link"><?php echo Text::_('JNEXT'); ?></span>
                                                <?php endif; ?>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            <?php endif; ?>
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
                                                    <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage - 1, (int) $this->telegramQueueSentPage, (int) $this->telegramWebhookLogPage); ?>"><?php echo Text::_('JPREVIOUS'); ?></a>
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
                                                            <a class="page-link" href="<?php echo $queueUrl($p, (int) $this->telegramQueueSentPage, (int) $this->telegramWebhookLogPage); ?>"><?php echo $p; ?></a>
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
                                                    <a class="page-link" href="<?php echo $queueUrl($pCur + 1, (int) $this->telegramQueueSentPage, (int) $this->telegramWebhookLogPage); ?>"><?php echo Text::_('JNEXT'); ?></a>
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
                                                    <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage, (int) $this->telegramQueueSentPage - 1, (int) $this->telegramWebhookLogPage); ?>"><?php echo Text::_('JPREVIOUS'); ?></a>
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
                                                            <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage, $s, (int) $this->telegramWebhookLogPage); ?>"><?php echo $s; ?></a>
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
                                                    <a class="page-link" href="<?php echo $queueUrl((int) $this->telegramQueuePendingPage, $sCur + 1, (int) $this->telegramWebhookLogPage); ?>"><?php echo Text::_('JNEXT'); ?></a>
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
                <?php if (!empty($this->telegramBotInfoDebug)) : ?>
                    <div class="mt-3 border rounded p-3 bg-light" id="grimpsabot-telegram-botinfo-debug">
                        <div class="fw-semibold mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TELEGRAM_BOTINFO_DEBUG_TITLE'); ?></div>
                        <p class="small text-muted mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TELEGRAM_BOTINFO_DEBUG_HELP'); ?></p>
                        <textarea readonly class="form-control font-monospace small" rows="18" spellcheck="false"><?php echo $this->escape((string) $this->telegramBotInfoDebug); ?></textarea>
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
    var dbg = document.getElementById('grimpsabot-telegram-botinfo-debug')
        || document.getElementById('grimpsabot-setwebhook-debug');
    if (dbg) {
        dbg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    var genBtn = document.getElementById('grimpsabot-webhook-generate-secret');
    if (genBtn) {
        genBtn.addEventListener('click', function () {
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789_-';
            var len = 40;
            var out = '';
            var i;
            if (window.crypto && window.crypto.getRandomValues) {
                var buf = new Uint8Array(len);
                window.crypto.getRandomValues(buf);
                for (i = 0; i < len; i++) {
                    out += chars.charAt(buf[i] % chars.length);
                }
            } else {
                for (i = 0; i < len; i++) {
                    out += chars.charAt(Math.floor(Math.random() * chars.length));
                }
            }
            var inp = document.getElementById('jform_telegram_webhook_secret');
            if (inp) {
                inp.value = out;
                inp.type = 'text';
                inp.focus();
            }
        });
    }
})();
</script>
<?php endif; ?>
