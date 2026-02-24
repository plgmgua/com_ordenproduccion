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

    <?php if (empty($this->quotations)): ?>
        <div class="no-quotations">
            <i class="fas fa-inbox fa-3x"></i>
            <p><?php echo $l('COM_ORDENPRODUCCION_NO_QUOTATIONS_FOUND', 'No quotations found.', 'No se encontraron cotizaciones.'); ?></p>
            <?php if ($hasVentasAccess): ?>
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
                        <th><?php echo $l('COM_ORDENPRODUCCION_NIT', 'Tax ID (NIT)', 'NIT'); ?></th>
                        <th><?php echo $l('COM_ORDENPRODUCCION_QUOTE_DATE', 'Quotation Date', 'Fecha de cotización'); ?></th>
                        <th><?php echo $l('COM_ORDENPRODUCCION_TOTAL_AMOUNT', 'Total Amount', 'Monto total'); ?></th>
                        <th><?php echo $l('COM_ORDENPRODUCCION_STATUS', 'Status', 'Estado'); ?></th>
                        <th><?php echo $l('COM_ORDENPRODUCCION_ACTIONS', 'Actions', 'Acciones'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->quotations as $quotation): ?>
                    <tr>
                        <td class="quotation-number">
                            <strong><?php echo htmlspecialchars($quotation->quotation_number); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($quotation->client_name); ?></td>
                        <td><?php echo htmlspecialchars($quotation->client_nit); ?></td>
                        <td><?php echo HTMLHelper::_('date', $quotation->quote_date, 'Y-m-d'); ?></td>
                        <td class="amount">
                            <?php echo $quotation->currency . ' ' . number_format($quotation->total_amount, 2); ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower($quotation->status); ?>">
                                <?php echo htmlspecialchars($quotation->status); ?>
                            </span>
                        </td>
                        <td class="actions">
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotation->id); ?>" 
                               class="btn-action btn-view" 
                               title="<?php echo $l('COM_ORDENPRODUCCION_VIEW', 'View', 'Ver'); ?>">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($hasVentasAccess): ?>
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotation->id . '&layout=edit'); ?>" 
                               class="btn-action btn-edit" 
                               title="<?php echo $l('COM_ORDENPRODUCCION_EDIT', 'Edit', 'Editar'); ?>">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&task=quotation.delete&id=' . (int) $quotation->id . '&' . Session::getFormToken() . '=1'); ?>" 
                               class="btn-action btn-delete" 
                               title="<?php echo $l('COM_ORDENPRODUCCION_DELETE', 'Delete', 'Eliminar'); ?>"
                               onclick="return confirm('<?php echo addslashes($l('COM_ORDENPRODUCCION_QUOTATION_DELETE_CONFIRM', 'Delete this quotation? This cannot be undone.', '¿Eliminar esta cotización? No se puede deshacer.')); ?>');">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
