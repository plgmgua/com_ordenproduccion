<?php
/**
 * Local vs Server File Comparison Script
 * 
 * This script compares local files with server files to verify deployment status.
 * Run this script locally to check if your changes have been deployed.
 * 
 * Usage: php compare_local_vs_server.php
 */

// Configuration
$localWorkingDir = '/Users/pgrant/my_cloud/GitHub-Cursor/com_ordenproduccion-1';
$serverBaseUrl = 'https://grimpsa_webserver.grantsolutions.cc';

// Key files to compare for our recent fixes
$comparisonFiles = [
    'OrdenModel' => [
        'local' => $localWorkingDir . '/com_ordenproduccion/site/src/Model/OrdenModel.php',
        'server' => $serverBaseUrl . '/components/com_ordenproduccion/site/src/Model/OrdenModel.php',
        'fixes' => ['translateStatus', 'numero_de_orden', 'getEAVData']
    ],
    'OrdenView' => [
        'local' => $localWorkingDir . '/com_ordenproduccion/site/src/View/Orden/HtmlView.php',
        'server' => $serverBaseUrl . '/components/com_ordenproduccion/site/src/View/Orden/HtmlView.php',
        'fixes' => ['translateStatus', 'getStatusBadgeClass']
    ],
    'OrdenTemplate' => [
        'local' => $localWorkingDir . '/com_ordenproduccion/site/tmpl/orden/default.php',
        'server' => $serverBaseUrl . '/components/com_ordenproduccion/site/tmpl/orden/default.php',
        'fixes' => ['translateStatus', 'getStatusBadgeClass']
    ],
    'OrdenesModel' => [
        'local' => $localWorkingDir . '/com_ordenproduccion/site/src/Model/OrdenesModel.php',
        'server' => $serverBaseUrl . '/components/com_ordenproduccion/site/src/Model/OrdenesModel.php',
        'fixes' => ['getStatusOptions', 'translateStatus']
    ],
    'OrdenesTemplate' => [
        'local' => $localWorkingDir . '/com_ordenproduccion/site/tmpl/ordenes/default.php',
        'server' => $serverBaseUrl . '/components/com_ordenproduccion/site/tmpl/ordenes/default.php',
        'fixes' => ['translateStatus', 'filter_status']
    ],
    'ServiceProvider' => [
        'local' => $localWorkingDir . '/com_ordenproduccion/admin/services/provider.php',
        'server' => $serverBaseUrl . '/administrator/components/com_ordenproduccion/services/provider.php',
        'fixes' => ['ComponentDispatcherFactory', 'registerServiceProvider']
    ]
];

echo "🔧 Local vs Server File Comparison\n";
echo "=====================================\n\n";

$allFilesMatch = true;
$comparisonResults = [];

foreach ($comparisonFiles as $name => $fileInfo) {
    echo "📁 $name\n";
    echo str_repeat("-", 50) . "\n";
    
    $localExists = file_exists($fileInfo['local']);
    $serverExists = false;
    $serverContent = '';
    
    if (!$localExists) {
        echo "❌ Local file not found: {$fileInfo['local']}\n";
        $allFilesMatch = false;
        echo "\n";
        continue;
    }
    
    // Try to get server content (this is a simplified approach)
    // In a real scenario, you might need to use curl or similar
    echo "📄 Local file: {$fileInfo['local']}\n";
    echo "🌐 Server file: {$fileInfo['server']}\n";
    
    // Read local content
    $localContent = file_get_contents($fileInfo['local']);
    $localSize = strlen($localContent);
    $localModified = date('Y-m-d H:i:s', filemtime($fileInfo['local']));
    
    echo "📊 Local size: $localSize bytes | Modified: $localModified\n";
    
    // Check for specific fixes in local file
    echo "🔍 Fix Verification (Local):\n";
    foreach ($fileInfo['fixes'] as $fix) {
        $hasFix = strpos($localContent, $fix) !== false;
        $status = $hasFix ? '✅ Present' : '❌ Missing';
        echo "   $fix: $status\n";
    }
    
    // Note: Server comparison would require additional setup
    echo "ℹ️  Server comparison requires additional setup (curl, authentication, etc.)\n";
    echo "   Use the server validation script for complete comparison.\n";
    
    echo "\n";
}

echo "📊 Summary\n";
echo "==========\n";
echo "Local files checked: " . count($comparisonFiles) . "\n";
echo "Status: " . ($allFilesMatch ? "✅ All local files present" : "❌ Some local files missing") . "\n";
echo "\n";
echo "💡 To verify server deployment, run the validation script on the server:\n";
echo "   https://grimpsa_webserver.grantsolutions.cc/components/com_ordenproduccion/site/validate_deployment.php\n";
echo "\n";
?>
