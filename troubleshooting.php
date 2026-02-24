<?php
/**
 * Backend Menu Diagnostic & Fix – com_ordenproduccion
 *
 * In-depth validation for why "Work Orders" may not appear in Administrator → Components.
 * Run from Joomla root: https://yoursite.com/troubleshooting.php
 * Optional: ?fix=1 to apply automatic fixes (manifest file + DB manifest_cache).
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
$manifestPath = $adminBase . '/com_ordenproduccion.xml';
$accessPath = $adminBase . '/access.xml';

$applyFix = isset($_GET['fix']) && $_GET['fix'] === '1';
$allOk = true;

// ---- Apply fix if requested ----
if ($applyFix) {
    $fixMessages = [];
    $manifestContent = file_exists($manifestPath) ? file_get_contents($manifestPath) : '';

    if ($manifestContent !== '') {
        $modified = false;
        if (!preg_match('/<menu\s+[^>]*link=/', $manifestContent)) {
            $manifestContent = preg_replace(
                '/<menu\s+([^>]*)>/',
                '<menu link="option=com_ordenproduccion&amp;view=dashboard" $1>',
                $manifestContent,
                1
            );
            $modified = true;
        }
        if (strpos($manifestContent, 'access.xml') === false && preg_match('/<files\s+folder="admin"/', $manifestContent)) {
            $manifestContent = preg_replace(
                '/(<files\s+folder="admin"[^>]*>\s*<filename>)([^<]+)(<\/filename>)/s',
                '$1$2$3' . "\n            " . '<filename>access.xml</filename>',
                $manifestContent,
                1
            );
            $modified = true;
        }
        if ($modified && @file_put_contents($manifestPath, $manifestContent) !== false) {
            $fixMessages[] = 'Manifest file updated on disk (menu link + access.xml in files).';
        } elseif ($modified) {
            $fixMessages[] = 'Manifest content was patched but could not be written (check file permissions for ' . $manifestPath . ').';
        } elseif ($manifestContent !== '' && preg_match('/<menu\s+[^>]*link=/', $manifestContent)) {
            $fixMessages[] = 'Manifest file already had menu link; no change written.';
        }

        // Update manifest_cache in #__extensions so cached data includes menu link
        try {
            $xml = @new \SimpleXMLElement($manifestContent);
            $q = $db->getQuery(true)
                ->select('manifest_cache')
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('element') . ' = ' . $db->quote($component))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
            $db->setQuery($q);
            $existing = $db->loadResult();
            $cache = $existing ? json_decode($existing, true) : [];
            if (!is_array($cache)) {
                $cache = [];
            }
            if (!isset($cache['administration'])) {
                $cache['administration'] = [];
            }
            if (!isset($cache['administration']['menu'])) {
                $cache['administration']['menu'] = [];
            }
            $cache['administration']['menu']['link'] = 'option=com_ordenproduccion&view=dashboard';
            $cache['administration']['menu']['img'] = 'class:cog';
            $cache['administration']['menu']['text'] = (string) $xml->name;
            $cache['version'] = (string) $xml->version;
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('manifest_cache') . ' = ' . $db->quote(json_encode($cache)))
                    ->where($db->quoteName('element') . ' = ' . $db->quote($component))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
            )->execute();
            $fixMessages[] = 'Extension manifest_cache updated in database (menu link merged).';
        } catch (\Exception $e) {
            $fixMessages[] = 'Could not update manifest_cache: ' . $e->getMessage();
        }
    } else {
        $fixMessages[] = 'Manifest file not found; cannot patch.';
    }

    if (file_exists($accessPath)) {
        $fixMessages[] = 'access.xml is present on disk.';
    } else {
        $fixMessages[] = 'access.xml missing in ' . $adminBase . ' – copy from repo admin/access.xml.';
    }

    // Post-fix: confirm what is on disk (Joomla reads the file)
    $recheck = file_exists($manifestPath) ? file_get_contents($manifestPath) : '';
    $linkOnDisk = $recheck && preg_match('/<menu\s+[^>]*link=/', $recheck);
    $fixMessages[] = 'Manifest on disk now has menu link: ' . ($linkOnDisk ? 'Yes' : 'No – fix may not have written; check permissions.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check: Backend menu item missing – com_ordenproduccion</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f0f0f0; font-size: 14px; line-height: 1.5; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; color: #1a1a1a; font-size: 1.5rem; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .fix-box { background: #e8f5e9; border: 1px solid #81c784; border-radius: 6px; padding: 12px 16px; margin-bottom: 20px; }
        .fix-box ul { margin: 0; padding-left: 20px; }
        .fix-box li { margin: 4px 0; }
        .section { margin: 24px 0; padding: 16px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; }
        .section h2 { margin: 0 0 12px; font-size: 1.1rem; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        .row { display: flex; align-items: flex-start; gap: 12px; margin: 8px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
        .row:last-child { border-bottom: 0; }
        .status { flex-shrink: 0; width: 28px; text-align: center; font-weight: bold; }
        .ok { color: #2e7d32; }
        .fail { color: #c62828; }
        .warn { color: #f57c00; }
        .label { font-weight: 600; min-width: 180px; color: #444; }
        .value { color: #333; word-break: break-all; }
        .detail { font-size: 12px; color: #666; margin-top: 4px; }
        .actions { margin-top: 24px; padding: 16px; background: #fff3e0; border-radius: 6px; border: 1px solid #ffb74d; }
        .actions h3 { margin: 0 0 10px; color: #e65100; }
        .actions ol { margin: 0; padding-left: 20px; }
        .actions li { margin: 6px 0; }
        .btn { display: inline-block; margin-top: 12px; padding: 10px 20px; background: #1976d2; color: #fff; text-decoration: none; border-radius: 4px; font-weight: 600; }
        .btn:hover { background: #1565c0; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Check: Backend menu item missing</h1>
    <p class="subtitle">com_ordenproduccion – why "Work Orders" does not appear in Administrator → Components</p>
    <p><strong>Time:</strong> <?php echo date('Y-m-d H:i:s'); ?> &nbsp;|&nbsp; <strong>JPATH_ROOT:</strong> <code><?php echo htmlspecialchars(JPATH_ROOT); ?></code></p>

    <?php if ($applyFix && !empty($fixMessages)): ?>
    <div class="fix-box">
        <strong>Fix applied</strong>
        <ul>
            <?php foreach ($fixMessages as $m): ?>
            <li><?php echo htmlspecialchars($m); ?></li>
            <?php endforeach; ?>
        </ul>
        <p>Next: <a href="?">Reload this page</a> (without ?fix=1), then in backend go to <strong>System → Clear Cache</strong> and clear all. Open <strong>Components</strong> again.</p>
        <p><strong>If the menu item still does not appear:</strong> See the "If still no menu item" section at the bottom of this page.</p>
    </div>
    <?php endif; ?>

    <?php
    // ---- 1. Extension record ----
    $q = $db->getQuery(true)
        ->select('extension_id, element, type, enabled, manifest_cache')
        ->from($db->quoteName('#__extensions'))
        ->where($db->quoteName('element') . ' = ' . $db->quote($component))
        ->where($db->quoteName('type') . ' = ' . $db->quote('component'));
    $db->setQuery($q);
    $ext = $db->loadObject();
    ?>
    <div class="section">
        <h2>1. Extension record (#__extensions)</h2>
        <?php if (!$ext): ?>
        <div class="row"><span class="status fail">✗</span><span class="label">Component</span><span class="value">Not found. Component may not be installed.</span></div>
        <?php $allOk = false; ?>
        <?php else: ?>
        <div class="row"><span class="status ok">✓</span><span class="label">Extension ID</span><span class="value"><?php echo (int) $ext->extension_id; ?></span></div>
        <div class="row"><span class="status ok">✓</span><span class="label">Element</span><span class="value"><code><?php echo htmlspecialchars($ext->element); ?></code></span></div>
        <div class="row">
            <span class="status <?php echo $ext->enabled ? 'ok' : 'fail'; ?>"><?php echo $ext->enabled ? '✓' : '✗'; ?></span>
            <span class="label">Enabled</span>
            <span class="value"><?php echo $ext->enabled ? 'Yes' : 'No – enable the component'; ?></span>
        </div>
        <?php
        $cache = $ext->manifest_cache ? json_decode($ext->manifest_cache, true) : null;
        $ver = is_array($cache) && isset($cache['version']) ? $cache['version'] : '—';
        $adminCache = is_array($cache) && isset($cache['administration']) ? $cache['administration'] : null;
        $menuInCache = is_array($adminCache) && isset($adminCache['menu']) && !empty($adminCache['menu']['link']);
        ?>
        <div class="row"><span class="label">Version (manifest_cache)</span><span class="value"><?php echo htmlspecialchars($ver); ?></span></div>
        <div class="row">
            <span class="status <?php echo $menuInCache ? 'ok' : 'warn'; ?>"><?php echo $menuInCache ? '✓' : '!'; ?></span>
            <span class="label">Menu link in manifest_cache</span>
            <span class="value"><?php echo $menuInCache ? 'Yes' : 'No – menu may not appear until cache has link'; ?></span>
        </div>
        <?php if (!$ext->enabled) $allOk = false; ?>
        <?php endif; ?>
    </div>

    <?php
    // ---- 2. Manifest file on disk ----
    $manifestExists = file_exists($manifestPath);
    $manifestContent = $manifestExists ? file_get_contents($manifestPath) : '';
    $hasMenuTag = $manifestContent && strpos($manifestContent, '<menu') !== false;
    $hasMenuLink = $manifestContent && preg_match('/<menu\s+[^>]*link=/', $manifestContent);
    $hasAccessInFiles = $manifestContent && strpos($manifestContent, 'access.xml') !== false && preg_match('/<files\s+folder="admin"[^>]*>.*?access\.xml/s', $manifestContent);
    ?>
    <div class="section">
        <h2>2. Manifest file (administrator)</h2>
        <div class="row">
            <span class="status <?php echo $manifestExists ? 'ok' : 'fail'; ?>"><?php echo $manifestExists ? '✓' : '✗'; ?></span>
            <span class="label">File exists</span>
            <span class="value"><code><?php echo htmlspecialchars($manifestPath); ?></code></span>
        </div>
        <?php if (!$manifestExists) $allOk = false; ?>
        <?php if ($manifestContent !== ''): ?>
        <div class="row">
            <span class="status <?php echo $hasMenuTag ? 'ok' : 'fail'; ?>"><?php echo $hasMenuTag ? '✓' : '✗'; ?></span>
            <span class="label">&lt;menu&gt; tag</span>
            <span class="value"><?php echo $hasMenuTag ? 'Present' : 'Missing'; ?></span>
        </div>
        <div class="row">
            <span class="status <?php echo $hasMenuLink ? 'ok' : 'fail'; ?>"><?php echo $hasMenuLink ? '✓' : '✗'; ?></span>
            <span class="label">Menu has <code>link=</code></span>
            <span class="value"><?php echo $hasMenuLink ? 'Yes' : 'No – Joomla needs link="option=com_ordenproduccion&amp;view=dashboard"'; ?></span>
        </div>
        <div class="row">
            <span class="status <?php echo $hasAccessInFiles ? 'ok' : 'fail'; ?>"><?php echo $hasAccessInFiles ? '✓' : '✗'; ?></span>
            <span class="label"><code>access.xml</code> in admin &lt;files&gt;</span>
            <span class="value"><?php echo $hasAccessInFiles ? 'Yes' : 'No – add &lt;filename&gt;access.xml&lt;/filename&gt;'; ?></span>
        </div>
        <?php if (!$hasMenuLink || !$hasAccessInFiles) $allOk = false; ?>
        <?php if (!$hasMenuLink && $manifestContent): ?>
        <?php
        $menuLine = '';
        if (preg_match('/<menu[^>]*>/', $manifestContent, $m)) {
            $menuLine = preg_replace('/\s+/', ' ', trim($m[0]));
        }
        ?>
        <div class="detail">Current &lt;menu&gt; line: <code><?php echo htmlspecialchars($menuLine); ?></code></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php
    // ---- 3. access.xml ----
    $accessExists = file_exists($accessPath);
    $accessContent = $accessExists ? file_get_contents($accessPath) : '';
    $accessHasManage = $accessContent && strpos($accessContent, 'core.manage') !== false;
    ?>
    <div class="section">
        <h2>3. access.xml</h2>
        <div class="row">
            <span class="status <?php echo $accessExists ? 'ok' : 'fail'; ?>"><?php echo $accessExists ? '✓' : '✗'; ?></span>
            <span class="label">File exists</span>
            <span class="value"><code><?php echo htmlspecialchars($accessPath); ?></code></span>
        </div>
        <?php if (!$accessExists) $allOk = false; ?>
        <?php if ($accessExists): ?>
        <div class="row">
            <span class="status <?php echo $accessHasManage ? 'ok' : 'warn'; ?>"><?php echo $accessHasManage ? '✓' : '!'; ?></span>
            <span class="label">Defines core.manage</span>
            <span class="value"><?php echo $accessHasManage ? 'Yes' : 'No'; ?></span>
        </div>
        <?php endif; ?>
    </div>

    <?php
    // ---- 4. Admin language (menu label) ----
    $sysIniPaths = [
        $adminBase . '/language/en-GB/com_ordenproduccion.sys.ini',
        JPATH_ADMINISTRATOR . '/language/en-GB/en-GB.com_ordenproduccion.sys.ini',
    ];
    $labelResolved = false;
    foreach ($sysIniPaths as $p) {
        if (file_exists($p)) {
            $ini = @parse_ini_file($p, false, INI_SCANNER_RAW);
            if (!empty($ini['COM_ORDENPRODUCCION']) && strpos($ini['COM_ORDENPRODUCCION'], 'COM_ORDENPRODUCCION') !== 0) {
                $labelResolved = true;
                break;
            }
        }
    }
    ?>
    <div class="section">
        <h2>4. Admin language (menu label)</h2>
        <div class="row">
            <span class="status <?php echo $labelResolved ? 'ok' : 'warn'; ?>"><?php echo $labelResolved ? '✓' : '!'; ?></span>
            <span class="label">COM_ORDENPRODUCCION resolved</span>
            <span class="value"><?php echo $labelResolved ? 'Yes (menu will show translated label)' : 'May show raw key – check admin language .sys.ini'; ?></span>
        </div>
    </div>

    <?php
    // ---- 5. Dashboard (menu target) ----
    $dashboardView = file_exists($adminBase . '/src/View/Dashboard/HtmlView.php');
    $displayController = file_exists($adminBase . '/src/Controller/DisplayController.php');
    $dashboardOk = $dashboardView && $displayController;
    ?>
    <div class="section">
        <h2>5. Dashboard (menu target)</h2>
        <div class="row">
            <span class="status <?php echo $dashboardView ? 'ok' : 'fail'; ?>"><?php echo $dashboardView ? '✓' : '✗'; ?></span>
            <span class="label">View/Dashboard/HtmlView.php</span>
            <span class="value"><?php echo $dashboardView ? 'Exists' : 'Missing'; ?></span>
        </div>
        <div class="row">
            <span class="status <?php echo $displayController ? 'ok' : 'fail'; ?>"><?php echo $displayController ? '✓' : '✗'; ?></span>
            <span class="label">Controller/DisplayController.php</span>
            <span class="value"><?php echo $displayController ? 'Exists' : 'Missing'; ?></span>
        </div>
        <?php if (!$dashboardOk) $allOk = false; ?>
    </div>

    <?php
    // ---- 6. Asset and core.manage ----
    $q = $db->getQuery(true)
        ->select('id, name, rules')
        ->from($db->quoteName('#__assets'))
        ->where($db->quoteName('name') . ' = ' . $db->quote($component));
    $db->setQuery($q);
    $asset = $db->loadObject();
    $hasAsset = (bool) $asset;
    $rules = $asset && $asset->rules ? json_decode($asset->rules, true) : [];
    $hasManage = is_array($rules) && isset($rules['core.manage']);
    ?>
    <div class="section">
        <h2>6. Component asset and permissions</h2>
        <div class="row">
            <span class="status <?php echo $hasAsset ? 'ok' : 'fail'; ?>"><?php echo $hasAsset ? '✓' : '✗'; ?></span>
            <span class="label">Asset row</span>
            <span class="value"><?php echo $hasAsset ? 'ID ' . (int) $asset->id : 'Missing – menu can be hidden'; ?></span>
        </div>
        <?php if (!$hasAsset) $allOk = false; ?>
        <div class="row">
            <span class="status <?php echo $hasManage ? 'ok' : 'warn'; ?>"><?php echo $hasManage ? '✓' : '!'; ?></span>
            <span class="label">core.manage in rules</span>
            <span class="value"><?php echo $hasManage ? 'Present' : 'Check Permissions for your group'; ?></span>
        </div>
    </div>

    <?php // ---- 7. Summary and fix ---- ?>
    <div class="section">
        <h2>7. Summary</h2>
        <p>
            <?php if ($allOk): ?>
            <span class="status ok">✓</span> All critical checks passed. If the menu still does not appear: clear cache (System → Clear Cache), hard-refresh the backend, or log out and back in.
            <?php else: ?>
            <span class="status fail">✗</span> Some checks failed. Use the <strong>Apply fix</strong> button below to patch the manifest and update the extension cache, then clear admin cache.
            <?php endif; ?>
        </p>
    </div>

    <div class="actions">
        <h3>Apply fix</h3>
        <p>This will:</p>
        <ol>
            <li>Patch <code>com_ordenproduccion.xml</code>: add <code>link="option=com_ordenproduccion&amp;view=dashboard"</code> to &lt;menu&gt; and <code>&lt;filename&gt;access.xml&lt;/filename&gt;</code> to admin &lt;files&gt; (if missing).</li>
            <li>Update <code>#__extensions.manifest_cache</code> so the cached manifest includes the menu link.</li>
        </ol>
        <p><a href="?fix=1" class="btn">Apply fix now</a></p>
        <p><strong>After applying:</strong> Reload this page without <code>?fix=1</code>, then go to <strong>System → Clear Cache</strong> and clear all. Check <strong>Components</strong> again.</p>
    </div>

    <div class="section" style="border-color: #c62828; background: #ffebee;">
        <h2>If still no menu item</h2>
        <ol style="margin: 0; padding-left: 20px;">
            <li><strong>Is "Cotizaciones" in the list?</strong> Click it. If it opens the Work Orders dashboard, that is this component under a different label (language override or alias).</li>
            <li><strong>Manifest file on disk:</strong> Reload this page <em>without</em> <code>?fix=1</code>. In section 2, if "Menu has <code>link=</code>" is ✗, the manifest file was not written (e.g. permissions). Copy <code>com_ordenproduccion.xml</code> from your repo to <code>administrator/components/com_ordenproduccion/</code> via SSH/FTP, or run: <code>chown www-data:www-data</code> (or your web user) on that file and try Apply fix again.</li>
            <li><strong>Reinstall from repo:</strong> Replace the whole <code>administrator/components/com_ordenproduccion/</code> folder with the version from your repo (with the correct manifest and access.xml), then in backend use <strong>System → Manage → Extensions</strong>, find Work Orders, and use <strong>Update</strong> or reinstall the component. Then clear cache again.</li>
            <li><strong>Debug:</strong> In <strong>System → Configuration → System</strong>, set Debug System to Yes, then open the Components menu and check the error log for PHP notices or errors when building the menu.</li>
        </ol>
    </div>
</div>
</body>
</html>
