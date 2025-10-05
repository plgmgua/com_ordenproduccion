<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Set headers for JSON output
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error', 'debug_info' => []];

try {
    // 1. Check Joomla environment
    if (!defined('_JEXEC')) {
        throw new Exception('Joomla _JEXEC constant NOT defined. Joomla environment not loaded.');
    }
    $app = Factory::getApplication();
    $response['debug_info']['joomla_environment'] = 'Joomla environment loaded. Application type: ' . $app->getName();

    // 2. Check if OrdenModel class exists
    $ordenModelClass = \Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel::class;
    if (!class_exists($ordenModelClass)) {
        throw new Exception('OrdenModel class not found: ' . $ordenModelClass);
    }
    $response['debug_info']['orden_model_class'] = 'OrdenModel class exists.';

    // 3. Check if Orden view class exists
    $ordenViewClass = \Grimpsa\Component\Ordenproduccion\Site\View\Orden\HtmlView::class;
    if (!class_exists($ordenViewClass)) {
        throw new Exception('Orden HtmlView class not found: ' . $ordenViewClass);
    }
    $response['debug_info']['orden_view_class'] = 'Orden HtmlView class exists.';

    // 4. Check database connection
    $db = Factory::getDbo();
    $response['debug_info']['database_connection'] = 'Database connection successful.';

    // 5. Check if orders table exists and has data
    $query = $db->getQuery(true)
        ->select('COUNT(*) as total')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('state') . ' = 1');
    
    $db->setQuery($query);
    $totalOrders = $db->loadResult();
    $response['debug_info']['total_orders'] = "Total active orders: $totalOrders";

    // 6. Check specific order ID 8
    $query = $db->getQuery(true)
        ->select('id, orden_de_trabajo, nombre_del_cliente, status')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = 8')
        ->where($db->quoteName('state') . ' = 1');
    
    $db->setQuery($query);
    $order8 = $db->loadObject();
    
    if ($order8) {
        $response['debug_info']['order_8_found'] = "Order ID 8 found: " . json_encode($order8);
    } else {
        $response['debug_info']['order_8_found'] = "Order ID 8 NOT found in database";
    }

    // 7. Check if order ID 8 exists but is inactive
    $query = $db->getQuery(true)
        ->select('id, orden_de_trabajo, nombre_del_cliente, status, state')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('id') . ' = 8');
    
    $db->setQuery($query);
    $order8Any = $db->loadObject();
    
    if ($order8Any) {
        $response['debug_info']['order_8_any_state'] = "Order ID 8 exists but state = " . $order8Any->state . ": " . json_encode($order8Any);
    } else {
        $response['debug_info']['order_8_any_state'] = "Order ID 8 does not exist at all in database";
    }

    // 8. List first 5 orders to see what IDs exist
    $query = $db->getQuery(true)
        ->select('id, orden_de_trabajo, nombre_del_cliente, status')
        ->from($db->quoteName('#__ordenproduccion_ordenes'))
        ->where($db->quoteName('state') . ' = 1')
        ->order('id ASC')
        ->setLimit(5);
    
    $db->setQuery($query);
    $firstOrders = $db->loadObjectList();
    $response['debug_info']['first_5_orders'] = "First 5 orders: " . json_encode($firstOrders);

    // 9. Check if OrdenController exists
    $ordenControllerClass = \Grimpsa\Component\Ordenproduccion\Site\Controller\OrdenController::class;
    if (!class_exists($ordenControllerClass)) {
        $response['debug_info']['orden_controller'] = 'OrdenController class NOT found: ' . $ordenControllerClass;
    } else {
        $response['debug_info']['orden_controller'] = 'OrdenController class exists.';
    }

    // 10. Test OrdenModel instantiation
    try {
        $model = new \Grimpsa\Component\Ordenproduccion\Site\Model\OrdenModel();
        $response['debug_info']['orden_model_instantiation'] = 'OrdenModel instantiated successfully.';
        
        // Test getting order ID 8
        $model->setState('orden.id', 8);
        $item = $model->getItem(8);
        
        if ($item) {
            $response['debug_info']['orden_model_get_item_8'] = 'OrdenModel getItem(8) returned data: ' . json_encode($item);
        } else {
            $response['debug_info']['orden_model_get_item_8'] = 'OrdenModel getItem(8) returned false/null';
        }
        
    } catch (Exception $e) {
        $response['debug_info']['orden_model_instantiation'] = 'OrdenModel instantiation failed: ' . $e->getMessage();
    }

    // 11. Check routing
    $input = $app->input;
    $response['debug_info']['current_request'] = [
        'option' => $input->get('option', 'none'),
        'view' => $input->get('view', 'none'),
        'id' => $input->get('id', 'none'),
        'task' => $input->get('task', 'none')
    ];

    $response['success'] = true;
    $response['message'] = 'Debug information collected successfully';

} catch (Exception $e) {
    $response['message'] = 'Error during debug: ' . $e->getMessage();
    $response['error_trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
