<?php
// No direct access
defined('_JEXEC') or die;

// Define fallback constant
define('DEFAULT_ORDER_ID', '03562');

// Get the database connection object
$db = JFactory::getDbo();

// Set timezone
date_default_timezone_set('America/Guatemala');
$date = new DateTime();
$formattedDate = $date->format('Y-m-d');

// Check if an order ID is submitted via the button
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'])) {
    $order_id = $_POST['order_id']; // Capture the order ID from the button click
} else {
    // Fallback to last order ID or default order ID if no button was clicked
    $order_id = getLastOrderId() ?? DEFAULT_ORDER_ID;
}

// Function to get the last inserted order ID
function getLastOrderId() {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);
    
    // Build the SQL query to select only the 'orden_de_trabajo' field
    $query->select($db->quoteName('orden_de_trabajo'))
          ->from($db->quoteName('ordenes_de_trabajo'))
          ->order('CAST(' . $db->quoteName('orden_de_trabajo') . ' AS UNSIGNED) DESC');

    $db->setQuery($query);

    try {
        return $db->loadResult();
    } catch (Exception $e) {
        error_log("Error fetching last inserted order ID: " . $e->getMessage());
        return null; // Handle error
    }
}



// Function to get tecnico by order ID
function getTecnicoByOrderId($order_id) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);

    // Replace '*' with actual column names from 'ordenes_info'
    $query->select($db->quoteName(array('valor', 'timestamp'))) // Specify columns
          ->from($db->quoteName('ordenes_info'))
          ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order_id))
          ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('tecnico'));

    $db->setQuery($query);

    try {
        return $db->loadAssocList(); // Fetch all results
    } catch (Exception $e) {
        error_log("Error fetching tecnico by order ID: " . $e->getMessage());
        return null; // Handle error
    }
}


// Function to get production notes by order ID
function getDetallesByOrderId($order_id) {
    $db = JFactory::getDbo();
    $query = $db->getQuery(true);

    // Replace '*' with actual column names from 'ordenes_info'
    $query->select($db->quoteName(array('valor', 'timestamp','usuario'))) // Specify columns
          ->from($db->quoteName('ordenes_info'))
          ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order_id))
          ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('detalles'));

    $db->setQuery($query);

    try {
        return $db->loadAssocList(); // Fetch all results
    } catch (Exception $e) {
        error_log("Error fetching tecnico by order ID: " . $e->getMessage());
        return null; // Handle error
    }
}


// Query to get "externa" order numbers
$externaQuery = $db->getQuery(true);
$externaQuery->select($db->quoteName('numero_de_orden'))
             ->from($db->quoteName('ordenes_info'))
             ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('estado'))
             ->where($db->quoteName('valor') . ' = ' . $db->quote('nueva'))
             ->where($db->quoteName('numero_de_orden') . ' IN (
                 SELECT ' . $db->quoteName('numero_de_orden') . '
                 FROM ' . $db->quoteName('ordenes_info') . '
                 WHERE ' . $db->quoteName('tipo_de_campo') . ' = ' . $db->quote('tipo') . '
                 AND ' . $db->quoteName('valor') . ' = ' . $db->quote('externa') . '
             )');

$db->setQuery($externaQuery);
$externa_orders = $db->loadColumn();

// Query to get "interna" order numbers
$internaQuery = $db->getQuery(true);

$internaQuery->select($db->quoteName('numero_de_orden'))
             ->from($db->quoteName('ordenes_info'))
             ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('estado'))
             ->where($db->quoteName('valor') . ' = ' . $db->quote('nueva'))
             ->where($db->quoteName('numero_de_orden') . ' NOT IN (
                 SELECT ' . $db->quoteName('numero_de_orden') . '
                 FROM ' . $db->quoteName('ordenes_info') . '
                 WHERE ' . $db->quoteName('tipo_de_campo') . ' = ' . $db->quote('tipo') . '
                 AND ' . $db->quoteName('valor') . ' = ' . $db->quote('externa') . '
             )');

$db->setQuery($internaQuery);
$interna_orders = $db->loadColumn();

// Check if there are no orders in either category
if (empty($externa_orders) && empty($interna_orders)) {
    echo "No records found.";
    return;
}

// Start Fetch distinct person names for the checkbox group
$personQuery = $db->getQuery(true);
$personQuery->select('DISTINCT ' . $db->quoteName('personname'))
            ->from($db->quoteName('asistencia'))
            ->where($db->quoteName('authdate') . ' = ' . $db->quote($formattedDate));

$db->setQuery($personQuery);
$persons = $db->loadColumn();

// Handle form submission for selected persons
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_persons'])) {
    // Get the current Joomla user
    $user = JFactory::getUser();
    $current_user = $user->username;

    $selected_persons = $_POST['selected_persons'];

    foreach ($selected_persons as $person_name) {
        if (!empty($person_name)) {
            $insertQuery = $db->getQuery(true);
            $columns = array('numero_de_orden', 'tipo_de_campo', 'valor', 'usuario');
            $values = array(
                $db->quote($order_id),
                $db->quote('tecnico'),
                $db->quote($person_name),
                $db->quote($current_user)
            );

            $insertQuery->insert($db->quoteName('ordenes_info'))
                         ->columns($db->quoteName($columns))
                         ->values(implode(',', $values));

            $db->setQuery($insertQuery);
            try {
                $db->execute();
            } catch (Exception $e) {
                echo "Error inserting record: " . $e->getMessage();
            }
        }
    }
}

// Check for detalles form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_detalles'])) {
    $detalles = isset($_POST['detalles']) ? trim($_POST['detalles']) : '';
    $order_id = $_POST['order_id'];
    $current_user = $user->username;

    if (!empty($detalles)) {
        $insertQuery = $db->getQuery(true);
        $columns = array('numero_de_orden', 'tipo_de_campo', 'valor', 'usuario');
        $values_detalles = array(
            $db->quote($order_id),
            $db->quote('detalles'),
            $db->quote($detalles),
            $db->quote($current_user)
        );

        $insertQuery->insert($db->quoteName('ordenes_info'))
                     ->columns($db->quoteName($columns))
                     ->values(implode(',', $values_detalles));

        $db->setQuery($insertQuery);
        try {
            $db->execute();
        } catch (Exception $e) {
            error_log("Error inserting detalles record: " . $e->getMessage());
            echo "An error occurred while processing your request. Please try again later.";
        }
    }
}

// Check if the estado update form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_estado'])) {
    $order_id = $_POST['order_id'];

    // Get the current timestamp in the required format
    $current_timestamp = date('Y-m-d H:i:s');
    
    // Initialize the query builder object
    $updateQuery = $db->getQuery(true);

    // Build the update query
    $updateQuery->update($db->quoteName('ordenes_info'))
                ->set($db->quoteName('valor') . ' = ' . $db->quote('terminada'))
                ->set($db->quoteName('usuario') . ' = ' . $db->quote($current_user))
                ->set($db->quoteName('timestamp') . ' = ' . $db->quote($current_timestamp))
                ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order_id))
                ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('estado'));

    // Set the query and execute
    $db->setQuery($updateQuery);
    try {
        $db->execute();
    } catch (Exception $e) {
        error_log("Error updating estado record: " . $e->getMessage());
        echo "An error occurred while updating the estado. Please try again later.";
    }
}

// Start Fetch details of the selected order
$query = $db->getQuery(true);

// List all the column names instead of using '*'
$query->select($db->quoteName(array('orden_de_trabajo', 'marca_temporal', 'fecha_de_solicitud', 'fecha_de_entrega', 'nombre_del_cliente', 'nit', 'direccion_de_entrega', 'agente_de_ventas', 'descripcion_de_trabajo', 'material', 'medidas_en_pulgadas', 'adjuntar_cotizacion', 'corte', 'detalles_de_corte', 'bloqueado', 'detalles_de_bloqueado', 'doblado', 'detalles_de_doblado', 'laminado', 'detalles_de_laminado', 'lomo', 'detalles_de_lomo', 'numerado', 'detalles_de_numerado', 'pegado', 'detalles_de_pegado', 'sizado', 'detalles_de_sizado', 'engrapado', 'detalles_de_engrapado', 'troquel', 'detalles_de_troquel', 'troquel_cameo', 'detalles_de_troquel_cameo', 'observaciones_instrucciones_generales', 'barniz', 'descripcion_de_barniz', 'impresion_en_blanco', 'descripcion_de_acabado_en_blanco', 'color_de_impresion', 'direccion_de_correo_electronico', 'tiro_retiro', 'valor_a_facturar', 'archivo_de_arte', 'despuntados', 'descripcion_de_despuntados', 'ojetes', 'contacto_nombre', 'contacto_telefono' )))
      ->from($db->quoteName('ordenes_de_trabajo'))
      ->where($db->quoteName('orden_de_trabajo') . ' = ' . $db->quote($order_id));

$db->setQuery($query);
$row = $db->loadAssoc();

if (!$row) {
    echo "No records found.";
    return;
}

  // Check if the form has been submitted to add a new "externa" record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_externa'])) {
    // Use the $order_id variable for the order number to insert
    $order_id = $_POST['order_id']; // Assuming this comes from the form submission

    // Insert new record into `ordenes_info`
    $insertQuery = $db->getQuery(true);
    $columns = ['numero_de_orden', 'tipo_de_campo', 'valor'];
    $values = [$db->quote($order_id), $db->quote('tipo'), $db->quote('externa')];

    $insertQuery->insert($db->quoteName('ordenes_info'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

    // Execute the insert query
    $db->setQuery($insertQuery);
    try {
        $db->execute();
        echo "Record added successfully!";
    } catch (Exception $e) {
        echo "Failed to add record: " . $e->getMessage();
    }
}
  
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Trabajo</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
        }
        .container {
            display: flex;
            flex-direction: column; /* Layout the buttons and details one below the other */
            width: 100%;
        }
        .buttons {
            display: flex;
            flex-wrap: wrap; /* This allows the buttons to wrap to the next line when needed */
            padding: 10px;
            gap: 10px; /* Optional spacing between buttons */
            justify-content: flex-start; /* Align buttons to the start */
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap; /* Ensures checkboxes are arranged horizontally and wrap onto new lines */
            gap: 10px; /* Optional spacing between checkboxes */
            padding: 10px;
        }
        .details {
            width: 100%;
            padding: 10px;
        }
        h1, h2 {
            text-align: center;
        }
        table {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 5px;
            text-align: left;
        }
        .highlight {
            background-color: yellow; /* Highlight color */
        }
        /* Custom styling for horizontal lines */
        hr {
            border: none;
            height: 2px;
            background-color: #ccc; /* Light grey color */
            margin: 20px 0; /* Spacing above and below the line */
        }
        /* Center the submit button */
        .submit-container {
            width: 100%;
            display: flex;
            justify-content: center; /* Centering horizontally */
            margin-top: 20px; /* Optional margin to add some space above the button */
        }
        /* Style the tab links */
        .tab {
            display: flex;
            cursor: pointer;
            background-color: #f1f1f1;
            padding: 10px 0;
            justify-content: center;
        }
        .tab button {
            background-color: inherit;
            border: none;
            outline: none;
            padding: 14px 20px;
            transition: 0.3s;
            font-size: 17px;
        }
        .tab button:hover {
            background-color: #ddd;
        }
        .tab button.active {
            background-color: #ccc;
        }
        /* Style the tab content */
        .tabcontent {
            display: none;
            padding: 20px;
            border-top: none;
        }
        .tabcontent.active {
            display: block;
        }
      .button-container {
            display: flex;
            justify-content: center; /* Horizontal center */
            align-items: center; /* Vertical center */
            height: 100vh; /* Full viewport height */
        }
    </style>

<body>

<div class="container">
    <!-- Buttons for "externas" ordenes de trabajo -->
    <div class="buttons">
        <h2>Externas</h2>
        <form method="POST" action="" style="display: flex; flex-wrap: wrap;">
            <?php foreach ($externa_orders as $order_number): ?>
                <button type="submit" name="order_id" value="<?php echo htmlspecialchars($order_number); ?>" style="margin: 5px;">
                    <?php echo htmlspecialchars($order_number); ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>

    <!-- Buttons for "internas" ordenes de trabajo -->
    <div class="buttons">
        <h2>Internas</h2>
        <form method="POST" action="" style="display: flex; flex-wrap: wrap;">
            <?php foreach ($interna_orders as $order_number): ?>
                <button type="submit" name="order_id" value="<?php echo htmlspecialchars($order_number); ?>" style="margin: 5px;">
                    <?php echo htmlspecialchars($order_number); ?>
                </button>
            <?php endforeach; ?>
        </form>
    </div>
</div>


    <div class="details">
        <h1>ORDEN DE TRABAJO <?php echo htmlspecialchars($row['orden_de_trabajo']); ?></h1>
        <table>
            <tr>
                <th>Fecha de Solicitud</th>
                <td><?php echo htmlspecialchars($row['marca_temporal']); ?></td>
                <th>Fecha de Entrega</th>
                <td><?php echo htmlspecialchars($row['fecha_de_entrega']); ?></td>
            </tr>
                                <tr>
                <th>Agente de Ventas</th>
                <td colspan="3"><?php echo htmlspecialchars($row['agente_de_ventas']); ?></td>
            </tr>
            <tr>
                <th>Cliente</th>
                <td colspan="3"><?php $entrega_cliente = htmlspecialchars($row['nombre_del_cliente']); echo $entrega_cliente;  ?></td>
            </tr>
                      <tr>
                <th>Trabajo</th>
                <td colspan="3"><?php $entrega_trabajo = htmlspecialchars($row['descripcion_de_trabajo']); echo $entrega_trabajo; ?></td>
            </tr>
 

                      <tr>
                <th>Dirección de Entrega</th>
                <td colspan="3"><?php $entrega_direccion = htmlspecialchars($row['direccion_de_entrega']); echo $entrega_direccion;  ?></td>
            </tr>
        </table>

        <h2>Detalles del Trabajo</h2>
        <table>
            <tr>
                <th>Color</th>
                <td><?php echo htmlspecialchars($row['color_de_impresion']); ?></td>

                <th>Tiro / Retiro</th>
                <td><?php echo htmlspecialchars($row['tiro_retiro']); ?></td>
              </tr>
            <tr>
                <th>Material</th>
                <td><?php echo htmlspecialchars($row['material']); ?></td>
            
                <th>Medidas</th>
                <td><?php echo htmlspecialchars($row['medidas_en_pulgadas']); ?></td>
            </tr>
        </table>


<!-- Tab links -->
<div class="tab">
    <button class="tablinks" onclick="openTab(event, 'Acabados')"><h3>Acabados</h3></button>
    <button class="tablinks" onclick="openTab(event, 'Mano de Obra')">Mano de Obra</button>
    <button class="tablinks" onclick="openTab(event, 'Notas de Produccion')">Notas de Produccion</button>
    <button class="tablinks" onclick="openTab(event, 'Envio')">Envio</button>
</div>
<!-- Tab content -->
<div id="Acabados" class="tabcontent">
    <h2>Acabados</h2>
<table>
    <tr>
        <th>Bloqueado</th>
        <td class="<?php echo ($row['bloqueado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['bloqueado']); ?>
        </td>
        <th>Detalles de Bloqueado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_bloqueado']); ?></td>
    </tr>
    <tr>
        <th>Corte</th>
        <td class="<?php echo ($row['corte'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['corte']); ?>
        </td>
        <th>Detalles de Corte</th>
        <td><?php echo htmlspecialchars($row['detalles_de_corte']); ?></td>
    </tr>
    <tr>
        <th>Doblado</th>
        <td class="<?php echo ($row['doblado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['doblado']); ?>
        </td>
        <th>Detalles de Doblado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_doblado']); ?></td>
    </tr>
    <tr>
        <th>Laminado</th>
        <td class="<?php echo ($row['laminado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['laminado']); ?>
        </td>
        <th>Detalles de Laminado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_laminado']); ?></td>
    </tr>
    <tr>
        <th>Lomo</th>
        <td class="<?php echo ($row['lomo'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['lomo']); ?>
        </td>
        <th>Detalles de Lomo</th>
        <td><?php echo htmlspecialchars($row['detalles_de_lomo']); ?></td>
    </tr>
    <tr>
        <th>Numerado</th>
        <td class="<?php echo ($row['numerado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['numerado']); ?>
        </td>
        <th>Detalles de Numerado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_numerado']); ?></td>
    </tr>
    <tr>
        <th>Pegado</th>
        <td class="<?php echo ($row['pegado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['pegado']); ?>
        </td>
        <th>Detalles de Pegado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_pegado']); ?></td>
    </tr>
    <tr>
        <th>Sizado</th>
        <td class="<?php echo ($row['sizado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['sizado']); ?>
        </td>
        <th>Detalles de Sizado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_sizado']); ?></td>
    </tr>
    <tr>
        <th>Engrapado</th>
        <td class="<?php echo ($row['engrapado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['engrapado']); ?>
        </td>
        <th>Detalles de Engrapado</th>
        <td><?php echo htmlspecialchars($row['detalles_de_engrapado']); ?></td>
    </tr>
    <tr>
        <th>Troquel</th>
        <td class="<?php echo ($row['troquel'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['troquel']); ?>
        </td>
        <th>Detalles de Troquel</th>
        <td><?php echo htmlspecialchars($row['detalles_de_troquel']); ?></td>
    </tr>
    <tr>
        <th>Troquel Cameo</th>
        <td class="<?php echo ($row['troquel_cameo'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['troquel_cameo']); ?>
        </td>
        <th>Detalles de Troquel Cameo</th>
        <td><?php echo htmlspecialchars($row['detalles_de_troquel_cameo']); ?></td>
    </tr>
    <tr>
        <th>Despuntados</th>
        <td class="<?php echo ($row['despuntados'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['despuntados']); ?>
        </td>
        <th>Detalles de Despuntados</th>
        <td><?php echo htmlspecialchars($row['descripcion_de_despuntados']); ?></td>
    </tr>
    <tr>
        <th>Ojetes</th>
        <td class="<?php echo ($row['ojetes'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['ojetes']); ?>
        </td>
        <th>Detalles de Ojetes</th>
        <td><?php echo htmlspecialchars($row['descripcion_de_ojetes']); ?></td>
    </tr>
    <tr>
        <th>Perforado</th>
        <td class="<?php echo ($row['perforado'] === 'SI') ? 'highlight' : ''; ?>">
            <?php echo htmlspecialchars($row['perforado']); ?>
        </td>
        <th>Detalles de Perforado</th>
        <td><?php echo htmlspecialchars($row['descripcion_de_perforado']); ?></td>
    </tr>
</table>
        <h2>Instrucciones / Observaciones</h2>
      <table>
            <tr><td>
               <p><?php echo htmlspecialchars($row['observaciones_instrucciones_generales']); ?></p>
            </td></tr>
        </table>
    </div>

</div>
<div id="Mano de Obra" class="tabcontent">
          <!-- Horizontal line between buttons and checkbox group -->
    <hr>
<?php

// Call the function to get the detalles records
$detalles_records = getTecnicoByOrderId($order_id);

// Check if any records were returned
if (!empty($detalles_records)) {
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Tecnico</th>';  // Updated label
    echo '<th>Fecha - Hora</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // Loop through the results and display each record in a table row
    foreach ($detalles_records as $record) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($record['valor']) . '</td>';      // Use 'valor' for the technician's name
        echo '<td>' . htmlspecialchars($record['timestamp']) . '</td>'; // Use 'timestamp' for the date and time
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
} else {
    // No records found
    echo '<p>Sin tecnicos asignados aun</p>';
}
?>
    <!-- New checkbox group -->
<div class="checkbox-group">
    <form method="POST" action="">
        <!-- Include a hidden input to pass the selected order_id -->
        <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
        <?php foreach ($persons as $person): ?>
        <br>
            <label>
                <input type="checkbox" name="selected_persons[]" value="<?php echo htmlspecialchars($person); ?>">
                <?php echo htmlspecialchars($person); ?>
            </label>
        <?php endforeach; ?>
        <br>
        <div class="submit-container">
            <button type="submit" name="submit_persons">Agregar tecnicos a la orden de trabajo</button>
        </div>
    </form>
</div>

    <!-- Horizontal line between checkbox group and details -->
    <hr>

</div>
<div id="Notas de Produccion" class="tabcontent">
<?php echo htmlspecialchars($order_id); ?>
    <!-- Form for details input -->
<?php
// Call the function to get the detalles records
$detalles_records = getDetallesByOrderId($order_id);

// Check if any records were returned
if (!empty($detalles_records)) {
    echo '<table border="1">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Usuario</th>';
    echo '<th>Fecha - Hora</th>';
    echo '<th>Notas</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // Loop through the results and display each record in a table row
    foreach ($detalles_records as $record) {
        echo '<tr>';
        
        // Adjusted to use 'valor' and 'timestamp' fields based on your previous output
        echo '<td>' . htmlspecialchars($record['usuario']) . '</td>';       // Assuming 'valor' stores user information
        echo '<td>' . htmlspecialchars($record['timestamp']) . '</td>';   // Assuming 'timestamp' stores the datetime
        echo '<td>' . htmlspecialchars($record['valor']) . '</td>';       // Assuming 'notas' holds production notes
        
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
} else {
    // No records found
    echo '<p>Sin notas de produccion aun</p>';
}
?>
 
  <br>
<form method="POST" action="">
    <input type="text" name="detalles" placeholder="Agregar Detalles de produccion" required style="width: 100%;">
    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>"> <!-- Assuming $order_id is available -->
<button type="submit" name="submit_detalles">Agregar detalles de produccion</button>
</form>
  <br>
  <!-- Form to update estado -->
<form method="POST" action="">
    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>"> <!-- Assuming $order_id is available -->
    <button type="submit" name="submit_estado">Cerrar Orden</button>
  <!-- HTML Form for inserting a new "externa" record -->
</form>
<br>
<form method="POST" action="">
    <!-- Pass the $order_id as a hidden input if it's coming from another part of the script -->
    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
    <!-- Submit button to add the "externa" record -->
    <button type="submit" name="add_externa">Marcar orden como Externa</button>
</form>
</div>


<!-- Notas de envio -->
<div id="Envio" class="tabcontent">

<?php
// Get the Joomla database object
$db = JFactory::getDbo();

// Fetch the image if it exists
$imageData = null;
$isEntregaFound = false; // Flag to track if 'entrega' record is found
if (isset($order_id)) {
    // Prepare the query to check for 'historial' and fetch 'imagen_entrega'
    $query = $db->getQuery(true)
        ->select($db->quoteName('valor'))
        ->from($db->quoteName('ordenes_info')) // Adjust the table name with your Joomla table prefix
        ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($order_id))
        ->where($db->quoteName('tipo_de_campo') . ' = ' . $db->quote('imagen_entrega'))
        ->where('EXISTS (
            SELECT 1 
            FROM ' . $db->quoteName('ordenes_info') . '
            WHERE ' . $db->quoteName('numero_de_orden') . ' = ' . $db->quote($order_id) . '
              AND ' . $db->quoteName('tipo_de_campo') . ' = ' . $db->quote('historial') . '
              AND ' . $db->quoteName('valor') . ' = ' . $db->quote('entrega') . '
        )');

    // Execute the query
    $db->setQuery($query);
    $imageData = $db->loadResult();
    $isEntregaFound = !empty($imageData); // Set flag if record is found
}
?>

<!-- Display the image if available -->
<?php if ($imageData): ?>
    <div>
        <h3>Constancia de Entrega:</h3>
        <img src="data:image/jpeg;base64,<?php echo htmlspecialchars($imageData); ?>" alt="Imagen de Entrega" style="max-width: 100%; height: auto; border: 1px solid #ccc;">
    </div>
    <br>
<?php endif; ?>

<!-- Toggle Button -->
<label for="generate_envio">Generar nuevo envio:</label>
<input type="radio" id="yes" name="generate_envio" value="yes" onclick="toggleFormVisibility()" <?php echo $isEntregaFound ? '' : 'checked'; ?>>
<label for="yes">Sí</label>
<input type="radio" id="no" name="generate_envio" value="no" onclick="toggleFormVisibility()" <?php echo $isEntregaFound ? 'checked' : ''; ?>>
<label for="no">No</label>
<br><br>

<!-- Form -->
<form id="envioForm" method="POST" action="<?php echo $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/scripts/ordenes/orden_envio.php'; ?>" target="_blank" style="display: <?php echo $isEntregaFound ? 'none' : 'block'; ?>;">
    <!-- Form Fields Here -->

    <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
    <input type="hidden" name="cliente" value="<?php echo htmlspecialchars($row['nombre_del_cliente']); ?>">
    <input type="hidden" name="agente_ventas" value="<?php echo htmlspecialchars($row['agente_de_ventas']); ?>">
    <label for="envio_tipo">Tipo de Envio:</label>
    <input type="radio" id="completa" name="envio_tipo" value="completa" checked onclick="toggleParcialInput()">
    <label for="completa">Completo</label>
    <input type="radio" id="parcial" name="envio_tipo" value="parcial" onclick="toggleParcialInput()">
    <label for="parcial">Parcial</label>
    <br><br>
    
    <div id="parcialInput" style="display: none;">
        <label for="envio_parcial">Datos de Envio Parcial</label>
        <input type="text" id="envio_descripcion" name="envio_descripcion" value="<?php echo $entrega_trabajo; ?>" style="width: 100%;">
    </div>
    <br>

    <label for="entrega_direccion">Direccion de Envio</label>
    <input type="text" name="entrega_direccion" value="<?php echo htmlspecialchars($row['direccion_de_entrega']); ?>" required style="width: 100%;">
    <label for="contacto">Contacto</label>
    <input type="text" name="contacto" value="<?php echo htmlspecialchars($row['contacto_nombre']); ?>" style="width: 100%;">
    <label for="telefono">Telefono</label>
    <input type="text" name="telefono" value="<?php echo htmlspecialchars($row['contacto_telefono']); ?>" style="width: 100%;">
    <label for="instrucciones">Instrucciones de envio</label>
    <input type="text" name="instrucciones" value=" " style="width: 100%;">
  
    <br><br>
    <button type="submit" name="submit_envio">Confirmar datos de Envio</button>
</form>

<script>
function toggleParcialInput() {
    const parcialInput = document.getElementById("parcialInput");
    const isParcialChecked = document.getElementById("parcial").checked;
    parcialInput.style.display = isParcialChecked ? "block" : "none";
}

function toggleFormVisibility() {
    const form = document.getElementById("envioForm");
    const isYesChecked = document.getElementById("yes").checked;
    form.style.display = isYesChecked ? "block" : "none";
}
</script>

</div>  
  
<script>
    function openTab(evt, tabName) {
        // Get all tab contents and hide them
        var tabcontent = document.getElementsByClassName("tabcontent");
        for (var i = 0; i < tabcontent.length; i++) {
            tabcontent[i].style.display = "none";
        }
        // Get all tab buttons and remove the active class
        var tablinks = document.getElementsByClassName("tablinks");
        for (var i = 0; i < tablinks.length; i++) {
            tablinks[i].className = tablinks[i].className.replace(" active", "");
        }
        // Show the current tab, and add an active class to the clicked tab
        document.getElementById(tabName).style.display = "block";
        evt.currentTarget.className += " active";
    }
    // Set default tab to open
    document.querySelector('.tablinks').click();
</script>

</div>

</body>
</html>


