<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_ordop_pending_approvals
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var array $rows */
/** @var bool $schemaOk */
/** @var bool $showHeading */
/** @var bool $showIntro */
/** @var bool $showFullLink */
/** @var int $pendingTotal */

$entityLabel = static function (string $entityType): string {
    $map = [
        'cotizacion_confirmation' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_CONFIRMATION',
        'orden_status'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_STATUS',
        'timesheet'               => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_TIMESHEET',
        'payment_proof'           => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_PAYMENT_PROOF',
    ];
    $key = $map[$entityType] ?? 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_GENERIC';

    return Text::_($key);
};

$adminAprobUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones', false);

$modId = 'mod-ordop-pending-approvals-' . (int) $module->id;
?>
<div id="<?php echo $modId; ?>" class="mod-ordop-pending-approvals <?php echo htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($showHeading) : ?>
    <h3 class="h5 mb-2">
        <span class="icon-check-square" aria-hidden="true"></span>
        <?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_TAB_HEADING'); ?>
    </h3>
    <?php endif; ?>

    <?php if ($showIntro) : ?>
    <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_TAB_INTRO'); ?></p>
    <?php endif; ?>

    <?php if ($showFullLink) : ?>
    <p class="mb-2">
        <a href="<?php echo htmlspecialchars($adminAprobUrl, ENT_QUOTES, 'UTF-8'); ?>" class="small">
            <?php echo Text::_('MOD_ORDOP_PENDING_APPROVALS_LINK_FULL'); ?>
        </a>
    </p>
    <?php endif; ?>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_SCHEMA_MISSING'); ?></div>
    <?php elseif ($rows === []) : ?>
        <p class="mb-2"><strong><?php echo Text::sprintf('MOD_ORDOP_PENDING_APPROVALS_TOTAL', 0); ?></strong></p>
        <div class="alert alert-info mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_EMPTY'); ?></div>
    <?php else : ?>
        <p class="mb-2"><strong><?php echo Text::sprintf('MOD_ORDOP_PENDING_APPROVALS_TOTAL', (int) $pendingTotal); ?></strong></p>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><?php echo Text::_('COM_ORDENPRODUCCION_APPROVAL_COL_TYPE'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $etype = isset($row->entity_type) ? (string) $row->entity_type : '';
                        $label = $entityLabel($etype);
                        $href  = isset($row->record_link) && is_string($row->record_link) && $row->record_link !== ''
                            ? Route::_($row->record_link, false)
                            : '';
                        ?>
                        <tr>
                            <td>
                                <?php if ($href !== '') : ?>
                                    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php else : ?>
                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
