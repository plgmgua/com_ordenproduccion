<?php
/**
 * Verificar pago — MT-940 & aprobaciones diagnostic for com_ordenproduccion.
 *
 * Standalone: https://yoursite.com/troubleshooting.php
 *
 * Sourcerer (Joomla article) — do NOT paste this whole file into the article.
 * Upload troubleshooting.php to the Joomla root, then put ONLY this in Sourcerer:
 *   require JPATH_ROOT . '/troubleshooting.php';
 * (Wrap that line in Sourcerer's php source tags in the article editor.)
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
    <title>Verificar pago — MT-940 diagnostics</title>
<?php endif; ?>
    <style>
        .odoo-ts, .odoo-ts * { box-sizing: border-box; }
        .odoo-ts { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; color: #222; margin: 16px 0; }
        .odoo-ts .container { max-width: 1100px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; border: 1px solid #e0e0e0; }
        .odoo-ts h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .odoo-ts h2 { margin: 16px 0 8px; font-size: 1.05rem; }
        .odoo-ts .subtitle { color: #666; margin-bottom: 20px; }
        .odoo-ts code { background: #eee; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
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
    </style>
<?php if ($standalone): ?>
</head>
<body>
<?php endif; ?>
<div class="odoo-ts">
<div class="container">
    <h1>Verificar pago — MT-940 &amp; aprobaciones</h1>
    <p class="subtitle">
        Cron matching, component options, approval workflow <code>payment_proof</code>, and match log.
        Options: <strong>Components → Orden Producción → Options → Configuration</strong>.
        Workflow: <strong>Administración → Ajustes → Flujos de aprobaciones → Comprobante de pago</strong> (must be <em>Publicado</em>).
        <?php if (!empty($componentVersion)): ?>
            <br>Component: <code><?php echo htmlspecialchars($componentVersion); ?></code>
        <?php endif; ?>
        <?php if (!$standalone): ?>
            <br><em>Rendered via Joomla (Sourcerer)</em>
        <?php endif; ?>
    </p>

    <?php if (!empty($fatalError)): ?>
        <p class="result-err"><?php echo htmlspecialchars((string) $fatalError); ?></p>
    <?php elseif (!empty($componentBootError)): ?>
        <p class="result-err">Component boot failed: <?php echo htmlspecialchars((string) $componentBootError); ?></p>
    <?php elseif (!empty($paymentVerifyError)): ?>
        <p class="result-err">Payment verification diagnostic error: <?php echo htmlspecialchars((string) $paymentVerifyError); ?></p>
    <?php elseif (!empty($paymentVerifyReport)): ?>
        <?php
        $pvMeta = $paymentVerifyReport['meta'] ?? [];
        $pvStatus = (string) ($pvMeta['status'] ?? 'info');
        $pvStatusLabel = $pvStatus === 'ok' ? 'OK' : ($pvStatus === 'fail' ? 'FAIL' : 'WARN');
        $pvStatusClass = $pvStatus === 'ok' ? 'ok' : ($pvStatus === 'fail' ? 'err' : 'warn');
        $pvCfg = $paymentVerifyReport['config'] ?? [];
        ?>
        <p>
            <span class="badge <?php echo htmlspecialchars($pvStatusClass); ?>"><?php echo htmlspecialchars($pvStatusLabel); ?></span>
            Failures: <?php echo (int) ($pvMeta['failures'] ?? 0); ?>
            &nbsp; Warnings: <?php echo (int) ($pvMeta['warnings'] ?? 0); ?>
            &nbsp; Run: <?php echo htmlspecialchars((string) ($pvMeta['time'] ?? '')); ?>
        </p>
        <div class="meta-grid">
            <div class="meta-item"><strong>approval_workflow_payment_proof</strong><?php echo htmlspecialchars((string) ($pvCfg['approval_workflow_payment_proof'] ?? '')); ?></div>
            <div class="meta-item"><strong>payment_proof_mt940_verification</strong><?php echo htmlspecialchars((string) ($pvCfg['payment_proof_mt940_verification'] ?? '')); ?></div>
            <div class="meta-item"><strong>MT-940 mode active</strong><?php echo htmlspecialchars((string) ($pvCfg['mt940_verification_active'] ?? '')); ?></div>
        </div>
        <?php foreach ($paymentVerifyReport['sections'] ?? [] as $section): ?>
            <h2><?php echo htmlspecialchars((string) ($section['title'] ?? '')); ?></h2>
            <?php if (!empty($section['details']) && is_array($section['details'])): ?>
                <?php foreach ($section['details'] as $dk => $dv): ?>
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
$paymentVerifyReport = null;
$paymentVerifyError = null;
$componentVersion = '';

try {
    $app = tsGetApplication();

    try {
        $app->bootComponent('com_ordenproduccion');
    } catch (\Throwable $e) {
        $componentBootError = $e->getMessage();
    }

    if ($componentBootError === null) {
        $pvClass = 'Grimpsa\\Component\\Ordenproduccion\\Site\\Helper\\PaymentVerificationDiagnosticHelper';
        if (class_exists($pvClass)) {
            try {
                $paymentVerifyReport = (new $pvClass())->run();
            } catch (\Throwable $e) {
                $paymentVerifyError = $e->getMessage();
            }
        } else {
            $paymentVerifyError = 'PaymentVerificationDiagnosticHelper not found. Deploy com_ordenproduccion 3.119.229+ and clear cache.';
        }
    }

    $root = defined('JPATH_ROOT') ? JPATH_ROOT : (defined('JPATH_BASE') ? JPATH_BASE : '');
    $versionFile = $root . '/components/com_ordenproduccion/VERSION';
    if ($root !== '' && is_file($versionFile)) {
        $componentVersion = trim((string) file_get_contents($versionFile));
    } elseif ($root !== '' && class_exists(\Joomla\CMS\Factory::class)) {
        try {
            $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
            $db->setQuery($query);
            $manifestCache = (string) $db->loadResult();
            if ($manifestCache !== '') {
                $manifest = json_decode($manifestCache, true);
                if (is_array($manifest) && !empty($manifest['version'])) {
                    $componentVersion = trim((string) $manifest['version']);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
} catch (\Throwable $e) {
    $fatalError = $e->getMessage();
}

tsRender(compact(
    'fatalError',
    'componentBootError',
    'paymentVerifyReport',
    'paymentVerifyError',
    'componentVersion'
));
