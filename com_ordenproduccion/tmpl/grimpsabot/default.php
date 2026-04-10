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
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_MSG_HELP_INVOICE'))); ?></p>
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_MSG_HELP_ENVIO'))); ?></p>
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
                        <div class="tab-pane fade" id="grimpsabot-pane-queue" role="tabpanel" aria-labelledby="grimpsabot-tab-queue" tabindex="0">
                            <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_INTRO'); ?></p>
                            <?php if (empty($this->telegramQueueTableOk)) : ?>
                                <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_TABLE_MISSING'); ?></div>
                            <?php endif; ?>
                            <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PENDING_HEADING'); ?>
                                <?php if (!empty($this->telegramQueueTableOk)) : ?>
                                    <span class="badge bg-secondary"><?php echo (int) $this->telegramQueuePendingTotal; ?></span>
                                    <?php if ($this->telegramQueuePendingTotal > TelegramQueueHelper::DISPLAY_LIST_LIMIT) : ?>
                                        <span class="small text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_LIST_CAPPED', TelegramQueueHelper::DISPLAY_LIST_LIMIT); ?></span>
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
                            <?php endif; ?>

                            <h3 class="h6"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_HEADING'); ?>
                                <?php if (!empty($this->telegramSentLogTableOk)) : ?>
                                    <span class="badge bg-secondary"><?php echo (int) $this->telegramQueueSentTotal; ?></span>
                                    <?php if ($this->telegramQueueSentTotal > TelegramQueueHelper::DISPLAY_LIST_LIMIT) : ?>
                                        <span class="small text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_LIST_CAPPED', TelegramQueueHelper::DISPLAY_LIST_LIMIT); ?></span>
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
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3 border-top pt-3">
                        <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
                    </div>
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
})();
</script>
<?php endif; ?>
