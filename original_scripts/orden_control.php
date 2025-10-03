<?php
// Prevent direct access
defined('_JEXEC') or die;

// Import Joomla's Factory class
use Joomla\CMS\Factory;

// Get the application and database objects
$app = Factory::getApplication();
$db = Factory::getDbo();

// Get the current year and month, either from POST or default to current
$currentYear = $app->input->getInt('year', date('Y'));
$currentMonth = $app->input->getInt('month', date('m'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if year and month are in POST request
    if (isset($_POST['year']) && isset($_POST['month'])) {
        $currentYear = (int)$_POST['year'];
        $currentMonth = (int)$_POST['month'];
    }
}

// Calculate days in month and first day of the month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfMonth = date('w', strtotime("$currentYear-$currentMonth-01"));

// Query to get counts for each day of the month
$query = $db->setQuery(
    "SELECT CONCAT(
        SUBSTRING_INDEX(a.orden_fecha, '/', -1), '-', -- Year
        LPAD(SUBSTRING_INDEX(SUBSTRING_INDEX(a.orden_fecha, '/', 2), '/', -1), 2, '0'), '-', -- Month
        LPAD(SUBSTRING_INDEX(a.orden_fecha, '/', 1), 2, '0') -- Day
    ) AS formatted_date, 
    a.contador
    FROM (
        SELECT TRIM(orden_fecha) AS orden_fecha, COUNT(orden_fecha) AS contador
        FROM (
            SELECT orden_de_trabajo AS orden_numero, marca_temporal, SUBSTRING_INDEX(marca_temporal,' ',1) AS orden_fecha
            FROM ordenes_de_trabajo
        ) AS subquery
        GROUP BY orden_fecha
    ) AS a 
    ORDER BY formatted_date DESC"
);

// Execute the query and fetch the results
$results = $db->loadObjectList();

// Convert results into an array (formatted_date => contador)
$counts = [];
if ($results) {
    foreach ($results as $result) {
        $counts[$result->formatted_date] = $result->contador;
    }
}

// Find the maximum occurrences for scaling
$maxOccurrences = !empty($counts) ? max($counts) : 0;

// Year and month options
$yearOptions = range($currentYear - 10, $currentYear + 10);
$monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendario</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 5px;
            max-width: 700px;
            margin: 20px auto;
        }
        .calendar-header, .day-cell {
            padding: 5px;
            border: 1px solid #000;
            background-color: #f9f9f9;
        }
        .calendar-header {
            font-weight: bold;
            background-color: #ddd;
        }
        .day-cell {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-template-rows: 1fr 1fr;
            border: 1px dotted lightgray;
            aspect-ratio: 1;
            position: relative;
        }
        .day-cell > div {
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        .day-cell .top-left {
            font-size: 18px;
            font-weight: bold;
            justify-content: center;
            align-items: center;
            color: #000;
        }
        .day-cell .top-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding: 5px;
        }
        .day-cell .bottom-left,
        .day-cell .bottom-right {
            font-size: 14px;
            font-weight: normal;
        }
        .form-controls {
            margin: 20px auto;
        }
        select, button, input[type="checkbox"] {
            padding: 5px;
            font-size: 14px;
        }
        .select-all {
            margin: 10px 0;
            font-size: 16px;
            font-weight: bold;
        }
    </style>
    <script>
        // Function to toggle all checkboxes
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.day-cell input[type="checkbox"]');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
    </script>
</head>
<body>
    <h1>Calendario - <?= $monthNames[$currentMonth - 1] ?> <?= $currentYear ?></h1>

    <!-- Form for Year and Month Selectors -->
    <form method="POST" action="" class="form-controls">
        <label for="year">Año:</label>
        <select name="year" id="year">
            <?php foreach ($yearOptions as $year): ?>
                <option value="<?= $year ?>" <?= $year == $currentYear ? 'selected' : '' ?>><?= $year ?></option>
            <?php endforeach; ?>
        </select>

        <label for="month">Mes:</label>
        <select name="month" id="month">
            <?php foreach ($monthNames as $index => $month): ?>
                <option value="<?= sprintf('%02d', $index + 1) ?>" <?= ($index + 1) == $currentMonth ? 'selected' : '' ?>><?= $month ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" name="update_calendar" id="update_calendar">Actualizar</button>
    </form>

    <!-- Calendar Display and Selection Form -->
    <form method="POST" action="/index.php?option=com_content&view=article&id=5">
        <!-- "Seleccionar todo" checkbox -->
        <label class="select-all">
            <input type="checkbox" id="select_all" onchange="toggleSelectAll(this)">
            Seleccionar todo
        </label>
        <div class="calendar">
            <!-- Calendar Headers -->
            <?php $weekdays = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb']; ?>
            <?php foreach ($weekdays as $weekday): ?>
                <div class="calendar-header"><?= $weekday ?></div>
            <?php endforeach; ?>

            <!-- Empty cells for days before the first day of the month -->
            <?php for ($i = 0; $i < $firstDayOfMonth; $i++): ?>
                <div class="day-cell"></div>
            <?php endfor; ?>

            <!-- Calendar Days -->
            <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
                <?php
                $currentDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                $countPerDay = $counts[$currentDate] ?? 0;

                // Calculate the background color intensity (green gradient)
                $intensity = $maxOccurrences > 0 ? intval(255 - ($countPerDay / $maxOccurrences) * 200) : 255;
                $backgroundColor = sprintf("rgb(%d, 255, %d)", $intensity, $intensity);
                ?>
                <div class="day-cell" style="background-color: <?= $backgroundColor ?>;">
                    <div class="top-left"><?= $day ?></div>
                    <div class="top-right">
                        <input type="checkbox" name="dates[]" value="<?= $currentDate ?>">
                    </div>
                    <div class="bottom-left"><?= $countPerDay ?></div>
                    <div class="bottom-right">0</div>
                </div>
            <?php endfor; ?>
        </div>
        <input type="hidden" name="year" value="<?= $currentYear ?>">
        <input type="hidden" name="month" value="<?= $currentMonth ?>">
        <button type="submit" name="submit_dates" id="submit_dates">Enviar</button>
    </form>
</body>
</html>
