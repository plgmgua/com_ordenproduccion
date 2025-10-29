<?php
/**
 * Data Validation Script - Compare Asistencia Raw Data vs Summary Table
 * 
 * Validates that summary calculations match raw biometric data
 * Tests: Last 5 days for Cristina Perez, Julio Alvarado, and Nery Ramirez
 */

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__, 3));

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

$app = Factory::getApplication('site');

// Get database
$db = Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);

// Employees to validate
$employees = ['Cristina Perez', 'Julio Alvarado', 'Nery Ramirez'];

// Get last 5 days
$today = new DateTime();
$dates = [];
for ($i = 4; $i >= 0; $i--) {
    $date = clone $today;
    $date->modify("-$i days");
    $dates[] = $date->format('Y-m-d');
}

echo "<html><head><style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
h1 { color: #2196F3; border-bottom: 3px solid #2196F3; padding-bottom: 10px; }
h2 { color: #4CAF50; margin-top: 30px; background: #E8F5E9; padding: 10px; border-left: 4px solid #4CAF50; }
h3 { color: #FF9800; margin-top: 20px; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
th { background: #2196F3; color: white; padding: 12px; text-align: left; font-weight: bold; }
td { padding: 10px; border: 1px solid #ddd; }
tr:nth-child(even) { background: #f9f9f9; }
.raw-data { background: #E3F2FD !important; }
.summary-data { background: #FFF9C4 !important; }
.match { color: green; font-weight: bold; }
.mismatch { color: red; font-weight: bold; }
.info { background: #E1F5FE; padding: 15px; margin: 15px 0; border-left: 4px solid #03A9F4; }
.warning { background: #FFF3E0; padding: 15px; margin: 15px 0; border-left: 4px solid #FF9800; }
.error { background: #FFEBEE; padding: 15px; margin: 15px 0; border-left: 4px solid #F44336; }
.success { background: #E8F5E9; padding: 15px; margin: 15px 0; border-left: 4px solid #4CAF50; }
code { background: #263238; color: #AEDD94; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
.stats { display: inline-block; margin: 5px 10px; padding: 8px 15px; background: #2196F3; color: white; border-radius: 4px; }
</style></head><body>";

echo "<h1>🔍 Data Validation Report - Asistencia Raw Data vs Summary</h1>";

echo "<div class='info'>";
echo "<strong>📅 Validation Period:</strong> Last 5 days<br>";
echo "<strong>👥 Employees:</strong> " . implode(', ', $employees) . "<br>";
echo "<strong>📊 Date Range:</strong> " . $dates[0] . " to " . $dates[count($dates)-1];
echo "</div>";

foreach ($employees as $employeeName) {
    echo "<h2>👤 Employee: $employeeName</h2>";
    
    foreach ($dates as $date) {
        $dayName = date('l', strtotime($date));
        echo "<h3>📆 Date: $date ($dayName)</h3>";
        
        // Get RAW data from asistencia table
        $rawQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('asistencia'))
            ->where($db->quoteName('personname') . ' = ' . $db->quote($employeeName))
            ->where($db->quoteName('authdate') . ' = ' . $db->quote($date))
            ->order($db->quoteName('authtime') . ' ASC');
        
        $db->setQuery($rawQuery);
        $rawRecords = $db->loadObjectList();
        
        // Get SUMMARY data from summary table
        $summaryQuery = $db->getQuery(true)
            ->select('a.*, e.group_id, g.name as group_name, g.work_start_time, g.work_end_time, g.expected_hours, g.weekly_schedule')
            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('a.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employee_groups', 'g') . ' ON ' .
                $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
            )
            ->where($db->quoteName('a.personname') . ' = ' . $db->quote($employeeName))
            ->where($db->quoteName('a.work_date') . ' = ' . $db->quote($date));
        
        $db->setQuery($summaryQuery);
        $summary = $db->loadObject();
        
        // Display RAW DATA
        echo "<div class='raw-data'>";
        echo "<h4>📋 RAW DATA from 'asistencia' table (" . count($rawRecords) . " records)</h4>";
        
        if (!empty($rawRecords)) {
            echo "<table>";
            echo "<tr><th>Time</th><th>Direction</th><th>Device</th></tr>";
            foreach ($rawRecords as $record) {
                echo "<tr>";
                echo "<td><strong>" . substr($record->authtime, 0, 8) . "</strong></td>";
                echo "<td>" . ($record->direction ?? 'N/A') . "</td>";
                echo "<td>" . ($record->devicename ?? 'N/A') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Calculate manually from raw data
            $times = array_map(function($r) { return $r->authtime; }, $rawRecords);
            $firstEntry = min($times);
            $lastExit = max($times);
            
            $first = new DateTime($firstEntry);
            $last = new DateTime($lastExit);
            $diff = $first->diff($last);
            $calculatedHours = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
            
            echo "<div class='info' style='margin-top: 10px;'>";
            echo "<strong>🧮 Manual Calculation from Raw Data:</strong><br>";
            echo "First Entry: <code>$firstEntry</code><br>";
            echo "Last Exit: <code>$lastExit</code><br>";
            echo "Calculated Hours: <code>" . number_format($calculatedHours, 2) . "</code> hours<br>";
            echo "Total Records: <code>" . count($rawRecords) . "</code>";
            echo "</div>";
        } else {
            echo "<div class='warning'>⚠️ No raw records found for this date</div>";
            $firstEntry = null;
            $lastExit = null;
            $calculatedHours = 0;
        }
        echo "</div>";
        
        // Display SUMMARY DATA
        echo "<div class='summary-data' style='margin-top: 20px;'>";
        echo "<h4>📊 SUMMARY DATA from 'joomla_ordenproduccion_asistencia_summary' table</h4>";
        
        if ($summary) {
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Work Date</td><td><strong>" . $summary->work_date . "</strong></td></tr>";
            echo "<tr><td>First Entry</td><td><strong>" . $summary->first_entry . "</strong></td></tr>";
            echo "<tr><td>Last Exit</td><td><strong>" . $summary->last_exit . "</strong></td></tr>";
            echo "<tr><td>Total Hours</td><td><strong>" . number_format($summary->total_hours, 2) . "</strong> hours</td></tr>";
            echo "<tr><td>Expected Hours</td><td><strong>" . number_format($summary->expected_hours, 2) . "</strong> hours</td></tr>";
            echo "<tr><td>Total Entries</td><td><strong>" . $summary->total_entries . "</strong> records</td></tr>";
            echo "<tr><td>Is Complete</td><td><strong>" . ($summary->is_complete ? '✅ Yes' : '❌ No') . "</strong></td></tr>";
            echo "<tr><td>Is Late</td><td><strong>" . ($summary->is_late ? '⏰ Yes' : '✅ No') . "</strong></td></tr>";
            echo "<tr><td>Is Early Exit</td><td><strong>" . ($summary->is_early_exit ? '🏃 Yes' : '✅ No') . "</strong></td></tr>";
            echo "<tr><td>Employee Group</td><td><strong>" . ($summary->group_name ?? 'No Group') . "</strong> (ID: " . ($summary->group_id ?? 'N/A') . ")</td></tr>";
            
            // Show group schedule
            if ($summary->weekly_schedule) {
                $weeklySchedule = json_decode($summary->weekly_schedule, true);
                $dayOfWeek = strtolower(date('l', strtotime($date)));
                $daySchedule = $weeklySchedule[$dayOfWeek] ?? null;
                
                if ($daySchedule && isset($daySchedule['enabled']) && $daySchedule['enabled']) {
                    echo "<tr><td>Day Schedule ($dayName)</td><td>";
                    echo "Start: <code>" . $daySchedule['start_time'] . "</code>, ";
                    echo "End: <code>" . $daySchedule['end_time'] . "</code>, ";
                    echo "Expected: <code>" . $daySchedule['expected_hours'] . "h</code>";
                    echo "</td></tr>";
                } else {
                    echo "<tr><td>Day Schedule ($dayName)</td><td>⚠️ Not enabled or no schedule</td></tr>";
                }
            }
            echo "</table>";
        } else {
            echo "<div class='error'>❌ No summary record found for this date</div>";
        }
        echo "</div>";
        
        // VALIDATION - Compare Raw vs Summary
        echo "<div style='margin-top: 20px;'>";
        echo "<h4>✅ Validation Results</h4>";
        
        if (!empty($rawRecords) && $summary) {
            $issues = [];
            
            // Check first entry
            if (substr($firstEntry, 0, 8) !== $summary->first_entry) {
                $issues[] = "First Entry mismatch: Raw=<code>$firstEntry</code> vs Summary=<code>{$summary->first_entry}</code>";
            }
            
            // Check last exit
            if (substr($lastExit, 0, 8) !== $summary->last_exit) {
                $issues[] = "Last Exit mismatch: Raw=<code>$lastExit</code> vs Summary=<code>{$summary->last_exit}</code>";
            }
            
            // Check total hours (allow 0.01 hour tolerance for rounding)
            $hoursDiff = abs($calculatedHours - $summary->total_hours);
            if ($hoursDiff > 0.02) {
                $issues[] = "Total Hours mismatch: Calculated=<code>" . number_format($calculatedHours, 2) . "</code> vs Summary=<code>" . number_format($summary->total_hours, 2) . "</code> (diff: " . number_format($hoursDiff, 4) . ")";
            }
            
            // Check total entries
            if (count($rawRecords) != $summary->total_entries) {
                $issues[] = "Total Entries mismatch: Raw=<code>" . count($rawRecords) . "</code> vs Summary=<code>{$summary->total_entries}</code>";
            }
            
            if (empty($issues)) {
                echo "<div class='success'>";
                echo "<strong>✅ ALL CHECKS PASSED</strong><br>";
                echo "✓ First Entry matches<br>";
                echo "✓ Last Exit matches<br>";
                echo "✓ Total Hours matches (within tolerance)<br>";
                echo "✓ Total Entries matches<br>";
                echo "<br><strong>🎉 Data is accurate!</strong>";
                echo "</div>";
            } else {
                echo "<div class='error'>";
                echo "<strong>❌ ISSUES FOUND:</strong><br>";
                foreach ($issues as $issue) {
                    echo "• $issue<br>";
                }
                echo "</div>";
            }
        } elseif (empty($rawRecords) && !$summary) {
            echo "<div class='warning'>⚠️ No data in either table for this date (employee might not have worked)</div>";
        } elseif (empty($rawRecords)) {
            echo "<div class='error'>❌ CRITICAL: Summary exists but no raw data found!</div>";
        } else {
            echo "<div class='error'>❌ CRITICAL: Raw data exists but no summary record!</div>";
        }
        echo "</div>";
        
        echo "<hr style='margin: 30px 0; border: 0; border-top: 2px dashed #ccc;'>";
    }
}

// Summary Statistics
echo "<h2>📈 Overall Statistics</h2>";

$totalRawQuery = $db->getQuery(true)
    ->select('COUNT(*) as total')
    ->from($db->quoteName('asistencia'))
    ->where($db->quoteName('personname') . ' IN (' . implode(',', array_map([$db, 'quote'], $employees)) . ')')
    ->where($db->quoteName('authdate') . ' >= ' . $db->quote($dates[0]))
    ->where($db->quoteName('authdate') . ' <= ' . $db->quote($dates[count($dates)-1]));

$db->setQuery($totalRawQuery);
$totalRaw = $db->loadResult();

$totalSummaryQuery = $db->getQuery(true)
    ->select('COUNT(*) as total')
    ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary'))
    ->where($db->quoteName('personname') . ' IN (' . implode(',', array_map([$db, 'quote'], $employees)) . ')')
    ->where($db->quoteName('work_date') . ' >= ' . $db->quote($dates[0]))
    ->where($db->quoteName('work_date') . ' <= ' . $db->quote($dates[count($dates)-1]));

$db->setQuery($totalSummaryQuery);
$totalSummary = $db->loadResult();

echo "<div class='info'>";
echo "<span class='stats'>📋 Total Raw Records: <strong>$totalRaw</strong></span>";
echo "<span class='stats'>📊 Total Summary Records: <strong>$totalSummary</strong></span>";
echo "<span class='stats'>👥 Employees Checked: <strong>" . count($employees) . "</strong></span>";
echo "<span class='stats'>📅 Days Checked: <strong>" . count($dates) . "</strong></span>";
echo "</div>";

echo "<div class='success' style='margin-top: 20px;'>";
echo "<h3>✅ Validation Complete</h3>";
echo "<p>Review the results above to ensure:</p>";
echo "<ul>";
echo "<li>✓ All expected dates have data</li>";
echo "<li>✓ Raw data matches summary calculations</li>";
echo "<li>✓ First entry and last exit times are correct</li>";
echo "<li>✓ Total hours are calculated accurately</li>";
echo "<li>✓ Employee groups and schedules are properly applied</li>";
echo "</ul>";
echo "</div>";

echo "</body></html>";
