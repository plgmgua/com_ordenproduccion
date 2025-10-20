<?php
/**
 * Test if modal and data are working correctly
 * Access: https://your-domain.com/components/com_ordenproduccion/test_modal.php
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Duplicar Solicitud Modal</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Test Duplicar Solicitud Modal</h1>
        <hr>
        
        <div class="alert alert-info">
            <strong>Purpose:</strong> Verify that the modal opens and receives work order data correctly.
        </div>
        
        <button type="button" class="btn btn-primary" id="test-modal-btn">
            <i class="fas fa-copy"></i>
            Open Test Modal
        </button>
        
        <hr>
        
        <h3>Test Work Order Data</h3>
        <pre id="test-data" style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
<?php
$testOrderData = [
    'id' => 5380,
    'client_name' => 'Super Combustibles S.A.',
    'nit' => '123456-7',
    'invoice_value' => 1500.00,
    'work_description' => '5 MENUS CARTA DE VINOS',
    'sales_agent' => 'Peter Grant',
    'print_color' => '4/4',
    'tiro_retiro' => 'Tiro y Retiro',
    'dimensions' => '21.5 x 28 cms',
    'material' => 'Couche 300 grs',
    'delivery_date' => '2025-10-15',
    'request_date' => '2025-10-10 11:09:00',
    'quotation_files' => '/media/com_convertforms/uploads/558726b5fa1365e3_5340.pdf',
    'shipping_address' => '5a Avenida 10-50 zona 14',
    'shipping_contact' => 'Juan Pérez',
    'shipping_phone' => '2345-6789',
    'instrucciones_entrega' => 'Entregar en horario de oficina',
    'instructions' => 'Revisar colores antes de imprimir',
    'cutting' => 'SI',
    'cutting_details' => 'Corte a medida final',
    'blocking' => 'SI',
    'blocking_details' => 'Block de 50 hojas',
    'folding' => 'NO',
    'laminating' => 'SI',
    'laminating_details' => 'Laminado mate'
];
echo json_encode($testOrderData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
        </pre>
        
        <hr>
        
        <h3>Console Output</h3>
        <div id="console-output" style="background: #000; color: #0f0; padding: 15px; font-family: monospace; height: 300px; overflow-y: auto;">
            Waiting for test...
        </div>
    </div>
    
    <!-- Load the actual modal -->
    <?php 
    $item = (object) $testOrderData;
    require __DIR__ . '/tmpl/orden/duplicate_modal.php'; 
    ?>
    
    <script>
    // Capture console logs
    const consoleDiv = document.getElementById('console-output');
    const originalLog = console.log;
    const originalError = console.error;
    
    function addToConsole(type, ...args) {
        const line = document.createElement('div');
        line.style.color = type === 'error' ? '#f00' : '#0f0';
        line.textContent = '[' + type + '] ' + args.join(' ');
        consoleDiv.appendChild(line);
        consoleDiv.scrollTop = consoleDiv.scrollHeight;
    }
    
    console.log = function(...args) {
        originalLog.apply(console, args);
        addToConsole('LOG', ...args);
    };
    
    console.error = function(...args) {
        originalError.apply(console, args);
        addToConsole('ERROR', ...args);
    };
    
    // Test button
    document.getElementById('test-modal-btn').addEventListener('click', function() {
        console.log('Test button clicked');
        console.log('window.currentOrderData:', JSON.stringify(window.currentOrderData, null, 2));
        
        if (typeof openDuplicateModal === 'function') {
            console.log('openDuplicateModal function exists');
            openDuplicateModal(window.currentOrderData);
        } else {
            console.error('openDuplicateModal function NOT found!');
        }
    });
    
    console.log('Test page loaded');
    console.log('Checking if modal functions exist...');
    console.log('openDuplicateModal:', typeof openDuplicateModal);
    console.log('closeDuplicateModal:', typeof closeDuplicateModal);
    console.log('submitDuplicateRequest:', typeof submitDuplicateRequest);
    </script>
</body>
</html>

