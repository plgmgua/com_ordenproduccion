<?php
// Import Joomla framework
defined('_JEXEC') or die;

// Get the input data from the request
$input = JFactory::getApplication()->input;
$data = json_decode(file_get_contents('php://input'), true);

// Check if the data is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    // Return an error response if JSON is invalid
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    http_response_code(400);
    exit;
}

// Get the database object
$db = JFactory::getDbo();

// Prepare the query to insert data into the 'asistencia' table
$query = $db->getQuery(true);
$query->insert($db->quoteName('asistencia')) // Assuming the Joomla table prefix is used
      ->columns([
          $db->quoteName('authdatetime'),
          $db->quoteName('authdate'),
          $db->quoteName('authtime'),
          $db->quoteName('direction'),
          $db->quoteName('devicename'),
          $db->quoteName('deviceserialno'),
          $db->quoteName('personname'),
          $db->quoteName('cardno')
      ])
      ->values(implode(',', [
          $db->quote($data['AuthDatetime']),
          $db->quote($data['AuthDate']),
          $db->quote($data['AuthTime']),
          $db->quote($data['Direction']),
          $db->quote($data['DeviceName']),
          $db->quote($data['DeviceSerialNo']),
          $db->quote($data['PersonName']),
          $db->quote($data['CardNo'])
      ]));

// Execute the query
try {
    $db->setQuery($query);
    $db->execute();

    // Return a success response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'message' => 'Record saved successfully']);
    http_response_code(200);
} catch (Exception $e) {
    // Return an error response if something goes wrong
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    http_response_code(500);
}
?>
