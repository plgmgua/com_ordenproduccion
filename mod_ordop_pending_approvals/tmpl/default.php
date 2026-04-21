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
/** @var bool $showFullLink */
/** @var int $pendingTotal */

$entityLabel = static function (string $entityType): string {
    $map = [
        'cotizacion_confirmation' => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_COTIZACION_CONFIRMATION',
        'orden_status'            => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_ORDEN_STATUS',
        'timesheet'               => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_TIMESHEET',
        'payment_proof'           => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_PAYMENT_PROOF',
        'solicitud_descuento'     => 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_SOLICITUD_DESCUENTO',
    ];
    $key = $map[$entityType] ?? 'COM_ORDENPRODUCCION_APPROVAL_ENTITY_GENERIC';

    return Text::_($key);
};

$adminAprobUrl = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=aprobaciones', false);

$modId = 'mod-ordop-pending-approvals-' . (int) $module->id;
?>
<div id="<?php echo $modId; ?>" class="mod-ordop-pending-approvals <?php echo htmlspecialchars($params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8'); ?>">
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
        <style>
        .mod-ordop-pending-approvals .mod-ordop-row-link:hover { background-color: rgba(13, 110, 253, 0.08); }
        </style>
        <div class="table-responsive">
            <table class="table table-sm table-striped table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col"><?php echo Text::_('MOD_ORDOP_PENDING_APPROVALS_COL_TYPE'); ?></th>
                        <th scope="col"><?php echo Text::_('MOD_ORDOP_PENDING_APPROVALS_COL_REQUESTER'); ?></th>
                        <th scope="col"><?php echo Text::_('MOD_ORDOP_PENDING_APPROVALS_COL_ID'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $etype = isset($row->entity_type) ? (string) $row->entity_type : '';
                        $isDiscount = $etype === 'solicitud_descuento';
                        $tipoLabel  = $isDiscount
                            ? Text::_('MOD_ORDOP_PENDING_APPROVALS_TYPE_DESCUENTO')
                            : $entityLabel($etype);
                        $eid = isset($row->entity_id) ? (int) $row->entity_id : 0;
                        if ($isDiscount) {
                            $idLabel = isset($row->precotizacion_number) && (string) $row->precotizacion_number !== ''
                                ? (string) $row->precotizacion_number
                                : (string) $eid;
                        } else {
                            $idLabel = (string) $eid;
                        }
                        $href = isset($row->record_link) && is_string($row->record_link) && $row->record_link !== ''
                            ? Route::_($row->record_link, false)
                            : '';
                        $reqName = isset($row->submitter_name) ? trim((string) $row->submitter_name) : '';
                        $reqUser = isset($row->submitter_username) ? trim((string) $row->submitter_username) : '';
                        if ($reqName !== '' && $reqUser !== '') {
                            $requesterLabel = $reqName . ' (' . $reqUser . ')';
                        } elseif ($reqName !== '') {
                            $requesterLabel = $reqName;
                        } elseif ($reqUser !== '') {
                            $requesterLabel = $reqUser;
                        } else {
                            $requesterLabel = '—';
                        }
                        ?>
                        <?php if ($href !== '') : ?>
                        <tr>
                            <td colspan="3" class="p-0">
                                <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="mod-ordop-row-link d-flex align-items-center w-100 px-2 py-2 text-decoration-none text-body gap-2">
                                    <span class="flex-shrink-0" style="min-width:4.5rem"><?php echo htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="flex-grow-1 small text-muted text-truncate" title="<?php echo htmlspecialchars($requesterLabel, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($requesterLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="text-end text-nowrap flex-shrink-0 border-start ps-2"><?php echo htmlspecialchars($idLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                                </a>
                            </td>
                        </tr>
                        <?php else : ?>
                        <tr>
                            <td><?php echo htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="small"><?php echo htmlspecialchars($requesterLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-end"><?php echo htmlspecialchars($idLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
