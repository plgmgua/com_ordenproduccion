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

echo "</body></html>";
