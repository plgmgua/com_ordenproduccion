<?php
defined('_JEXEC') or die('Restricted access'); // Ensures the script is accessed via Joomla

/*
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
}
*/



include('/var/www/grimpsa_webserver/scripts/ordenes/ordenes_to_excel.php'); // Include the Excel generation script

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $year = isset($_POST['year']) ? (int)$_POST['year'] : null;
    $month = isset($_POST['month']) ? sprintf('%02d', (int)$_POST['month']) : null;
    $selectedDates = isset($_POST['dates']) ? $_POST['dates'] : [];

    if ($year && $month && !empty($selectedDates)) {
        try {
            $db = JFactory::getDbo();

            // Query to fetch data
            $query = $db->getQuery(true)
                ->select([
                    'STR_TO_DATE(SUBSTRING_INDEX(' . $db->quoteName('marca_temporal') . ', " ", 1), "%d/%m/%Y") AS formatted_date',
                    $db->quoteName('agente_de_ventas'),
                    $db->quoteName('nombre_del_cliente'),
                    $db->quoteName('orden_de_trabajo'),
                    'CAST(REPLACE(REPLACE(SUBSTRING_INDEX(' . $db->quoteName('valor_a_facturar') . ', " ", -1), "Q.", ""), ",", "") AS DECIMAL(10,2)) AS valor_decimal'
                ])
                ->from($db->quoteName('ordenes_de_trabajo'))
                ->where(
                    'STR_TO_DATE(SUBSTRING_INDEX(' . $db->quoteName('marca_temporal') . ', " ", 1), "%d/%m/%Y") IN (' .
                    implode(',', array_map([$db, 'quote'], $selectedDates)) . ')'
                );

            $db->setQuery($query);
            $results = $db->loadAssocList();

            if (!empty($results)) {
                // Get the start and end dates for file naming
                $startDate = reset($selectedDates);
                $endDate = end($selectedDates);

                // Generate the Excel file using the external script
                $excelFileName = generateExcelFileWithPivot($results, $startDate, $endDate);

                // Display the download link at the top
                echo "<p><a href='/excel_tmp/{$excelFileName}' download style='font-size: 18px; font-weight: bold; color: blue;'>Download Excel File</a></p>";

                // Prepare HTML pivot table structure
                echo "<h3>Pivot Table Results</h3>";
                echo "<table border='1' cellpadding='5' cellspacing='0' style='border-collapse: collapse;'>";
                echo "<thead>";
                echo "<tr>";
                echo "<th style='border: 2px solid black; white-space: nowrap; width: 150px;'>Fecha</th>"; // Fixed width for date
                echo "<th>Agente de Ventas</th>";
                echo "<th>Cliente</th>";
                echo "<th style='border: 1px solid lightgray;'>Orden #</th>";
                echo "<th style='border: 1px solid lightgray;'>Valor a facturar</th>";
                echo "<th>Total Agente</th>";
                echo "<th>Total Fecha</th>";
                echo "<th>Total Summary</th>";
                echo "</tr>";
                echo "</thead>";
                echo "<tbody>";

                // Process results into a pivot-style format
                $pivotData = [];
                $dailyTotals = [];

                foreach ($results as $row) {
                    $date = $row['formatted_date'];
                    $agent = $row['agente_de_ventas'];
                    $client = $row['nombre_del_cliente'];
                    $workOrder = $row['orden_de_trabajo'];
                    $value = (float)$row['valor_decimal'];

                    if (!isset($pivotData[$date])) {
                        $pivotData[$date] = [];
                        $dailyTotals[$date] = 0;
                    }

                    if (!isset($pivotData[$date][$agent])) {
                        $pivotData[$date][$agent] = ['clients' => [], 'agentTotal' => 0];
                    }

                    if (!isset($pivotData[$date][$agent]['clients'][$client])) {
                        $pivotData[$date][$agent]['clients'][$client] = [];
                    }

                    $pivotData[$date][$agent]['clients'][$client][$workOrder] = $value;

                    // Calculate totals
                    $pivotData[$date][$agent]['agentTotal'] += $value;
                    $dailyTotals[$date] += $value;
                }

                // Render pivot table rows
                foreach ($pivotData as $date => $agentsData) {
                    $firstDateRow = true;
                    foreach ($agentsData as $agent => $agentData) {
                        $firstAgentRow = true;
                        foreach ($agentData['clients'] as $client => $workOrdersData) {
                            $firstClientRow = true;
                            foreach ($workOrdersData as $workOrder => $value) {
                                echo "<tr>";
                                if ($firstDateRow) {
                                    echo "<td style='border: 2px solid black; white-space: nowrap;' rowspan='" . (array_sum(array_map(function ($agent) {
                                        return array_sum(array_map('count', $agent['clients']));
                                    }, $agentsData)) + count($agentsData) + 1) . "'>{$date}</td>";
                                    $firstDateRow = false;
                                }
                                if ($firstAgentRow) {
                                    echo "<td rowspan='" . (array_sum(array_map('count', $agentData['clients'])) + 1) . "' style='border: 2px solid black;'>{$agent}</td>";
                                    $firstAgentRow = false;
                                }
                                if ($firstClientRow) {
                                    echo "<td rowspan='" . count($workOrdersData) . "' style='border: 2px solid black;'>{$client}</td>";
                                    $firstClientRow = false;
                                }

                                // Add the link for the work order
                                echo "<td style='border: 1px solid lightgray; text-align: center;'>
                                    <form action='/index.php/ordenes-activas' method='POST' target='_blank' style='margin: 0;'>
                                        <input type='hidden' name='order_id' value='{$workOrder}' />
                                        <button type='submit' style='background: none; border: none; color: blue; text-decoration: underline; cursor: pointer;'>{$workOrder}</button>
                                    </form>
                                </td>";

                                echo "<td style='border: 1px solid lightgray;'>" . number_format($value, 2, '.', ',') . "</td>";
                                echo "<td></td>";
                                echo "<td></td>";
                                echo "<td></td>";
                                echo "</tr>";
                            }
                        }

                        // Agent total row
                        echo "<tr>";
                        echo "<td colspan='4' style='border: 2px solid black;'><strong>Total Agente: {$agent}</strong></td>";
                        echo "<td style='border: 2px solid black;'><strong>" . number_format($agentData['agentTotal'], 2, '.', ',') . "</strong></td>";
                        echo "<td style='border: 2px solid black;' colspan='2'></td>";
                        echo "</tr>";
                    }

                    // Date total row
                    echo "<tr>";
                    echo "<td colspan='5' style='border: 2px solid black;'><strong>Total Fecha: {$date}</strong></td>";
                    echo "<td style='border: 2px solid black;'><strong>" . number_format($dailyTotals[$date], 2, '.', ',') . "</strong></td>";
                    echo "<td style='border: 2px solid black;'><strong>" . number_format($dailyTotals[$date], 2, '.', ',') . "</strong></td>";
                    echo "</tr>";
                }

                echo "</tbody>";
                echo "</table>";
            } else {
                echo "<p>No records found for the selected dates.</p>";
            }
        } catch (Exception $e) {
            echo "<p>Database query error: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    } else {
        echo "<p>Year, month, or selected dates are missing.</p>";
    }
} else {
    echo "<p>Invalid request method.</p>";
}
