<?php
/**
 * Webhook Payload Test Script
 * Access via: /components/com_ordenproduccion/test_webhook_payload.php
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

echo "<h1>Webhook Payload Test</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .code{background:#f5f5f5;padding:10px;border:1px solid #ddd;font-family:monospace;}</style>";

try {
    $app = Factory::getApplication('site');
    $baseUrl = $app->get('live_site');
    
    echo "<h2>Test Payload</h2>";
    
    $testPayload = [
        "request_title" => "Solicitud Ventas a Produccion",
        "form_data" => [
            "client_id" => "7",
            "cliente" => "Grupo Impre S.A.",
            "nit" => "114441782",
            "valor_factura" => "2500",
            "descripcion_trabajo" => "1000 Flyers Full Color con acabados especiales",
            "color_impresion" => "Full Color",
            "tiro_retiro" => "Tiro/Retiro",
            "medidas" => "8.5 x 11",
            "fecha_entrega" => "15/10/2025",
            "material" => "Husky 250 grms",
            "cotizacion" => ["/media/com_convertforms/uploads/cotizacion_001.pdf"],
            "arte" => ["/media/com_convertforms/uploads/arte_001.pdf"],
            "corte" => "SI",
            "detalles_corte" => "Corte recto en guillotina",
            "blocado" => "SI",
            "detalles_blocado" => "Blocado de 50 unidades",
            "doblado" => "SI",
            "detalles_doblado" => "Doblez a la mitad",
            "laminado" => "SI",
            "detalles_laminado" => "Laminado brillante tiro y retiro",
            "lomo" => "NO",
            "pegado" => "NO",
            "numerado" => "SI",
            "detalles_numerado" => "Numerado consecutivo del 1 al 1000",
            "sizado" => "NO",
            "engrapado" => "NO",
            "troquel" => "SI",
            "detalles_troquel" => "Troquel circular en esquinas",
            "barniz" => "SI",
            "detalles_barniz" => "Barniz UV en logo",
            "impresion_blanco" => "NO",
            "despuntado" => "SI",
            "detalles_despuntado" => "Despunte en esquinas superiores",
            "ojetes" => "NO",
            "perforado" => "SI",
            "detalles_perforado" => "Perforado horizontal para calendario",
            "instrucciones" => "Entregar en caja de 50 unidades. Cliente recogerá personalmente.",
            "agente_de_ventas" => "Peter Grant",
            "fecha_de_solicitud" => "2025-10-01 17:00:00"
        ]
    ];
    
    echo "<div class='code'>";
    echo htmlspecialchars(json_encode($testPayload, JSON_PRETTY_PRINT));
    echo "</div>";
    
    echo "<h2>Test Webhook Endpoint</h2>";
    
    $webhookUrl = rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.process&format=json';
    echo "<div class='info'>Webhook URL: <a href='$webhookUrl' target='_blank'>$webhookUrl</a></div>";
    
    echo "<h2>Send Test Request</h2>";
    echo "<button onclick='testWebhook()' style='padding:10px 20px;background:#007cba;color:white;border:none;border-radius:4px;cursor:pointer;'>Send Test Request</button>";
    echo "<div id='result' style='margin-top:20px;'></div>";
    
    echo "<h2>curl Command</h2>";
    echo "<div class='code'>";
    echo "curl -X POST \\<br>";
    echo "  '" . $webhookUrl . "' \\<br>";
    echo "  -H 'Content-Type: application/json' \\<br>";
    echo "  -d '" . json_encode($testPayload) . "'";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>Error: " . $e->getMessage() . "</div>";
}

echo "<script>
function testWebhook() {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = '<div class=\"info\">Sending request...</div>';
    
    const payload = " . json_encode($testPayload) . ";
    const webhookUrl = '" . $webhookUrl . "';
    
    fetch(webhookUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.innerHTML = '<div class=\"success\">✓ Request successful!</div><div class=\"code\">' + JSON.stringify(data, null, 2) + '</div>';
    })
    .catch(error => {
        resultDiv.innerHTML = '<div class=\"error\">✗ Request failed: ' + error.message + '</div>';
    });
}
</script>";

echo "<h2>Test Complete</h2>";
echo "<p>Click the 'Send Test Request' button above to test the webhook with your actual payload.</p>";
?>
