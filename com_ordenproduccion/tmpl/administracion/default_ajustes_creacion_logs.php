<?php
/**
 * Ajustes → Creación OT: lee entradas desde logs de Joomla (`OT wizard create failed:`).
 *
 * @package     com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$entries = is_array($this->otWizardLogEntries ?? null) ? $this->otWizardLogEntries : [];
$dirs    = is_array($this->otWizardLogScannedDirs ?? null) ? $this->otWizardLogScannedDirs : [];

if ($entries !== []) {
    HTMLHelper::_('bootstrap.collapse');
}
?>
<div class="admin-dashboard ot-wizard-creation-log px-2 py-1 mx-auto" style="max-width: 1400px; font-size: 0.8125rem;">
    <h2 class="h5 mb-2">
        <i class="fas fa-file-medical-alt"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_TITLE'); ?>
    </h2>
    <p class="text-muted mb-2 small">
        <?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_INTRO'); ?>
    </p>
    <?php if ($dirs !== []) : ?>
        <p class="text-muted mb-2 small" style="font-size: 0.75rem;">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_PATHS'); ?>:</strong>
            <?php echo htmlspecialchars(implode(' · ', $dirs), ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if ($entries === []) : ?>
        <div class="alert alert-info py-2 mb-0">
            <?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_EMPTY'); ?>
        </div>
    <?php else : ?>
        <div class="table-responsive mb-2">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr class="text-muted" style="font-size: 0.75rem;">
                        <th scope="col" style="width:2rem;"></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_STAGE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_QUOT'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_PRE'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_USER'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_ERR'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_DETAIL'); ?></th>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_COL_FILE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $idx => $entry) : ?>
                        <?php
                        $pl = is_array($entry['payload'] ?? null) ? $entry['payload'] : null;
                        $rid = 'otwlog-' . (int) $idx;
                        $stage = $pl ? (string) ($pl['stage'] ?? '') : '';
                        $qid   = $pl ? (int) ($pl['quotation_id'] ?? 0) : 0;
                        $preId = $pl ? (int) ($pl['pre_cotizacion_id'] ?? 0) : 0;
                        $uid   = $pl ? (int) ($pl['user_id'] ?? 0) : 0;
                        $errc  = $pl ? (string) ($pl['error_code'] ?? '') : '';
                        $det   = $pl ? (string) ($pl['detail'] ?? '') : '';
                        if ($det === '' && $pl) {
                            $det = (string) ($pl['message'] ?? '');
                        }
                        $detShort = $det;
                        if (function_exists('mb_strlen') && mb_strlen($detShort, 'UTF-8') > 140) {
                            $detShort = mb_substr($detShort, 0, 137, 'UTF-8') . '…';
                        } elseif (\strlen($detShort) > 140) {
                            $detShort = substr($detShort, 0, 137) . '…';
                        }
                        $file = (string) ($entry['file'] ?? '');
                        $raw  = (string) ($entry['raw'] ?? '');
                        ?>
                        <tr>
                            <td class="py-1">
                                <button class="btn btn-sm btn-outline-secondary p-0 px-1"
                                        type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?php echo $rid; ?>"
                                        aria-expanded="false"
                                        title="<?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_RAW'); ?>">
                                    <i class="fas fa-code"></i>
                                </button>
                            </td>
                            <td class="py-1"><code class="small"><?php echo htmlspecialchars($stage, ENT_QUOTES, 'UTF-8'); ?></code></td>
                            <td class="py-1"><?php echo $qid > 0 ? (int) $qid : '—'; ?></td>
                            <td class="py-1"><?php echo $preId > 0 ? (int) $preId : '—'; ?></td>
                            <td class="py-1"><?php echo $uid >= 0 ? (int) $uid : '—'; ?></td>
                            <td class="py-1 small"><?php echo $errc !== '' ? htmlspecialchars($errc, ENT_QUOTES, 'UTF-8') : '—'; ?></td>
                            <td class="py-1 small"><?php echo htmlspecialchars($detShort, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-1 small text-muted"><?php echo htmlspecialchars($file, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <tr class="collapse" id="<?php echo $rid; ?>">
                            <td colspan="8" class="bg-light border-0 small py-2">
                                <div class="font-monospace text-break" style="white-space: pre-wrap; font-size: 0.7rem; max-height: 18rem; overflow: auto;">
                                    <?php echo htmlspecialchars($raw, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php if ($pl !== null) : ?>
                                    <div class="mt-2 small text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_OT_WIZARD_LOG_JSON'); ?></div>
                                    <pre class="mb-0 mt-1 small bg-white p-2 border rounded" style="max-height:14rem;overflow:auto;"><?php
                                        echo htmlspecialchars(json_encode($pl, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
