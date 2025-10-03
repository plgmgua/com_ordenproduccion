<?php

inclde('/var/www/grimpsa_webserver/scripts/ordenes/tecnicos_de_hoy.php');
?>

<!-- activas.php -->
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
