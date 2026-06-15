<?php
/**
 * Ajustes > MT-940 > Registro de importación: cron and manual run history.
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$app = Factory::getApplication();
$app->getLanguage()->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
$app->getLanguage()->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

$rows       = isset($this->mt940RunLogRows) && \is_array($this->mt940RunLogRows) ? $this->mt940RunLogRows : [];
$tableOk    = !empty($this->mt940RunLogTableOk);
$pagination = $this->mt940RunLogPagination ?? null;

$triggerLabel = static function (string $type): string {
    $map = [
        'cron'           => 'COM_ORDENPRODUCCION_MT940_RUN_TRIGGER_CRON',
        'manual_mailbox' => 'COM_ORDENPRODUCCION_MT940_RUN_TRIGGER_MANUAL_MAILBOX',
        'manual_file'    => 'COM_ORDENPRODUCCION_MT940_RUN_TRIGGER_MANUAL_FILE',
    ];

    return Text::_($map[$type] ?? 'COM_ORDENPRODUCCION_MT940_RUN_TRIGGER_UNKNOWN');
};

$statusBadge = static function (string $status): string {
    $map = [
        'success' => 'success',
        'fail'    => 'danger',
        'skipped' => 'secondary',
    ];
    $cls = $map[$status] ?? 'warning';
    $key = 'COM_ORDENPRODUCCION_MT940_RUN_STATUS_' . \strtoupper($status);
    $lbl = Text::_($key);
    if ($lbl === $key) {
        $lbl = $status;
    }

    return '<span class="badge bg-' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($lbl, ENT_QUOTES, 'UTF-8') . '</span>';
};

$actorNameById = [];
if ($rows !== []) {
    $ids = [];
    foreach ($rows as $r) {
        $aid = (int) ($r->created_by ?? 0);
        if ($aid > 0) {
            $ids[$aid] = true;
        }
    }
    $idList = \array_keys($ids);
    if ($idList !== []) {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('name'), $db->quoteName('username')])
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('id') . ' IN (' . \implode(',', \array_map('intval', $idList)) . ')');
        $db->setQuery($query);
        foreach ($db->loadObjectList() ?: [] as $u) {
            $uid = (int) $u->id;
            $nm  = \trim((string) ($u->name ?? ''));
            $un  = \trim((string) ($u->username ?? ''));
            $actorNameById[$uid] = $nm !== '' ? $nm : ($un !== '' ? $un : '#' . $uid);
        }
    }
}
?>
<p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_LOG_INTRO'); ?></p>

<?php if (!$tableOk) : ?>
    <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_LOG_SCHEMA_MISSING'); ?></div>
<?php elseif ($rows === []) : ?>
    <p class="small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_LOG_EMPTY'); ?></p>
<?php else : ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle" style="font-size: 11px;">
            <thead class="table-light">
                <tr>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_RAN_AT'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_TRIGGER'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_STATUS'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_EMAILS'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_FILES'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_SKIPPED'); ?></th>
                    <th class="text-end"><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_TX'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_MESSAGE'); ?></th>
                    <th><?php echo Text::_('COM_ORDENPRODUCCION_MT940_RUN_COL_ACTOR'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row) :
                    $trigger = (string) ($row->trigger_type ?? '');
                    $status  = (string) ($row->status ?? '');
                    $actorId = (int) ($row->created_by ?? 0);
                    if ($trigger === 'cron') {
                        $actorLbl = Text::_('COM_ORDENPRODUCCION_MT940_RUN_ACTOR_CRON');
                    } elseif ($actorId > 0) {
                        $actorLbl = $actorNameById[$actorId] ?? ('#' . $actorId);
                    } else {
                        $actorLbl = '—';
                    }
                    $msg = \trim((string) ($row->message ?? ''));
                    ?>
                    <tr>
                        <td class="text-nowrap"><?php echo htmlspecialchars((string) ($row->ran_at ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($triggerLabel($trigger), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $statusBadge($status); ?></td>
                        <td class="text-end"><?php echo (int) ($row->emails_scanned ?? 0); ?></td>
                        <td class="text-end"><?php echo (int) ($row->files_imported ?? 0); ?></td>
                        <td class="text-end"><?php echo (int) ($row->files_skipped ?? 0); ?></td>
                        <td class="text-end"><?php echo (int) ($row->transactions_imported ?? 0); ?></td>
                        <td style="max-width: 280px; word-break: break-word;"><?php echo $msg !== '' ? htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                        <td><?php echo htmlspecialchars($actorLbl, ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pagination && (int) ($this->mt940RunLogTotal ?? 0) > 0) : ?>
        <div class="com-content-pagination mt-3 small"><?php echo $pagination->getListFooter(); ?></div>
    <?php endif; ?>
<?php endif; ?>
