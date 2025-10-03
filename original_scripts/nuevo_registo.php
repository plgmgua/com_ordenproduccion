<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MySQL connection parameters
$host = 'localhost';
$db = 'grimpsa_prod';  // Replace with your database name
$user = 'joomla';     // Replace with your MySQL username
$pass = 'Blob-Repair-Commodore6';     // Replace with your MySQL password

// Create a connection using mysqli
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Retrieve the input from the query parameter
$row_index = isset($_GET['row_index']) ? $_GET['row_index'] : null;

// Ensure there is a row index before proceeding
if (empty($row_index)) {
    die('No input provided, exiting script.');
}

// Set up the Google Sheets API URL
$row_ranges = 'A'.$row_index.':BC'.$row_index;
$data_url = 'https://sheets.googleapis.com/v4/spreadsheets/1eknuxDla8v7ccsYJbYoRVhx0lLUL_MgEJkU-iWNuIfY/values/ordenes-produccion!'.$row_ranges.'?key=AIzaSyC2y9oqMWDwIqaTPLoU73wRvgPH2Y-YyP0';

// Initialize cURL
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => $data_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10
));

$response = curl_exec($curl);
curl_close($curl);

// Decode the JSON response
$decoded = json_decode($response, true);

// Log the full response for debugging
error_log(print_r($decoded, true));
//var_dump($decoded);

echo "<pre>";
   print_r($decoded);
echo "</pre>";

// Check if the response contains data
if (isset($decoded['values']) && count($decoded['values']) > 0) {
    // Prepare the query to insert data
    $columns = [
        'orden_de_trabajo','marca_temporal', 'fecha_de_solicitud', 'fecha_de_entrega', 'nombre_del_cliente', 
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

    // Prepare the query to insert data
    $query = "INSERT INTO ordenes_de_trabajo (" . implode(',', $columns) . ") VALUES ";
    $values_arr = [];

    foreach ($decoded['values'] as $row) {
        // Ensure the row has exactly 55 columns
        while (count($row) < 55) {
            $row[] = ''; // Add empty strings for missing columns
        }

        // Check if the row has more than 55 columns and truncate if necessary
        if (count($row) > 55) {
            echo 'Row has too many columns, truncating.<br>';
            $row = array_slice($row, 0, 55); // Keep only the first 55 values
        }

        // Bind values to columns
        $values = [];
        for ($i = 0; $i < 55; $i++) {
            $values[] = $row[$i]; // Get value, empty strings will fill in missing columns
        }

        // Escape values for SQL
        $escaped_values = array_map(function($value) use ($conn) {
            return "'" . $conn->real_escape_string($value) . "'";
        }, $values);

        $values_arr[] = "(" . implode(",", $escaped_values) . ")";
    }

    if (!empty($values_arr)) {
        $query .= implode(',', $values_arr) . ";";
echo $query;
        // Execute the query
        if ($conn->query($query)) {
            echo 'Data successfully inserted into the database.';
        } else {
            echo 'Error inserting data: ' . $conn->error;
        }
    } else {
        echo 'No valid rows to insert.';
    }
} else {
    echo 'No data found or invalid response format.';
}
  
// Close the MySQL connection
$conn->close();
?>
