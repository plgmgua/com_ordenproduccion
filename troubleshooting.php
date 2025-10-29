<?php
/**
 * Employee Management System Diagnostics
 * Place in Joomla root and access via: https://your-domain.com/troubleshooting.php
 */

define('_JEXEC', 1);
define('JPATH_BASE', __DIR__);

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

$app = Factory::getApplication('site');
$db = Factory::getContainer()->get(DatabaseInterface::class);

header('Content-Type: text/html; charset=utf-8');

// Styling
$style = "
<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #28a745; padding-bottom: 8px; }
h3 { color: #666; margin-top: 20px; }
.success { background: #d4edda; color: #155724; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #28a745; }
.warning { background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #ffc107; }
.error { background: #f8d7da; color: #721c24; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #dc3545; }
.info { background: #d1ecf1; color: #0c5460; padding: 12px; border-radius: 4px; margin: 10px 0; border-left: 4px solid #17a2b8; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th { background: #007bff; color: white; padding: 10px; text-align: left; }
td { padding: 8px; border-bottom: 1px solid #ddd; }
tr:hover { background: #f9f9f9; }
.code { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; margin: 10px 0; border-left: 4px solid #007bff; }
pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
.sql-block { background: #272822; color: #f8f8f2; padding: 15px; border-radius: 4px; margin: 10px 0; overflow-x: auto; }
.badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
.badge-success { background: #28a745; color: white; }
.badge-danger { background: #dc3545; color: white; }
.badge-warning { background: #ffc107; color: #333; }
.timestamp { color: #999; font-size: 12px; }
</style>
";

echo $style;
echo '<div class="container">';
echo '<h1>🔍 Employee Management System - Database Diagnostics</h1>';
echo '<p class="timestamp">Generated: ' . date('Y-m-d H:i:s') . '</p>';

// ============================================
// 1. CHECK DATABASE CONNECTION
// ============================================
echo '<h2>1. Database Connection</h2>';
try {
    $dbName = $db->getDatabase();
    echo '<div class="success">✅ Connected to database: <strong>' . htmlspecialchars($dbName) . '</strong></div>';
} catch (Exception $e) {
    echo '<div class="error">❌ Database connection failed: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// ============================================
// 2. CHECK EMPLOYEE GROUPS TABLE
// ============================================
echo '<h2>2. Employee Groups Table Check</h2>';

// Check if table exists
$tables = $db->getTableList();
$groupsTableExists = in_array($db->getPrefix() . 'ordenproduccion_employee_groups', $tables);

if ($groupsTableExists) {
    echo '<div class="success">✅ Table exists: <code>joomla_ordenproduccion_employee_groups</code></div>';
    
    // Get table structure
    $query = "DESCRIBE " . $db->quoteName('#__ordenproduccion_employee_groups');
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    echo '<h3>Table Structure</h3>';
    echo '<table>';
    echo '<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>';
    foreach ($columns as $col) {
        echo '<tr>';
        echo '<td><strong>' . htmlspecialchars($col->Field) . '</strong></td>';
        echo '<td>' . htmlspecialchars($col->Type) . '</td>';
        echo '<td>' . htmlspecialchars($col->Null) . '</td>';
        echo '<td>' . htmlspecialchars($col->Key) . '</td>';
        echo '<td>' . htmlspecialchars($col->Default) . '</td>';
        echo '<td>' . htmlspecialchars($col->Extra) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    // Count records
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__ordenproduccion_employee_groups'));
    $db->setQuery($query);
    $groupCount = $db->loadResult();
    
    if ($groupCount > 0) {
        echo '<div class="success">✅ Found <strong>' . $groupCount . '</strong> employee group(s)</div>';
        
        // Show groups
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_employee_groups'))
            ->order($db->quoteName('ordering'));
        $db->setQuery($query);
        $groups = $db->loadObjectList();
        
        echo '<h3>Current Employee Groups</h3>';
        echo '<table>';
        echo '<tr><th>ID</th><th>Name</th><th>Start Time</th><th>End Time</th><th>Expected Hours</th><th>Grace Period</th><th>Color</th><th>State</th></tr>';
        foreach ($groups as $group) {
            $state = $group->state == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Inactive</span>';
            echo '<tr>';
            echo '<td>' . $group->id . '</td>';
            echo '<td><strong>' . htmlspecialchars($group->name) . '</strong><br><small>' . htmlspecialchars($group->description) . '</small></td>';
            echo '<td>' . substr($group->work_start_time, 0, 5) . '</td>';
            echo '<td>' . substr($group->work_end_time, 0, 5) . '</td>';
            echo '<td>' . $group->expected_hours . 'h</td>';
            echo '<td>' . $group->grace_period_minutes . ' min</td>';
            echo '<td><span style="background:' . $group->color . '; color:white; padding:2px 8px; border-radius:3px;">' . $group->color . '</span></td>';
            echo '<td>' . $state . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<div class="warning">⚠️ Table exists but is <strong>EMPTY</strong>. No employee groups found.</div>';
        echo '<div class="info">📝 <strong>Action Required:</strong> You need to insert default employee groups.</div>';
        
        echo '<div class="sql-block"><pre>';
        echo "INSERT INTO `joomla_ordenproduccion_employee_groups` \n";
        echo "    (`name`, `description`, `work_start_time`, `work_end_time`, `expected_hours`, `grace_period_minutes`, `color`, `state`, `ordering`, `created_by`)\n";
        echo "VALUES\n";
        echo "    ('Turno Regular', 'Horario estándar de oficina', '08:00:00', '17:00:00', 8.00, 15, '#007bff', 1, 1, 0),\n";
        echo "    ('Turno Matutino', 'Turno de mañana', '07:00:00', '15:00:00', 8.00, 15, '#28a745', 1, 2, 0),\n";
        echo "    ('Turno Vespertino', 'Turno de tarde', '15:00:00', '23:00:00', 8.00, 15, '#ffc107', 1, 3, 0);";
        echo '</pre></div>';
    }
} else {
    echo '<div class="error">❌ Table does NOT exist: <code>joomla_ordenproduccion_employee_groups</code></div>';
    echo '<div class="info">📝 <strong>Action Required:</strong> You need to create the employee groups table.</div>';
    
    echo '<div class="sql-block"><pre>';
    echo "CREATE TABLE IF NOT EXISTS `joomla_ordenproduccion_employee_groups` (\n";
    echo "    `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,\n";
    echo "    `name` varchar(255) NOT NULL,\n";
    echo "    `description` text,\n";
    echo "    `work_start_time` time NOT NULL DEFAULT '08:00:00',\n";
    echo "    `work_end_time` time NOT NULL DEFAULT '17:00:00',\n";
    echo "    `expected_hours` decimal(5,2) NOT NULL DEFAULT 8.00,\n";
    echo "    `grace_period_minutes` int(11) NOT NULL DEFAULT 15,\n";
    echo "    `color` varchar(7) DEFAULT '#007bff',\n";
    echo "    `state` tinyint(3) NOT NULL DEFAULT 1,\n";
    echo "    `ordering` int(11) NOT NULL DEFAULT 0,\n";
    echo "    `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,\n";
    echo "    `created_by` int(11) NOT NULL DEFAULT 0,\n";
    echo "    `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,\n";
    echo "    `modified_by` int(11) DEFAULT NULL,\n";
    echo "    PRIMARY KEY (`id`),\n";
    echo "    KEY `idx_state` (`state`),\n";
    echo "    KEY `idx_ordering` (`ordering`)\n";
    echo ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";
    echo '</pre></div>';
}

// ============================================
// 3. CHECK EMPLOYEES TABLE
// ============================================
echo '<h2>3. Employees Table Check</h2>';

$employeesTableExists = in_array($db->getPrefix() . 'ordenproduccion_employees', $tables);

if ($employeesTableExists) {
    echo '<div class="success">✅ Table exists: <code>joomla_ordenproduccion_employees</code></div>';
    
    // Get table structure
    $query = "DESCRIBE " . $db->quoteName('#__ordenproduccion_employees');
    $db->setQuery($query);
    $columns = $db->loadObjectList();
    
    // Check for required columns
    $requiredColumns = [
        'group_id' => 'int(11) UNSIGNED',
        'employee_number' => 'varchar(50)',
        'department' => 'varchar(100)',
        'position' => 'varchar(100)',
        'email' => 'varchar(255)',
        'phone' => 'varchar(50)',
        'hire_date' => 'date',
        'notes' => 'text',
        'state' => 'tinyint(3)',
        'modified' => 'datetime',
        'modified_by' => 'int(11)'
    ];
    
    $existingColumns = [];
    foreach ($columns as $col) {
        $existingColumns[$col->Field] = $col->Type;
    }
    
    echo '<h3>Required Columns Check</h3>';
    echo '<table>';
    echo '<tr><th>Column</th><th>Required Type</th><th>Status</th><th>Current Type</th></tr>';
    
    $missingColumns = [];
    foreach ($requiredColumns as $colName => $colType) {
        if (isset($existingColumns[$colName])) {
            echo '<tr>';
            echo '<td><strong>' . $colName . '</strong></td>';
            echo '<td>' . $colType . '</td>';
            echo '<td><span class="badge badge-success">EXISTS</span></td>';
            echo '<td>' . htmlspecialchars($existingColumns[$colName]) . '</td>';
            echo '</tr>';
        } else {
            $missingColumns[] = $colName;
            echo '<tr style="background:#fff3cd;">';
            echo '<td><strong>' . $colName . '</strong></td>';
            echo '<td>' . $colType . '</td>';
            echo '<td><span class="badge badge-danger">MISSING</span></td>';
            echo '<td>-</td>';
            echo '</tr>';
        }
    }
    echo '</table>';
    
    if (count($missingColumns) > 0) {
        echo '<div class="warning">⚠️ Missing <strong>' . count($missingColumns) . '</strong> column(s): ' . implode(', ', $missingColumns) . '</div>';
        echo '<div class="info">📝 <strong>Action Required:</strong> Add missing columns to employees table.</div>';
        
        echo '<div class="sql-block"><pre>';
        foreach ($missingColumns as $col) {
            switch($col) {
                case 'group_id':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `group_id` int(11) UNSIGNED DEFAULT NULL AFTER `personname`;\n";
                    break;
                case 'employee_number':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `employee_number` varchar(50) DEFAULT NULL AFTER `group_id`;\n";
                    break;
                case 'department':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `department` varchar(100) DEFAULT NULL AFTER `employee_number`;\n";
                    break;
                case 'position':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `position` varchar(100) DEFAULT NULL AFTER `department`;\n";
                    break;
                case 'email':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `email` varchar(255) DEFAULT NULL AFTER `position`;\n";
                    break;
                case 'phone':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `phone` varchar(50) DEFAULT NULL AFTER `email`;\n";
                    break;
                case 'hire_date':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `hire_date` date DEFAULT NULL AFTER `phone`;\n";
                    break;
                case 'notes':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `notes` text AFTER `hire_date`;\n";
                    break;
                case 'state':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `state` tinyint(3) NOT NULL DEFAULT 1 AFTER `active`;\n";
                    break;
                case 'modified':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `modified` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created`;\n";
                    break;
                case 'modified_by':
                    echo "ALTER TABLE `joomla_ordenproduccion_employees` ADD COLUMN `modified_by` int(11) DEFAULT NULL AFTER `modified`;\n";
                    break;
            }
        }
        echo '</pre></div>';
    } else {
        echo '<div class="success">✅ All required columns exist!</div>';
    }
    
    // Count employees
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName('#__ordenproduccion_employees'));
    $db->setQuery($query);
    $employeeCount = $db->loadResult();
    
    echo '<h3>Employee Statistics</h3>';
    echo '<div class="info">📊 Total Employees: <strong>' . $employeeCount . '</strong></div>';
    
    if ($employeeCount > 0 && !in_array('group_id', $missingColumns)) {
        // Count employees with/without groups
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ordenproduccion_employees'))
            ->where($db->quoteName('group_id') . ' IS NOT NULL');
        $db->setQuery($query);
        $withGroup = $db->loadResult();
        
        $withoutGroup = $employeeCount - $withGroup;
        
        echo '<div class="info">👥 Employees with group assignment: <strong>' . $withGroup . '</strong></div>';
        
        if ($withoutGroup > 0) {
            echo '<div class="warning">⚠️ Employees without group assignment: <strong>' . $withoutGroup . '</strong></div>';
            
            // Show sample employees without groups
            $query = $db->getQuery(true)
                ->select(['id', 'personname', 'cardno', 'active'])
                ->from($db->quoteName('#__ordenproduccion_employees'))
                ->where($db->quoteName('group_id') . ' IS NULL')
                ->setLimit(10);
            $db->setQuery($query);
            $unassignedEmployees = $db->loadObjectList();
            
            if (count($unassignedEmployees) > 0) {
                echo '<h4>Sample Employees Without Group (first 10)</h4>';
                echo '<table>';
                echo '<tr><th>ID</th><th>Name</th><th>Card No</th><th>Active</th></tr>';
                foreach ($unassignedEmployees as $emp) {
                    $activeStatus = $emp->active == 1 ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-danger">No</span>';
                    echo '<tr>';
                    echo '<td>' . $emp->id . '</td>';
                    echo '<td><strong>' . htmlspecialchars($emp->personname) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($emp->cardno) . '</td>';
                    echo '<td>' . $activeStatus . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                
                if ($groupsTableExists && $groupCount > 0) {
                    echo '<div class="info">📝 <strong>Action Required:</strong> Assign employees to groups.</div>';
                    echo '<div class="sql-block"><pre>';
                    echo "-- Assign all active employees to 'Turno Regular' (group_id = 1)\n";
                    echo "UPDATE `joomla_ordenproduccion_employees` \n";
                    echo "SET `group_id` = 1 \n";
                    echo "WHERE `active` = 1 \n";
                    echo "  AND `group_id` IS NULL;";
                    echo '</pre></div>';
                }
            }
        } else {
            echo '<div class="success">✅ All employees have group assignments!</div>';
        }
        
        // Show employees with groups
        if ($withGroup > 0 && $groupsTableExists) {
            $query = $db->getQuery(true)
                ->select([
                    'e.id',
                    'e.personname',
                    'e.cardno',
                    'e.department',
                    'e.position',
                    'g.name AS group_name',
                    'g.work_start_time',
                    'g.work_end_time',
                    'g.expected_hours'
                ])
                ->from($db->quoteName('#__ordenproduccion_employees', 'e'))
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_employee_groups', 'g'),
                    $db->quoteName('e.group_id') . ' = ' . $db->quoteName('g.id')
                )
                ->where($db->quoteName('e.group_id') . ' IS NOT NULL')
                ->order($db->quoteName('g.ordering') . ', ' . $db->quoteName('e.personname'))
                ->setLimit(10);
            $db->setQuery($query);
            $assignedEmployees = $db->loadObjectList();
            
            echo '<h4>Sample Employees With Groups (first 10)</h4>';
            echo '<table>';
            echo '<tr><th>ID</th><th>Name</th><th>Group</th><th>Schedule</th><th>Department</th><th>Position</th></tr>';
            foreach ($assignedEmployees as $emp) {
                echo '<tr>';
                echo '<td>' . $emp->id . '</td>';
                echo '<td><strong>' . htmlspecialchars($emp->personname) . '</strong></td>';
                echo '<td>' . htmlspecialchars($emp->group_name) . '</td>';
                echo '<td>' . substr($emp->work_start_time, 0, 5) . ' - ' . substr($emp->work_end_time, 0, 5) . ' (' . $emp->expected_hours . 'h)</td>';
                echo '<td>' . htmlspecialchars($emp->department ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($emp->position ?? '-') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
    
} else {
    echo '<div class="error">❌ Table does NOT exist: <code>joomla_ordenproduccion_employees</code></div>';
    echo '<div class="info">This is unexpected! The employees table should exist from the attendance system.</div>';
}

// ============================================
// 4. SUMMARY & RECOMMENDATIONS
// ============================================
echo '<h2>4. Summary & Recommendations</h2>';

$issues = [];
$actions = [];

if (!$groupsTableExists) {
    $issues[] = 'Employee groups table is missing';
    $actions[] = 'Create the joomla_ordenproduccion_employee_groups table';
} elseif ($groupCount == 0) {
    $issues[] = 'Employee groups table is empty';
    $actions[] = 'Insert default employee groups';
}

if ($employeesTableExists && count($missingColumns) > 0) {
    $issues[] = count($missingColumns) . ' column(s) missing from employees table';
    $actions[] = 'Add missing columns to employees table: ' . implode(', ', $missingColumns);
}

if ($employeesTableExists && !in_array('group_id', $missingColumns) && isset($withoutGroup) && $withoutGroup > 0 && $groupCount > 0) {
    $issues[] = $withoutGroup . ' employee(s) without group assignment';
    $actions[] = 'Assign employees to appropriate groups';
}

if (count($issues) > 0) {
    echo '<div class="warning"><h3>⚠️ Issues Found</h3><ul>';
    foreach ($issues as $issue) {
        echo '<li>' . $issue . '</li>';
    }
    echo '</ul></div>';
    
    echo '<div class="info"><h3>📋 Required Actions</h3><ol>';
    foreach ($actions as $action) {
        echo '<li>' . $action . '</li>';
    }
    echo '</ol></div>';
} else {
    echo '<div class="success"><h3>✅ All Checks Passed!</h3>';
    echo '<p>The employee management system database is properly configured.</p>';
    echo '<p>You can now access:</p>';
    echo '<ul>';
    echo '<li><strong>Employee Groups:</strong> Administration → Components → Ordenes Produccion → Employee Groups</li>';
    echo '<li><strong>Employees:</strong> Administration → Components → Ordenes Produccion → Employees</li>';
    echo '</ul>';
    echo '</div>';
}

echo '<hr style="margin: 30px 0;">';
echo '<p style="text-align: center; color: #999; font-size: 12px;">Employee Management System Diagnostics v3.3.0 | Generated: ' . date('Y-m-d H:i:s') . '</p>';
echo '</div>';
?>
