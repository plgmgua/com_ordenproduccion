<?php
/**
 * Joomla Component Troubleshooting Script
 * Validates file structure and deployment status
 */

// Prevent direct access
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

// Load Joomla framework
require_once JPATH_ROOT . '/configuration.php';
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$app = Factory::getApplication('site');
$user = Factory::getUser();

// Set error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Joomla Component Troubleshooting</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: #007cba; color: white; padding: 20px; margin: -20px -20px 20px -20px; border-radius: 8px 8px 0 0; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background: #d4edda; border-color: #c3e6cb; color: #155724; }
        .error { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        .warning { background: #fff3cd; border-color: #ffeaa7; color: #856404; }
        .info { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
        .file-list { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; }
        .status { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .status-ok { background: #28a745; color: white; }
        .status-error { background: #dc3545; color: white; }
        .status-warning { background: #ffc107; color: black; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f8f9fa; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 12px; overflow-x: auto; }
        .btn { display: inline-block; padding: 8px 16px; background: #007cba; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #005a8b; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Joomla Component Troubleshooting</h1>
            <p>Validación de estructura de archivos y estado de despliegue</p>
        </div>

        <?php
        // Component configuration
        $componentName = 'com_ordenproduccion';
        $joomlaRoot = JPATH_ROOT;
        $adminPath = $joomlaRoot . '/administrator/components/' . $componentName;
        $sitePath = $joomlaRoot . '/components/' . $componentName;
        $mediaPath = $joomlaRoot . '/media/' . $componentName;
        $modulePath = $joomlaRoot . '/modules/mod_acciones_produccion';

        echo '<div class="section info">';
        echo '<h3>📋 Información del Sistema</h3>';
        echo '<table>';
        echo '<tr><td><strong>Joomla Root:</strong></td><td>' . $joomlaRoot . '</td></tr>';
        echo '<tr><td><strong>Component Name:</strong></td><td>' . $componentName . '</td></tr>';
        echo '<tr><td><strong>Current User:</strong></td><td>' . ($user->guest ? 'Guest' : $user->name) . '</td></tr>';
        echo '<tr><td><strong>PHP Version:</strong></td><td>' . PHP_VERSION . '</td></tr>';
        echo '<tr><td><strong>Joomla Version:</strong></td><td>' . JVERSION . '</td></tr>';
        echo '</table>';
        echo '</div>';

        // Check directory structure
        echo '<div class="section">';
        echo '<h3>📁 Estructura de Directorios</h3>';
        
        $directories = [
            'Admin Component' => $adminPath,
            'Site Component' => $sitePath,
            'Media' => $mediaPath,
            'Module' => $modulePath
        ];

        echo '<table>';
        echo '<tr><th>Directorio</th><th>Ruta</th><th>Estado</th><th>Tamaño</th></tr>';
        
        foreach ($directories as $name => $path) {
            $exists = is_dir($path);
            $status = $exists ? '<span class="status status-ok">EXISTS</span>' : '<span class="status status-error">MISSING</span>';
            $size = $exists ? formatBytes(getDirSize($path)) : 'N/A';
            
            echo '<tr>';
            echo '<td><strong>' . $name . '</strong></td>';
            echo '<td><code>' . $path . '</code></td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $size . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';

        // Check critical files
        echo '<div class="section">';
        echo '<h3>📄 Archivos Críticos</h3>';
        
        $criticalFiles = [
            // Admin files
            'Admin Manifest' => $adminPath . '/com_ordenproduccion.xml',
            'Admin Services' => $adminPath . '/services/provider.php',
            
            // Site files
            'Site Entry Point' => $sitePath . '/ordenproduccion.php',
            'Site Manifest' => $sitePath . '/com_ordenproduccion.xml',
            'Site Services' => $sitePath . '/services/provider.php',
            
            // Templates
            'Admin Templates' => $adminPath . '/tmpl',
            'Site Templates' => $sitePath . '/tmpl',
            'Administracion Template' => $sitePath . '/tmpl/administracion',
            'Default Tabs Template' => $sitePath . '/tmpl/administracion/default_tabs.php',
            'Workorders Template' => $sitePath . '/tmpl/administracion/default_workorders.php',
            
            // Models and Views
            'Administracion View' => $sitePath . '/src/View/Administracion/HtmlView.php',
            'Administracion Model' => $sitePath . '/src/Model/AdministracionModel.php',
            'Ordenes Model' => $sitePath . '/src/Model/OrdenesModel.php',
            
            // Module files
            'Module Entry' => $modulePath . '/mod_acciones_produccion.php',
            'Module Manifest' => $modulePath . '/mod_acciones_produccion.xml',
            'Module Template' => $modulePath . '/tmpl/default.php'
        ];

        echo '<table>';
        echo '<tr><th>Archivo/Directorio</th><th>Ruta</th><th>Estado</th><th>Tamaño</th><th>Última Modificación</th></tr>';
        
        foreach ($criticalFiles as $name => $path) {
            $exists = file_exists($path) || is_dir($path);
            $status = $exists ? '<span class="status status-ok">EXISTS</span>' : '<span class="status status-error">MISSING</span>';
            
            if ($exists) {
                $size = is_dir($path) ? formatBytes(getDirSize($path)) : formatBytes(filesize($path));
                $modified = date('Y-m-d H:i:s', filemtime($path));
            } else {
                $size = 'N/A';
                $modified = 'N/A';
            }
            
            echo '<tr>';
            echo '<td><strong>' . $name . '</strong></td>';
            echo '<td><code>' . $path . '</code></td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $size . '</td>';
            echo '<td>' . $modified . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';

        // Check template content
        echo '<div class="section">';
        echo '<h3>🔍 Análisis de Contenido de Templates</h3>';
        
        $defaultTabsPath = $sitePath . '/tmpl/administracion/default_tabs.php';
        if (file_exists($defaultTabsPath)) {
            $content = file_get_contents($defaultTabsPath);
            
            echo '<h4>📋 default_tabs.php Analysis</h4>';
            echo '<div class="code">';
            echo '<strong>File size:</strong> ' . formatBytes(strlen($content)) . '<br>';
            echo '<strong>Contains "workorders":</strong> ' . (strpos($content, 'workorders') !== false ? '✅ YES' : '❌ NO') . '<br>';
            echo '<strong>Contains "default_workorders.php":</strong> ' . (strpos($content, 'default_workorders.php') !== false ? '✅ YES' : '❌ NO') . '<br>';
            echo '<strong>Contains "Direct include":</strong> ' . (strpos($content, 'Direct include') !== false ? '✅ YES' : '❌ NO') . '<br>';
            echo '<strong>Contains "bypass loadTemplate":</strong> ' . (strpos($content, 'bypass loadTemplate') !== false ? '✅ YES' : '❌ NO') . '<br>';
            
            // Show the workorders section
            if (preg_match('/workorders.*?\{.*?\}/s', $content, $matches)) {
                echo '<br><strong>Workorders section found:</strong><br>';
                echo '<pre>' . htmlspecialchars($matches[0]) . '</pre>';
            }
            echo '</div>';
        } else {
            echo '<div class="error">❌ default_tabs.php not found</div>';
        }

        $workordersPath = $sitePath . '/tmpl/administracion/default_workorders.php';
        if (file_exists($workordersPath)) {
            $content = file_get_contents($workordersPath);
            
            echo '<h4>📋 default_workorders.php Analysis</h4>';
            echo '<div class="code">';
            echo '<strong>File size:</strong> ' . formatBytes(strlen($content)) . '<br>';
            echo '<strong>Contains "workorders-table":</strong> ' . (strpos($content, 'workorders-table') !== false ? '✅ YES' : '❌ NO') . '<br>';
            echo '<strong>Contains "foreach.*workOrders":</strong> ' . (preg_match('/foreach.*workOrders/', $content) ? '✅ YES' : '❌ NO') . '<br>';
            echo '<strong>Contains "Assign Invoice Modal":</strong> ' . (strpos($content, 'assign-invoice-modal') !== false ? '✅ YES' : '❌ NO') . '<br>';
            echo '<strong>Contains "PDF extraction":</strong> ' . (strpos($content, 'extractPDFData') !== false ? '✅ YES' : '❌ NO') . '<br>';
            echo '</div>';
        } else {
            echo '<div class="error">❌ default_workorders.php not found</div>';
        }
        echo '</div>';

        // Database check
        echo '<div class="section">';
        echo '<h3>🗄️ Verificación de Base de Datos</h3>';
        
        try {
            $db = Factory::getDbo();
            
            // Check if component tables exist
            $tables = [
                'joomla_ordenproduccion_ordenes' => 'Main orders table',
                'joomla_ordenproduccion_webhook_logs' => 'Webhook logs table',
                'joomla_ordenproduccion_settings' => 'Settings table',
                'joomla_ordenproduccion_invoices' => 'Invoices table'
            ];
            
            echo '<table>';
            echo '<tr><th>Tabla</th><th>Descripción</th><th>Estado</th><th>Registros</th></tr>';
            
            foreach ($tables as $table => $description) {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName($table));
                
                try {
                    $db->setQuery($query);
                    $count = $db->loadResult();
                    $status = '<span class="status status-ok">EXISTS</span>';
                    $records = number_format($count);
                } catch (Exception $e) {
                    $status = '<span class="status status-error">MISSING</span>';
                    $records = 'N/A';
                }
                
                echo '<tr>';
                echo '<td><code>' . $table . '</code></td>';
                echo '<td>' . $description . '</td>';
                echo '<td>' . $status . '</td>';
                echo '<td>' . $records . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
        // Check recent work orders
        try {
            $query = $db->getQuery(true)
                ->select(['id', 'orden_de_trabajo', 'client_name', 'created', 'quotation_files'])
                ->from($db->quoteName('joomla_ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('created') . ' DESC')
                ->setLimit(5);
            
            $db->setQuery($query);
            $recentOrders = $db->loadObjectList();
            
            echo '<h4>📋 Últimas 5 Órdenes de Trabajo</h4>';
            if ($recentOrders) {
                echo '<table>';
                echo '<tr><th>ID</th><th>Orden</th><th>Cliente</th><th>Creado</th><th>Cotización</th></tr>';
                foreach ($recentOrders as $order) {
                    echo '<tr>';
                    echo '<td>' . $order->id . '</td>';
                    echo '<td>' . htmlspecialchars($order->orden_de_trabajo) . '</td>';
                    echo '<td>' . htmlspecialchars($order->client_name) . '</td>';
                    echo '<td>' . $order->created . '</td>';
                    echo '<td>' . htmlspecialchars($order->quotation_files ?: 'NULL') . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<div class="warning">⚠️ No hay órdenes de trabajo en la base de datos</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">❌ Error al consultar órdenes: ' . $e->getMessage() . '</div>';
        }
        
        // Specific check for order 5543 (ORD-005543)
        try {
            echo '<h4>🔍 Análisis Específico - Orden ORD-005543</h4>';
            
            $query = $db->getQuery(true)
                ->select(['id', 'orden_de_trabajo', 'client_name', 'quotation_files', 'created', 'modified'])
                ->from($db->quoteName('joomla_ordenproduccion_ordenes'))
                ->where($db->quoteName('orden_de_trabajo') . ' = ' . $db->quote('ORD-005543'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $order5543 = $db->loadObject();
            
            if ($order5543) {
                echo '<div class="info">';
                echo '<h5>✅ Orden ORD-005543 Encontrada</h5>';
                echo '<table>';
                echo '<tr><th>Campo</th><th>Valor</th></tr>';
                echo '<tr><td><strong>ID:</strong></td><td>' . $order5543->id . '</td></tr>';
                echo '<tr><td><strong>Orden de Trabajo:</strong></td><td>' . htmlspecialchars($order5543->orden_de_trabajo) . '</td></tr>';
                echo '<tr><td><strong>Cliente:</strong></td><td>' . htmlspecialchars($order5543->client_name) . '</td></tr>';
                echo '<tr><td><strong>Creado:</strong></td><td>' . $order5543->created . '</td></tr>';
                echo '<tr><td><strong>Modificado:</strong></td><td>' . $order5543->modified . '</td></tr>';
                echo '<tr><td><strong>quotation_files (RAW):</strong></td><td><code>' . htmlspecialchars($order5543->quotation_files ?: 'NULL') . '</code></td></tr>';
                echo '</table>';
                
                // Analyze quotation_files field
                if (!empty($order5543->quotation_files)) {
                    echo '<h5>🔍 Análisis de quotation_files:</h5>';
                    echo '<div class="code">';
                    echo '<strong>Valor original:</strong> ' . htmlspecialchars($order5543->quotation_files) . '<br>';
                    echo '<strong>Longitud:</strong> ' . strlen($order5543->quotation_files) . ' caracteres<br>';
                    echo '<strong>Es JSON válido:</strong> ' . (json_decode($order5543->quotation_files) !== null ? '✅ SÍ' : '❌ NO') . '<br>';
                    
                    // Try to decode JSON
                    $decoded = json_decode($order5543->quotation_files, true);
                    if ($decoded !== null) {
                        echo '<strong>JSON decodificado:</strong><br>';
                        echo '<pre>' . htmlspecialchars(json_encode($decoded, JSON_PRETTY_PRINT)) . '</pre>';
                        
                        if (is_array($decoded) && !empty($decoded[0])) {
                            $filePath = $decoded[0];
                            $cleanPath = str_replace('\\/', '/', $filePath);
                            $fullUrl = Factory::getApplication()->get('live_site') . $cleanPath;
                            
                            echo '<strong>Primer archivo:</strong> ' . htmlspecialchars($filePath) . '<br>';
                            echo '<strong>Ruta limpia:</strong> ' . htmlspecialchars($cleanPath) . '<br>';
                            echo '<strong>Live Site:</strong> ' . htmlspecialchars(Factory::getApplication()->get('live_site')) . '<br>';
                            echo '<strong>URL completa:</strong> <a href="' . htmlspecialchars($fullUrl) . '" target="_blank">' . htmlspecialchars($fullUrl) . '</a><br>';
                            
                            // Check if file exists
                            $localPath = JPATH_ROOT . $cleanPath;
                            echo '<strong>Ruta local:</strong> ' . htmlspecialchars($localPath) . '<br>';
                            echo '<strong>Archivo existe:</strong> ' . (file_exists($localPath) ? '✅ SÍ' : '❌ NO') . '<br>';
                            
                            if (file_exists($localPath)) {
                                echo '<strong>Tamaño del archivo:</strong> ' . formatBytes(filesize($localPath)) . '<br>';
                                echo '<strong>Última modificación:</strong> ' . date('Y-m-d H:i:s', filemtime($localPath)) . '<br>';
                            }
                        }
                    } else {
                        echo '<strong>Error JSON:</strong> ' . json_last_error_msg() . '<br>';
                    }
                    echo '</div>';
                } else {
                    echo '<div class="warning">⚠️ El campo quotation_files está vacío o es NULL</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="error">❌ Orden ORD-005543 no encontrada en la base de datos</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">❌ Error al analizar orden 5543: ' . $e->getMessage() . '</div>';
        }
        
        // Check quotation files statistics
        try {
            echo '<h4>📊 Estadísticas de Archivos de Cotización</h4>';
            
            $query = $db->getQuery(true)
                ->select('COUNT(*) as total')
                ->from($db->quoteName('joomla_ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1');
            
            $db->setQuery($query);
            $totalOrders = $db->loadResult();
            
            $query = $db->getQuery(true)
                ->select('COUNT(*) as with_quotation')
                ->from($db->quoteName('joomla_ordenproduccion_ordenes'))
                ->where($db->quoteName('state') . ' = 1')
                ->where($db->quoteName('quotation_files') . ' IS NOT NULL')
                ->where($db->quoteName('quotation_files') . ' != ' . $db->quote(''))
                ->where($db->quoteName('quotation_files') . ' != ' . $db->quote('[]'));
            
            $db->setQuery($query);
            $ordersWithQuotation = $db->loadResult();
            
            echo '<table>';
            echo '<tr><th>Métrica</th><th>Valor</th><th>Porcentaje</th></tr>';
            echo '<tr><td>Total de órdenes</td><td>' . number_format($totalOrders) . '</td><td>100%</td></tr>';
            echo '<tr><td>Con archivos de cotización</td><td>' . number_format($ordersWithQuotation) . '</td><td>' . number_format(($ordersWithQuotation / $totalOrders) * 100, 1) . '%</td></tr>';
            echo '<tr><td>Sin archivos de cotización</td><td>' . number_format($totalOrders - $ordersWithQuotation) . '</td><td>' . number_format((($totalOrders - $ordersWithQuotation) / $totalOrders) * 100, 1) . '%</td></tr>';
            echo '</table>';
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Error al obtener estadísticas: ' . $e->getMessage() . '</div>';
        }
            
        } catch (Exception $e) {
            echo '<div class="error">❌ Error de conexión a la base de datos: ' . $e->getMessage() . '</div>';
        }
        echo '</div>';

        // AJAX Endpoint Check
        echo '<div class="section">';
        echo '<h3>🔗 Verificación de Endpoints AJAX</h3>';
        
        $ajaxFiles = [
            'change_status.php' => $sitePath . '/change_status.php',
            'get_duplicate_settings.php' => $sitePath . '/get_duplicate_settings.php',
            'upload_cotizacion.php' => $sitePath . '/upload_cotizacion.php'
        ];
        
        echo '<table>';
        echo '<tr><th>Endpoint</th><th>Archivo</th><th>Estado</th><th>Tamaño</th></tr>';
        
        foreach ($ajaxFiles as $name => $path) {
            $exists = file_exists($path);
            $status = $exists ? '<span class="status status-ok">EXISTS</span>' : '<span class="status status-error">MISSING</span>';
            $size = $exists ? formatBytes(filesize($path)) : 'N/A';
            
            echo '<tr>';
            echo '<td><strong>' . $name . '</strong></td>';
            echo '<td><code>' . $path . '</code></td>';
            echo '<td>' . $status . '</td>';
            echo '<td>' . $size . '</td>';
            echo '</tr>';
        }
        echo '</table>';
        echo '</div>';

        // File permissions check
        echo '<div class="section">';
        echo '<h3>🔐 Verificación de Permisos</h3>';
        
        $permissionPaths = [
            'Site Component' => $sitePath,
            'Admin Component' => $adminPath,
            'Media' => $mediaPath,
            'Module' => $modulePath
        ];
        
        echo '<table>';
        echo '<tr><th>Directorio</th><th>Permisos</th><th>Propietario</th><th>Grupo</th></tr>';
        
        foreach ($permissionPaths as $name => $path) {
            if (is_dir($path)) {
                $perms = fileperms($path);
                $owner = posix_getpwuid(fileowner($path));
                $group = posix_getgrgid(filegroup($path));
                
                echo '<tr>';
                echo '<td><strong>' . $name . '</strong></td>';
                echo '<td><code>' . substr(sprintf('%o', $perms), -4) . '</code></td>';
                echo '<td>' . $owner['name'] . '</td>';
                echo '<td>' . $group['name'] . '</td>';
                echo '</tr>';
            } else {
                echo '<tr>';
                echo '<td><strong>' . $name . '</strong></td>';
                echo '<td colspan="3"><span class="status status-error">DIRECTORY NOT FOUND</span></td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        echo '</div>';

        // Recommendations
        echo '<div class="section warning">';
        echo '<h3>💡 Recomendaciones</h3>';
        echo '<ul>';
        echo '<li>Si faltan archivos, ejecuta <code>update_build_simple.sh</code> para redeployar</li>';
        echo '<li>Si hay problemas de permisos, ejecuta: <code>sudo chown -R www-data:www-data /var/www/grimpsa_webserver/components/com_ordenproduccion</code></li>';
        echo '<li>Si los templates no cargan, verifica que existan en <code>/components/com_ordenproduccion/tmpl/administracion/</code></li>';
        echo '<li>Para limpiar caché: <code>sudo rm -rf /var/www/grimpsa_webserver/cache/*</code></li>';
        echo '</ul>';
        echo '</div>';

        // Helper functions
        function formatBytes($bytes, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }
            return round($bytes, $precision) . ' ' . $units[$i];
        }

        function getDirSize($dir) {
            $size = 0;
            if (is_dir($dir)) {
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                }
            }
            return $size;
        }
        ?>

        // Quotation View Analysis
        echo '<div class="section">';
        echo '<h3>🔍 Análisis de Vista de Cotización (Quotation View)</h3>';
        echo '<p>Diagnóstico específico para el error 404 en la vista de cotización.</p>';
        
        // Check quotation view files
        $quotationFiles = [
            'QuotationController.php' => $SITE_COMPONENT_PATH . '/src/Controller/QuotationController.php',
            'Quotation/HtmlView.php' => $SITE_COMPONENT_PATH . '/src/View/Quotation/HtmlView.php',
            'quotation/display.php' => $SITE_COMPONENT_PATH . '/tmpl/quotation/display.php'
        ];
        
        echo '<h4>📄 Archivos de Vista de Cotización</h4>';
        echo '<table>';
        echo '<tr><th>Archivo</th><th>Ruta</th><th>Estado</th><th>Tamaño</th><th>Permisos</th><th>Propietario</th></tr>';
        
        foreach ($quotationFiles as $name => $path) {
            if (file_exists($path)) {
                $size = filesize($path);
                $perms = fileperms($path);
                $owner = posix_getpwuid(fileowner($path));
                $group = posix_getgrgid(filegroup($path));
                
                echo '<tr>';
                echo '<td><strong>' . $name . '</strong></td>';
                echo '<td><code>' . $path . '</code></td>';
                echo '<td><span class="status status-ok">EXISTS</span></td>';
                echo '<td>' . formatBytes($size) . '</td>';
                echo '<td><code>' . substr(sprintf('%o', $perms), -4) . '</code></td>';
                echo '<td>' . $owner['name'] . ':' . $group['name'] . '</td>';
                echo '</tr>';
            } else {
                echo '<tr>';
                echo '<td><strong>' . $name . '</strong></td>';
                echo '<td><code>' . $path . '</code></td>';
                echo '<td><span class="status status-error">MISSING</span></td>';
                echo '<td colspan="3">Archivo no encontrado</td>';
                echo '</tr>';
            }
        }
        echo '</table>';
        
        // Test quotation view loading
        echo '<h4>🧪 Prueba de Carga de Vista de Cotización</h4>';
        echo '<div class="file-list">';
        
        try {
            // Test if the quotation view can be instantiated
            $testPath = $SITE_COMPONENT_PATH . '/src/View/Quotation/HtmlView.php';
            if (file_exists($testPath)) {
                echo "✅ Quotation HtmlView.php existe\n";
                
                // Check if the class is properly defined
                $content = file_get_contents($testPath);
                if (strpos($content, 'class OrdenproduccionViewQuotationHtml') !== false) {
                    echo "✅ Clase OrdenproduccionViewQuotationHtml definida correctamente\n";
                } else {
                    echo "❌ Clase OrdenproduccionViewQuotationHtml NO encontrada en el archivo\n";
                    echo "Contenido del archivo (primeras 500 chars):\n";
                    echo htmlspecialchars(substr($content, 0, 500)) . "\n";
                }
                
                if (strpos($content, 'function display(') !== false) {
                    echo "✅ Método display() encontrado\n";
                } else {
                    echo "❌ Método display() NO encontrado\n";
                }
                
                if (strpos($content, 'getQuotationFileUrl()') !== false) {
                    echo "✅ Método getQuotationFileUrl() encontrado\n";
                } else {
                    echo "❌ Método getQuotationFileUrl() NO encontrado\n";
                }
            } else {
                echo "❌ Quotation HtmlView.php NO existe\n";
                echo "Ruta esperada: " . $testPath . "\n";
            }
            
            // Test controller
            $controllerPath = $SITE_COMPONENT_PATH . '/src/Controller/QuotationController.php';
            if (file_exists($controllerPath)) {
                echo "✅ QuotationController.php existe\n";
                
                $content = file_get_contents($controllerPath);
                if (strpos($content, 'class OrdenproduccionControllerQuotation') !== false) {
                    echo "✅ Clase OrdenproduccionControllerQuotation definida correctamente\n";
                } else {
                    echo "❌ Clase OrdenproduccionControllerQuotation NO encontrada\n";
                    echo "Contenido del archivo (primeras 500 chars):\n";
                    echo htmlspecialchars(substr($content, 0, 500)) . "\n";
                }
            } else {
                echo "❌ QuotationController.php NO existe\n";
                echo "Ruta esperada: " . $controllerPath . "\n";
            }
            
            // Test template
            $templatePath = $SITE_COMPONENT_PATH . '/tmpl/quotation/display.php';
            if (file_exists($templatePath)) {
                echo "✅ quotation/display.php existe\n";
                
                $content = file_get_contents($templatePath);
                if (strpos($content, 'defined(\'_JEXEC\')') !== false) {
                    echo "✅ Template tiene protección _JEXEC\n";
                } else {
                    echo "❌ Template NO tiene protección _JEXEC\n";
                }
                
                if (strpos($content, 'getQuotationFileUrl()') !== false) {
                    echo "✅ Template llama a getQuotationFileUrl()\n";
                } else {
                    echo "❌ Template NO llama a getQuotationFileUrl()\n";
                }
            } else {
                echo "❌ quotation/display.php NO existe\n";
                echo "Ruta esperada: " . $templatePath . "\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error durante la prueba: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
        
        echo '</div>';
        
        // Test URL construction
        echo '<h4>🔗 Prueba de Construcción de URL</h4>';
        echo '<div class="file-list">';
        
        $testOrderId = 5389;
        $testOrderNumber = 'ORD-005543';
        $testQuotationFiles = '["\\/media\\/com_convertforms\\/uploads\\/9a9945bed17c3630_5336.pdf"]';
        
        $testUrl = '?option=com_ordenproduccion&view=quotation&layout=display&order_id=' . $testOrderId . 
                  '&order_number=' . urlencode($testOrderNumber) . 
                  '&quotation_files=' . urlencode($testQuotationFiles);
        
        echo "URL de prueba: " . $testUrl . "\n";
        echo "URL completa: " . Factory::getApplication()->get('live_site') . '/index.php' . $testUrl . "\n";
        
        // Test quotation file URL processing
        $quotationFilesDecoded = json_decode($testQuotationFiles, true);
        if (is_array($quotationFilesDecoded) && !empty($quotationFilesDecoded[0])) {
            $filePath = str_replace('\\/', '/', $quotationFilesDecoded[0]);
            echo "Archivo de cotización procesado: " . $filePath . "\n";
            
            $fullUrl = Factory::getApplication()->get('live_site') . $filePath;
            echo "URL completa del archivo: " . $fullUrl . "\n";
            
            // Test if file exists
            $localPath = JPATH_ROOT . $filePath;
            if (file_exists($localPath)) {
                echo "✅ Archivo de cotización existe localmente\n";
                echo "Tamaño: " . formatBytes(filesize($localPath)) . "\n";
            } else {
                echo "❌ Archivo de cotización NO existe localmente: " . $localPath . "\n";
            }
        } else {
            echo "❌ Error decodificando quotation_files JSON\n";
        }
        
        echo '</div>';
        
        // Joomla routing test
        echo '<h4>🛣️ Prueba de Enrutamiento de Joomla</h4>';
        echo '<div class="file-list">';
        
        try {
            // Test if Joomla can resolve the view
            $app = Factory::getApplication('site');
            $input = $app->input;
            
            // Set test parameters
            $input->set('option', 'com_ordenproduccion');
            $input->set('view', 'quotation');
            $input->set('layout', 'display');
            $input->set('order_id', $testOrderId);
            $input->set('order_number', $testOrderNumber);
            $input->set('quotation_files', $testQuotationFiles);
            
            echo "✅ Parámetros de entrada configurados\n";
            echo "Option: " . $input->get('option') . "\n";
            echo "View: " . $input->get('view') . "\n";
            echo "Layout: " . $input->get('layout') . "\n";
            echo "Order ID: " . $input->get('order_id') . "\n";
            echo "Order Number: " . $input->get('order_number') . "\n";
            echo "Quotation Files: " . $input->get('quotation_files') . "\n";
            
        } catch (Exception $e) {
            echo "❌ Error en prueba de enrutamiento: " . $e->getMessage() . "\n";
        }
        
        echo '</div>';
        
        // Cache analysis
        echo '<h4>💾 Análisis de Caché</h4>';
        echo '<div class="file-list">';
        
        $cacheDir = JPATH_ROOT . '/cache';
        if (is_dir($cacheDir)) {
            echo "✅ Directorio de caché existe: " . $cacheDir . "\n";
            
            $cacheFiles = glob($cacheDir . '/*');
            echo "Archivos de caché encontrados: " . count($cacheFiles) . "\n";
            
            // Check for component-specific cache
            $componentCache = glob($cacheDir . '/*com_ordenproduccion*');
            if (!empty($componentCache)) {
                echo "✅ Caché del componente encontrado: " . count($componentCache) . " archivos\n";
                foreach ($componentCache as $file) {
                    echo "  - " . basename($file) . "\n";
                }
            } else {
                echo "ℹ️ No se encontró caché específico del componente\n";
            }
        } else {
            echo "❌ Directorio de caché no existe\n";
        }
        
        echo '</div>';
        
        // Direct instantiation test
        echo '<h4>🚀 Prueba de Instanciación Directa</h4>';
        echo '<div class="file-list">';
        
        try {
            // Try to directly instantiate the quotation view
            echo "Intentando instanciar la vista de cotización directamente...\n";
            
            // Check if we can load the view class
            $viewPath = $SITE_COMPONENT_PATH . '/src/View/Quotation/HtmlView.php';
            if (file_exists($viewPath)) {
                echo "✅ Archivo de vista existe, intentando incluir...\n";
                
                // Try to include the file
                ob_start();
                include_once $viewPath;
                $includeOutput = ob_get_clean();
                
                if (!empty($includeOutput)) {
                    echo "⚠️ Output durante include: " . $includeOutput . "\n";
                } else {
                    echo "✅ Archivo incluido sin output\n";
                }
                
                // Try to instantiate the class
                if (class_exists('OrdenproduccionViewQuotationHtml')) {
                    echo "✅ Clase OrdenproduccionViewQuotationHtml disponible\n";
                    
                    try {
                        $view = new OrdenproduccionViewQuotationHtml();
                        echo "✅ Vista instanciada correctamente\n";
                        
                        // Try to call display method
                        ob_start();
                        $view->display();
                        $displayOutput = ob_get_clean();
                        
                        if (!empty($displayOutput)) {
                            echo "⚠️ Output durante display(): " . substr($displayOutput, 0, 200) . "...\n";
                        } else {
                            echo "✅ Método display() ejecutado sin output\n";
                        }
                        
                    } catch (Exception $e) {
                        echo "❌ Error instanciando vista: " . $e->getMessage() . "\n";
                        echo "Stack trace: " . $e->getTraceAsString() . "\n";
                    }
                } else {
                    echo "❌ Clase OrdenproduccionViewQuotationHtml NO disponible después del include\n";
                }
            } else {
                echo "❌ Archivo de vista no existe: $viewPath\n";
            }
            
        } catch (Exception $e) {
            echo "❌ Error durante prueba de instanciación: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
        
        echo '</div>';
        
        // Joomla routing simulation
        echo '<h4>🎭 Simulación de Enrutamiento Joomla</h4>';
        echo '<div class="file-list">';
        
        try {
            echo "Simulando el proceso de enrutamiento de Joomla...\n";
            
            // Simulate the URL parameters
            $_GET['option'] = 'com_ordenproduccion';
            $_GET['view'] = 'quotation';
            $_GET['layout'] = 'display';
            $_GET['order_id'] = '5389';
            $_GET['order_number'] = 'ORD-005543';
            $_GET['quotation_files'] = '["\/media\/com_convertforms\/uploads\/9a9945bed17c3630_5336.pdf"]';
            
            echo "✅ Parámetros GET configurados\n";
            echo "Option: " . $_GET['option'] . "\n";
            echo "View: " . $_GET['view'] . "\n";
            echo "Layout: " . $_GET['layout'] . "\n";
            echo "Order ID: " . $_GET['order_id'] . "\n";
            echo "Order Number: " . $_GET['order_number'] . "\n";
            echo "Quotation Files: " . $_GET['quotation_files'] . "\n";
            
            // Try to get Joomla application
            $app = Factory::getApplication('site');
            echo "✅ Aplicación Joomla obtenida\n";
            
            // Try to get input
            $input = $app->input;
            echo "✅ Input obtenido\n";
            
            // Check what Joomla sees
            echo "Joomla ve:\n";
            echo "- Option: " . $input->get('option') . "\n";
            echo "- View: " . $input->get('view') . "\n";
            echo "- Layout: " . $input->get('layout') . "\n";
            
        } catch (Exception $e) {
            echo "❌ Error durante simulación: " . $e->getMessage() . "\n";
            echo "Stack trace: " . $e->getTraceAsString() . "\n";
        }
        
        echo '</div>';
        
        echo '</div>';

        echo '<div class="section info">';
        echo '<h3>🔄 Acciones Rápidas</h3>';
        echo '<a href="javascript:location.reload()" class="btn">🔄 Recargar Página</a>';
        echo '<a href="index.php?option=com_ordenproduccion&view=administracion&tab=workorders" class="btn">📊 Ir a Work Orders</a>';
        echo '<a href="index.php?option=com_ordenproduccion&view=ordenes" class="btn">📋 Ir a Órdenes</a>';
        echo '<a href="index.php?option=com_ordenproduccion&view=quotation&layout=display&order_id=5389&order_number=ORD-005543&quotation_files=%5B%22%2Fmedia%2Fcom_convertforms%2Fuploads%2F9a9945bed17c3630_5336.pdf%22%5D" class="btn">🧪 Probar Vista de Cotización</a>';
        echo '</div>';
    </div>
</body>
</html>