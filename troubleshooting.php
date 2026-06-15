<?php
/**
 * com_ordenproduccion troubleshooting (Joomla root)
 *
 * - Odoo / Mis Clientes: full diagnostic using stored component credentials + Joomla users
 * - Webhook: validate pending pre-cotizaciones API vs SQL
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
$config = Factory::getConfig();

try {
    $app->bootComponent('com_ordenproduccion');
} catch (\Throwable $e) {
    $componentBootError = $e->getMessage();
}

// --- Odoo diagnostic (auto-run with stored credentials) ---
$odooReport = null;
$odooError = null;
$odooUserId = isset($_GET['odoo_user_id']) ? max(0, (int) $_GET['odoo_user_id']) : 0;
$odooLogin = isset($_GET['odoo_login']) ? trim((string) $_GET['odoo_login']) : '';
$runOdoo = !isset($_GET['skip_odoo']);

if ($runOdoo && empty($componentBootError)) {
    try {
        $helper = new OdooDiagnosticHelper();
        $odooReport = $helper->run([
            'joomla_user_id' => $odooUserId,
            'odoo_login'     => $odooLogin,
            'scan_users'     => $odooUserId === 0,
            'user_limit'     => 30,
        ]);
    } catch (\Throwable $e) {
        $odooError = $e->getMessage();
    }
}

// --- Validate pending pre-cotizaciones webhook ---
$webhookValidation = null;
if (isset($_POST['validate_webhook']) && isset($_POST['client_id'])) {
    $clientId = trim((string) $_POST['client_id']);
    if ($clientId !== '') {
        try {
            $db = Factory::getDbo();
            $q = $db->getQuery(true);
            $q->select([
                $db->quoteName('q.id', 'quotation_id'),
                'COALESCE(NULLIF(TRIM(q.quotation_number), \'\'), CONCAT(\'COT-\', LPAD(q.id, 6, \'0\'))) AS ' . $db->quoteName('quotation_number'),
                $db->quoteName('qi.pre_cotizacion_id'),
                'COALESCE(NULLIF(TRIM(pc.number), \'\'), CONCAT(\'PRE-\', LPAD(pc.id, 5, \'0\'))) AS ' . $db->quoteName('pre_cotizacion_number'),
                'COALESCE(pc.descripcion, \'\') AS ' . $db->quoteName('pre_cotizacion_description'),
            ]);
            $lineSub = 'COALESCE((SELECT SUM(l.total) FROM ' . $db->quoteName('#__ordenproduccion_pre_cotizacion_line', 'l') . ' WHERE l.pre_cotizacion_id = pc.id), 0)';
            $q->select($lineSub . ' AS ' . $db->quoteName('lines_subtotal'));
            $q->from($db->quoteName('#__ordenproduccion_quotations', 'q'));
            $q->innerJoin($db->quoteName('#__ordenproduccion_quotation_items', 'qi') . ' ON qi.quotation_id = q.id AND qi.pre_cotizacion_id IS NOT NULL');
            $q->innerJoin($db->quoteName('#__ordenproduccion_pre_cotizacion', 'pc') . ' ON pc.id = qi.pre_cotizacion_id AND pc.state = 1');
            $q->leftJoin($db->quoteName('#__ordenproduccion_ordenes', 'o') . ' ON o.pre_cotizacion_id = qi.pre_cotizacion_id AND o.state = 1');
            $q->where('q.state = 1');
            $q->where('(q.client_id = ' . $db->quote($clientId) . ' OR (q.client_id IS NOT NULL AND CAST(q.client_id AS CHAR) = ' . $db->quote((string) $clientId) . '))');
            $q->where('o.id IS NULL');
            $q->group([$db->quoteName('q.id'), $db->quoteName('q.quotation_number'), $db->quoteName('qi.pre_cotizacion_id'), $db->quoteName('pc.id'), $db->quoteName('pc.number'), $db->quoteName('pc.descripcion')]);
            $q->order($db->quoteName('q.id') . ', ' . $db->quoteName('qi.pre_cotizacion_id'));
            $db->setQuery($q);
            $sqlRows = $db->loadObjectList() ?: [];

            $apiUrl = null;
            $apiResponse = null;
            $apiError = null;
            $baseUrl = $config->get('live_site', '');
            if ($baseUrl === '' && isset($_SERVER['HTTP_HOST'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $baseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
                if (substr($baseUrl, -1) !== '/') {
                    $baseUrl .= '/';
                }
            }
            if ($baseUrl !== '') {
                $apiUrl = rtrim($baseUrl, '/') . '/index.php?option=com_ordenproduccion&task=webhook.pendingPrecotizaciones&format=json&client_id=' . urlencode($clientId);
                $ctx = stream_context_create(['http' => ['timeout' => 10]]);
                $raw = @file_get_contents($apiUrl, false, $ctx);
                if ($raw !== false) {
                    $apiResponse = json_decode($raw, true);
                    if ($apiResponse === null && json_last_error() !== JSON_ERROR_NONE) {
                        $apiError = 'Invalid JSON: ' . substr($raw, 0, 200);
                        $apiResponse = null;
                    }
                } else {
                    $apiError = 'Could not fetch URL (check allow_url_fopen or run from same server).';
                }
            } else {
                $apiError = 'Could not determine base URL (set live_site in configuration).';
            }

            $webhookValidation = [
                'client_id'    => $clientId,
                'sql_rows'     => $sqlRows,
                'api_url'      => $apiUrl,
                'api_response' => $apiResponse,
                'api_error'    => $apiError,
            ];
        } catch (Exception $e) {
            $webhookValidation = ['client_id' => $clientId, 'error' => $e->getMessage(), 'sql_rows' => [], 'api_response' => null, 'api_error' => null];
        }
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
    <title>com_ordenproduccion — Troubleshooting</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f0f0f0; font-size: 14px; color: #222; }
        .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        h2 { margin: 0 0 12px; font-size: 1.15rem; border-bottom: 1px solid #ddd; padding-bottom: 8px; }
        h3 { margin: 16px 0 8px; font-size: 1rem; }
        .subtitle { color: #666; margin-bottom: 20px; }
        .section { margin: 24px 0; padding: 16px; border: 1px solid #e0e0e0; border-radius: 6px; background: #fafafa; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        th { font-weight: 600; background: #f5f5f5; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .btn { display: inline-block; padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; background: #1976d2; color: #fff; }
        .btn:hover { background: #1565c0; color: #fff; }
        .btn-secondary { background: #607d8b; }
        .result-err { color: #c62828; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge.ok { background: #e8f5e9; color: #2e7d32; }
        .badge.err { background: #ffebee; color: #c62828; }
        .badge.warn { background: #fff8e1; color: #f57f17; }
        .badge.info { background: #e3f2fd; color: #1565c0; }
        .check-row { margin: 6px 0; padding: 8px 10px; border-radius: 4px; background: #fff; border: 1px solid #eee; }
        .check-row.ok { border-left: 4px solid #43a047; }
        .check-row.err { border-left: 4px solid #e53935; }
        .check-row.warn { border-left: 4px solid #fb8c00; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 12px 0; }
        .meta-item { background: #fff; border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .meta-item strong { display: block; font-size: 11px; color: #666; text-transform: uppercase; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 11px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }
        .inline-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
        .inline-form input[type="text"], .inline-form input[type="number"] { padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; }
        ul.agents { margin: 8px 0; padding-left: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>com_ordenproduccion — Troubleshooting</h1>
    <p class="subtitle">
        Diagnostics for Odoo (Mis Clientes) and pre-cotizaciones webhook.
        <?php if ($componentVersion !== ''): ?>
            Component version: <code><?php echo htmlspecialchars($componentVersion); ?></code>
        <?php endif; ?>
    </p>

    <div class="section">
        <h2>Odoo connection &amp; Mis Clientes</h2>
        <p>Uses stored credentials from <strong>Components → Orden de Producción → Options → Odoo connection</strong>.
           Mis Clientes filters Odoo contacts where <code>x_studio_agente_de_ventas</code> equals the logged-in Joomla user's <code>name</code>.</p>

        <?php if (!empty($componentBootError)): ?>
            <p class="result-err">Component boot failed: <?php echo htmlspecialchars($componentBootError); ?></p>
        <?php elseif ($odooError !== null): ?>
            <p class="result-err">Odoo diagnostic error: <?php echo htmlspecialchars($odooError); ?></p>
        <?php elseif ($odooReport !== null): ?>
            <?php
            $meta = $odooReport['meta'] ?? [];
            $status = (string) ($meta['status'] ?? 'info');
            $statusLabel = $status === 'ok' ? 'OK' : ($status === 'fail' ? 'FAIL' : 'WARN');
            $statusClass = $status === 'ok' ? 'ok' : ($status === 'fail' ? 'err' : 'warn');
            ?>
            <p>
                <span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                &nbsp; Failures: <?php echo (int) ($meta['failures'] ?? 0); ?>
                &nbsp; Warnings: <?php echo (int) ($meta['warnings'] ?? 0); ?>
                &nbsp; Run at: <?php echo htmlspecialchars((string) ($meta['time'] ?? '')); ?>
            </p>

            <form method="get" action="" class="inline-form">
                <label for="odoo_user_id">Joomla user ID</label>
                <input type="number" id="odoo_user_id" name="odoo_user_id" value="<?php echo $odooUserId > 0 ? $odooUserId : ''; ?>" placeholder="all users" min="1" style="width:90px;" />
                <label for="odoo_login">Odoo login (UID check)</label>
                <input type="text" id="odoo_login" name="odoo_login" value="<?php echo htmlspecialchars($odooLogin); ?>" placeholder="optional email" style="width:220px;" />
                <button type="submit" class="btn">Re-run Odoo tests</button>
                <a class="btn btn-secondary" href="?skip_odoo=1#webhook">Skip Odoo</a>
            </form>

            <?php $cfg = $odooReport['config'] ?? []; ?>
            <div class="meta-grid">
                <div class="meta-item"><strong>Odoo URL</strong><?php echo htmlspecialchars((string) ($cfg['odoo_url'] ?? '')); ?></div>
                <div class="meta-item"><strong>Database</strong><?php echo htmlspecialchars((string) ($cfg['odoo_db'] ?? '')); ?></div>
                <div class="meta-item"><strong>User ID</strong><?php echo (int) ($cfg['odoo_user_id'] ?? 0); ?></div>
                <div class="meta-item"><strong>API key</strong><?php echo htmlspecialchars((string) ($cfg['odoo_api_key'] ?? '')); ?></div>
            </div>

            <?php foreach ($odooReport['sections'] ?? [] as $section): ?>
                <h3><?php echo htmlspecialchars((string) ($section['title'] ?? '')); ?></h3>
                <?php if (!empty($section['details']) && is_array($section['details'])): ?>
                    <?php foreach ($section['details'] as $dk => $dv): ?>
                        <?php if ($dk === 'sample_agents' || $dk === 'odoo_agents_sample'): continue; endif; ?>
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
                <h3>Odoo sales-agent values (sample from parent contacts)</h3>
                <ul class="agents">
                    <?php foreach ($sampleAgents as $agent): ?>
                        <li><?php echo htmlspecialchars((string) $agent); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php $agentTests = $odooReport['agent_tests'] ?? []; ?>
            <?php if ($agentTests !== []): ?>
                <h3>Mis Clientes simulation per Joomla user</h3>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Joomla name (agent)</th>
                            <th>Odoo exact match</th>
                            <th>RPC contacts</th>
                            <th>Helper contacts</th>
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

            <p style="margin-top:14px; color:#666; font-size:12px;">
                CLI: <code>php components/com_ordenproduccion/tools/test_odoo_connection.php</code>
                &nbsp;|&nbsp; Odoo docs:
                <a href="https://www.odoo.com/documentation/saas-19.3/developer/reference/external_rpc_api.html" target="_blank" rel="noopener">External RPC API</a>
            </p>
        <?php elseif (!$runOdoo): ?>
            <p>Odoo tests skipped. <a href="?">Run Odoo diagnostic</a></p>
        <?php endif; ?>
    </div>

    <div class="section" id="webhook">
        <h2>Validate pending pre-cotizaciones webhook</h2>
        <p>Run the same dataset as the API (current DB) and call the API to compare.
           <code>pre_cotizacion_value</code> = lines subtotal + margin/IVA/ISR/commission.</p>

        <form method="post" action="#webhook" class="inline-form">
            <input type="hidden" name="validate_webhook" value="1" />
            <label for="client_id">client_id</label>
            <input type="text" id="client_id" name="client_id" value="<?php echo $webhookValidation !== null ? htmlspecialchars($webhookValidation['client_id']) : '7'; ?>" placeholder="e.g. 7" style="width:80px;" />
            <button type="submit" class="btn">Validate webhook</button>
        </form>

        <?php if ($webhookValidation !== null): ?>
            <?php if (isset($webhookValidation['error'])): ?>
                <p class="result-err" style="margin-top:12px;"><?php echo htmlspecialchars($webhookValidation['error']); ?></p>
            <?php else: ?>
                <h3>SQL result (current DB) — <?php echo count($webhookValidation['sql_rows']); ?> row(s)</h3>
                <table>
                    <thead>
                        <tr><th>quotation_id</th><th>quotation_number</th><th>pre_cotizacion_id</th><th>pre_cotizacion_number</th><th>pre_cotizacion_description</th><th>lines_subtotal</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($webhookValidation['sql_rows'] as $r): ?>
                        <tr>
                            <td><?php echo (int) ($r->quotation_id ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($r->quotation_number ?? '—'); ?></td>
                            <td><?php echo (int) ($r->pre_cotizacion_id ?? 0); ?></td>
                            <td><?php echo htmlspecialchars($r->pre_cotizacion_number ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($r->pre_cotizacion_description ?? ''); ?></td>
                            <td><?php echo number_format((float) ($r->lines_subtotal ?? 0), 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($webhookValidation['sql_rows'])): ?>
                <p class="result-err">No rows. Check that client_id exists on quotations and that pre-cotizaciones have no orden de trabajo.</p>
                <?php endif; ?>
                <h3>API response</h3>
                <?php if ($webhookValidation['api_error']): ?>
                <p class="result-err"><?php echo htmlspecialchars($webhookValidation['api_error']); ?></p>
                <?php endif; ?>
                <?php if ($webhookValidation['api_url']): ?>
                <p><strong>URL</strong>: <code style="word-break:break-all;"><?php echo htmlspecialchars($webhookValidation['api_url']); ?></code></p>
                <?php endif; ?>
                <?php if ($webhookValidation['api_response'] !== null): ?>
                <p><strong>API data</strong>: <?php echo isset($webhookValidation['api_response']['data']) ? count($webhookValidation['api_response']['data']) : 0; ?> row(s).</p>
                <pre><?php echo htmlspecialchars(json_encode($webhookValidation['api_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
