<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the database connection file
require_once __DIR__ . '/../db_connect.php'; // Adjust the path to your db_connect.php file

// Hardcoded configuration
$apiKey = 'AIzaSyC2y9oqMWDwIqaTPLoU73wRvgPH2Y-YyP0';
$spreadsheetId = '1eknuxDla8v7ccsYJbYoRVhx0lLUL_MgEJkU-iWNuIfY';
$range = 'ordenes-produccion!A1:AZ';

// Fetch last processed row from the database
function getLastProcessedRow($conn)
{
    $query = "SELECT last_processed_row FROM joomla_processed_rows ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);

    if ($result && $row = $result->fetch_assoc()) {
        return (int)$row['last_processed_row'];
    }
    return 0; // Default to 0 if no rows exist
}

// Update the last processed row in the database
function updateLastProcessedRow($conn, $rowIndex)
{
    $query = "INSERT INTO joomla_processed_rows (last_processed_row) VALUES (?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $rowIndex);
    $stmt->execute();
    $stmt->close();
}

// Fetch data from Google Sheets
function fetchGoogleSheetData($spreadsheetId, $apiKey, $range)
{
    $dataUrl = sprintf(
        'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s?key=%s',
        $spreadsheetId,
        $range,
        $apiKey
    );

    $response = file_get_contents($dataUrl);

    if ($response === false) {
        die('Error fetching data from Google Sheets.');
    }

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Error decoding JSON: ' . json_last_error_msg());
    }

    return $decoded['values'] ?? [];
}

// Process and send data to Power Automate
function processAndSendRow($row, $rowIndex)
{
    // Full mapping of fields
    $mappedFields = [
        0 => 'numero_de_orden',                // Column A
        1 => 'fecha_solicitud',                // Column B
        3 => 'fecha_de_entrega',               // Column D
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
        31 => 'trabajo_troquel_detalles',      // Column AF
        32 => 'trabajo_troquel_cameo',         // Column AG
        33 => 'trabajo_troquel_cameo_detalles',// Column AH
        34 => 'trabajo_observaciones',         // Column AI
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

    // Map row data
    $dataAssoc = [];
    foreach ($mappedFields as $index => $fieldName) {
        $dataAssoc[$fieldName] = $row[$index] ?? null;
    }

    // Generate JSON payload
    $jsonPayload = json_encode($dataAssoc);

    // Send payload to Power Automate (modify URLs as needed)
    $urls = [
        'https://prod-111.westus.logic.azure.com/workflows/6aa2f1100dd2402588374dc1fa098d7c/triggers/manual/paths/invoke?api-version=2016-06-01&sig=Qf3UcXcuT3MWebaGWTB0rFMg_y8lp3D-yCiUx1nMoc0',
        'https://prod-65.westus.logic.azure.com/workflows/c03340342e6e4723bece7f8d9051921b/triggers/manual/paths/invoke?api-version=2016-06-01&sig=WsPKu3L5KEDYm__aZlz4ahrP63f3Dc-BlfewrI9YzPk'
    ];

    foreach ($urls as $url) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        echo "Row $rowIndex sent to $url - Response: $response\n";
    }
}

// Main Execution
$lastProcessedRow = getLastProcessedRow($conn);
$data = fetchGoogleSheetData($spreadsheetId, $apiKey, $range);

for ($i = $lastProcessedRow; $i < count($data); $i++) {
    $row = $data[$i];
    $rowIndex = $i + 1;
    processAndSendRow($row, $rowIndex);
    updateLastProcessedRow($conn, $rowIndex);
}

echo "All new rows processed successfully.\n";

?>
