<?php
// Comprehensive syntax diagnostics for com_ordenproduccion Paymentproof view
// Place in Joomla root: /var/www/grimpsa_webserver/troubleshooting.php
// Access via: https://<host>/troubleshooting.php

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

$app = Factory::getApplication('site');
header('Content-Type: text/html; charset=utf-8');

function renderRow($label, $path) {
    $exists = file_exists($path);
    $status = $exists ? '<span style="color:green">FOUND</span>' : '<span style="color:red">MISSING</span>';
    $size = $exists ? filesize($path) : '-';
    $mod = $exists ? date('Y-m-d H:i:s', filemtime($path)) : '-';
    echo '<tr>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . htmlspecialchars($label) . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee;word-break:break-all">' . htmlspecialchars($path) . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . $status . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . $size . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . $mod . '</td>';
    echo '</tr>';
}

// Page header
echo '<div style="font-family:Arial,sans-serif;max-width:100%;margin:20px auto;box-sizing:border-box;overflow:auto">';
echo '<h2 style="margin:0 0 10px">com_ordenproduccion Paymentproof Diagnostics</h2>';
echo '<div style="color:#666;margin-bottom:20px">' . date('Y-m-d H:i:s') . '</div>';

// Request data
$input = $app->input;
$req = [
    'option' => $input->getCmd('option'),
    'view' => $input->getCmd('view'),
    'order_id' => $input->getInt('order_id'),
    'task' => $input->getCmd('task'),
    'Itemid' => $input->getInt('Itemid')
];

echo '<h3>Request</h3><pre style="background:#f9f9f9;padding:10px;border:1px solid #eee">' . htmlspecialchars(print_r($req, true)) . '</pre>';

// Component files to check
$base = JPATH_BASE . '/components/com_ordenproduccion';
$files = [
    'View class' => $base . '/src/View/Paymentproof/HtmlView.php',
    'View template' => $base . '/tmpl/paymentproof/default.php',
    'Model (PaymentproofModel.php)' => $base . '/src/Model/PaymentproofModel.php',
    'Model (PaymentProofModel.php)' => $base . '/src/Model/PaymentProofModel.php',
    'Controller (PaymentproofController.php)' => $base . '/src/Controller/PaymentproofController.php',
    'Controller (PaymentProofController.php)' => $base . '/src/Controller/PaymentProofController.php'
];

echo '<h3>Component Files</h3>';

echo '<table style="width:100%;border-collapse:collapse;table-layout:fixed">';
echo '<tr>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd;width:180px">File</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd;word-break:break-all">Path</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd;width:90px">Status</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd;width:80px">Size</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd;width:160px">Modified</th>' .
     '</tr>';
foreach ($files as $label => $path) {
    renderRow($label, $path);
}

echo '</table>';

// PHP Syntax Check
echo '<h3 style="margin-top:30px">PHP Syntax Check (php -l)</h3>';
echo '<div style="background:#f5f5f5;padding:15px;border:1px solid #ddd;max-height:600px;overflow-y:auto">';

$phpFiles = [
    $base . '/src/View/Paymentproof/HtmlView.php',
    $base . '/tmpl/paymentproof/default.php',
    $base . '/src/Model/PaymentproofModel.php',
    $base . '/src/Model/PaymentProofModel.php',
    $base . '/src/Controller/PaymentproofController.php',
    $base . '/src/Controller/PaymentProofController.php'
];

foreach ($phpFiles as $phpFile) {
    if (file_exists($phpFile)) {
        $relPath = str_replace($base, '', $phpFile);
        echo '<h4 style="margin:15px 0 5px;color:#333;border-bottom:1px solid #ccc;padding-bottom:5px">' . htmlspecialchars($relPath) . '</h4>';
        
        $output = [];
        $returnCode = 0;
        exec('php -l ' . escapeshellarg($phpFile) . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0) {
            echo '<div style="color:green;font-weight:bold;margin:5px 0">✓ No syntax errors detected</div>';
        } else {
            echo '<div style="color:red;font-weight:bold;margin:5px 0;background:#ffe0e0;padding:10px;border:1px solid #ffcccc">';
            echo '✗ SYNTAX ERROR FOUND:<br>';
            echo '<pre style="margin:10px 0;white-space:pre-wrap;font-family:monospace">';
            echo htmlspecialchars(implode("\n", $output));
            echo '</pre></div>';
        }
        
        // Show first 15 and last 15 lines
        $lines = file($phpFile);
        $totalLines = count($lines);
        
        echo '<details style="margin:10px 0">';
        echo '<summary style="cursor:pointer;color:#0066cc;font-weight:bold">► View first/last 15 lines (Total: ' . $totalLines . ' lines)</summary>';
        echo '<div style="background:#fff;padding:10px;border:1px solid #ddd;margin-top:5px">';
        echo '<pre style="font-size:0.85em;font-family:monospace;margin:0">';
        echo '<strong>First 15 lines:</strong>' . "\n";
        for ($i = 0; $i < min(15, $totalLines); $i++) {
            echo sprintf('%4d | %s', $i + 1, htmlspecialchars($lines[$i]));
        }
        if ($totalLines > 30) {
            echo "\n" . str_repeat('─', 80) . "\n\n";
        }
        echo '<strong>Last 15 lines:</strong>' . "\n";
        for ($i = max(0, $totalLines - 15); $i < $totalLines; $i++) {
            echo sprintf('%4d | %s', $i + 1, htmlspecialchars($lines[$i]));
        }
        echo '</pre>';
        echo '</div>';
        echo '</details>';
    }
}

echo '</div>';

// Check autoload cache
echo '<h3 style="margin-top:30px">Joomla Autoload Cache</h3>';
$autoloadFile = JPATH_BASE . '/administrator/cache/autoload_psr4.php';
if (file_exists($autoloadFile)) {
    echo '<p>Cache file: <code>' . htmlspecialchars($autoloadFile) . '</code></p>';
    echo '<p>Last modified: <strong>' . date('Y-m-d H:i:s', filemtime($autoloadFile)) . '</strong></p>';
    echo '<p>Size: ' . number_format(filesize($autoloadFile)) . ' bytes</p>';
    
    // Check if our namespace is registered
    $autoloadData = @include $autoloadFile;
    if (is_array($autoloadData) && isset($autoloadData['Grimpsa\\Component\\Ordenproduccion'])) {
        echo '<p style="color:green;font-weight:bold">✓ Component namespace is registered in autoload</p>';
        echo '<details><summary style="cursor:pointer;color:#0066cc">View namespace paths</summary>';
        echo '<pre style="font-size:0.85em;background:#f9f9f9;padding:10px;margin-top:5px">';
        print_r($autoloadData['Grimpsa\\Component\\Ordenproduccion']);
        echo '</pre></details>';
    } else {
        echo '<p style="color:red;font-weight:bold">✗ Component namespace NOT found in autoload cache</p>';
        echo '<p style="background:#ffe0e0;padding:10px;border:1px solid #ffcccc">The autoloader may not know about the Paymentproof classes. Try clearing Joomla cache or deleting this file to force regeneration.</p>';
    }
} else {
    echo '<p style="color:red;font-weight:bold">✗ Autoload cache file does not exist</p>';
    echo '<p>This file will be auto-generated on next request.</p>';
}

// Suggested URL
echo '<h3 style="margin-top:30px">Suggested Non-SEF URL</h3>';
$oid = (int) ($req['order_id'] ?: 0);
$url = 'index.php?option=com_ordenproduccion&view=paymentproof' . ($oid ? ('&order_id=' . $oid) : '');
echo '<div style="background:#e8f4f8;padding:15px;border:1px solid #b3d9e6">';
echo '<a href="' . htmlspecialchars($url) . '" style="font-size:1.1em;color:#0066cc;text-decoration:none">' . htmlspecialchars($url) . '</a>';
echo '</div>';

echo '</div>';
