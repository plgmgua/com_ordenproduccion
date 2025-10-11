<?php
/**
 * Troubleshooting Script - Orden Produccion Component
 * Purpose: Validate payload structure for Duplicar Solicitud functionality
 * 
 * This script examines joomla_approvalworkflow_requests table (record ID 43)
 * to understand the payload format needed for the Ventas "Duplicar Solicitud" button
 */

// Joomla initialization
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

// Create the Joomla application
$app = Factory::getApplication('site');

// Get database connection
try {
    $db = Factory::getDbo();
} catch (Exception $e) {
    die('❌ Database connection failed: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payload Validation - Duplicar Solicitud</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .content {
            padding: 30px;
        }
        
        .section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        h2 {
            color: #343a40;
            font-size: 24px;
            margin-bottom: 15px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        
        h3 {
            color: #495057;
            font-size: 18px;
            margin: 20px 0 10px 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #667eea;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .json-viewer {
            background: #282c34;
            color: #abb2bf;
            padding: 20px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'Courier New', Courier, monospace;
            font-size: 14px;
            line-height: 1.5;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .json-key {
            color: #e06c75;
        }
        
        .json-string {
            color: #98c379;
        }
        
        .json-number {
            color: #d19a66;
        }
        
        .json-boolean {
            color: #61afef;
        }
        
        .json-null {
            color: #c678dd;
        }
        
        .comparison-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .comparison-card {
            background: #fff;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }
        
        .comparison-card h4 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .field-mapping {
            display: flex;
            align-items: center;
            padding: 8px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .field-mapping .source {
            flex: 1;
            color: #495057;
            font-weight: 500;
        }
        
        .field-mapping .arrow {
            margin: 0 10px;
            color: #667eea;
            font-weight: bold;
        }
        
        .field-mapping .target {
            flex: 1;
            color: #28a745;
            font-weight: 500;
        }
        
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #e83e8c;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin: 15px 0;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }
        
        .timestamp {
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            padding: 20px;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 Payload Validation</h1>
            <p>Analyzing payload structure for "Duplicar Solicitud" functionality</p>
        </div>
        
        <div class="content">
            
            <?php
            // ============================================
            // STEP 1: FETCH APPROVAL WORKFLOW REQUEST
            // ============================================
            echo '<div class="section">';
            echo '<h2>📋 Step 1: Approval Workflow Request (ID: 43)</h2>';
            
            try {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__approvalworkflow_requests'))
                    ->where($db->quoteName('id') . ' = 43');
                
                $db->setQuery($query);
                $approvalRequest = $db->loadObject();
                
                if ($approvalRequest) {
                    echo '<div class="alert alert-success">✅ Record found successfully</div>';
                    
                    echo '<h3>Raw Database Record:</h3>';
                    echo '<table>';
                    echo '<tr><th>Field</th><th>Value</th></tr>';
                    
                    foreach ($approvalRequest as $key => $value) {
                        $displayValue = $value;
                        if (strlen($value) > 200) {
                            $displayValue = substr($value, 0, 200) . '... <em>(truncated)</em>';
                        }
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($displayValue) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    
                    // Try to parse form_data as JSON
                    if (isset($approvalRequest->form_data)) {
                        echo '<h3>📦 Parsed JSON Payload (form_data):</h3>';
                        
                        $formData = json_decode($approvalRequest->form_data, true);
                        
                        if ($formData !== null) {
                            echo '<div class="json-viewer">';
                            echo '<pre>' . json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                            echo '</div>';
                            
                            // Store for comparison
                            $payloadStructure = $formData;
                        } else {
                            echo '<div class="alert alert-warning">⚠️ form_data is not valid JSON</div>';
                            echo '<div class="json-viewer">';
                            echo '<pre>' . htmlspecialchars($approvalRequest->form_data) . '</pre>';
                            echo '</div>';
                        }
                    }
                } else {
                    echo '<div class="alert alert-warning">⚠️ No record found with ID 43</div>';
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            echo '</div>';
            
            // ============================================
            // STEP 2: FETCH CORRESPONDING WORK ORDER (ID 5380)
            // ============================================
            echo '<div class="section">';
            echo '<h2>📦 Step 2: Corresponding Work Order (ID: 5380)</h2>';
            
            try {
                $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_ordenes'))
                    ->where($db->quoteName('id') . ' = 5380');
                
                $db->setQuery($query);
                $sampleOrder = $db->loadObject();
                
                if ($sampleOrder) {
                    echo '<div class="alert alert-success">✅ Work order ID 5380 found - This order was created from Approval Workflow Request ID 43</div>';
                    
                    echo '<h3>Work Order Fields:</h3>';
                    echo '<table>';
                    echo '<tr><th>Field Name</th><th>Actual Value</th><th>Type</th></tr>';
                    
                    foreach ($sampleOrder as $key => $value) {
                        $type = gettype($value);
                        $displayValue = $value;
                        
                        if (strlen($value) > 100) {
                            $displayValue = substr($value, 0, 100) . '...';
                        }
                        
                        if ($value === null) {
                            $displayValue = '<em style="color: #999;">NULL</em>';
                        }
                        
                        echo '<tr>';
                        echo '<td><code>' . htmlspecialchars($key) . '</code></td>';
                        echo '<td>' . htmlspecialchars($displayValue) . '</td>';
                        echo '<td><span class="badge badge-info">' . $type . '</span></td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                } else {
                    echo '<div class="alert alert-warning">⚠️ Work order ID 5380 not found - trying latest order instead</div>';
                    
                    // Fallback to latest order
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->order($db->quoteName('id') . ' DESC')
                        ->setLimit(1);
                    
                    $db->setQuery($query);
                    $sampleOrder = $db->loadObject();
                    
                    if ($sampleOrder) {
                        echo '<div class="alert alert-info">ℹ️ Using latest order instead (ID: ' . $sampleOrder->id . ')</div>';
                    }
                }
            } catch (Exception $e) {
                echo '<div class="alert alert-error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            
            echo '</div>';
            
            // ============================================
            // STEP 3: FIELD MAPPING COMPARISON
            // ============================================
            if (isset($payloadStructure) && isset($sampleOrder)) {
                echo '<div class="section">';
                echo '<h2>🔄 Step 3: Field Mapping Analysis</h2>';
                
                echo '<div class="comparison-grid">';
                
                // Payload Fields
                echo '<div class="comparison-card">';
                echo '<h4>📥 Payload Fields (from ID 43)</h4>';
                
                if (is_array($payloadStructure)) {
                    foreach ($payloadStructure as $key => $value) {
                        $valuePreview = is_array($value) ? '[Array]' : (string)$value;
                        if (strlen($valuePreview) > 30) {
                            $valuePreview = substr($valuePreview, 0, 30) . '...';
                        }
                        echo '<div class="field-mapping">';
                        echo '<div class="source"><code>' . htmlspecialchars($key) . '</code>: ' . htmlspecialchars($valuePreview) . '</div>';
                        echo '</div>';
                    }
                }
                
                echo '</div>';
                
                // Work Order Fields
                echo '<div class="comparison-card">';
                echo '<h4>📦 Work Order Fields (joomla_ordenproduccion_ordenes)</h4>';
                
                $workOrderFields = array_keys((array)$sampleOrder);
                foreach ($workOrderFields as $field) {
                    echo '<div class="field-mapping">';
                    echo '<div class="target"><code>' . htmlspecialchars($field) . '</code></div>';
                    echo '</div>';
                }
                
                echo '</div>';
                
                echo '</div>';
                
                // Suggested Mapping
                echo '<h3>💡 Suggested Field Mapping:</h3>';
                echo '<table>';
                echo '<tr><th>Payload Field</th><th>→</th><th>Work Order Field</th><th>Status</th></tr>';
                
                // Common mappings
                $mappings = [
                    'cliente' => 'client_name',
                    'nit' => 'client_nit',
                    'descripcion_trabajo' => 'work_description',
                    'fecha_entrega' => 'delivery_date',
                    'fecha_de_solicitud' => 'request_date',
                    'agente_de_ventas' => 'sales_agent',
                    'color_impresion' => 'print_color',
                    'tiro_retiro' => 'tiro_retiro',
                    'medidas' => 'dimensions',
                    'material' => 'material',
                    'valor_factura' => 'invoice_amount',
                    'instrucciones' => 'general_instructions',
                    'direccion_entrega' => 'shipping_address',
                    'instrucciones_entrega' => 'instrucciones_entrega',
                    'contacto_nombre' => 'shipping_contact',
                    'contacto_telefono' => 'shipping_phone',
                    // Acabados
                    'corte' => 'cutting',
                    'blocado' => 'blocking',
                    'doblado' => 'folding',
                    'laminado' => 'laminating',
                    'lomo' => 'spine',
                    'pegado' => 'gluing',
                    'numerado' => 'numbering',
                    'sizado' => 'sizing',
                    'engrapado' => 'stapling',
                    'troquel' => 'die_cutting',
                    'barniz' => 'varnish',
                    'impresion_blanco' => 'white_print',
                    'despuntado' => 'trimming',
                    'ojetes' => 'eyelets',
                    'perforado' => 'perforation'
                ];
                
                if (is_array($payloadStructure)) {
                    foreach ($payloadStructure as $payloadKey => $payloadValue) {
                        $workOrderField = isset($mappings[$payloadKey]) ? $mappings[$payloadKey] : '?';
                        $exists = property_exists($sampleOrder, $workOrderField);
                        
                        echo '<tr>';
                        echo '<td><code>' . htmlspecialchars($payloadKey) . '</code></td>';
                        echo '<td style="text-align: center; color: #667eea; font-weight: bold;">→</td>';
                        echo '<td><code>' . htmlspecialchars($workOrderField) . '</code></td>';
                        
                        if ($workOrderField === '?') {
                            echo '<td><span class="badge badge-warning">❓ Unknown</span></td>';
                        } elseif ($exists) {
                            echo '<td><span class="badge badge-success">✅ Exists</span></td>';
                        } else {
                            echo '<td><span class="badge badge-error">❌ Missing</span></td>';
                        }
                        
                        echo '</tr>';
                    }
                }
                
                echo '</table>';
                
                echo '</div>';
            }
            
            // ============================================
            // STEP 3.5: DIRECT DATA COMPARISON (ID 43 → ID 5380)
            // ============================================
            if (isset($payloadStructure) && isset($sampleOrder) && $sampleOrder->id == 5380) {
                echo '<div class="section">';
                echo '<h2>🔍 Step 3.5: Direct Data Comparison (Payload → Work Order)</h2>';
                echo '<p>Comparing the data from Approval Workflow Request ID 43 with the actual saved Work Order ID 5380:</p>';
                
                echo '<table>';
                echo '<tr>';
                echo '<th style="width: 25%;">Field Name</th>';
                echo '<th style="width: 35%;">Payload Value (ID 43)</th>';
                echo '<th style="width: 35%;">Work Order Value (ID 5380)</th>';
                echo '<th style="width: 5%;">Match</th>';
                echo '</tr>';
                
                // Field mappings for comparison
                $comparisonMappings = [
                    'cliente' => 'client_name',
                    'nit' => 'client_nit',
                    'client_id' => 'client_id',
                    'descripcion_trabajo' => 'work_description',
                    'fecha_entrega' => 'delivery_date',
                    'fecha_de_solicitud' => 'request_date',
                    'agente_de_ventas' => 'sales_agent',
                    'color_impresion' => 'print_color',
                    'tiro_retiro' => 'tiro_retiro',
                    'medidas' => 'dimensions',
                    'material' => 'material',
                    'valor_factura' => 'invoice_amount',
                    'instrucciones' => 'general_instructions',
                    'direccion_entrega' => 'shipping_address',
                    'instrucciones_entrega' => 'instrucciones_entrega',
                    'contacto_nombre' => 'shipping_contact',
                    'contacto_telefono' => 'shipping_phone',
                    'corte' => 'cutting',
                    'blocado' => 'blocking',
                    'doblado' => 'folding',
                    'laminado' => 'laminating',
                    'lomo' => 'spine',
                    'pegado' => 'gluing',
                    'numerado' => 'numbering',
                    'sizado' => 'sizing',
                    'engrapado' => 'stapling',
                    'troquel' => 'die_cutting',
                    'barniz' => 'varnish',
                    'impresion_blanco' => 'white_print',
                    'despuntado' => 'trimming',
                    'ojetes' => 'eyelets',
                    'perforado' => 'perforation'
                ];
                
                foreach ($comparisonMappings as $payloadKey => $workOrderKey) {
                    $payloadValue = isset($payloadStructure[$payloadKey]) ? $payloadStructure[$payloadKey] : null;
                    $workOrderValue = isset($sampleOrder->$workOrderKey) ? $sampleOrder->$workOrderKey : null;
                    
                    // Format values for display
                    $payloadDisplay = $payloadValue;
                    $workOrderDisplay = $workOrderValue;
                    
                    if (is_array($payloadValue)) {
                        $payloadDisplay = '[Array: ' . count($payloadValue) . ' items]';
                    } elseif ($payloadValue === null) {
                        $payloadDisplay = '<em style="color: #999;">NULL</em>';
                    } elseif (strlen($payloadValue) > 50) {
                        $payloadDisplay = substr($payloadValue, 0, 50) . '...';
                    }
                    
                    if ($workOrderValue === null) {
                        $workOrderDisplay = '<em style="color: #999;">NULL</em>';
                    } elseif (strlen($workOrderValue) > 50) {
                        $workOrderDisplay = substr($workOrderValue, 0, 50) . '...';
                    }
                    
                    // Determine match status
                    $match = false;
                    $matchBadge = '<span class="badge badge-error">❌</span>';
                    
                    if ($payloadValue == $workOrderValue) {
                        $match = true;
                        $matchBadge = '<span class="badge badge-success">✅</span>';
                    } elseif ($payloadValue === null && $workOrderValue === null) {
                        $match = true;
                        $matchBadge = '<span class="badge badge-info">∅</span>';
                    } elseif ($payloadValue === null || $workOrderValue === null) {
                        $matchBadge = '<span class="badge badge-warning">⚠️</span>';
                    }
                    
                    // Highlight row if no match
                    $rowStyle = $match ? '' : 'style="background: #fff3cd;"';
                    
                    echo '<tr ' . $rowStyle . '>';
                    echo '<td><code>' . htmlspecialchars($payloadKey) . '</code><br><small style="color: #999;">→ ' . htmlspecialchars($workOrderKey) . '</small></td>';
                    echo '<td>' . htmlspecialchars($payloadDisplay) . '</td>';
                    echo '<td>' . htmlspecialchars($workOrderDisplay) . '</td>';
                    echo '<td style="text-align: center;">' . $matchBadge . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
                
                echo '<div class="alert alert-info" style="margin-top: 20px;">';
                echo '<strong>Legend:</strong><br>';
                echo '<span class="badge badge-success">✅</span> Values match<br>';
                echo '<span class="badge badge-warning">⚠️</span> One value is NULL<br>';
                echo '<span class="badge badge-error">❌</span> Values don\'t match<br>';
                echo '<span class="badge badge-info">∅</span> Both NULL<br>';
                echo '<em>Yellow rows indicate data differences that may need investigation</em>';
                echo '</div>';
                
                echo '</div>';
            }
            
            // ============================================
            // STEP 4: GENERATE SAMPLE PAYLOAD
            // ============================================
            if (isset($sampleOrder)) {
                echo '<div class="section">';
                echo '<h2>🚀 Step 4: Generated Payload for Duplicar Solicitud</h2>';
                
                echo '<p>This is the JSON payload that will be sent to the configured endpoint when "Duplicar Solicitud" is clicked:</p>';
                
                $generatedPayload = [
                    'request_title' => 'Solicitud Ventas a Produccion',
                    'source' => 'Joomla - Orden Produccion Component',
                    'order_id' => $sampleOrder->id,
                    'orden_de_trabajo' => $sampleOrder->orden_de_trabajo ?? $sampleOrder->order_number,
                    'form_data' => [
                        'client_id' => $sampleOrder->client_id ?? null,
                        'cliente' => $sampleOrder->client_name ?? '',
                        'nit' => $sampleOrder->client_nit ?? '0',
                        'valor_factura' => $sampleOrder->invoice_amount ?? 0,
                        'descripcion_trabajo' => $sampleOrder->work_description ?? '',
                        'color_impresion' => $sampleOrder->print_color ?? '',
                        'tiro_retiro' => $sampleOrder->tiro_retiro ?? '',
                        'medidas' => $sampleOrder->dimensions ?? '',
                        'fecha_entrega' => $sampleOrder->delivery_date ?? '',
                        'material' => $sampleOrder->material ?? '',
                        'cotizacion' => [],
                        'arte' => [],
                        'corte' => $sampleOrder->cutting ?? 'NO',
                        'blocado' => $sampleOrder->blocking ?? 'NO',
                        'doblado' => $sampleOrder->folding ?? 'NO',
                        'laminado' => $sampleOrder->laminating ?? 'NO',
                        'lomo' => $sampleOrder->spine ?? 'NO',
                        'pegado' => $sampleOrder->gluing ?? 'NO',
                        'numerado' => $sampleOrder->numbering ?? 'NO',
                        'sizado' => $sampleOrder->sizing ?? 'NO',
                        'engrapado' => $sampleOrder->stapling ?? 'NO',
                        'troquel' => $sampleOrder->die_cutting ?? 'NO',
                        'barniz' => $sampleOrder->varnish ?? 'NO',
                        'impresion_blanco' => $sampleOrder->white_print ?? 'NO',
                        'despuntado' => $sampleOrder->trimming ?? 'NO',
                        'ojetes' => $sampleOrder->eyelets ?? 'NO',
                        'perforado' => $sampleOrder->perforation ?? 'NO',
                        'instrucciones' => $sampleOrder->general_instructions ?? '',
                        'agente_de_ventas' => $sampleOrder->sales_agent ?? '',
                        'fecha_de_solicitud' => $sampleOrder->request_date ?? date('Y-m-d H:i:s'),
                        'direccion_entrega' => $sampleOrder->shipping_address ?? '',
                        'instrucciones_entrega' => $sampleOrder->instrucciones_entrega ?? '',
                        'contacto_nombre' => $sampleOrder->shipping_contact ?? '',
                        'contacto_telefono' => $sampleOrder->shipping_phone ?? ''
                    ]
                ];
                
                echo '<h3>📦 JSON Payload:</h3>';
                echo '<div class="json-viewer">';
                echo '<pre>' . json_encode($generatedPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
                echo '</div>';
                
                echo '<div class="alert alert-info">';
                echo '<strong>ℹ️ How to use this payload:</strong><br>';
                echo '1. Configure endpoint URL in <code>Settings → Ventas Settings</code><br>';
                echo '2. The button will send this JSON structure via HTTP POST<br>';
                echo '3. Optional: Add API Key for authentication (sent as Bearer token)<br>';
                echo '4. The endpoint should return a JSON response with success/error status';
                echo '</div>';
                
                echo '</div>';
            }
            ?>
            
        </div>
        
        <div class="timestamp">
            Generated: <?php echo date('Y-m-d H:i:s'); ?> | 
            Database: <?php echo $db->getPrefix(); ?> | 
            Version: 2.6.0-STABLE
        </div>
    </div>
</body>
</html>
