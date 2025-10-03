<?php
// Prevent direct access
defined('_JEXEC') or die;

// Load Joomla framework
// Uncomment these lines if running outside Joomla
// define('_JEXEC', 1);
// define('JPATH_BASE', dirname(__FILE__));
// require_once JPATH_BASE . '/includes/defines.php';
// require_once JPATH_BASE . '/includes/framework.php';

// Get the database object
$db = JFactory::getDbo();

// SQL query to get the personname from the asistencia table
$query = $db->getQuery(true); // Create a new query object
$query->select('DISTINCT ' . $db->quoteName('personname'))
      ->from($db->quoteName('asistencia'))
      ->where($db->quoteName('authdate') . ' = CURDATE()');

// Execute the query
$db->setQuery($query);

try {
    // Load the results as an array of values
    $personNames = $db->loadColumn();
} catch (Exception $e) {
    // Handle error and display the message
    echo 'Error: ' . $e->getMessage();
    exit; // Stop execution
}

// You can use the $personNames array as needed
?>

<!-- HTML part for dropdown -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Person Dropdown</title>
</head>
<body>
    <form method="post" action="your_form_action.php"> <!-- Update the action URL -->
        <label for="person">Select a person:</label>
        <select name="person" id="person">
            <?php
            // Check if there are names to display
            if (!empty($personNames)) {
                // Loop through the array and create dropdown options
                foreach ($personNames as $person) {
                    echo "<option value=\"" . htmlspecialchars($person) . "\">" . htmlspecialchars($person) . "</option>";
                }
            } else {
                echo "<option>No person available today</option>";
            }
            ?>
        </select>
        <input type="submit" value="Submit">
    </form>
</body>
</html>
