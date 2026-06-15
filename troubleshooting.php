<?php
/**
 * Odoo / Mis Clientes diagnostic for com_ordenproduccion.
 *
 * Standalone: https://yoursite.com/troubleshooting.php
 * Sourcerer (Joomla article): {source type=php}<?php require JPATH_ROOT . '/troubleshooting.php'; ?>{/source}
 *
 * Optional params: odoo_user_id, odoo_login
 */

declare(strict_types=1);

/**
 * True when executed directly at Joomla root; false when included via Sourcerer/article.
 */
function tsIsStandalone(): bool
{
    $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    return $script === 'troubleshooting.php';
}

/**
 * Bootstrap Joomla for standalone access, or reuse the running frontend app (Sourcerer).
 */
function tsGetApplication(): \Joomla\CMS\Application\CMSApplication
{
    if (defined('_JEXEC') && class_exists(\Joomla\CMS\Factory::class)) {
        try {
            return \Joomla\CMS\Factory::getApplication();
        } catch (\Throwable $e) {
            if (!tsIsStandalone()) {
                throw $e;
            }
        }
    }

    if (!defined('_JEXEC')) {
        define('_JEXEC', 1);
    }

    if (!defined('JPATH_BASE')) {
        $roots = [__DIR__, '/var/www/grimpsa_webserver'];
        foreach ($roots as $path) {
            if (is_file($path . '/configuration.php')) {
                define('JPATH_BASE', $path);
                break;
            }
        }
        if (!defined('JPATH_BASE')) {
            throw new \RuntimeException('Cannot find Joomla root (configuration.php).');
        }
    }

    require_once JPATH_BASE . '/includes/defines.php';
    require_once JPATH_BASE . '/includes/framework.php';

    $container = \Joomla\CMS\Factory::getContainer();
    $container->alias('session.web', 'session.web.site')
        ->alias('session', 'session.web.site')
        ->alias('JSession', 'session.web.site')
        ->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
        ->alias(\Joomla\Session\Session::class, 'session.web.site')
        ->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

    $app = $container->get(\Joomla\CMS\Application\SiteApplication::class);
    \Joomla\CMS\Factory::$application = $app;
    $app->createExtensionNamespaceMap();

    return $app;
}

/**
 * @param array<string, mixed> $check
 */
function tsStatusClass(array $check): string
{
    $map = ['pass' => 'ok', 'fail' => 'err', 'warn' => 'warn'];

    return $map[$check['status'] ?? ''] ?? 'info';
}

/**
 * Form target for Sourcerer/article embed — keeps current menu Itemid.
 */
function tsEmbeddedFormAction(\Joomla\CMS\Application\CMSApplication $app): string
{
    $itemId = $app->getInput()->getInt('Itemid', 0);
    if ($itemId > 0) {
        return \Joomla\CMS\Router\Route::_('index.php?Itemid=' . $itemId, false);
    }

    return \Joomla\CMS\Uri\Uri::getInstance()->toString(['path']);
}

function tsRender(array $vars): void
{
    extract($vars, EXTR_SKIP);
    $standalone = tsIsStandalone();

    if ($standalone && !headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($standalone): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Odoo diagnostic — com_ordenproduccion</title>
<?php endif; ?>
    <style>
        .odoo-ts, .odoo-ts * { box-sizing: border-box; }
        .odoo-ts { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #222; margin: 16px 0; }
        .odoo-ts .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .odoo-ts h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .odoo-ts h2 { margin: 16px 0 8px; font-size: 1.05rem; }
        .odoo-ts .subtitle { color: #666; margin-bottom: 20px; }
        .odoo-ts table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .odoo-ts th, .odoo-ts td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        .odoo-ts th { font-weight: 600; background: #f5f5f5; }
        .odoo-ts code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
        .odoo-ts .btn { display: inline-block; padding: 6px 14px; border-radius: 4px; border: none; cursor: pointer; font-size: 13px; background: #1976d2; color: #fff; text-decoration: none; }
        .odoo-ts .btn:hover { background: #1565c0; color: #fff; }
        .odoo-ts .result-err { color: #c62828; font-weight: 600; }
        .odoo-ts .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .odoo-ts .badge.ok, .odoo-ts .badge.pass { background: #e8f5e9; color: #2e7d32; }
        .odoo-ts .badge.err, .odoo-ts .badge.fail { background: #ffebee; color: #c62828; }
        .odoo-ts .badge.warn { background: #fff8e1; color: #f57f17; }
        .odoo-ts .badge.info { background: #e3f2fd; color: #1565c0; }
        .odoo-ts .check-row { margin: 6px 0; padding: 8px 10px; border-radius: 4px; background: #fafafa; border: 1px solid #eee; }
        .odoo-ts .check-row.ok { border-left: 4px solid #43a047; }
        .odoo-ts .check-row.err { border-left: 4px solid #e53935; }
        .odoo-ts .check-row.warn { border-left: 4px solid #fb8c00; }
        .odoo-ts .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 12px 0; }
        .odoo-ts .meta-item { background: #fafafa; border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .odoo-ts .meta-item strong { display: block; font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 4px; }
        .odoo-ts .inline-form { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 12px 0; }
        .odoo-ts .inline-form input { padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; }
        .odoo-ts ul.agents { margin: 8px 0; padding-left: 20px; }
        .odoo-ts .footer { margin-top: 16px; color: #666; font-size: 12px; }
    </style>
<?php if ($standalone): ?>
</head>
<body>
<?php endif; ?>
<div class="odoo-ts">
<div class="container">
    <h1>Odoo connection &amp; Mis Clientes</h1>
    <p class="subtitle">
        Stored credentials from <strong>Components → Orden de Producción → Options → Odoo connection</strong>.
        Mis Clientes matches Odoo <code>x_studio_agente_de_ventas</code> to Joomla <code>user.name</code>.
        <?php if (!empty($componentVersion)): ?>
            Component: <code><?php echo htmlspecialchars($componentVersion); ?></code>
        <?php endif; ?>
        <?php if (!$standalone): ?>
            <br><em>Rendered via Joomla (Sourcerer)</em>
        <?php endif; ?>
    </p>

    <?php if (!empty($fatalError)): ?>
        <p class="result-err"><?php echo htmlspecialchars((string) $fatalError); ?></p>
    <?php elseif (!empty($componentBootError)): ?>
        <p class="result-err">Component boot failed: <?php echo htmlspecialchars((string) $componentBootError); ?></p>
    <?php elseif (!empty($odooError)): ?>
        <p class="result-err">Diagnostic error: <?php echo htmlspecialchars((string) $odooError); ?></p>
    <?php elseif (!empty($odooReport)): ?>
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

        <form method="get" action="<?php echo htmlspecialchars((string) $formAction); ?>" class="inline-form">
            <label for="odoo_user_id">Joomla user ID</label>
            <input type="number" id="odoo_user_id" name="odoo_user_id" value="<?php echo (int) $odooUserId > 0 ? (int) $odooUserId : ''; ?>" placeholder="all users" min="1" style="width:90px;" />
            <label for="odoo_login">Odoo login (UID check)</label>
            <input type="text" id="odoo_login" name="odoo_login" value="<?php echo htmlspecialchars((string) $odooLogin); ?>" placeholder="optional email" style="width:220px;" />
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
                        <th>Probe</th>
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
                        <td><?php echo (int) ($t['helper_probe_count'] ?? 0); ?></td>
                        <td><span class="badge <?php echo htmlspecialchars((string) ($t['status'] ?? 'info')); ?>"><?php echo htmlspecialchars((string) ($t['status'] ?? '')); ?></span></td>
                        <?php
                        $helperFaultMsg = '';
                        if (!empty($t['helper_fault']) && class_exists(\Grimpsa\Component\Ordenproduccion\Site\Helper\OdooDiagnosticHelper::class)) {
                            $helperFaultMsg = \Grimpsa\Component\Ordenproduccion\Site\Helper\OdooDiagnosticHelper::summarizeFaultString((string) $t['helper_fault']);
                        } elseif (!empty($t['helper_fault'])) {
                            $helperFaultMsg = (string) $t['helper_fault'];
                        }
                        $rowMsg = (string) ($t['message'] ?? '');
                        if ($helperFaultMsg !== '') {
                            $rowMsg .= ' · Helper: ' . $helperFaultMsg;
                        }
                        ?>
                        <td><?php echo htmlspecialchars($rowMsg); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <p class="footer">
            CLI: <code>php components/com_ordenproduccion/tools/test_odoo_connection.php</code>
        </p>
    <?php endif; ?>
</div>
</div>
<?php if ($standalone): ?>
</body>
</html>
<?php endif;
}

// ---------------------------------------------------------------------------
// Run diagnostic
// ---------------------------------------------------------------------------

$fatalError = null;
$componentBootError = null;
$odooError = null;
$odooReport = null;
$odooUserId = 0;
$odooLogin = '';
$componentVersion = '';
$formAction = '';

try {
    $app = tsGetApplication();
    $input = $app->getInput();

    $odooUserId = max(0, $input->getInt('odoo_user_id', 0));
    $odooLogin  = trim($input->getString('odoo_login', '', 'STRING'));

    $formAction = tsIsStandalone()
        ? (string) ($_SERVER['SCRIPT_NAME'] ?? '/troubleshooting.php')
        : tsEmbeddedFormAction($app);

    try {
        $app->bootComponent('com_ordenproduccion');
    } catch (\Throwable $e) {
        $componentBootError = $e->getMessage();
    }

    if ($componentBootError === null) {
        $helperClass = \Grimpsa\Component\Ordenproduccion\Site\Helper\OdooDiagnosticHelper::class;
        if (!class_exists($helperClass)) {
            throw new \RuntimeException(
                'OdooDiagnosticHelper not found. Deploy com_ordenproduccion and clear Joomla cache.'
            );
        }

        $odooReport = (new $helperClass())->run([
            'joomla_user_id' => $odooUserId,
            'odoo_login'     => $odooLogin,
            'scan_users'     => $odooUserId === 0,
            'user_limit'     => 30,
        ]);
    }

    $root = defined('JPATH_ROOT') ? JPATH_ROOT : (defined('JPATH_BASE') ? JPATH_BASE : '');
    $versionFile = $root . '/components/com_ordenproduccion/VERSION';
    if ($root !== '' && is_file($versionFile)) {
        $componentVersion = trim((string) file_get_contents($versionFile));
    }
} catch (\Throwable $e) {
    $fatalError = $e->getMessage();
}

tsRender(compact(
    'fatalError',
    'componentBootError',
    'odooError',
    'odooReport',
    'odooUserId',
    'odooLogin',
    'componentVersion',
    'formAction'
));
