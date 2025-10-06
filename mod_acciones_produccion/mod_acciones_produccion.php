<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_acciones_produccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

// Get the application
$app = Factory::getApplication();
$user = Factory::getUser();

// Check if user is in produccion group
$userGroups = $user->getAuthorisedGroups();
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select('id')
    ->from($db->quoteName('#__usergroups'))
    ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

$db->setQuery($query);
$produccionGroupId = $db->loadResult();

$hasProductionAccess = false;
if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
    $hasProductionAccess = true;
}

// Get module parameters
$orderId = $params->get('order_id', '');
$showStatistics = $params->get('show_statistics', 1);
$showPdfButton = $params->get('show_pdf_button', 1);
$showExcelButton = $params->get('show_excel_button', 1);

// Get current order if not specified
if (empty($orderId)) {
    // Try to get from URL parameter
    $orderId = $app->input->getInt('id', 0);
}

// Get production statistics if enabled
$statistics = null;
if ($showStatistics && $hasProductionAccess) {
    try {
        $query = $db->getQuery(true)
            ->select([
                'COUNT(*) as total_orders',
                'SUM(invoice_value) as total_value',
                'COUNT(CASE WHEN status = "Terminada" THEN 1 END) as completed_orders',
                'COUNT(CASE WHEN status = "En Proceso" THEN 1 END) as in_progress_orders'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('request_date') . ' >= ' . $db->quote(date('Y-m-01')));

        $db->setQuery($query);
        $statistics = $db->loadObject();
    } catch (Exception $e) {
        // Statistics not available
        $statistics = null;
    }
}

// Generate PDF action
if ($app->input->get('task') === 'generate_pdf' && $hasProductionAccess) {
    if (!Session::checkToken()) {
        $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
        $app->redirect(Uri::current());
        return;
    }
    
    $orderId = $app->input->getInt('order_id', 0);
    
    if ($orderId) {
        try {
            // Include the production helper
            require_once JPATH_ROOT . '/components/com_ordenproduccion/site/src/Helper/ProductionActionsHelper.php';
            
            $helper = new \Grimpsa\Component\Ordenproduccion\Site\Helper\ProductionActionsHelper();
            $pdfPath = $helper->generateWorkOrderPDF($orderId);
            
            if ($pdfPath) {
                $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_PDF_GENERATED'), 'success');
                // Redirect to PDF file
                $app->redirect($pdfPath);
            } else {
                $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_PDF_ERROR'), 'error');
            }
        } catch (Exception $e) {
            $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_PDF_ERROR') . ': ' . $e->getMessage(), 'error');
        }
    } else {
        $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_INVALID_ORDER'), 'error');
    }
    
    $app->redirect(Uri::current());
    return;
}

// Export Excel action
if ($app->input->get('task') === 'export_excel' && $hasProductionAccess) {
    if (!Session::checkToken()) {
        $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
        $app->redirect(Uri::current());
        return;
    }
    
    try {
        // Include the production helper
        require_once JPATH_ROOT . '/components/com_ordenproduccion/site/src/Helper/ProductionActionsHelper.php';
        
        $helper = new \Grimpsa\Component\Ordenproduccion\Site\Helper\ProductionActionsHelper();
        $excelPath = $helper->exportOrdersToExcel();
        
        if ($excelPath) {
            $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_EXCEL_EXPORTED'), 'success');
            // Redirect to Excel file
            $app->redirect($excelPath);
        } else {
            $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_EXCEL_ERROR'), 'error');
        }
    } catch (Exception $e) {
        $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_EXCEL_ERROR') . ': ' . $e->getMessage(), 'error');
    }
    
    $app->redirect(Uri::current());
    return;
}

// Load the template
require ModuleHelper::getLayoutPath('mod_acciones_produccion', $params->get('layout', 'default'));
