<?php
/**
 * Odoo / Mis Clientes diagnostic for com_ordenproduccion (Joomla root).
 *
 * Uses stored component credentials and scans Joomla users automatically.
 *
 * Run: https://yoursite.com/troubleshooting.php
 * Optional: ?odoo_user_id=15&odoo_login=integration@company.com
 */

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
            die('ERROR: Cannot find Joomla root.');
        }
    }
    require_once JPATH_ROOT . '/includes/defines.php';
    require_once JPATH_ROOT . '/includes/framework.php';
}

use Grimpsa\Component\Ordenproduccion\Site\Helper\OdooDiagnosticHelper;
use Joomla\CMS\Factory;

$app = Factory::getApplication('site');

$componentBootError = null;
try {
    $app->bootComponent('com_ordenproduccion');
} catch (\Throwable $e) {
    $componentBootError = $e->getMessage();
}

$odooUserId = isset($_GET['odoo_user_id']) ? max(0, (int) $_GET['odoo_user_id']) : 0;
$odooLogin  = isset($_GET['odoo_login']) ? trim((string) $_GET['odoo_login']) : '';

$odooReport = null;
$odooError  = null;

if ($componentBootError === null) {
    try {
        $odooReport = (new OdooDiagnosticHelper())->run([
            'joomla_user_id' => $odooUserId,
            'odoo_login'     => $odooLogin,
            'scan_users'     => $odooUserId === 0,
            'user_limit'     => 30,
        ]);
    } catch (\Throwable $e) {
        $odooError = $e->getMessage();
    }
}

$componentVersion = '';
$versionFile = JPATH_ROOT . '/components/com_ordenproduccion/VERSION';
if (is_file($versionFile)) {
    $componentVersion = trim((string) file_get_contents($versionFile));
}

/**
 * @param array<string, mixed> $check
 */
function tsStatusClass(array $check): string
{
    $map = ['pass' => 'ok', 'fail' => 'err', 'warn' => 'warn'];

    return $map[$check['status'] ?? ''] ?? 'info';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odoo diagnostic — com_ordenproduccion</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f0f0f0; font-size: 14px; color: #222; }
        .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        h2 { margin: 16px 0 8px; font-size: 1.05rem; }
        .subtitle { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { font-weight: 600; background: #f5f5f5; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .btn { display: inline-block; padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; background: #1976d2; color: #fff; }
        .btn:hover { background: #1565c0; }
        .result-err { color: #c62828; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge.ok, .badge.pass { background: #e8f5e9; color: #2e7d32; }
        .badge.err, .badge.fail { background: #ffebee; color: #c62828; }
        .badge.warn { background: #fff8e1; color: #f57f17; }
        .badge.info { background: #e3f2fd; color: #1565c0; }
        .check-row { margin: 6px 0; padding: 8px 10px; border-radius: 4px; background: #fafafa; border: 1px solid #eee; }
        .check-row.ok { border-left: 4px solid #43a047; }
        .check-row.err { border-left: 4px solid #e53935; }
        .check-row.warn { border-left: 4px solid #fb8c00; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 12px 0; }
        .meta-item { background: #fafafa; border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .meta-item strong { display: block; font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 4px; }
        .inline-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 12px 0; }
        .inline-form input { padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; }
        ul.agents { margin: 8px 0; padding-left: 20px; }
        .footer { margin-top: 16px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Odoo connection &amp; Mis Clientes</h1>
    <p class="subtitle">
        Reads stored credentials from <strong>Components → Orden de Producción → Options → Odoo connection</strong>.
        Mis Clientes matches Odoo <code>x_studio_agente_de_ventas</code> to the Joomla user's <code>name</code>.
        <?php if ($componentVersion !== ''): ?>
            Component: <code><?php echo htmlspecialchars($componentVersion); ?></code>
        <?php endif; ?>
    </p>

    <?php if ($componentBootError !== null): ?>
        <p class="result-err">Component boot failed: <?php echo htmlspecialchars($componentBootError); ?></p>
    <?php elseif ($odooError !== null): ?>
        <p class="result-err">Diagnostic error: <?php echo htmlspecialchars($odooError); ?></p>
    <?php elseif ($odooReport !== null): ?>
        <?php
        $meta = $odooReport['meta'] ?? [];
        $status = (string) ($meta['status'] ?? 'info');
        $statusLabel = $status === 'ok' ? 'OK' : ($status === 'fail' ? 'FAIL' : 'WARN');
        $statusClass = $status === 'ok' ? 'ok' : ($status === 'fail' ? 'err' : 'warn');
        $cfg = $odooReport['config'] ?? [];
        ?>

        <p>
            <span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
            Failures: <?php echo (int) ($meta['failures'] ?? 0); ?>
            &nbsp; Warnings: <?php echo (int) ($meta['warnings'] ?? 0); ?>
            &nbsp; Run: <?php echo htmlspecialchars((string) ($meta['time'] ?? '')); ?>
        </p>

        <form method="get" action="" class="inline-form">
            <label for="odoo_user_id">Joomla user ID</label>
            <input type="number" id="odoo_user_id" name="odoo_user_id" value="<?php echo $odooUserId > 0 ? $odooUserId : ''; ?>" placeholder="all users" min="1" style="width:90px;" />
            <label for="odoo_login">Odoo login (UID check)</label>
            <input type="text" id="odoo_login" name="odoo_login" value="<?php echo htmlspecialchars($odooLogin); ?>" placeholder="optional email" style="width:220px;" />
            <button type="submit" class="btn">Re-run tests</button>
        </form>

        <div class="meta-grid">
            <div class="meta-item"><strong>Odoo URL</strong><?php echo htmlspecialchars((string) ($cfg['odoo_url'] ?? '')); ?></div>
            <div class="meta-item"><strong>Database</strong><?php echo htmlspecialchars((string) ($cfg['odoo_db'] ?? '')); ?></div>
            <div class="meta-item"><strong>User ID</strong><?php echo (int) ($cfg['odoo_user_id'] ?? 0); ?></div>
            <div class="meta-item"><strong>API key</strong><?php echo htmlspecialchars((string) ($cfg['odoo_api_key'] ?? '')); ?></div>
        </div>

        <?php foreach ($odooReport['sections'] ?? [] as $section): ?>
            <h2><?php echo htmlspecialchars((string) ($section['title'] ?? '')); ?></h2>
            <?php if (!empty($section['details']) && is_array($section['details'])): ?>
                <?php foreach ($section['details'] as $dk => $dv): ?>
                    <?php if ($dk === 'sample_agents' || $dk === 'odoo_agents_sample') { continue; } ?>
                    <p><code><?php echo htmlspecialchars((string) $dk); ?></code>: <?php echo htmlspecialchars(is_scalar($dv) ? (string) $dv : json_encode($dv)); ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php foreach ($section['checks'] ?? [] as $check): ?>
                <div class="check-row <?php echo htmlspecialchars(tsStatusClass($check)); ?>">
                    <span class="badge <?php echo htmlspecialchars(tsStatusClass($check)); ?>"><?php echo htmlspecialchars((string) ($check['status'] ?? '')); ?></span>
                    <strong><?php echo htmlspecialchars((string) ($check['label'] ?? '')); ?></strong>
                    — <?php echo htmlspecialchars((string) ($check['detail'] ?? '')); ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <?php $sampleAgents = $odooReport['odoo_agent_sample'] ?? []; ?>
        <?php if ($sampleAgents !== []): ?>
            <h2>Odoo sales-agent values (sample)</h2>
            <ul class="agents">
                <?php foreach ($sampleAgents as $agent): ?>
                    <li><?php echo htmlspecialchars((string) $agent); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php $agentTests = $odooReport['agent_tests'] ?? []; ?>
        <?php if ($agentTests !== []): ?>
            <h2>Mis Clientes simulation per Joomla user</h2>
            <table>
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Joomla name</th>
                        <th>Odoo match</th>
                        <th>RPC</th>
                        <th>Helper</th>
                        <th>Status</th>
                        <th>Message</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agentTests as $t): ?>
                    <tr>
                        <td><?php echo (int) ($t['joomla_user_id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($t['joomla_user_name'] ?? '')); ?></td>
                        <td><?php echo !empty($t['odoo_exact_match']) ? 'yes' : 'no'; ?></td>
                        <td><?php echo (int) ($t['rpc_count'] ?? 0); ?></td>
                        <td><?php echo (int) ($t['helper_count'] ?? 0); ?></td>
                        <td><span class="badge <?php echo htmlspecialchars((string) ($t['status'] ?? 'info')); ?>"><?php echo htmlspecialchars((string) ($t['status'] ?? '')); ?></span></td>
                        <td><?php echo htmlspecialchars((string) ($t['message'] ?? '')); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="footer">
            CLI: <code>php components/com_ordenproduccion/tools/test_odoo_connection.php</code>
            &nbsp;|&nbsp;
            <a href="https://www.odoo.com/documentation/saas-19.3/developer/reference/external_rpc_api.html" target="_blank" rel="noopener">Odoo External RPC API</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
