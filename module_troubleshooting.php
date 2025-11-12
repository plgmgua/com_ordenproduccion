<?php
/**
 * Module Troubleshooting Script - mod_acciones_produccion
 * Diagnoses JavaScript loading issues for the shipping slip functionality
 */

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__, 3));

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

$app = Factory::getApplication('site');
$db = Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);
$input = $app->input;

// Get order ID from URL
$orderId = $input->getInt('id', 0);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Module Troubleshooting - mod_acciones_produccion</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 20px;
            background: white;
            font-size: 12px;
        }
        .section {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
        }
        .ok { background: #d4edda; color: #155724; padding: 8px; margin: 5px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 8px; margin: 5px 0; font-weight: bold; }
        .warning { background: #fff3cd; color: #856404; padding: 8px; margin: 5px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 8px; margin: 5px 0; }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 5px; }
        h3 { color: #555; margin-top: 20px; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        pre { background: #e9ecef; padding: 10px; overflow-x: auto; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #333; color: white; padding: 8px; text-align: left; }
        td { padding: 8px; border: 1px solid #dee2e6; }
        .btn { display: inline-block; padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>

<h2>🔍 mod_acciones_produccion Troubleshooting</h2>

<?php
// ============================================
// SECTION 1: Module File Checks
// ============================================
echo "<div class='section'>";
echo "<h3>1️⃣ Module File Checks</h3>";

$moduleFiles = [
    '/var/www/grimpsa_webserver/modules/mod_acciones_produccion/mod_acciones_produccion.php' => 'Main Module File',
    '/var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/default.php' => 'Template File',
    '/var/www/grimpsa_webserver/modules/mod_acciones_produccion/mod_acciones_produccion.xml' => 'Manifest File'
];

$allFilesExist = true;
foreach ($moduleFiles as $path => $label) {
    if (file_exists($path)) {
        echo "<div class='ok'>✅ {$label}: EXISTS</div>";
        echo "<div class='info'>Path: <code>{$path}</code></div>";
        echo "<div class='info'>Size: " . filesize($path) . " bytes | Modified: " . date('Y-m-d H:i:s', filemtime($path)) . "</div>";
    } else {
        echo "<div class='error'>❌ {$label}: NOT FOUND</div>";
        echo "<div class='error'>Expected: <code>{$path}</code></div>";
        $allFilesExist = false;
    }
}

if (!$allFilesExist) {
    echo "<div class='error'><strong>⚠️ MODULE FILES MISSING!</strong> Run the deployment script: <code>./update_build_simple.sh</code></div>";
}

echo "</div>";

// ============================================
// SECTION 2: Check submitShippingWithDescription in File
// ============================================
echo "<div class='section'>";
echo "<h3>2️⃣ Check submitShippingWithDescription Function in File</h3>";

$templateFile = '/var/www/grimpsa_webserver/modules/mod_acciones_produccion/tmpl/default.php';
if (file_exists($templateFile)) {
    $contents = file_get_contents($templateFile);
    
    // Check for the function definition
    if (strpos($contents, 'window.submitShippingWithDescription') !== false) {
        echo "<div class='ok'>✅ Function definition FOUND in template file</div>";
        
        // Check for the fix (const shippingForm declaration)
        if (strpos($contents, 'const shippingForm = document.getElementById') !== false) {
            echo "<div class='ok'>✅ FIXED VERSION: <code>const shippingForm</code> declaration present</div>";
        } else {
            echo "<div class='error'>❌ BROKEN VERSION: Missing <code>const shippingForm</code> declaration</div>";
            echo "<div class='error'>This is the bug! The variable is used but never declared.</div>";
        }
        
        // Show the actual code snippet
        preg_match('/window\.submitShippingWithDescription\s*=\s*function\(\)\s*\{[^\}]{0,300}/s', $contents, $matches);
        if (!empty($matches[0])) {
            echo "<div class='info'><strong>Code Snippet:</strong></div>";
            echo "<pre>" . htmlspecialchars(substr($matches[0], 0, 400)) . "...</pre>";
        }
    } else {
        echo "<div class='error'>❌ Function definition NOT FOUND in template file</div>";
        echo "<div class='error'>The function should be defined in the template</div>";
    }
    
    // Check for debug logging
    if (strpos($contents, "console.log('Module script loading...')") !== false) {
        echo "<div class='ok'>✅ Debug logging present</div>";
    } else {
        echo "<div class='warning'>⚠️ Debug logging not found (may be older version)</div>";
    }
} else {
    echo "<div class='error'>❌ Template file not found</div>";
}

echo "</div>";

// ============================================
// SECTION 3: Module Database Registration
// ============================================
echo "<div class='section'>";
echo "<h3>3️⃣ Module Database Registration</h3>";

try {
    $moduleQuery = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));
    
    $db->setQuery($moduleQuery);
    $modules = $db->loadObjectList();
    
    if (empty($modules)) {
        echo "<div class='error'>❌ Module NOT registered in database</div>";
        echo "<div class='error'>Run: <code>php /var/www/grimpsa_webserver/modules/mod_acciones_produccion/register_module_joomla5.php</code></div>";
    } else {
        echo "<div class='ok'>✅ Module registered in database</div>";
        echo "<div class='info'>Found: <strong>" . count($modules) . "</strong> instance(s)</div>";
        
        if (count($modules) > 1) {
            echo "<div class='warning'>⚠️ MULTIPLE INSTANCES FOUND! This causes duplication.</div>";
        }
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Title</th><th>Position</th><th>Published</th><th>Ordering</th></tr>";
        foreach ($modules as $mod) {
            $publishedClass = $mod->published ? 'ok' : 'error';
            echo "<tr class='{$publishedClass}'>";
            echo "<td>{$mod->id}</td>";
            echo "<td>{$mod->title}</td>";
            echo "<td><strong>{$mod->position}</strong></td>";
            echo "<td>" . ($mod->published ? '✅ Yes' : '❌ No') . "</td>";
            echo "<td>{$mod->ordering}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error querying modules: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

// ============================================
// SECTION 4: Test with Specific Order
// ============================================
echo "<div class='section'>";
echo "<h3>4️⃣ Test Module with Specific Order</h3>";

if ($orderId > 0) {
    echo "<div class='info'>Testing with Order ID: <strong>{$orderId}</strong></div>";
    
    // Simulate module logic
    try {
        $orderQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int)$orderId)
            ->where($db->quoteName('state') . ' = 1');
        
        $db->setQuery($orderQuery);
        $workOrderData = $db->loadObject();
        
        if ($workOrderData) {
            echo "<div class='ok'>✅ Work order data FOUND</div>";
            echo "<div class='info'>Order Number: <strong>" . ($workOrderData->orden_de_trabajo ?? 'N/A') . "</strong></div>";
            echo "<div class='info'>Client: <strong>" . ($workOrderData->client_name ?? 'N/A') . "</strong></div>";
            echo "<div class='info'>Status: <strong>" . ($workOrderData->status ?? 'N/A') . "</strong></div>";
            
            echo "<div class='ok'>✅ PHP Condition <code>if (\$orderId && \$workOrderData)</code> would be TRUE</div>";
            echo "<div class='ok'>✅ Script block SHOULD be output</div>";
        } else {
            echo "<div class='error'>❌ Work order data NOT FOUND</div>";
            echo "<div class='error'>PHP Condition <code>if (\$orderId && \$workOrderData)</code> would be FALSE</div>";
            echo "<div class='error'>Script block will NOT be output!</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error querying work order: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ No Order ID provided</div>";
    echo "<div class='info'>Add <code>?id=5610</code> to URL to test with specific order</div>";
}

echo "</div>";

// ============================================
// SECTION 5: Recommendations
// ============================================
echo "<div class='section'>";
echo "<h3>5️⃣ Recommendations & Next Steps</h3>";

echo "<div class='info'><strong>Deploy Latest Version:</strong></div>";
echo "<pre>ssh pgrant@192.168.1.208
cd /var/www/grimpsa_webserver
sudo ./update_build_simple.sh</pre>";

echo "<div class='info'><strong>Clear All Caches:</strong></div>";
echo "<pre>cd /var/www/grimpsa_webserver
sudo rm -rf administrator/cache/*
sudo rm -rf cache/*
sudo systemctl restart php-fpm</pre>";

echo "<div class='info'><strong>Test in Browser:</strong></div>";
echo "<ol>";
echo "<li>Open incognito window</li>";
echo "<li>Press F12 → Console tab</li>";
echo "<li>Navigate to orden page</li>";
echo "<li>Look for: <code>Module script loading...</code></li>";
echo "<li>Check: <code>typeof window.submitShippingWithDescription</code> should be 'function'</li>";
echo "</ol>";

echo "<div class='info'><strong>Check PHP Error Logs:</strong></div>";
echo "<pre>tail -50 /var/log/php8.1-fpm/error.log | grep 'MOD_ACCIONES'</pre>";

echo "</div>";

// ============================================
// SECTION 6: Quick Actions
// ============================================
echo "<div class='section'>";
echo "<h3>6️⃣ Quick Actions</h3>";

echo "<a href='?id=5610' class='btn'>Test with Order 5610</a>";
echo "<a href='?id=5613' class='btn'>Test with Order 5613</a>";
echo "<a href='?' class='btn'>Refresh</a>";

echo "</div>";

?>

<div class="section" style="background: #e7f3ff;">
    <h3>📝 Summary</h3>
    <p><strong>Current Status:</strong></p>
    <ul>
        <li>Module files: <?php echo $allFilesExist ? '✅ All present' : '❌ Missing files'; ?></li>
        <li>Function definition: <?php echo (isset($contents) && strpos($contents, 'window.submitShippingWithDescription') !== false) ? '✅ Found' : '❌ Not found'; ?></li>
        <li>Fix applied: <?php echo (isset($contents) && strpos($contents, 'const shippingForm = document.getElementById') !== false) ? '✅ Yes' : '❌ No'; ?></li>
        <li>Database registration: <?php echo (isset($modules) && !empty($modules)) ? '✅ Registered' : '❌ Not registered'; ?></li>
    </ul>
    
    <p><strong>If script still not loading:</strong></p>
    <ol>
        <li>Deploy latest version using <code>update_build_simple.sh</code></li>
        <li>Clear all caches (Joomla + PHP + Browser)</li>
        <li>Check PHP error logs for MOD_ACCIONES errors</li>
        <li>Verify in browser console that script loads</li>
    </ol>
</div>

</body>
</html>

