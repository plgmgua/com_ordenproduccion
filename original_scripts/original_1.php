<?php
// Hardcoded configuration
$apiKey = 'AIzaSyC2y9oqMWDwIqaTPLoU73wRvgPH2Y-YyP0';
$templateId = '69877b237a075ab2';
$iftttKey = 'oXdSR0w98C_tWE0j_4FNk1usEUQ_WPdmeyNzlHjKOJH';
$rowIndex = file_get_contents('php://input');

if (!$rowIndex) {
    die('Error: Missing row index.');
}

$rowIndex = htmlspecialchars($rowIndex);
echo $rowIndex . '***';

// Google Sheets API Request
$rowRanges = 'A' . $rowIndex . ':AZ' . $rowIndex;
$dataUrl = sprintf(
    'https://sheets.googleapis.com/v4/spreadsheets/1eknuxDla8v7ccsYJbYoRVhx0lLUL_MgEJkU-iWNuIfY/values/ordenes-produccion!%s?key=%s',
    $rowRanges,
    $apiKey
);

// Query the spreadsheet using cURL
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $dataUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'GET',
));
$response = curl_exec($curl);
curl_close($curl);

$decoded = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error decoding JSON: ' . json_last_error_msg());
}

// Extract required data
if (!isset($decoded['values'][0])) {
    die('Error: Missing values in the response.');
}

$data = $decoded['values'][0];

// Map spreadsheet columns to fields explicitly
$mappedFields = [
    0 => 'numero_de_orden',                // Column A
    1 => 'fecha_solicitud',                // Column B
    3 => 'fecha_de_entrega',               // Column D (skips column C)
    4 => 'cliente_nombre',                 // Column E
    5 => 'cliente_nit',                    // Column F
    6 => 'cliente_direccion',              // Column G
    7 => 'agente_de_ventas',               // Column H
    8 => 'trabajo_descripcion',            // Column I
    9 => 'trabajo_material',               // Column J
    10 => 'trabajo_medidas',               // Column K
    11 => 'trabajo_archivo',               // Column L
    12 => 'trabajo_corte',                 // Column M
    13 => 'trabajo_corte_detalles',        // Column N
    14 => 'trabajo_blocado',               // Column O
    15 => 'trabajo_blocado_detalles',      // Column P
    16 => 'trabajo_doblado',               // Column Q
    17 => 'trabajo_doblado_detalles',      // Column R
    18 => 'trabajo_laminado',              // Column S
    19 => 'trabajo_laminado_detalles',     // Column T
    20 => 'trabajo_lomo',                  // Column U
    21 => 'trabajo_lomo_detalles',         // Column V
    22 => 'trabajo_numerado',              // Column W
    23 => 'trabajo_numerado_detalles',     // Column X
    24 => 'trabajo_pegado',                // Column Y
    25 => 'trabajo_pegado_detalles',       // Column Z
    26 => 'trabajo_sizado',                // Column AA
    27 => 'trabajo_sizado_detalles',       // Column AB
    28 => 'trabajo_engrapado',             // Column AC
    29 => 'trabajo_engrapado_detalles',    // Column AD
    30 => 'trabajo_troquel',               // Column AE
    31 => 'trabajo_broquel_detalles',      // Column AF
    32 => 'trabajo_troquel_cameo',         // Column AG
    33 => 'trabajo_troquel_cameo_detalles',// Column AH
    34 => 'trabajo_observasiones',         // Column AI
    35 => 'trabajo_barniz',                // Column AJ
    36 => 'trabajo_barniz_detalles',       // Column AK
    37 => 'trabajo_impresion_en_blanco',   // Column AL
    38 => 'trabajo_impresion_en_blanco_detalles', // Column AM
    39 => 'trabajo_color_de_impresion',    // Column AN
    40 => 'trabajo_correo_solicitante',    // Column AO
    41 => 'trabajo_tiro_retiro',           // Column AP
    42 => 'cliente_valor_factura',         // Column AQ
    43 => 'trabajo_archivo_arte',          // Column AR
    44 => 'trabajo_despuntado',            // Column AS
    45 => 'trabajo_despuntado_detalles',   // Column AT
    46 => 'trabajo_ojetes',                // Column AU
    47 => 'trabajo_ojetes_detalles',       // Column AV
    48 => 'trabajo_perforado',             // Column AW
    49 => 'trabajo_perforado_detalles'     // Column AX
];

// Prepare dataAssoc by mapping the fields
$dataAssoc = [];
foreach ($mappedFields as $index => $fieldName) {
    $dataAssoc[$fieldName] = isset($data[$index]) ? $data[$index] : null;
}

// Debugging to ensure correct mapping
echo 'Mapped Data: ' . print_r($dataAssoc, true);

// Generate JSON payload
$jsonPayload = json_encode($dataAssoc);
if ($jsonPayload === false) {
    die('Error encoding JSON payload: ' . json_last_error_msg());
}

// Inicia orden de trabajo a MS Flows
echo "inicia el registro en el flow de power automate";
$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://prod-111.westus.logic.azure.com:443/workflows/6aa2f1100dd2402588374dc1fa098d7c/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=Qf3UcXcuT3MWebaGWTB0rFMg_y8lp3D-yCiUx1nMoc0',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $jsonPayload,
    CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
    ),
));

$response = curl_exec($curl);
curl_close($curl);

echo $response;

// Finaliza orden de trabajo a MS Flows

// Function to handle the additional cURL workflow
function triggerAdditionalWorkflow($json_payload) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://prod-65.westus.logic.azure.com:443/workflows/c03340342e6e4723bece7f8d9051921b/triggers/manual/paths/invoke?api-version=2016-06-01&sp=%2Ftriggers%2Fmanual%2Frun&sv=1.0&sig=WsPKu3L5KEDYm__aZlz4ahrP63f3Dc-BlfewrI9YzPk',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $json_payload,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    echo $response;

    echo "aca finaliza el script";
}

// Explicitly call the function
triggerAdditionalWorkflow($jsonPayload);
