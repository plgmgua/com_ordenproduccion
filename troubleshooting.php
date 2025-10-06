<!DOCTYPE html>
<html>
<head>
    <title>Work Order Detail Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 2px solid #5B9BD5; padding-bottom: 10px; }
        h2 { color: #5B9BD5; margin-top: 30px; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        pre { background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Work Order Detail Test</h1>
        <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <?php
        // Test work order ID 15
        $workOrderId = 15;
        
        try {
            // Include Joomla
            define('_JEXEC', 1);
            define('JPATH_BASE', '/var/www/grimpsa_webserver');
            require_once JPATH_BASE . '/libraries/vendor/autoload.php';
            
            echo "<h2>1. Joomla Framework Check</h2>";
            if (class_exists('Joomla\\CMS\\Factory')) {
                echo "<p class='success'>✅ <strong>Joomla Factory class found</strong></p>\n";
            } else {
                echo "<p class='error'>❌ <strong>Joomla Factory class not found</strong></p>\n";
                exit;
            }
            
            echo "<h2>2. Database Connection Test</h2>";
            $app = Joomla\CMS\Factory::getApplication('site');
            $db = Joomla\CMS\Factory::getDbo();
            echo "<p class='success'>✅ <strong>Database connection successful</strong></p>\n";
            
            echo "<h2>3. Work Order Existence Check</h2>";
            $query = $db->getQuery(true)
                ->select('id, state, orden_de_trabajo, nombre_del_cliente, agente_de_ventas')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int) $workOrderId);
            
            $db->setQuery($query);
            $workOrder = $db->loadObject();
            
            if ($workOrder) {
                echo "<p class='success'>✅ <strong>Work order found in database</strong></p>\n";
                echo "<p><strong>ID:</strong> " . $workOrder->id . "</p>\n";
                echo "<p><strong>State:</strong> " . $workOrder->state . "</p>\n";
                echo "<p><strong>Order Number:</strong> " . htmlspecialchars($workOrder->orden_de_trabajo) . "</p>\n";
                echo "<p><strong>Client:</strong> " . htmlspecialchars($workOrder->nombre_del_cliente) . "</p>\n";
                echo "<p><strong>Sales Agent:</strong> " . htmlspecialchars($workOrder->agente_de_ventas) . "</p>\n";
                
                if ($workOrder->state != 1) {
                    echo "<p class='warning'>⚠️ <strong>Work order exists but state is not 1 (published)</strong></p>\n";
                }
            } else {
                echo "<p class='error'>❌ <strong>Work order not found in database</strong></p>\n";
            }
            
            echo "<h2>4. User Access Check</h2>";
            $user = Joomla\CMS\Factory::getUser();
            echo "<p><strong>User ID:</strong> " . $user->id . "</p>\n";
            echo "<p><strong>User Name:</strong> " . htmlspecialchars($user->name) . "</p>\n";
            echo "<p><strong>User Groups:</strong> " . implode(', ', $user->getAuthorisedGroups()) . "</p>\n";
            echo "<p><strong>Is Guest:</strong> " . ($user->guest ? 'Yes' : 'No') . "</p>\n";
            
            // Check permissions
            $canView = $user->authorise('core.view', 'com_ordenproduccion');
            echo "<p><strong>Can view component:</strong> " . ($canView ? 'Yes' : 'No') . "</p>\n";
            
            if (!$canView) {
                echo "<p class='error'>❌ <strong>User does not have permission to view component</strong></p>\n";
            }
            
            echo "<h2>5. Component Model Test</h2>";
            try {
                // Try to get the model
                $model = $app->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()
                    ->createModel('Orden', 'Site');
                
                if ($model) {
                    echo "<p class='success'>✅ <strong>Orden model created successfully</strong></p>\n";
                    
                    // Try to get the item
                    $item = $model->getItem($workOrderId);
                    
                    if ($item) {
                        echo "<p class='success'>✅ <strong>Work order retrieved successfully</strong></p>\n";
                        echo "<p><strong>Order Number:</strong> " . htmlspecialchars($item->order_number ?? 'N/A') . "</p>\n";
                        echo "<p><strong>Client:</strong> " . htmlspecialchars($item->client_name ?? 'N/A') . "</p>\n";
                    } else {
                        echo "<p class='error'>❌ <strong>Failed to retrieve work order from model</strong></p>\n";
                    }
                } else {
                    echo "<p class='error'>❌ <strong>Failed to create Orden model</strong></p>\n";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ <strong>Model error:</strong> " . $e->getMessage() . "</p>\n";
                echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
            }
            
            echo "<h2>6. Component View Test</h2>";
            try {
                // Try to get the view
                $view = $app->bootComponent('com_ordenproduccion')
                    ->getMVCFactory()
                    ->createView('Orden', 'Site', 'html');
                
                if ($view) {
                    echo "<p class='success'>✅ <strong>Orden view created successfully</strong></p>\n";
                } else {
                    echo "<p class='error'>❌ <strong>Failed to create Orden view</strong></p>\n";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ <strong>View error:</strong> " . $e->getMessage() . "</p>\n";
                echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
            }
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ <strong>Error:</strong> " . $e->getMessage() . "</p>\n";
            echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
        }
        ?>

        <h2>Recommendations</h2>
        <ul>
            <li>If work order exists but state is not 1, check the state field in database</li>
            <li>If user doesn't have permission, check Joomla user groups and permissions</li>
            <li>If model fails, check for errors in OrdenModel getItem method</li>
            <li>If view fails, check for errors in Orden HtmlView class</li>
        </ul>
    </div>
</body>
</html>