<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MySQL connection parameters
$host = 'localhost';
$db = 'grimpsa_prod';  // Replace with your database name
$user = 'joomla';      // Replace with your MySQL username
$pass = 'Blob-Repair-Commodore6'; // Replace with your MySQL password

// Create a connection using mysqli
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve the input row index from the URL parameter
$row_index = isset($_GET['row_index']) ? intval($_GET['row_index']) : null;

// Validate the row index
if (is_null($row_index) || $row_index <= 0) {
    die('Invalid or missing row_index parameter. Provide a valid row index.');
}

// Google Sheets API URL
$row_range = 'ordenes-produccion!A' . $row_index . ':BC' . $row_index;
$sheet_url = 'https://sheets.googleapis.com/v4/spreadsheets/1eknuxDla8v7ccsYJbYoRVhx0lLUL_MgEJkU-iWNuIfY/values/' . $row_range . '?key=AIzaSyC2y9oqMWDwIqaTPLoU73wRvgPH2Y-YyP0';

// Initialize cURL to fetch the specific row data
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $sheet_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_VERBOSE => true, // Enable verbose cURL output for debugging
));

$response = curl_exec($curl);

// Check for cURL errors
if ($response === false) {
    die('cURL error: ' . curl_error($curl));
}
curl_close($curl);

// Log the raw response for debugging
echo "<pre>Raw response:</pre>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

// Decode the JSON response
$decoded = json_decode($response, true);

// Check for JSON decoding errors
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Error decoding JSON response: ' . json_last_error_msg());
}

// Check if the response contains the row data
if (!isset($decoded['values']) || count($decoded['values']) === 0) {
    die('No data found for the specified row index.');
}

// Column mapping
$columns = [
    'orden_de_trabajo', 'marca_temporal', 'fecha_de_solicitud', 'fecha_de_entrega', 'nombre_del_cliente',
    'nit', 'direccion_de_entrega', 'agente_de_ventas', 'descripcion_de_trabajo', 'material',
    'medidas_en_pulgadas', 'adjuntar_cotizacion', 'corte', 'detalles_de_corte', 'bloqueado',
    'detalles_de_bloqueado', 'doblado', 'detalles_de_doblado', 'laminado', 'detalles_de_laminado',
    'lomo', 'detalles_de_lomo', 'numerado', 'detalles_de_numerado', 'pegado', 'detalles_de_pegado',
    'sizado', 'detalles_de_sizado', 'engrapado', 'detalles_de_engrapado', 'troquel', 'detalles_de_troquel',
    'troquel_cameo', 'detalles_de_troquel_cameo', 'observaciones_instrucciones_generales', 'barniz',
    'descripcion_de_barniz', 'impresion_en_blanco', 'descripcion_de_acabado_en_blanco', 'color_de_impresion',
    'direccion_de_correo_electronico', 'tiro_retiro', 'valor_a_facturar', 'archivo_de_arte',
    'despuntados', 'descripcion_de_despuntados', 'ojetes', 'descripcion_de_ojetes', 'perforado',
    'descripcion_de_perforado', 'agregar_datos_contacto', 'contacto_nombre', 'contacto_telefono',
    'contacto_correo_electronico', 'tipo_de_orden'
];

// Function to clean strings and remove line returns
function cleanString($string) {
    // Replace line returns (\n, \r) with a space and remove unwanted characters
    $string = preg_replace('/[\r\n]+/', ' ', $string); // Replace newlines with a space
    $string = preg_replace('/[\'\"\x0B\\\\]/', '', $string); // Remove quotes, backslashes, etc.
    return trim($string); // Trim leading/trailing whitespace
}

// Function to convert "Valor a Facturar" into a decimal value
function cleanAndConvertDecimal($string) {
    $cleaned = preg_replace('/[^\d.]/', '', $string); // Keep only digits and dot
    return is_numeric($cleaned) ? number_format((float)$cleaned, 2, '.', '') : '0.00'; // Format as decimal
}

// Function to convert date to MySQL DATETIME format
function convertToMySQLDatetime($dateString) {
    $dateTime = DateTime::createFromFormat('d/m/Y H:i:s', $dateString);
    return $dateTime ? $dateTime->format('Y-m-d H:i:s') : null;
}

// Process the single row
$row = $decoded['values'][0];

// Ensure row has the correct number of columns
$row_count = count($row);
$expected_count = count($columns);

if ($row_count < $expected_count) {
    $row = array_pad($row, $expected_count, '');
} elseif ($row_count > $expected_count) {
    $row = array_slice($row, 0, $expected_count);
}

// Create mapping of column names to values
$row_data = array_combine($columns, $row);

// Extract values for insertion
$orden_id = cleanString($conn->real_escape_string($row_data['orden_de_trabajo']));
$usuario = cleanString($conn->real_escape_string($row_data['agente_de_ventas']));
$marca_temporal = convertToMySQLDatetime($row_data['marca_temporal']);

if (!$marca_temporal) {
    die("Error: Invalid date format for marca_temporal: " . $row_data['marca_temporal']);
}

// Insert each field from the row into the destination table
foreach ($row_data as $field_name => $field_value) {
    if ($field_name === 'marca_temporal' || $field_name === 'orden_de_trabajo' || $field_name === 'agente_de_ventas') {
        continue;
    }

    $tipo_de_campo = cleanString($conn->real_escape_string($field_name));

    // Special handling for "valor_a_facturar"
    if ($field_name === 'valor_a_facturar') {
        $valor = cleanAndConvertDecimal($field_value);
    } else {
        $valor = cleanString($conn->real_escape_string($field_value));
    }

    // Debugging: Print comparison for each value
    echo "Field: $field_name<br>";
    echo "Spreadsheet: " . htmlspecialchars($field_value) . "<br>";
    echo "Cleaned for Database: " . htmlspecialchars($valor) . "<br><br>";

    $query = "INSERT INTO joomla_ordenes_info_2 (orden_id, tipo_de_campo, valor, usuario, timestamp)
              VALUES ('$orden_id', '$tipo_de_campo', '$valor', '$usuario', '$marca_temporal')";

    if (!$conn->query($query)) {
        echo "Error inserting data: " . $conn->error . "<br>";
    }
}

echo "Row successfully processed and inserted.";

// Close the MySQL connection
$conn->close();
?>
