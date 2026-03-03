<?php
/**
 * Validate pending pre-cotizaciones webhook
 *
 * Runs the same dataset as the API (current Joomla DB) and calls the API to compare.
 * API returns the same rows; pre_cotizacion_value = lines subtotal + margin/IVA/ISR/commission.
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

// --- Validate pending pre-cotizaciones webhook (same dataset as API) ---
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
                if (substr($baseUrl, -1) !== '/') $baseUrl .= '/';
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

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validate pending pre-cotizaciones webhook</title>
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
        .btn { display: inline-block; padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; text-decoration: none; background: #1976d2; color: #fff; }
        .btn:hover { background: #1565c0; color: #fff; }
        .result-err { color: #c62828; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 11px; white-space: pre-wrap; word-break: break-all; max-height: 300px; overflow-y: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>Validate pending pre-cotizaciones webhook</h1>
    <p class="subtitle">Run the same dataset as the API (current DB) and call the API to compare. <code>pre_cotizacion_value</code> = lines subtotal + margin/IVA/ISR/commission.</p>

    <div class="section">
        <h2>Validate</h2>
        <form method="post" action="" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <input type="hidden" name="validate_webhook" value="1" />
            <label for="client_id">client_id</label>
            <input type="text" id="client_id" name="client_id" value="<?php echo $webhookValidation !== null ? htmlspecialchars($webhookValidation['client_id']) : '7'; ?>" placeholder="e.g. 7" style="width:80px;" />
            <button type="submit" class="btn">Validate webhook</button>
        </form>
        <?php if ($webhookValidation !== null): ?>
            <?php if (isset($webhookValidation['error'])): ?>
                <p class="result-err" style="margin-top:12px;"><?php echo htmlspecialchars($webhookValidation['error']); ?></p>
            <?php else: ?>
                <h3 style="margin-top:16px; font-size:1rem;">SQL result (current DB) — <?php echo count($webhookValidation['sql_rows']); ?> row(s)</h3>
                <table style="margin-top:8px;">
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
                <h3 style="margin-top:20px; font-size:1rem;">API response</h3>
                <?php if ($webhookValidation['api_error']): ?>
                <p class="result-err"><?php echo htmlspecialchars($webhookValidation['api_error']); ?></p>
                <?php endif; ?>
                <?php if ($webhookValidation['api_url']): ?>
                <p><strong>URL</strong>: <code style="word-break:break-all;"><?php echo htmlspecialchars($webhookValidation['api_url']); ?></code></p>
                <?php endif; ?>
                <?php if ($webhookValidation['api_response'] !== null): ?>
                <p><strong>API data</strong>: <?php echo isset($webhookValidation['api_response']['data']) ? count($webhookValidation['api_response']['data']) : 0; ?> row(s). Same quotation_id/pre_cotizacion_id as SQL above; <code>quotation_number</code> and <code>pre_cotizacion_value</code> (computed) in API.</p>
                <pre style="max-height:280px;"><?php echo htmlspecialchars(json_encode($webhookValidation['api_response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
