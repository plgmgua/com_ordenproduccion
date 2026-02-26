<?php
/**
 * Copy missing data: source DB → current installation DB
 *
 * Lists all non-default Joomla tables. For each table:
 * - If table does NOT exist in destination DB: button "Copy full table" (structure + data from source).
 * - If table exists in destination DB: button "Copy missing records" (INSERT rows from source that don't exist in destination by primary key).
 *
 * Source DB = "grimpsa_prod" (production), Destination DB = current Joomla config DB
 * (when this script runs on testing installation, destination = testing).
 * Run from Joomla root: https://yoursite.com/troubleshooting.php
 */

if (!class_exists('Joomla\CMS\Factory')) {
    if (!defined('_JEXEC')) define('_JEXEC', 1);
    if (!defined('JPATH_ROOT')) {
        $possibleRoots = [__DIR__, dirname(__DIR__), dirname(dirname(__DIR__)), '/var/www/grimpsa_webserver'];
        foreach ($possibleRoots as $path) {
            if (file_exists($path . '/includes/defines.php')) {
                define('JPATH_ROOT', $path);
                break;
            }
        }
        if (!defined('JPATH_ROOT')) die("ERROR: Cannot find Joomla root.");
    }
    require_once JPATH_ROOT . '/includes/defines.php';
    require_once JPATH_ROOT . '/includes/framework.php';
}

use Joomla\CMS\Factory;

$config = Factory::getConfig();
$host = $config->get('host', 'localhost');
$user = $config->get('user', '');
$password = $config->get('password', '');

// --- Source = grimpsa_prod (production). Destination = current install DB (testing when run on testing). ---
$sourceDbName = 'grimpsa_prod';
$destDbName = (string) $config->get('db', '');

// Source DB credentials (for connecting to "joomla"). Destination uses current Joomla config.
$sourceUser = 'joomla';
$sourcePassword = 'Blob-Repair-Commodore6';
if (getenv('SOURCE_DB_USER') !== false && getenv('SOURCE_DB_USER') !== '') {
    $sourceUser = getenv('SOURCE_DB_USER');
}
if (getenv('SOURCE_DB_PASSWORD') !== false) {
    $sourcePassword = getenv('SOURCE_DB_PASSWORD');
}

// Default Joomla core table suffixes (no prefix). Tables matching prefix + one of these are skipped.
$joomlaCoreSuffixes = [
    'actions', 'assets', 'associations', 'banner_clients', 'banners', 'banners_tracks',
    'categories', 'contact_details', 'content', 'content_frontpage', 'content_rating', 'content_types',
    'extensions', 'extension_schemas', 'finder_links', 'finder_links_terms0', 'finder_links_terms1',
    'finder_links_terms2', 'finder_links_terms3', 'finder_log', 'finder_tokens', 'finder_tokens_aggregate', 'finder_types',
    'languages', 'menu', 'menu_types', 'message_queues', 'messages', 'modules', 'modules_menu',
    'newsfeeds', 'overrider', 'postinstall_messages', 'redirect_links', 'schemas', 'session',
    'tags', 'template_overrides', 'template_styles', 'ucm_base', 'ucm_content', 'ucm_history',
    'update_sites', 'update_sites_extensions', 'updates', 'user_notes', 'user_profiles', 'users', 'viewlevels',
    'workflow_associations', 'workflow_stages', 'workflow_transitions', 'workflows',
    'contentitem_tag_map', 'fields', 'fields_categories', 'fields_groups', 'fields_values',
];

$hostOnly = $host;
$port = null;
if (strpos($host, ':') !== false) {
    $parts = explode(':', $host, 2);
    $hostOnly = $parts[0];
    $port = (int) $parts[1];
}
$dsnBase = "mysql:host=" . $hostOnly . ";charset=utf8mb4";
if ($port > 0) $dsnBase .= ";port=" . $port;

$sourceDb = null;
$destDb = null;
$sourceTables = [];
$destTables = [];
$customTables = [];
$copyResult = null;

$connectError = null;
try {
    $destDb = new PDO($dsnBase . ";dbname=" . $destDbName, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    $connectError = 'Destination: ' . $e->getMessage();
}
if ($destDb && !$sourceDb) {
    try {
        $sourceDb = new PDO($dsnBase . ";dbname=" . $sourceDbName, $sourceUser, $sourcePassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (Exception $e) {
        if (preg_match('/1044|1045|Access denied/i', $e->getMessage())) {
            try {
                $sourceDb = new PDO($dsnBase . ";dbname=" . $sourceDbName, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } catch (Exception $e2) {
                $connectError = 'Source: ' . $e->getMessage() . ' — Fallback (current Joomla user): ' . $e2->getMessage();
            }
        } else {
            $connectError = 'Source: ' . $e->getMessage();
        }
    }
}
if (!$sourceDb || !$destDb) {
    if (!$connectError) $connectError = 'Could not connect to source or destination database.';
}

if ($sourceDb && $destDb) {
    $stmt = $sourceDb->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $sourceTables[] = $row[0];
    }
    $stmt = $destDb->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $destTables[$row[0]] = true;
    }
    $prefix = $config->get('dbprefix', 'joomla_');
    $prefixLen = strlen($prefix);
    $tablePrefixFilter = $prefix . 'ordenproduccion_'; // Only list tables for this component
    foreach ($sourceTables as $t) {
        if ($prefixLen > 0 && strpos($t, $prefix) !== 0) continue;
        if (strpos($t, $tablePrefixFilter) !== 0) continue; // Limit to joomla_ordenproduccion_*
        $suffix = $prefixLen > 0 ? substr($t, $prefixLen) : $t;
        if (in_array($suffix, $joomlaCoreSuffixes, true)) continue;
        $customTables[] = [
            'name' => $t,
            'exists_in_dest' => isset($destTables[$t]),
            'missing_count' => null,
        ];
    }
    usort($customTables, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });

    // For tables that exist in both DBs, count rows in source that are missing in dest (by primary key).
    $sourceDb->exec("USE `" . str_replace('`', '``', $sourceDbName) . "`");
    $destDb->exec("USE `" . str_replace('`', '``', $destDbName) . "`");
    $sourceDbIdent = '`' . str_replace('`', '``', $sourceDbName) . "`";
    foreach ($customTables as &$t) {
        if (!$t['exists_in_dest']) {
            continue;
        }
        $tableName = $t['name'];
        $tableIdent = '`' . str_replace('`', '``', $tableName) . '`';
        try {
            $pkStmt = $sourceDb->query("SHOW KEYS FROM " . $tableIdent . " WHERE Key_name = 'PRIMARY'");
            $pkCols = [];
            while ($pkRow = $pkStmt->fetch(PDO::FETCH_OBJ)) {
                $pkCols[] = $pkRow->Column_name;
            }
            if (empty($pkCols)) {
                $colStmt = $sourceDb->query("SHOW COLUMNS FROM " . $tableIdent);
                $first = $colStmt->fetch(PDO::FETCH_OBJ);
                if ($first) $pkCols = [$first->Field];
            }
            if (empty($pkCols)) {
                $t['missing_count'] = null;
                continue;
            }
            $joinCond = implode(' AND ', array_map(function ($c) use ($tableIdent, $sourceDbIdent) {
                $q = '`' . str_replace('`', '``', $c) . '`';
                return "dest." . $q . " = src." . $q;
            }, $pkCols));
            $countSql = "SELECT COUNT(*) FROM " . $sourceDbIdent . "." . $tableIdent . " AS src "
                . "WHERE NOT EXISTS (SELECT 1 FROM " . $tableIdent . " AS dest WHERE " . $joinCond . ")";
            $t['missing_count'] = (int) $destDb->query($countSql)->fetchColumn();
        } catch (Exception $e) {
            $t['missing_count'] = null;
        }
    }
    unset($t);
}

// Handle copy action
$copyAction = isset($_POST['copy_action']) ? $_POST['copy_action'] : '';
if ($sourceDb && $destDb && isset($_POST['copy_table']) && isset($_POST['table_name'])) {
    $tableName = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['table_name']);
    if (!in_array($tableName, $sourceTables, true)) {
        $copyResult = ['success' => false, 'message' => 'Invalid table.', 'query' => '', 'affected' => 0];
    } elseif ($sourceDbName === $destDbName) {
        $copyResult = ['success' => false, 'message' => 'Source and destination DB are the same. Aborting to prevent accidental duplicate copy.', 'query' => '', 'affected' => 0];
    } else {
        $existsInDest = isset($destTables[$tableName]);
        $rebuildTable = ($copyAction === 'rebuild');
        $copyResult = ['success' => false, 'message' => '', 'query' => '', 'affected' => 0, 'details' => []];

        try {
            $tableIdent = '`' . str_replace('`', '``', $tableName) . '`';
            $sourceDbIdent = '`' . str_replace('`', '``', $sourceDbName) . '`';
            $sourceDb->exec("USE `" . str_replace('`', '``', $sourceDbName) . "`");
            $destDb->exec("USE `" . str_replace('`', '``', $destDbName) . "`");

            $doFullCopy = function () use ($sourceDb, $destDb, $tableIdent, $sourceDbIdent, $sourceDbName, $destDbName, $tableName, &$copyResult) {
                $createStmt = $sourceDb->query("SHOW CREATE TABLE " . $tableIdent);
                $createRow = $createStmt->fetch(PDO::FETCH_NUM);
                $createSql = $createRow[1];
                $destDb->exec($createSql);
                $copyResult['details'][] = "CREATE TABLE executed.";
                $destDb->exec("INSERT INTO " . $tableIdent . " SELECT * FROM " . $sourceDbIdent . "." . $tableIdent);
                $copyResult['affected'] = (int) $destDb->query("SELECT COUNT(*) FROM " . $tableIdent)->fetchColumn();
                $copyResult['query'] = $createSql . "\n\nINSERT ... SELECT * FROM source.";
                $copyResult['message'] = "Full table copied. Rows in destination: " . $copyResult['affected'];
                $copyResult['success'] = true;
            };

            if ($rebuildTable && $existsInDest) {
                // Drop destination table and recreate from source (structure + data)
                $destDb->exec("DROP TABLE " . $tableIdent);
                $copyResult['details'][] = "DROP TABLE executed.";
                $doFullCopy();
                $copyResult['message'] = "Table dropped and rebuilt from source. Rows in destination: " . $copyResult['affected'];
            } elseif (!$existsInDest) {
                // Copy full table: CREATE TABLE ... + INSERT ... SELECT
                $doFullCopy();
            } else {
                // Copy missing records: need primary key. Use id if exists, else first column.
                $pkStmt = $sourceDb->query("SHOW KEYS FROM " . $tableIdent . " WHERE Key_name = 'PRIMARY'");
                $pkCols = [];
                while ($pkRow = $pkStmt->fetch(PDO::FETCH_OBJ)) {
                    $pkCols[] = $pkRow->Column_name;
                }
                if (empty($pkCols)) {
                    $colStmt = $sourceDb->query("SHOW COLUMNS FROM " . $tableIdent);
                    $first = $colStmt->fetch(PDO::FETCH_OBJ);
                    if ($first) $pkCols = [$first->Field];
                }
                if (empty($pkCols)) {
                    $copyResult['message'] = "Could not determine primary key for table.";
                } else {
                    $colsStmt = $sourceDb->query("SHOW COLUMNS FROM " . $tableIdent);
                    $allCols = [];
                    while ($r = $colsStmt->fetch(PDO::FETCH_OBJ)) $allCols[] = $r->Field;
                    $colList = implode(',', array_map(function ($c) { return '`' . str_replace('`', '``', $c) . '`'; }, $allCols));
                    $sub = "SELECT " . $colList . " FROM " . $sourceDbIdent . "." . $tableIdent . " AS src "
                        . "WHERE NOT EXISTS (SELECT 1 FROM " . $tableIdent . " AS dest WHERE "
                        . implode(' AND ', array_map(function ($c) {
                            return "dest.`" . str_replace('`', '``', $c) . "` = src.`" . str_replace('`', '``', $c) . "`";
                        }, $pkCols)) . ")";
                    $insertSql = "INSERT INTO " . $tableIdent . " (" . $colList . ") " . $sub;
                    $copyResult['affected'] = (int) $destDb->exec($insertSql);
                    $copyResult['query'] = $insertSql;
                    $copyResult['message'] = "Missing records copied. Rows inserted: " . $copyResult['affected'];
                    $copyResult['success'] = true;
                }
            }
        } catch (Exception $e) {
            $copyResult['message'] = 'Error: ' . $e->getMessage();
            $copyResult['details'][] = $e->getMessage();
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copy data: joomla → current DB</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f0f0f0; font-size: 14px; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .section { margin: 24px 0; padding: 16px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; }
        .section h2 { margin: 0 0 12px; font-size: 1.1rem; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #eee; }
        th { font-weight: 600; background: #f5f5f5; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .btn { display: inline-block; padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; }
        .btn-copy { background: #1976d2; color: #fff; }
        .btn-copy:hover { background: #1565c0; color: #fff; }
        .btn-full { background: #2e7d32; color: #fff; }
        .btn-full:hover { background: #1b5e20; color: #fff; }
        .btn-rebuild { background: #ed6c02; color: #fff; }
        .btn-rebuild:hover { background: #e65100; color: #fff; }
        .result-ok { color: #2e7d32; }
        .result-err { color: #c62828; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 11px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; }
        .badge-missing { background: #fff3e0; color: #e65100; }
        .badge-exists { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
<div class="container">
    <h1>Copy data: source → destination</h1>
    <p class="subtitle">Source: <code><?php echo htmlspecialchars($sourceDbName); ?></code> → Destination: <code><?php echo htmlspecialchars($destDbName); ?></code>. Non-default Joomla tables only.</p>

    <?php if (!($sourceDb && $destDb)): ?>
    <div class="section" style="border-color: #c62828;">
        <p class="result-err">Could not connect to database(s). <?php echo isset($connectError) ? htmlspecialchars($connectError) : ''; ?></p>
        <p style="margin-top: 12px;">To allow the current Joomla user to read from the source DB, run on MySQL (as root):</p>
        <pre style="margin: 8px 0; font-size: 12px;">GRANT SELECT ON <?php echo htmlspecialchars($sourceDbName); ?>.* TO '<?php echo htmlspecialchars($user); ?>'@'%';
FLUSH PRIVILEGES;</pre>
        <p>Or grant the source user (<code><?php echo htmlspecialchars($sourceUser); ?></code>) access to database <code><?php echo htmlspecialchars($sourceDbName); ?></code>. Check credentials at the top of this script.</p>
    </div>
    <?php else: ?>

    <?php if ($copyResult !== null): ?>
    <div class="section" style="border-color: <?php echo $copyResult['success'] ? '#2e7d32' : '#c62828'; ?>;">
        <h2>MySQL result</h2>
        <p class="<?php echo $copyResult['success'] ? 'result-ok' : 'result-err'; ?>"><?php echo htmlspecialchars($copyResult['message']); ?></p>
        <?php if (!empty($copyResult['query'])): ?>
        <p><strong>Query / action</strong></p>
        <pre><?php echo htmlspecialchars($copyResult['query']); ?></pre>
        <?php endif; ?>
        <p><strong>Rows affected / inserted</strong>: <?php echo (int) $copyResult['affected']; ?></p>
        <?php if (!empty($copyResult['details'])): ?>
        <pre><?php echo htmlspecialchars(implode("\n", $copyResult['details'])); ?></pre>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="section">
        <h2>Tables <code><?php echo htmlspecialchars($tablePrefixFilter); ?>*</code> (source: <?php echo htmlspecialchars($sourceDbName); ?>)</h2>
        <p>Only tables starting with <code><?php echo htmlspecialchars($tablePrefixFilter); ?></code>. Click a button to copy.</p>
        <table>
            <thead>
                <tr>
                    <th>Table</th>
                    <th>In <?php echo htmlspecialchars($destDbName); ?></th>
                    <th>Missing records</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customTables as $t): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($t['name']); ?></code></td>
                    <td>
                        <?php if ($t['exists_in_dest']): ?>
                        <span class="badge badge-exists">exists</span>
                        <?php else: ?>
                        <span class="badge badge-missing">missing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$t['exists_in_dest']): ?>
                        —
                        <?php elseif (isset($t['missing_count'])): ?>
                        <?php echo (int) $t['missing_count']; ?>
                        <?php else: ?>
                        <span title="Could not determine">?</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($t['exists_in_dest']): ?>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="copy_table" value="1" />
                            <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($t['name']); ?>" />
                            <button type="submit" name="copy_action" value="missing" class="btn btn-copy">Copy missing records</button>
                        </form>
                        <form method="post" action="" style="display:inline; margin-left:6px;">
                            <input type="hidden" name="copy_table" value="1" />
                            <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($t['name']); ?>" />
                            <button type="submit" name="copy_action" value="rebuild" class="btn btn-rebuild">Drop &amp; rebuild from source</button>
                        </form>
                        <?php else: ?>
                        <form method="post" action="" style="display:inline;">
                            <input type="hidden" name="copy_table" value="1" />
                            <input type="hidden" name="table_name" value="<?php echo htmlspecialchars($t['name']); ?>" />
                            <button type="submit" class="btn btn-full">Copy full table</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (empty($customTables)): ?>
        <p>No tables matching <code><?php echo htmlspecialchars($tablePrefixFilter); ?>*</code> found in source.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
