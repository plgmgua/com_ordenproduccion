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

// Get current work order ID from URL
$orderId = $app->input->getInt('id', 0);

// Check if we're on the correct component and view
$option = $app->input->get('option', '');
$view = $app->input->get('view', '');

// Only show module on ordenproduccion component pages
if ($option !== 'com_ordenproduccion') {
    return; // Don't display the module
}

// Get work order data for PDF generation
$workOrderData = null;
if ($orderId && $hasProductionAccess) {
    try {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int)$orderId)
            ->where($db->quoteName('state') . ' = 1');

        $db->setQuery($query);
        $workOrderData = $db->loadObject();
    } catch (Exception $e) {
        // Work order data not available
        $workOrderData = null;
    }
}

// Generate PDF action
if ($app->input->get('task') === 'generate_pdf' && $hasProductionAccess) {
    if (!Session::checkToken()) {
        $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
        $app->redirect(Uri::current());
        return;
    }
    
    if ($orderId && $workOrderData) {
        try {
            // Simple PDF generation using TCPDF or similar
            $pdfPath = $this->generateWorkOrderPDF($orderId, $workOrderData);
            
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

// Simple PDF generation function
function generateWorkOrderPDF($orderId, $workOrderData) {
    // Create PDF directory if it doesn't exist
    $pdfDir = JPATH_ROOT . '/media/com_ordenproduccion/pdf';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0755, true);
    }
    
    $pdfFile = $pdfDir . '/orden_trabajo_' . $orderId . '_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Simple HTML to PDF conversion (you can enhance this)
    $html = '<html><body>';
    $html .= '<h1>Orden de Trabajo ' . htmlspecialchars($workOrderData->numero_de_orden ?? 'N/A') . '</h1>';
    $html .= '<p><strong>Cliente:</strong> ' . htmlspecialchars($workOrderData->client_name ?? 'N/A') . '</p>';
    $html .= '<p><strong>Fecha de Solicitud:</strong> ' . htmlspecialchars($workOrderData->request_date ?? 'N/A') . '</p>';
    $html .= '<p><strong>Fecha de Entrega:</strong> ' . htmlspecialchars($workOrderData->delivery_date ?? 'N/A') . '</p>';
    $html .= '<p><strong>Estado:</strong> ' . htmlspecialchars($workOrderData->status ?? 'N/A') . '</p>';
    $html .= '<p><strong>Valor Factura:</strong> $' . number_format($workOrderData->invoice_value ?? 0, 2) . '</p>';
    $html .= '<p><strong>Descripción:</strong> ' . htmlspecialchars($workOrderData->description ?? 'N/A') . '</p>';
    $html .= '</body></html>';
    
    // For now, create a simple text file (you can enhance this with proper PDF generation)
    file_put_contents($pdfFile, $html);
    
    return $pdfFile;
}

// Load the template
require ModuleHelper::getLayoutPath('mod_acciones_produccion', $params->get('layout', 'default'));
