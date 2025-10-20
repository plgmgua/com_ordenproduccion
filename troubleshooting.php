<?php
// Minimal diagnostics for com_ordenproduccion Paymentproof view
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
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . htmlspecialchars($path) . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . $status . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . $size . '</td>';
    echo '<td style="padding:6px 10px;border-bottom:1px solid #eee">' . $mod . '</td>';
    echo '</tr>';
}

// Page header
echo '<div style="font-family:Arial,sans-serif;max-width:960px;margin:20px auto">';
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
    'Controller (PaymentproofController.php)' => $base . '/src/Controller/PaymentproofController.php'
];

echo '<h3>Component Files</h3>';

echo '<table style="width:100%;border-collapse:collapse">';
echo '<tr>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd">File</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd">Path</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd">Status</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd">Size</th>' .
     '<th style="text-align:left;padding:6px 10px;border-bottom:2px solid #ddd">Modified</th>' .
     '</tr>';
foreach ($files as $label => $path) {
    renderRow($label, $path);
}

echo '</table>';

// Suggested URL
$oid = (int) ($req['order_id'] ?: 0);
$url = 'index.php?option=com_ordenproduccion&view=paymentproof' . ($oid ? ('&order_id=' . $oid) : '');
echo '<h3>Suggested URL</h3><div><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($url) . '</a></div>';

echo '</div>';