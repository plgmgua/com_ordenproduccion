<?php

/**
 * Telegram queue admin template.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Administrator\Model\TelegramqueueModel;
use Joomla\CMS\Language\Text;

/**
 * @var \Grimpsa\Component\Ordenproduccion\Administrator\View\Telegramqueue\HtmlView $this
 */

$truncateBody = static function (string $text, int $max = 200): string {
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

<div class="com-ordenproduccion-telegram-queue">
    <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_INTRO'); ?></p>

    <?php if (!$this->queueTableOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_TABLE_MISSING'); ?></div>
    <?php endif; ?>

    <h2 class="h4 mt-4"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PENDING_HEADING'); ?>
        <?php if ($this->queueTableOk) : ?>
            <span class="badge bg-secondary"><?php echo (int) $this->pendingTotal; ?></span>
            <?php if ($this->pendingTotal > TelegramqueueModel::LIST_LIMIT) : ?>
                <span class="small text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_LIST_CAPPED', TelegramqueueModel::LIST_LIMIT); ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </h2>
    <?php if ($this->queueTableOk && $this->pendingTotal === 0) : ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_PENDING_EMPTY'); ?></p>
    <?php elseif ($this->queueTableOk) : ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
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
                    <?php foreach ($this->pendingItems as $row) : ?>
                        <tr>
                            <td><?php echo (int) ($row->id ?? 0); ?></td>
                            <td><code><?php echo $this->escape((string) ($row->chat_id ?? '')); ?></code></td>
                            <td><?php echo $this->escape((string) ($row->created ?? '')); ?></td>
                            <td><?php echo (int) ($row->attempts ?? 0); ?></td>
                            <td><?php echo $this->escape((string) ($row->last_try ?? '')); ?></td>
                            <td class="small text-break"><?php echo $this->escape((string) ($row->last_error ?? '')); ?></td>
                            <td class="small"><span title="<?php echo $this->escape((string) ($row->body ?? '')); ?>"><?php echo $this->escape($truncateBody((string) ($row->body ?? ''))); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="h4 mt-5"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_HEADING'); ?>
        <?php if ($this->sentLogTableOk) : ?>
            <span class="badge bg-secondary"><?php echo (int) $this->sentTotal; ?></span>
            <?php if ($this->sentTotal > TelegramqueueModel::LIST_LIMIT) : ?>
                <span class="small text-muted"><?php echo Text::sprintf('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_LIST_CAPPED', TelegramqueueModel::LIST_LIMIT); ?></span>
            <?php endif; ?>
        <?php endif; ?>
    </h2>
    <?php if (!$this->sentLogTableOk) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_LOG_MISSING'); ?></div>
    <?php elseif ($this->sentTotal === 0) : ?>
        <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_TELEGRAM_QUEUE_SENT_EMPTY'); ?></p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
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
                    <?php foreach ($this->sentItems as $row) : ?>
                        <tr>
                            <td><?php echo (int) ($row->id ?? 0); ?></td>
                            <td><code><?php echo $this->escape((string) ($row->chat_id ?? '')); ?></code></td>
                            <td><?php echo $this->escape((string) ($row->queued_created ?? '')); ?></td>
                            <td><?php echo $this->escape((string) ($row->sent_at ?? '')); ?></td>
                            <td><?php echo (int) ($row->source_queue_id ?? 0); ?></td>
                            <td class="small"><span title="<?php echo $this->escape((string) ($row->body ?? '')); ?>"><?php echo $this->escape($truncateBody((string) ($row->body ?? ''))); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
