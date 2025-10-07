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

// Handle status change via AJAX
if ($app->input->get('task') === 'change_status' && $hasProductionAccess) {
    // Set proper headers for JSON response
    header('Content-Type: application/json');
    
    if (!Session::checkToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid token']);
        exit;
    }
    
    $orderId = $app->input->getInt('order_id', 0);
    $newStatus = $app->input->getString('new_status', '');
    
    if ($orderId > 0 && !empty($newStatus)) {
        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->set($db->quoteName('status') . ' = ' . $db->quote($newStatus))
                ->set($db->quoteName('modified') . ' = NOW()')
                ->set($db->quoteName('modified_by') . ' = ' . (int)$user->id)
                ->where($db->quoteName('id') . ' = ' . (int)$orderId);

            $db->setQuery($query);
            $result = $db->execute();
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
    }
    
    exit;
}

// Generate PDF action - redirect to component
if ($app->input->get('task') === 'generate_pdf' && $hasProductionAccess) {
    if (!Session::checkToken()) {
        $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
        $app->redirect(Uri::current());
        return;
    }
    
    // No need to handle PDF generation in module - let template handle it
}

// Get work order data for display
$workOrderData = null;
if ($orderId > 0) {
    try {
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int)$orderId)
            ->where($db->quoteName('state') . ' = 1');

        $db->setQuery($query);
        $workOrderData = $db->loadObject();
    } catch (Exception $e) {
        // Handle error silently
    }
}

// Get status options for dropdown
$statusOptions = [
    'en_progreso' => 'En Progreso',
    'terminada' => 'Terminada',
    'entregada' => 'Entregada'
];

// Load the template
require ModuleHelper::getLayoutPath('mod_acciones_produccion', $params->get('layout', 'default'));
