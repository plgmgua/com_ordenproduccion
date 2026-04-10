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

HTMLHelper::_('bootstrap.collapse', '.collapse', []);
HTMLHelper::_('bootstrap.alert', '.alert', []);

$canAdmin = !empty($this->canManageBotSettings);
$tableOk  = !empty($this->telegramTableOk);
$form     = $this->form;
?>
<div class="com-ordenproduccion-grimpsabot container py-3">
    <h1 class="h3 mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TITLE'); ?></h1>
    <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_INTRO'); ?></p>

    <?php if (!$tableOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_TABLE_MISSING'); ?></div>
    <?php endif; ?>

    <?php if ($canAdmin && $form) : ?>
        <div class="card mb-4">
            <div class="card-header"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_FIELDSET_BOT'); ?></div>
            <div class="card-body">
                <form action="<?php echo Route::_('index.php'); ?>" method="post" name="grimpsabot-config" id="grimpsabot-config" class="form-validate">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.saveconfig" />
                    <?php echo HTMLHelper::_('form.token'); ?>

                    <?php foreach ($form->getFieldset('telegram') as $field) : ?>
                        <div class="mb-3">
                            <?php echo $field->label; ?>
                            <?php echo $field->input; ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary"><?php echo Text::_('JSAVE'); ?></button>
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

                <form action="<?php echo Route::_('index.php'); ?>" method="post" class="mt-3">
                    <input type="hidden" name="option" value="com_ordenproduccion" />
                    <input type="hidden" name="task" value="grimpsabot.sendtest" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                    <button type="submit" class="btn btn-outline-primary btn-sm"><?php echo Text::_('COM_ORDENPRODUCCION_GRIMPSABOT_SEND_TEST'); ?></button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
