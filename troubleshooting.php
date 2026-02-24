<?php
/**
 * Menu labels diagnostic – com_ordenproduccion
 *
 * Validates that backend menu labels (Work Orders, Dashboard, Orders, etc.) resolve correctly.
 * Run from Joomla root: https://yoursite.com/troubleshooting.php
 */

// Bootstrap Joomla only if not already loaded
if (!class_exists('Joomla\CMS\Factory')) {
    if (!defined('_JEXEC')) {
        define('_JEXEC', 1);
    }
    if (!defined('JPATH_ROOT')) {
        $possibleRoots = [__DIR__, dirname(__DIR__), dirname(dirname(__DIR__)), '/var/www/grimpsa_webserver'];
        foreach ($possibleRoots as $path) {
            if (file_exists($path . '/includes/defines.php')) {
                define('JPATH_ROOT', $path);
                break;
            }
        }
        if (!defined('JPATH_ROOT')) {
            die("ERROR: Cannot find Joomla root. Set JPATH_ROOT or run from Joomla root.");
        }
    }
    require_once JPATH_ROOT . '/includes/defines.php';
    require_once JPATH_ROOT . '/includes/framework.php';
}

use Joomla\CMS\Factory;

$app = Factory::getApplication('site');
$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

$component = 'com_ordenproduccion';
$adminBase = JPATH_ADMINISTRATOR . '/components/' . $component;

// Menu label constants we expect to resolve
$menuKeys = [
    'COM_ORDENPRODUCCION',
    'COM_ORDENPRODUCCION_MENU_DASHBOARD',
    'COM_ORDENPRODUCCION_MENU_ORDERS',
    'COM_ORDENPRODUCCION_MENU_TECHNICIANS',
    'COM_ORDENPRODUCCION_MENU_WEBHOOK',
    'COM_ORDENPRODUCCION_MENU_DEBUG',
    'COM_ORDENPRODUCCION_MENU_SETTINGS',
    'COM_ORDENPRODUCCION_MENU_TESTING',
];

// Load language so we can resolve keys (admin .sys.ini)
$lang = Factory::getLanguage();
$lang->load($component, JPATH_ADMINISTRATOR);
$lang->load($component, $adminBase);

// Resolve each key
$resolved = [];
foreach ($menuKeys as $key) {
    $resolved[$key] = $lang->hasKey($key) ? $lang->_($key) : null;
}

// #__menu rows for this component (administrator)
$q = $db->getQuery(true)->select('extension_id')->from($db->quoteName('#__extensions'))
    ->where($db->quoteName('element') . ' = ' . $db->quote($component))
    ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
$db->setQuery($q);
$extId = (int) $db->loadResult();
$menuRows = [];
if ($extId > 0) {
    $q = $db->getQuery(true)->select('id, title, link, published')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('client_id') . ' = 1')
        ->where($db->quoteName('component_id') . ' = ' . $extId)
        ->order($db->quoteName('id'));
    $db->setQuery($q);
    $menuRows = $db->loadObjectList() ?: [];
}

// Language file locations checked
$sysIniPaths = [
    'Component (admin)' => $adminBase . '/language/en-GB/com_ordenproduccion.sys.ini',
    'System (admin)'    => JPATH_ADMINISTRATOR . '/language/en-GB/en-GB.com_ordenproduccion.sys.ini',
];
$sysIniFound = [];
foreach ($sysIniPaths as $label => $p) {
    $sysIniFound[$label] = file_exists($p);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Menu labels diagnostic – com_ordenproduccion</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f0f0f0; font-size: 14px; line-height: 1.5; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; color: #1a1a1a; font-size: 1.5rem; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .section { margin: 24px 0; padding: 16px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; }
        .section h2 { margin: 0 0 12px; font-size: 1.1rem; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        .row { display: flex; align-items: flex-start; gap: 12px; margin: 8px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .row:last-child { border-bottom: 0; }
        .status { flex-shrink: 0; width: 28px; text-align: center; font-weight: bold; }
        .ok { color: #2e7d32; }
        .warn { color: #f57c00; }
        .label { font-weight: 600; min-width: 200px; color: #444; }
        .value { color: #333; word-break: break-all; }
        .detail { font-size: 12px; color: #666; margin-top: 4px; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }
        th { font-weight: 600; color: #444; }
    </style>
</head>
<body>
<div class="container">
    <h1>Menu labels diagnostic</h1>
    <p class="subtitle">com_ordenproduccion – validate backend menu labels (Administrator → Components)</p>
    <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?> &nbsp;|&nbsp; <strong>JPATH_ROOT:</strong> <code><?php echo htmlspecialchars(JPATH_ROOT); ?></code></p>

    <div class="section">
        <h2>1. Language files (.sys.ini)</h2>
        <p class="detail">Joomla resolves menu text from these files. For labels to show without opening the component, the system path should exist (e.g. after install script or first open of Work Orders).</p>
        <?php foreach ($sysIniFound as $label => $exists): ?>
        <div class="row">
            <span class="status <?php echo $exists ? 'ok' : 'warn'; ?>"><?php echo $exists ? '✓' : '!'; ?></span>
            <span class="label"><?php echo htmlspecialchars($label); ?></span>
            <span class="value"><?php echo $exists ? 'Found' : 'Not found'; ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="section">
        <h2>2. Resolved menu labels</h2>
        <p class="detail">Each constant should show a human-friendly string. If it shows the key or empty, the language file is missing or the key is not defined.</p>
        <table>
            <thead>
                <tr><th>Constant</th><th>Resolved label</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($resolved as $key => $value): ?>
                <?php
                $isResolved = $value !== null && $value !== '' && $value !== $key;
                ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($key); ?></code></td>
                    <td><?php echo $value !== null ? htmlspecialchars($value) : '<em>—</em>'; ?></td>
                    <td><span class="status <?php echo $isResolved ? 'ok' : 'warn'; ?>"><?php echo $isResolved ? '✓' : '!'; ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>3. #__menu rows (administrator)</h2>
        <p class="detail">Rows in <code>#__menu</code> for this component (client_id=1). The <strong>title</strong> column is the language key; it will display as resolved text in the sidebar only if that key is loaded. Duplicate rows with title <code>COM_ORDENPRODUCCION_MENU</code> show as raw keys.</p>
        <?php if (empty($menuRows)): ?>
        <div class="row">
            <span class="status warn">!</span>
            <span class="label">Rows</span>
            <span class="value">None found for this component.</span>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr><th>ID</th><th>Title (key)</th><th>Link</th><th>Published</th></tr>
            </thead>
            <tbody>
                <?php foreach ($menuRows as $r): ?>
                <tr>
                    <td><?php echo (int) $r->id; ?></td>
                    <td><code><?php echo htmlspecialchars($r->title ?? ''); ?></code></td>
                    <td><?php echo htmlspecialchars($r->link ?? ''); ?></td>
                    <td><?php echo (int) $r->published ? 'Yes' : 'No'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
