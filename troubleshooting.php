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
    // Simplified UNION using only personname and date (matching AsistenciaHelper)
    $unionTestQuery = "
        SELECT 
            CAST(personname AS CHAR) COLLATE utf8mb4_unicode_ci AS personname,
            CAST(authdate AS CHAR) COLLATE utf8mb4_unicode_ci AS authdate,
            CAST(authtime AS CHAR) COLLATE utf8mb4_unicode_ci AS authtime,
            CAST(direction AS CHAR) COLLATE utf8mb4_unicode_ci AS direction,
            'biometric' AS source
        FROM asistencia
        WHERE DATE(CAST(authdate AS DATE)) = " . $db->quote($testDate) . "
        UNION ALL
        SELECT 
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

// Query 6: Manual trigger updateDailySummary
if (!empty($manualEntries)) {
    echo "<h4>6. Manually Trigger updateDailySummary</h4>";
    $testEmp = $manualEntries[0];
    echo "<p>Test employee: <strong>{$testEmp->personname}</strong> on <strong>{$testDate}</strong></p>";
    
    if (isset($_GET['trigger_update'])) {
        try {
            require_once JPATH_BASE . '/components/com_ordenproduccion/src/Helper/AsistenciaHelper.php';
            $result = \Grimpsa\Component\Ordenproduccion\Site\Helper\AsistenciaHelper::updateDailySummary($testEmp->personname, $testDate);
            
            if ($result) {
                echo "<p class='ok'>✅ updateDailySummary returned TRUE - summary should now exist</p>";
                echo "<p><a href='?date={$testDate}'>Refresh to see updated summary</a></p>";
            } else {
                echo "<p class='error'>❌ updateDailySummary returned FALSE - check error logs</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>❌ Error calling updateDailySummary: " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre style='background: #f0f0f0; padding: 10px; overflow: auto;'>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }
    } else {
        echo "<p><a href='?date={$testDate}&trigger_update=1' class='btn' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>Trigger updateDailySummary</a></p>";
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

// ============================================
// MODULE TROUBLESHOOTING SECTION
// ============================================
echo "<hr style='margin: 30px 0; border: 2px solid #333;'>";
echo "<h3>🔧 MODULE TROUBLESHOOTING - mod_acciones_produccion</h3>";

// Get order ID from URL
$moduleTestOrderId = $_GET['module_test_id'] ?? 0;

echo "<div class='summary'>";
echo "<strong>Module Diagnostics:</strong> Check shipping slip JavaScript functionality | ";
echo "<form method='GET' style='display: inline;'>";
if (isset($_GET['date'])) {
    echo "<input type='hidden' name='date' value='{$_GET['date']}'>";
}
echo "Test Order ID: <input type='number' name='module_test_id' value='{$moduleTestOrderId}' style='margin: 0 5px; width: 100px;'>";
echo "<button type='submit'>Test Module</button>";
echo "</form>";
echo "</div>";

// ============================================
// Check 1: Module File Checks
// ============================================
echo "<h4>1. Module File Checks</h4>";

$moduleFiles = [
    JPATH_BASE . '/modules/mod_acciones_produccion/mod_acciones_produccion.php' => 'Main Module File',
    JPATH_BASE . '/modules/mod_acciones_produccion/tmpl/default.php' => 'Template File',
    JPATH_BASE . '/modules/mod_acciones_produccion/mod_acciones_produccion.xml' => 'Manifest File'
];

$allModuleFilesExist = true;
echo "<table>";
echo "<tr><th>File</th><th>Status</th><th>Size</th><th>Modified</th></tr>";
foreach ($moduleFiles as $path => $label) {
    $exists = file_exists($path);
    $allModuleFilesExist = $allModuleFilesExist && $exists;
    $rowClass = $exists ? 'ok' : 'error';
    
    echo "<tr class='{$rowClass}'>";
    echo "<td><strong>{$label}</strong><br><small>" . basename($path) . "</small></td>";
    echo "<td>" . ($exists ? '✅ EXISTS' : '❌ NOT FOUND') . "</td>";
    echo "<td>" . ($exists ? filesize($path) . ' bytes' : '-') . "</td>";
    echo "<td>" . ($exists ? date('Y-m-d H:i:s', filemtime($path)) : '-') . "</td>";
    echo "</tr>";
}
echo "</table>";

if (!$allModuleFilesExist) {
    echo "<p class='error'><strong>⚠️ MODULE FILES MISSING!</strong> Deploy using: <code>sudo ./update_build_simple.sh</code></p>";
} else {
    echo "<p class='ok'>✅ All module files present</p>";
}

// ============================================
// Check 2: submitShippingWithDescription Function Code
// ============================================
echo "<h4>2. submitShippingWithDescription Function Code</h4>";

$templateFile = JPATH_BASE . '/modules/mod_acciones_produccion/tmpl/default.php';
if (file_exists($templateFile)) {
    $contents = file_get_contents($templateFile);
    
    // Check for the function definition
    if (strpos($contents, 'window.submitShippingWithDescription') !== false) {
        echo "<p class='ok'>✅ Function definition FOUND in template file</p>";
        
        // Check for the fix (const shippingForm declaration)
        if (strpos($contents, 'const shippingForm = document.getElementById') !== false) {
            echo "<p class='ok'>✅ FIXED VERSION: <code>const shippingForm</code> declaration present</p>";
            
            // Extract and show the code snippet
            preg_match('/window\.submitShippingWithDescription\s*=\s*function\(\)\s*\{([^\}]{0,400})/s', $contents, $matches);
            if (!empty($matches[0])) {
                echo "<p><strong>Code Snippet (first 400 chars):</strong></p>";
                echo "<pre style='max-height: 200px; overflow-y: auto;'>" . htmlspecialchars(substr($matches[0], 0, 500)) . "...</pre>";
            }
        } else {
            echo "<p class='error'>❌ BROKEN VERSION: Missing <code>const shippingForm</code> declaration</p>";
            echo "<p class='error'>This is the bug! The variable is used but never declared.</p>";
            echo "<p class='warning'>Deploy fix: <code>cd /var/www/grimpsa_webserver && sudo ./update_build_simple.sh</code></p>";
        }
        
        // Check for debug logging
        if (strpos($contents, "console.log('Module script loading...')") !== false) {
            echo "<p class='ok'>✅ Debug logging present (helps diagnose issues)</p>";
        } else {
            echo "<p class='warning'>⚠️ Debug logging not found (older version)</p>";
        }
    } else {
        echo "<p class='error'>❌ Function definition NOT FOUND in template file</p>";
    }
} else {
    echo "<p class='error'>❌ Template file not found at: {$templateFile}</p>";
}

// ============================================
// Check 3: Module Database Registration
// ============================================
echo "<h4>3. Module Database Registration</h4>";

try {
    $moduleQuery = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName('#__modules'))
        ->where($db->quoteName('module') . ' = ' . $db->quote('mod_acciones_produccion'));
    
    $db->setQuery($moduleQuery);
    $modules = $db->loadObjectList();
    
    if (empty($modules)) {
        echo "<p class='error'>❌ Module NOT registered in database</p>";
        echo "<p class='error'>Register it: <code>php " . JPATH_BASE . "/modules/mod_acciones_produccion/register_module_joomla5.php</code></p>";
    } else {
        echo "<p class='ok'>✅ Module registered in database</p>";
        echo "<p>Found: <strong>" . count($modules) . "</strong> instance(s)</p>";
        
        if (count($modules) > 1) {
            echo "<p class='warning'>⚠️ MULTIPLE INSTANCES FOUND! This causes duplication and JavaScript conflicts.</p>";
            echo "<p class='warning'>Delete duplicate instances in: System → Manage → Site Modules</p>";
        }
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Title</th><th>Position</th><th>Published</th><th>Access</th><th>Ordering</th></tr>";
        foreach ($modules as $mod) {
            $publishedClass = $mod->published ? 'ok' : 'error';
            echo "<tr class='{$publishedClass}'>";
            echo "<td>{$mod->id}</td>";
            echo "<td>{$mod->title}</td>";
            echo "<td><strong>{$mod->position}</strong></td>";
            echo "<td>" . ($mod->published ? '✅ Published' : '❌ Unpublished') . "</td>";
            echo "<td>{$mod->access}</td>";
            echo "<td>{$mod->ordering}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error querying modules: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// ============================================
// Check 4: Test with Specific Order
// ============================================
echo "<h4>4. Test Module Logic with Specific Order</h4>";

if ($moduleTestOrderId > 0) {
    echo "<p>Testing with Order ID: <strong>{$moduleTestOrderId}</strong></p>";
    
    try {
        $orderQuery = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int)$moduleTestOrderId)
            ->where($db->quoteName('state') . ' = 1');
        
        $db->setQuery($orderQuery);
        $workOrderData = $db->loadObject();
        
        if ($workOrderData) {
            echo "<p class='ok'>✅ Work order data FOUND</p>";
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th></tr>";
            echo "<tr><td>Order Number</td><td><strong>" . htmlspecialchars($workOrderData->orden_de_trabajo ?? 'N/A') . "</strong></td></tr>";
            echo "<tr><td>Client</td><td>" . htmlspecialchars($workOrderData->client_name ?? 'N/A') . "</td></tr>";
            echo "<tr><td>Status</td><td>" . htmlspecialchars($workOrderData->status ?? 'N/A') . "</td></tr>";
            echo "<tr><td>Order Type</td><td>" . htmlspecialchars($workOrderData->order_type ?? 'N/A') . "</td></tr>";
            echo "<tr><td>Created</td><td>" . htmlspecialchars($workOrderData->created ?? 'N/A') . "</td></tr>";
            echo "</table>";
            
            echo "<p class='ok'>✅ PHP Condition <code>if (\$orderId && \$workOrderData)</code> would be <strong>TRUE</strong></p>";
            echo "<p class='ok'>✅ Script block SHOULD be output to HTML</p>";
            
            echo "<p><strong>Test in Browser:</strong></p>";
            echo "<ol>";
            echo "<li>Open: <a href='/index.php/component/ordenproduccion/?view=orden&id={$moduleTestOrderId}' target='_blank'>Order {$moduleTestOrderId}</a></li>";
            echo "<li>Press F12 → Console tab</li>";
            echo "<li>Look for: <code>Module script loading...</code></li>";
            echo "<li>Check: <code>typeof window.submitShippingWithDescription</code> should be 'function'</li>";
            echo "</ol>";
        } else {
            echo "<p class='error'>❌ Work order data NOT FOUND</p>";
            echo "<p class='error'>PHP Condition <code>if (\$orderId && \$workOrderData)</code> would be <strong>FALSE</strong></p>";
            echo "<p class='error'>Script block will NOT be output!</p>";
            echo "<p>Possible reasons:</p>";
            echo "<ul>";
            echo "<li>Order ID {$moduleTestOrderId} doesn't exist</li>";
            echo "<li>Order state is not 1 (not published)</li>";
            echo "<li>Order was deleted</li>";
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error querying work order: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p class='warning'>⚠️ No Order ID provided for testing</p>";
    echo "<p>Enter an order ID above and click 'Test Module' to verify module logic</p>";
}

// ============================================
// Check 5: Quick Action Links
// ============================================
echo "<h4>5. Quick Actions & Recommendations</h4>";

echo "<div class='summary' style='background: #e7f3ff;'>";
echo "<p><strong>🚀 Deploy Latest Version:</strong></p>";
echo "<pre>ssh pgrant@192.168.1.208
cd /var/www/grimpsa_webserver
sudo ./update_build_simple.sh</pre>";

echo "<p><strong>🧹 Clear All Caches:</strong></p>";
echo "<pre>sudo rm -rf /var/www/grimpsa_webserver/administrator/cache/*
sudo rm -rf /var/www/grimpsa_webserver/cache/*
sudo systemctl restart php-fpm</pre>";

echo "<p><strong>🔍 Check PHP Error Logs:</strong></p>";
echo "<pre>tail -50 /var/log/php8.1-fpm/error.log | grep 'MOD_ACCIONES'</pre>";

echo "<p><strong>✅ Test Orders:</strong></p>";
echo "<p>";
echo "<a href='?module_test_id=5610" . (isset($_GET['date']) ? "&date={$_GET['date']}" : "") . "' style='display: inline-block; padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 2px;'>Test Order 5610</a>";
echo "<a href='?module_test_id=5613" . (isset($_GET['date']) ? "&date={$_GET['date']}" : "") . "' style='display: inline-block; padding: 8px 12px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 2px;'>Test Order 5613</a>";
echo "<a href='/index.php/component/ordenproduccion/?view=ordenes' target='_blank' style='display: inline-block; padding: 8px 12px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; margin: 2px;'>View All Orders</a>";
echo "</p>";
echo "</div>";

// ============================================
// Summary
// ============================================
echo "<div class='summary' style='background: #f0f0f0; margin-top: 20px;'>";
echo "<h4>📊 Module Troubleshooting Summary</h4>";
echo "<table style='width: auto;'>";
echo "<tr><th>Check</th><th>Status</th></tr>";
echo "<tr><td>Module Files</td><td>" . ($allModuleFilesExist ? '✅ Present' : '❌ Missing') . "</td></tr>";
echo "<tr><td>Function Code</td><td>" . ((isset($contents) && strpos($contents, 'window.submitShippingWithDescription') !== false) ? '✅ Found' : '❌ Not Found') . "</td></tr>";
echo "<tr><td>Fix Applied</td><td>" . ((isset($contents) && strpos($contents, 'const shippingForm = document.getElementById') !== false) ? '✅ Yes' : '❌ No') . "</td></tr>";
echo "<tr><td>Database Registration</td><td>" . ((isset($modules) && !empty($modules)) ? '✅ Registered' : '❌ Not Registered') . "</td></tr>";
echo "<tr><td>Multiple Instances</td><td>" . ((isset($modules) && count($modules) > 1) ? '⚠️ Yes (BAD)' : '✅ No') . "</td></tr>";
if ($moduleTestOrderId > 0) {
    echo "<tr><td>Test Order Data</td><td>" . (isset($workOrderData) && $workOrderData ? '✅ Found' : '❌ Not Found') . "</td></tr>";
}
echo "</table>";

echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
if (!$allModuleFilesExist || !(isset($contents) && strpos($contents, 'const shippingForm = document.getElementById') !== false)) {
    echo "<li><strong>DEPLOY:</strong> Run <code>sudo ./update_build_simple.sh</code> on server</li>";
}
if (isset($modules) && count($modules) > 1) {
    echo "<li><strong>FIX DUPLICATES:</strong> Delete extra module instances in Joomla Admin</li>";
}
echo "<li><strong>CLEAR CACHE:</strong> Delete Joomla cache and restart PHP-FPM</li>";
echo "<li><strong>TEST:</strong> Open orden page in incognito window with DevTools console</li>";
echo "<li><strong>VERIFY:</strong> Look for 'Module script loading...' in console</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
