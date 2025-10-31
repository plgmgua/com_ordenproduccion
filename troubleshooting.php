<?php
/**
 * Compact Data Validation Script - Last 2 Weeks
 * Compares raw biometric data vs summary table
 */

define('_JEXEC', 1);
define('JPATH_BASE', dirname(__DIR__, 3));

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;

$app = Factory::getApplication('site');
$db = Factory::getContainer()->get(Joomla\Database\DatabaseInterface::class);

// Employees to validate
$employees = ['Cristina Perez', 'Julio Alvarado', 'Nery Ramirez'];

// Get last 2 weeks (14 days)
$today = new DateTime();
$dates = [];
for ($i = 13; $i >= 0; $i--) {
    $date = clone $today;
    $date->modify("-$i days");
    $dates[] = $date->format('Y-m-d');
}

echo "<html><head><style>
body { font-family: 'Courier New', monospace; margin: 20px; background: white; font-size: 11px; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th { background: #333; color: white; padding: 6px 4px; text-align: left; font-size: 10px; border: 1px solid #000; }
td { padding: 4px; border: 1px solid #ccc; font-size: 10px; }
.ok { background: #d4edda; }
.error { background: #f8d7da; font-weight: bold; }
.warning { background: #fff3cd; }
.nodata { background: #e2e3e5; color: #666; }
h3 { margin: 15px 0 5px 0; font-size: 13px; }
.summary { background: #f0f0f0; padding: 10px; margin: 10px 0; }
</style></head><body>";

echo "<h3>📊 COMPACT VALIDATION REPORT - Last 2 Weeks</h3>";
echo "<div class='summary'>";
echo "Period: <strong>{$dates[0]}</strong> to <strong>{$dates[count($dates)-1]}</strong> | ";
echo "Employees: <strong>" . implode(', ', $employees) . "</strong>";
echo "</div>";

// Collect validation data
foreach ($employees as $employeeName) {
    echo "<h3>👤 {$employeeName}</h3>";
    echo "<table>";
    echo "<tr>";
    echo "<th>Date</th>";
    echo "<th>Day</th>";
    echo "<th>Raw Punches</th>";
    echo "<th>First In</th>";
    echo "<th>Last Out</th>";
    echo "<th>Calc Hrs</th>";
    echo "<th>Sum Hrs</th>";
    echo "<th>Exp Hrs</th>";
    echo "<th>Status</th>";
    echo "<th>Validation</th>";
    echo "</tr>";
    
    foreach ($dates as $date) {
        $dayName = date('D', strtotime($date));
        
        // Get RAW data
        $rawQuery = $db->getQuery(true)
            ->select('authtime')
            ->from($db->quoteName('asistencia'))
            ->where($db->quoteName('personname') . ' = ' . $db->quote($employeeName))
            ->where($db->quoteName('authdate') . ' = ' . $db->quote($date))
            ->order($db->quoteName('authtime') . ' ASC');
        
        $db->setQuery($rawQuery);
        $rawTimes = $db->loadColumn();
        
        // Get SUMMARY data
        $summaryQuery = $db->getQuery(true)
            ->select('a.*, e.group_id')
            ->from($db->quoteName('joomla_ordenproduccion_asistencia_summary', 'a'))
            ->leftJoin(
                $db->quoteName('joomla_ordenproduccion_employees', 'e') . ' ON ' .
                $db->quoteName('a.personname') . ' = ' . $db->quoteName('e.personname')
            )
            ->where($db->quoteName('a.personname') . ' = ' . $db->quote($employeeName))
            ->where($db->quoteName('a.work_date') . ' = ' . $db->quote($date));
        
        $db->setQuery($summaryQuery);
        $summary = $db->loadObject();
        
        // Calculate from raw
        $calcHours = 0;
        $firstIn = '';
        $lastOut = '';
        $rawCount = count($rawTimes);
        
        if ($rawCount > 0) {
            $firstIn = substr($rawTimes[0], 0, 5);
            $lastOut = substr($rawTimes[$rawCount - 1], 0, 5);
            
            $first = new DateTime($rawTimes[0]);
            $last = new DateTime($rawTimes[$rawCount - 1]);
            $diff = $first->diff($last);
            $calcHours = $diff->h + ($diff->i / 60);
        }
        
        // Determine status and validation
        $status = 'N/A';
        $validation = '';
        $rowClass = 'nodata';
        
        if ($rawCount > 0 || $summary) {
            if (!$summary) {
                $status = 'NO SUMMARY';
                $validation = '❌ Missing Summary';
                $rowClass = 'error';
            } elseif ($rawCount == 0) {
                $status = 'NO RAW DATA';
                $validation = '❌ Missing Raw';
                $rowClass = 'error';
            } else {
                // Compare data
                $firstMatch = (substr($rawTimes[0], 0, 8) === $summary->first_entry);
                $lastMatch = (substr($rawTimes[$rawCount-1], 0, 8) === $summary->last_exit);
                $hoursMatch = (abs($calcHours - $summary->total_hours) < 0.02);
                $countMatch = ($rawCount == $summary->total_entries);
                
                if ($firstMatch && $lastMatch && $hoursMatch && $countMatch) {
                    $status = $summary->is_complete ? '✅ Complete' : '⚠️ Incomplete';
                    $validation = '✅ OK';
                    $rowClass = 'ok';
                } else {
                    $status = 'MISMATCH';
                    $validation = '';
                    if (!$firstMatch) $validation .= '❌First ';
                    if (!$lastMatch) $validation .= '❌Last ';
                    if (!$hoursMatch) $validation .= '❌Hours ';
                    if (!$countMatch) $validation .= '❌Count ';
                    $rowClass = 'error';
                }
            }
        }
        
        echo "<tr class='{$rowClass}'>";
        echo "<td>{$date}</td>";
        echo "<td>{$dayName}</td>";
        echo "<td>" . ($rawCount > 0 ? $rawCount : '-') . "</td>";
        echo "<td>" . ($firstIn ?: '-') . "</td>";
        echo "<td>" . ($lastOut ?: '-') . "</td>";
        echo "<td>" . ($calcHours > 0 ? number_format($calcHours, 2) : '-') . "</td>";
        echo "<td>" . ($summary ? number_format($summary->total_hours, 2) : '-') . "</td>";
        echo "<td>" . ($summary ? number_format($summary->expected_hours, 1) : '-') . "</td>";
        echo "<td>{$status}</td>";
        echo "<td>{$validation}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// Overall summary
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

echo "<div class='summary' style='margin-top: 20px;'>";
echo "<strong>TOTALS:</strong> ";
echo "Raw Punch Records: <strong>{$totalRaw}</strong> | ";
echo "Summary Records: <strong>{$totalSummary}</strong> | ";
echo "Employees: <strong>" . count($employees) . "</strong> | ";
echo "Days: <strong>" . count($dates) . "</strong>";
echo "</div>";

echo "<div class='summary'>";
echo "<strong>Legend:</strong> ";
echo "✅ OK = All data matches | ";
echo "❌ = Mismatch or missing data | ";
echo "⚠️ Incomplete = Less than expected hours | ";
echo "- = No data";
echo "</div>";

// ============================================
// MANUAL ENTRIES DIAGNOSTIC SECTION
// ============================================
echo "<hr style='margin: 30px 0; border: 2px solid #333;'>";
echo "<h3>🔍 MANUAL ENTRIES DIAGNOSTIC</h3>";

// Get date parameter or use today
$testDate = $_GET['date'] ?? date('Y-m-d');

echo "<div class='summary'>";
echo "<strong>Testing Date:</strong> <strong>{$testDate}</strong> | ";
echo "<form method='GET' style='display: inline;'>";
echo "<input type='date' name='date' value='{$testDate}' style='margin: 0 5px;'>";
echo "<button type='submit'>Change Date</button>";
echo "</form>";
echo "</div>";

// Query 1: Check manual entries
echo "<h4>1. Manual Entries in Manual Table</h4>";
try {
    $manualQuery = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__ordenproduccion_asistencia_manual'))
        ->where('DATE(' . $db->quoteName('authdate') . ') = ' . $db->quote($testDate))
        ->order($db->quoteName('created') . ' DESC')
        ->setLimit(20);
    
    $db->setQuery($manualQuery);
    $manualEntries = $db->loadObjectList();
    
    if (empty($manualEntries)) {
        echo "<p class='warning'>⚠️ No manual entries found for {$testDate}</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Person</th><th>Card No</th><th>Date</th><th>Time</th><th>Direction</th><th>Created</th><th>Created By</th></tr>";
        foreach ($manualEntries as $entry) {
            echo "<tr>";
            echo "<td>{$entry->id}</td>";
            echo "<td>{$entry->personname}</td>";
            echo "<td>" . ($entry->cardno ?: '-') . "</td>";
            echo "<td>{$entry->authdate}</td>";
            echo "<td>{$entry->authtime}</td>";
            echo "<td>{$entry->direction}</td>";
            echo "<td>{$entry->created}</td>";
            echo "<td>{$entry->created_by}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p>✅ Found <strong>" . count($manualEntries) . "</strong> manual entries</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error querying manual entries: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Query 2: Check summaries for that date
echo "<h4>2. Summary Records for {$testDate}</h4>";
try {
    $summaryQuery = $db->getQuery(true)
        ->select('s.*, e.group_id, g.name AS group_name')
        ->from($db->quoteName('#__ordenproduccion_asistencia_summary', 's'))
        ->leftJoin($db->quoteName('#__ordenproduccion_employees', 'e') . ' ON s.personname = e.personname')
        ->leftJoin($db->quoteName('#__ordenproduccion_employee_groups', 'g') . ' ON e.group_id = g.id')
        ->where($db->quoteName('s.work_date') . ' = ' . $db->quote($testDate))
        ->order($db->quoteName('s.personname') . ' ASC');
    
    $db->setQuery($summaryQuery);
    $summaries = $db->loadObjectList();
    
    if (empty($summaries)) {
        echo "<p class='error'>❌ No summary records found for {$testDate}</p>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Person</th><th>Card No</th><th>Date</th><th>Entry</th><th>Exit</th><th>Hours</th><th>Group</th><th>Approval</th></tr>";
        foreach ($summaries as $summary) {
            echo "<tr>";
            echo "<td>{$summary->id}</td>";
            echo "<td>{$summary->personname}</td>";
            echo "<td>" . ($summary->cardno ?: '-') . "</td>";
            echo "<td>{$summary->work_date}</td>";
            echo "<td>" . ($summary->first_entry ?: '-') . "</td>";
            echo "<td>" . ($summary->last_exit ?: '-') . "</td>";
            echo "<td><strong>" . number_format($summary->total_hours, 2) . "h</strong></td>";
            echo "<td>" . ($summary->group_name ?: '<span style="color:red">NO GROUP</span>') . "</td>";
            echo "<td>" . ($summary->approval_status ?? 'pending') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "<p>✅ Found <strong>" . count($summaries) . "</strong> summary records</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error querying summaries: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Query 3: Test UNION query
echo "<h4>3. UNION Query Test (Combined asistencia + manual)</h4>";
try {
    $unionTestQuery = "
        SELECT 
            CAST(cardno AS CHAR) COLLATE utf8mb4_unicode_ci AS cardno,
            CAST(personname AS CHAR) COLLATE utf8mb4_unicode_ci AS personname,
            CAST(authdate AS CHAR) COLLATE utf8mb4_unicode_ci AS authdate,
            CAST(authtime AS CHAR) COLLATE utf8mb4_unicode_ci AS authtime,
            CAST(direction AS CHAR) COLLATE utf8mb4_unicode_ci AS direction,
            'biometric' AS source
        FROM asistencia
        WHERE DATE(CAST(authdate AS DATE)) = " . $db->quote($testDate) . "
        UNION ALL
        SELECT 
            CAST(cardno AS CHAR) COLLATE utf8mb4_unicode_ci AS cardno,
            CAST(personname AS CHAR) COLLATE utf8mb4_unicode_ci AS personname,
            CAST(authdate AS CHAR) COLLATE utf8mb4_unicode_ci AS authdate,
            CAST(authtime AS CHAR) COLLATE utf8mb4_unicode_ci AS authtime,
            CAST(direction AS CHAR) COLLATE utf8mb4_unicode_ci AS direction,
            'manual' AS source
        FROM " . $db->quoteName('#__ordenproduccion_asistencia_manual') . "
        WHERE state = 1
        AND DATE(CAST(authdate AS DATE)) = " . $db->quote($testDate) . "
        ORDER BY personname, authtime
        LIMIT 50";
    
    $db->setQuery($unionTestQuery);
    $combined = $db->loadObjectList();
    
    if (empty($combined)) {
        echo "<p class='warning'>⚠️ UNION query returned no results for {$testDate}</p>";
    } else {
        echo "<table>";
        echo "<tr><th>Person</th><th>Date</th><th>Time</th><th>Direction</th><th>Source</th></tr>";
        foreach ($combined as $row) {
            $sourceColor = $row->source === 'manual' ? 'style="background: #d4edda;"' : '';
            echo "<tr {$sourceColor}>";
            echo "<td>{$row->personname}</td>";
            echo "<td>{$row->authdate}</td>";
            echo "<td>{$row->authtime}</td>";
            echo "<td>{$row->direction}</td>";
            echo "<td><strong>" . strtoupper($row->source) . "</strong></td>";
            echo "</tr>";
        }
        echo "</table>";
        
        $biometricCount = count(array_filter($combined, fn($r) => $r->source === 'biometric'));
        $manualCount = count(array_filter($combined, fn($r) => $r->source === 'manual'));
        
        echo "<p>✅ UNION found: <strong>{$biometricCount}</strong> biometric + <strong>{$manualCount}</strong> manual = <strong>" . count($combined) . "</strong> total entries</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error in UNION query: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; overflow: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

// Query 4: Test calculateDailyHours for a specific employee
if (!empty($manualEntries)) {
    echo "<h4>4. Test calculateDailyHours for Manual Entry Employee</h4>";
    $testEmployee = $manualEntries[0]->personname;
    echo "<p>Testing: <strong>{$testEmployee}</strong> on <strong>{$testDate}</strong></p>";
    
    try {
        require_once JPATH_BASE . '/components/com_ordenproduccion/src/Helper/AsistenciaHelper.php';
        $calculation = \Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper::calculateDailyHours($testEmployee, $testDate);
        
        if ($calculation) {
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Personname</td><td>{$calculation->personname}</td></tr>";
            echo "<tr><td>Cardno</td><td>" . ($calculation->cardno ?: '-') . "</td></tr>";
            echo "<tr><td>Work Date</td><td>{$calculation->work_date}</td></tr>";
            echo "<tr><td>First Entry</td><td>" . ($calculation->first_entry ?: '-') . "</td></tr>";
            echo "<tr><td>Last Exit</td><td>" . ($calculation->last_exit ?: '-') . "</td></tr>";
            echo "<tr><td>Total Hours</td><td><strong>" . number_format($calculation->total_hours, 2) . "h</strong></td></tr>";
            echo "<tr><td>Total Entries</td><td>{$calculation->total_entries}</td></tr>";
            echo "<tr><td>Is Complete</td><td>" . ($calculation->is_complete ? 'Yes' : 'No') . "</td></tr>";
            echo "<tr><td>Is Late</td><td>" . ($calculation->is_late ? 'Yes' : 'No') . "</td></tr>";
            echo "</table>";
            echo "<p class='ok'>✅ calculateDailyHours returned valid data</p>";
        } else {
            echo "<p class='error'>❌ calculateDailyHours returned NULL/empty</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error calling calculateDailyHours: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

// Query 5: Employees table check
echo "<h4>5. Employee Records Check</h4>";
if (!empty($manualEntries)) {
    $uniqueEmployees = array_unique(array_column($manualEntries, 'personname'));
    echo "<p>Checking if manual entry employees exist in employees table...</p>";
    echo "<table>";
    echo "<tr><th>Personname</th><th>Exists in Employees?</th><th>Group ID</th><th>Group Name</th></tr>";
    
    foreach ($uniqueEmployees as $empName) {
        $empQuery = $db->getQuery(true)
            ->select('e.*, g.name AS group_name, g.manager_user_id')
            ->from($db->quoteName('#__ordenproduccion_employees', 'e'))
            ->leftJoin($db->quoteName('#__ordenproduccion_employee_groups', 'g') . ' ON e.group_id = g.id')
            ->where($db->quoteName('e.personname') . ' = ' . $db->quote($empName));
        
        $db->setQuery($empQuery);
        $employee = $db->loadObject();
        
        if ($employee) {
            echo "<tr class='ok'>";
            echo "<td>{$empName}</td>";
            echo "<td>✅ Yes</td>";
            echo "<td>" . ($employee->group_id ?: '<span style="color:red">NULL</span>') . "</td>";
            echo "<td>" . ($employee->group_name ?: '<span style="color:red">NO GROUP</span>') . "</td>";
            echo "</tr>";
        } else {
            echo "<tr class='error'>";
            echo "<td>{$empName}</td>";
            echo "<td>❌ No</td>";
            echo "<td>-</td>";
            echo "<td>-</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
}

echo "</body></html>";
