<?php

/**
 * Grimpsa bot — Telegram settings (frontend).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @since       3.105.0
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

HTMLHelper::_('bootstrap.collapse', '.collapse', []);
HTMLHelper::_('bootstrap.alert', '.alert', []);

$canAdmin = !empty($this->canManageBotSettings);
$tableOk  = !empty($this->telegramTableOk);
$form     = $this->form;
$root    = rtrim(Uri::root(), '/');
$cronUrl = $root . '/index.php?option=com_ordenproduccion&controller=telegram&task=processQueue&format=raw&cron_key=';
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
                            <button class="nav-link active" id="grimpsabot-tab-bot" data-bs-toggle="tab" data-bs-target="#grimpsabot-pane-bot" type="button" role="tab" aria-controls="grimpsabot-pane-bot" aria-selected="true">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_BOT'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="grimpsabot-tab-broadcast" data-bs-toggle="tab" data-bs-target="#grimpsabot-pane-broadcast" type="button" role="tab" aria-controls="grimpsabot-pane-broadcast" aria-selected="false">
                                <?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TAB_BROADCAST'); ?>
                            </button>
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
                            <p class="small text-muted"><?php echo nl2br($this->escape(Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BROADCAST_CRON_HELP'))); ?></p>
                            <p class="small font-monospace text-break bg-light p-2 rounded border"><?php echo $this->escape($cronUrl); ?><span class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BROADCAST_CRON_KEY_PLACEHOLDER'); ?></span></p>
                            <p class="small text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_BROADCAST_TEST_BELOW'); ?></p>
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
