<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;

// Fallback for labels so we never show raw language keys
$l = function($key, $fallbackEn, $fallbackEs = null) {
    $t = Text::_($key);
    if ($t === $key || (is_string($t) && strpos($t, 'COM_ORDENPRODUCCION_') === 0)) {
        $lang = Factory::getApplication()->getLanguage()->getTag();
        return (strpos($lang, 'es') !== false && $fallbackEs !== null) ? $fallbackEs : $fallbackEn;
    }
    return $t;
};

$user = Factory::getUser();
$userGroups = $user->getAuthorisedGroups();
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select('id')
    ->from($db->quoteName('#__usergroups'))
    ->where($db->quoteName('title') . ' = ' . $db->quote('ventas'));
$db->setQuery($query);
$ventasGroupId = $db->loadResult();
$hasVentasAccess = $ventasGroupId && in_array($ventasGroupId, $userGroups);
?>

<div class="cotizaciones-container">
    <div class="cotizaciones-header">
        <h2>
            <i class="fas fa-file-invoice-dollar"></i>
            <?php echo $l('COM_ORDENPRODUCCION_QUOTATIONS_LIST_TITLE', 'Quotations List', 'Lista de cotizaciones'); ?>
        </h2>
        
        <?php if ($hasVentasAccess): ?>
        <div class="header-actions">
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion'); ?>" 
               class="btn-new-quotation">
                <i class="fas fa-plus"></i>
                <?php echo $l('COM_ORDENPRODUCCION_NEW_QUOTATION', 'New Quotation', 'Nueva cotización'); ?>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php
    $qf = $this->quotationFilters ?? [];
    $fName = isset($qf['client_name']) ? (string) $qf['client_name'] : '';
    $fNit  = isset($qf['client_nit']) ? (string) $qf['client_nit'] : '';
    $fFrom = isset($qf['date_from']) ? (string) $qf['date_from'] : '';
    $fTo   = isset($qf['date_to']) ? (string) $qf['date_to'] : '';
    $listLimit = isset($this->listLimit) ? (int) $this->listLimit : 20;
    $totalQuot = isset($this->totalQuotations) ? (int) $this->totalQuotations : 0;
    $hasFilters = trim($fName) !== '' || trim($fNit) !== '' || trim($fFrom) !== '' || trim($fTo) !== '';
    $filterFormAction = Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false);
    ?>
    <form method="get" action="<?php echo htmlspecialchars($filterFormAction); ?>" class="cotizaciones-filters" id="cotizaciones-filters-form">
        <input type="hidden" name="option" value="com_ordenproduccion">
        <input type="hidden" name="view" value="cotizaciones">
        <input type="hidden" name="limitstart" value="0">
        <div class="cotizaciones-filters-grid">
            <div class="cotizaciones-filter-field">
                <label for="filter_client_name" class="form-label small mb-1"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_FILTER_CLIENT_NAME', 'Client name', 'Nombre del cliente'); ?></label>
                <input type="text" class="form-control form-control-sm" name="filter_client_name" id="filter_client_name"
                       value="<?php echo htmlspecialchars($fName); ?>" autocomplete="organization">
            </div>
            <div class="cotizaciones-filter-field">
                <label for="filter_client_nit" class="form-label small mb-1"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_FILTER_NIT', 'NIT', 'NIT'); ?></label>
                <input type="text" class="form-control form-control-sm" name="filter_client_nit" id="filter_client_nit"
                       value="<?php echo htmlspecialchars($fNit); ?>" autocomplete="off">
            </div>
            <div class="cotizaciones-filter-field">
                <label for="filter_date_from" class="form-label small mb-1"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_FILTER_DATE_FROM', 'Quote date from', 'Fecha desde'); ?></label>
                <input type="date" class="form-control form-control-sm" name="filter_date_from" id="filter_date_from"
                       value="<?php echo htmlspecialchars($fFrom); ?>">
            </div>
            <div class="cotizaciones-filter-field">
                <label for="filter_date_to" class="form-label small mb-1"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_FILTER_DATE_TO', 'Quote date to', 'Fecha hasta'); ?></label>
                <input type="date" class="form-control form-control-sm" name="filter_date_to" id="filter_date_to"
                       value="<?php echo htmlspecialchars($fTo); ?>">
            </div>
            <div class="cotizaciones-filter-field cotizaciones-filter-field-limit">
                <label for="cotizaciones_limit" class="form-label small mb-1"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_PAGE_SIZE', 'Per page', 'Por página'); ?></label>
                <select class="form-select form-select-sm" name="limit" id="cotizaciones_limit">
                    <?php foreach ([10, 20, 50, 100] as $opt) : ?>
                    <option value="<?php echo (int) $opt; ?>"<?php echo $listLimit === $opt ? ' selected' : ''; ?>><?php echo (int) $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cotizaciones-filter-actions">
                <button type="submit" class="btn btn-primary btn-sm"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_FILTER_APPLY', 'Apply filters', 'Filtrar'); ?></button>
                <a href="<?php echo htmlspecialchars(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false)); ?>" class="btn btn-outline-secondary btn-sm"><?php echo $l('COM_ORDENPRODUCCION_COTIZACIONES_FILTER_RESET', 'Clear', 'Limpiar'); ?></a>
            </div>
        </div>
    </form>

    <?php if ($totalQuot < 1): ?>
        <div class="no-quotations">
            <i class="fas fa-inbox fa-3x"></i>
            <p><?php echo $hasFilters
                ? $l('COM_ORDENPRODUCCION_NO_QUOTATIONS_MATCH_FILTERS', 'No quotations match your filters.', 'No hay cotizaciones que coincidan con los filtros.')
                : $l('COM_ORDENPRODUCCION_NO_QUOTATIONS_FOUND', 'No quotations found.', 'No se encontraron cotizaciones.'); ?></p>
            <?php if ($hasVentasAccess && !$hasFilters): ?>
            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion'); ?>"
               class="btn-new-quotation">
                <i class="fas fa-plus"></i>
                <?php echo $l('COM_ORDENPRODUCCION_CREATE_FIRST_QUOTATION', 'Create First Quotation', 'Crear primera cotización'); ?>
            </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="quotations-table-wrapper">
            <table class="quotations-table">
                <thead>
                    <tr>
                        <th><?php echo $l('COM_ORDENPRODUCCION_QUOTATION_NUMBER', 'Quotation Number', 'Número de cotización'); ?></th>
                        <th><?php echo $l('COM_ORDENPRODUCCION_CLIENT_NAME', 'Client Name', 'Nombre del cliente'); ?></th>
                        <th class="col-cot-nit"><?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?></th>
                        <th class="col-cot-date"><?php echo $l('COM_ORDENPRODUCCION_QUOTE_DATE', 'Quotation Date', 'Fecha de cotización'); ?></th>
                        <th class="col-cot-amount"><?php echo $l('COM_ORDENPRODUCCION_TOTAL_AMOUNT', 'Total Amount', 'Monto total'); ?></th>
                        <th class="col-cot-status"><?php echo $l('COM_ORDENPRODUCCION_STATUS', 'Status', 'Estado'); ?></th>
                        <th class="col-cot-actions"><?php echo $l('COM_ORDENPRODUCCION_ACTIONS', 'Actions', 'Acciones'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->quotations as $quotation): ?>
                    <tr>
                        <td class="quotation-number">
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . (int) $quotation->id); ?>">
                                <strong><?php echo htmlspecialchars($quotation->quotation_number); ?></strong>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($quotation->client_name); ?></td>
                        <td class="col-cot-nit"><?php echo htmlspecialchars($quotation->client_nit); ?></td>
                        <td class="col-cot-date"><?php echo htmlspecialchars(CotizacionHelper::formatQuoteDateYmd($quotation->quote_date ?? '')); ?></td>
                        <td class="amount col-cot-amount">
                            <?php echo $quotation->currency . ' ' . number_format($quotation->total_amount, 2); ?>
                        </td>
                        <td class="col-cot-status">
                            <?php
                            $estadoInfo = CotizacionHelper::resolveQuotationListEstado($quotation);
                            ?>
                            <span class="status-badge <?php echo htmlspecialchars($estadoInfo['cssClass']); ?>">
                                <?php echo htmlspecialchars($l($estadoInfo['langKey'], $estadoInfo['fallbackEn'], $estadoInfo['fallbackEs'])); ?>
                            </span>
                        </td>
                        <td class="actions col-cot-actions">
                            <?php if ($hasVentasAccess): ?>
                            <form action="<?php echo Route::_('index.php?option=com_ordenproduccion&task=quotation.delete', false); ?>" method="post" class="d-inline" onsubmit="return confirm('<?php echo addslashes($l('COM_ORDENPRODUCCION_QUOTATION_DELETE_CONFIRM', 'Delete this quotation? This cannot be undone.', '¿Eliminar esta cotización? No se puede deshacer.')); ?>');">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <input type="hidden" name="id" value="<?php echo (int) $quotation->id; ?>">
                                <button type="submit" class="btn-action btn-delete" title="<?php echo $l('COM_ORDENPRODUCCION_DELETE', 'Delete', 'Eliminar'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($this->pagination && $this->pagination->pagesTotal > 1) : ?>
        <div class="cotizaciones-pagination mt-2 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="pagination"><?php echo $this->pagination->getPagesLinks(); ?></div>
            <p class="small text-muted mb-0"><?php echo $this->pagination->getPagesCounter(); ?></p>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
