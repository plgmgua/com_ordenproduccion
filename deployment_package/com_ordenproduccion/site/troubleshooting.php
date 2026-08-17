<?php
/**
 * com_ordenproduccion — multi-tool troubleshooting hub (single file).
 *
 * Deploy to: JPATH_ROOT/troubleshooting.php
 *
 * Sourcerer — paste ONLY this one line inside Sourcerer php tags:
 *   require JPATH_ROOT . '/troubleshooting.php';
 *
 * Standalone URL: https://yoursite.com/troubleshooting.php
 *
 * Tools (?tool=): home | payment | precot | invoice | table | schema
 */

declare(strict_types=1);

if (defined('COM_ORDENPRODUCCION_TS_LOADED')) {
    return;
}
define('COM_ORDENPRODUCCION_TS_LOADED', true);

function tsJoomlaRootPath(): string
{
    if (defined('JPATH_ROOT') && JPATH_ROOT !== '') {
        return JPATH_ROOT;
    }
    if (defined('JPATH_BASE') && JPATH_BASE !== '') {
        return JPATH_BASE;
    }

    return __DIR__;
}

function tsIsStandalone(): bool
{
    $script = basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));

    return $script === 'troubleshooting.php';
}

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
        $roots = [
            tsJoomlaRootPath(),
            __DIR__,
            '/var/www/grimpsa_webserver',
        ];
        foreach (array_unique(array_filter($roots)) as $path) {
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

function tsInput(): \Joomla\Input\Input
{
    return tsGetApplication()->input;
}

/** @return array<int, string> */
function tsToolQueryKeys(): array
{
    return ['tool', 'pre_id', 'pre_number', 'invoice_id', 'fel_uuid', 'cot_number', 'quotation_id', 'table', 'limit'];
}

/**
 * @param array<string, scalar|null> $params
 */
function tsBuildUrl(array $params = []): string
{
    if (!class_exists(\Joomla\CMS\Uri\Uri::class)) {
        $q = http_build_query(array_filter($params, static fn($v) => $v !== '' && $v !== null));

        return $q !== '' ? ('?' . $q) : '?';
    }

    $uri = \Joomla\CMS\Uri\Uri::getInstance();
    $existing = $uri->getQuery(true);
    if (!is_array($existing)) {
        $existing = [];
    }

    foreach (tsToolQueryKeys() as $key) {
        unset($existing[$key]);
    }

    $merged = array_merge($existing, $params);
    $merged = array_filter(
        $merged,
        static fn($v) => $v !== '' && $v !== null && !is_array($v)
    );

    $newUri = clone $uri;
    $newUri->setQuery($merged);

    if (tsIsStandalone()) {
        $query = $newUri->getQuery();

        return $query !== '' ? ('?' . $query) : '?';
    }

    return (string) $newUri;
}

function tsFormAction(): string
{
    if (tsIsStandalone()) {
        return '';
    }
    if (!class_exists(\Joomla\CMS\Uri\Uri::class)) {
        return '';
    }

    return \Joomla\CMS\Uri\Uri::getInstance()->toString(['scheme', 'host', 'port', 'path']);
}

function tsRenderHiddenRoutingFields(): void
{
    if (tsIsStandalone() || !class_exists(\Joomla\CMS\Uri\Uri::class)) {
        return;
    }

    $vars = \Joomla\CMS\Uri\Uri::getInstance()->getQuery(true);
    if (!is_array($vars)) {
        return;
    }

    $skip = array_flip(tsToolQueryKeys());
    foreach ($vars as $key => $value) {
        if (isset($skip[$key]) || is_array($value)) {
            continue;
        }
        echo '<input type="hidden" name="' . htmlspecialchars((string) $key) . '" value="'
            . htmlspecialchars((string) $value) . '">';
    }
}

/**
 * @param array<string, mixed> $check
 */
function tsStatusClass(array $check): string
{
    $map = ['pass' => 'ok', 'fail' => 'err', 'warn' => 'warn', 'info' => 'info'];

    return $map[$check['status'] ?? ''] ?? 'info';
}

/**
 * @return array<int, array{slug: string, label: string, desc: string}>
 */
function tsToolCatalog(): array
{
    return [
        ['slug' => 'home', 'label' => 'Inicio', 'desc' => 'Entorno y lista de herramientas'],
        ['slug' => 'payment', 'label' => 'Verificar pago', 'desc' => 'MT-940, cron, aprobaciones payment_proof'],
        ['slug' => 'precot', 'label' => 'PRE → Cotización', 'desc' => 'Oferta, propietario, vínculos, selector'],
        ['slug' => 'invoice', 'label' => 'Factura', 'desc' => 'NUC vs DB, timbre, fixes Digifact'],
        ['slug' => 'table', 'label' => 'Tabla', 'desc' => 'Columnas, conteo y filas de muestra'],
        ['slug' => 'schema', 'label' => 'Schema', 'desc' => 'Tablas clave del componente'],
    ];
}

function tsCurrentTool(): string
{
    $tool = strtolower(trim((string) tsInput()->get('tool', 'home', 'cmd')));

    foreach (tsToolCatalog() as $t) {
        if ($t['slug'] === $tool) {
            return $tool;
        }
    }

    return 'home';
}

/**
 * @return array<string, mixed>
 */
function tsCollectInput(string $tool): array
{
    $input = tsInput();
    switch ($tool) {
        case 'precot':
            return [
                'pre_id'     => trim((string) $input->get('pre_id', '', 'string')),
                'pre_number' => trim((string) $input->get('pre_number', '', 'string')),
            ];
        case 'invoice':
            return [
                'invoice_id'   => trim((string) $input->get('invoice_id', '', 'string')),
                'fel_uuid'     => trim((string) $input->get('fel_uuid', '', 'string')),
                'cot_number'   => trim((string) $input->get('cot_number', '', 'string')),
                'quotation_id' => trim((string) $input->get('quotation_id', '', 'string')),
                'apply_fix'    => (int) $input->post->get('apply_fix', $input->get('apply_fix', 0, 'int'), 'int'),
                'fix'          => $input->post->get('fix', $input->get('fix', [], 'array'), 'array'),
            ];
        case 'table':
            return [
                'table' => trim((string) $input->get('table', '', 'string')),
                'limit' => trim((string) $input->get('limit', '5', 'string')),
            ];
        default:
            return [];
    }
}

function tsCanAccess(): bool
{
    $user = \Joomla\CMS\Factory::getUser();
    if ($user->guest) {
        return false;
    }
    if ($user->authorise('core.admin')) {
        return true;
    }
    $accessClass = 'Grimpsa\\Component\\Ordenproduccion\\Site\\Helper\\AccessHelper';

    return class_exists($accessClass) && $accessClass::isSuperUser();
}

function tsRender(array $vars): void
{
    extract($vars, EXTR_SKIP);
    $standalone = tsIsStandalone();
    $tool       = (string) ($currentTool ?? 'home');
    $catalog    = tsToolCatalog();

    if ($standalone && !headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }

    if ($standalone): ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>com_ordenproduccion — Troubleshooting</title>
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
        .odoo-ts .check-row.info { border-left: 4px solid #1e88e5; }
        .odoo-ts .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin: 12px 0; }
        .odoo-ts .meta-item { background: #fafafa; border: 1px solid #eee; padding: 10px; border-radius: 4px; }
        .odoo-ts .meta-item strong { display: block; font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 4px; }
        .odoo-ts nav.tools { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0; }
        .odoo-ts nav.tools a { padding: 6px 12px; border-radius: 4px; text-decoration: none; border: 1px solid #ccc; color: #333; background: #f5f5f5; font-size: 13px; }
        .odoo-ts nav.tools a.active { background: #1565c0; color: #fff; border-color: #1565c0; }
        .odoo-ts .tool-form { background: #f9f9f9; border: 1px solid #e0e0e0; padding: 16px; border-radius: 6px; margin: 16px 0; }
        .odoo-ts .tool-form label { display: block; margin: 8px 0 4px; font-weight: 600; font-size: 12px; }
        .odoo-ts .tool-form input[type=text], .odoo-ts .tool-form input[type=number] { width: 100%; max-width: 360px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .odoo-ts .tool-form button { margin-top: 12px; padding: 8px 16px; background: #1565c0; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
        .odoo-ts table.data { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 12px; }
        .odoo-ts table.data th, .odoo-ts table.data td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; vertical-align: top; }
        .odoo-ts table.data th { background: #f0f0f0; }
        .odoo-ts table.data tr:nth-child(even) { background: #fafafa; }
        .odoo-ts .tool-form button.fix { background: #2e7d32; margin-left: 8px; }
        .odoo-ts .fix-box { background: #fff3e0; border: 1px solid #ffb74d; padding: 12px; border-radius: 6px; margin: 12px 0; }
    </style>
<?php if ($standalone): ?>
</head>
<body>
<?php endif; ?>
<div class="odoo-ts">
<div class="container">
    <h1>com_ordenproduccion — Troubleshooting</h1>
    <p class="subtitle">
        Diagnóstico de base de datos y flujos del componente.
        <?php if (!empty($componentVersion)): ?>
            Component: <code><?php echo htmlspecialchars($componentVersion); ?></code>
        <?php endif; ?>
        <?php if (!$standalone): ?>
            <br><em>Rendered via Joomla (Sourcerer)</em>
        <?php endif; ?>
    </p>

    <nav class="tools">
        <?php foreach ($catalog as $t): ?>
            <a href="<?php echo htmlspecialchars(tsBuildUrl(['tool' => $t['slug']])); ?>" class="<?php echo $tool === $t['slug'] ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($t['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($accessDenied)): ?>
        <p class="result-err">Acceso denegado. Inicie sesión como Super User (core.admin).</p>
    <?php elseif (!empty($fatalError)): ?>
        <p class="result-err"><?php echo htmlspecialchars((string) $fatalError); ?></p>
    <?php elseif (!empty($componentBootError)): ?>
        <p class="result-err">Component boot failed: <?php echo htmlspecialchars((string) $componentBootError); ?></p>
    <?php elseif (!empty($diagnosticError)): ?>
        <p class="result-err"><?php echo htmlspecialchars((string) $diagnosticError); ?></p>
    <?php else: ?>

        <?php tsRenderToolForm($tool, $report ?? null); ?>

        <?php if ($tool === 'invoice' && !empty($report['form']['suggested_fixes']) && is_array($report['form']['suggested_fixes'])) : ?>
            <div class="fix-box">
                <strong>Correcciones sugeridas</strong>
                <form method="post" action="<?php echo htmlspecialchars(tsFormAction()); ?>" class="tool-form" style="margin-top:10px;background:transparent;border:none;padding:0;">
                    <?php tsRenderHiddenRoutingFields(); ?>
                    <input type="hidden" name="tool" value="invoice">
                    <input type="hidden" name="apply_fix" value="1">
                    <input type="hidden" name="invoice_id" value="<?php echo htmlspecialchars((string) ($report['form']['invoice_id'] ?? '')); ?>">
                    <input type="hidden" name="fel_uuid" value="<?php echo htmlspecialchars((string) ($report['form']['fel_uuid'] ?? '')); ?>">
                    <input type="hidden" name="cot_number" value="<?php echo htmlspecialchars((string) ($report['form']['cot_number'] ?? '')); ?>">
                    <input type="hidden" name="quotation_id" value="<?php echo htmlspecialchars((string) ($report['form']['quotation_id'] ?? '')); ?>">
                    <?php foreach ($report['form']['suggested_fixes'] as $fix) : ?>
                        <?php if (!is_array($fix)) { continue; } ?>
                        <label style="display:block;margin:6px 0;font-weight:normal;">
                            <input type="checkbox" name="fix[]" value="<?php echo htmlspecialchars((string) ($fix['id'] ?? '')); ?>">
                            <strong><?php echo htmlspecialchars((string) ($fix['label'] ?? '')); ?></strong>
                            — <?php echo htmlspecialchars((string) ($fix['detail'] ?? '')); ?>
                        </label>
                    <?php endforeach; ?>
                    <button type="submit" class="fix">Aplicar correcciones seleccionadas</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if (!empty($report) && is_array($report)): ?>
            <?php
            $meta = $report['meta'] ?? [];
            $status = (string) ($meta['status'] ?? 'info');
            $statusLabel = $status === 'ok' ? 'OK' : ($status === 'fail' ? 'FAIL' : ($status === 'warn' ? 'WARN' : 'INFO'));
            $statusClass = $status === 'ok' ? 'ok' : ($status === 'fail' ? 'err' : ($status === 'warn' ? 'warn' : 'info'));
            ?>
            <h2><?php echo htmlspecialchars((string) ($report['title'] ?? 'Results')); ?></h2>
            <p>
                <span class="badge <?php echo htmlspecialchars($statusClass); ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                Failures: <?php echo (int) ($meta['failures'] ?? 0); ?>
                &nbsp; Warnings: <?php echo (int) ($meta['warnings'] ?? 0); ?>
                &nbsp; Run: <?php echo htmlspecialchars((string) ($meta['time'] ?? '')); ?>
            </p>

            <?php if (!empty($report['config']) && is_array($report['config'])): ?>
                <div class="meta-grid">
                    <?php foreach ($report['config'] as $ck => $cv): ?>
                        <div class="meta-item"><strong><?php echo htmlspecialchars((string) $ck); ?></strong><?php echo htmlspecialchars(is_scalar($cv) ? (string) $cv : json_encode($cv)); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($report['sections'] ?? [] as $section): ?>
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
                <?php foreach ($section['tables'] ?? [] as $tbl): ?>
                    <?php if (!empty($tbl['headers']) && !empty($tbl['rows'])): ?>
                        <table class="data">
                            <thead><tr>
                                <?php foreach ($tbl['headers'] as $h): ?>
                                    <th><?php echo htmlspecialchars((string) $h); ?></th>
                                <?php endforeach; ?>
                            </tr></thead>
                            <tbody>
                                <?php foreach ($tbl['rows'] as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $cell): ?>
                                            <td><?php echo htmlspecialchars((string) $cell); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</div>
<?php if ($standalone): ?>
</body>
</html>
<?php endif;
}

/**
 * @param array<string, mixed>|null $report
 */
function tsRenderToolForm(string $tool, ?array $report): void
{
    $form = is_array($report) ? ($report['form'] ?? []) : [];

    switch ($tool) {
        case 'precot':
            ?>
            <div class="tool-form">
                <form method="get" action="<?php echo htmlspecialchars(tsFormAction()); ?>">
                    <?php tsRenderHiddenRoutingFields(); ?>
                    <input type="hidden" name="tool" value="precot">
                    <label for="pre_number">Número PRE (ej. PRE-01235)</label>
                    <input type="text" name="pre_number" id="pre_number" value="<?php echo htmlspecialchars((string) ($form['pre_number'] ?? '')); ?>">
                    <label for="pre_id">o ID numérico</label>
                    <input type="text" name="pre_id" id="pre_id" value="<?php echo htmlspecialchars((string) ($form['pre_id'] ?? '')); ?>">
                    <button type="submit">Analizar</button>
                </form>
            </div>
            <?php
            break;

        case 'invoice':
            ?>
            <div class="tool-form">
                <form method="get" action="<?php echo htmlspecialchars(tsFormAction()); ?>">
                    <?php tsRenderHiddenRoutingFields(); ?>
                    <input type="hidden" name="tool" value="invoice">
                    <label for="invoice_id">ID factura (joomla_ordenproduccion_invoices.id)</label>
                    <input type="text" name="invoice_id" id="invoice_id" value="<?php echo htmlspecialchars((string) ($form['invoice_id'] ?? '')); ?>">
                    <label for="fel_uuid">o FEL UUID</label>
                    <input type="text" name="fel_uuid" id="fel_uuid" value="<?php echo htmlspecialchars((string) ($form['fel_uuid'] ?? '')); ?>">
                    <label for="cot_number">o Cotización (ej. COT-001032)</label>
                    <input type="text" name="cot_number" id="cot_number" value="<?php echo htmlspecialchars((string) ($form['cot_number'] ?? '')); ?>">
                    <label for="quotation_id">o quotation_id numérico</label>
                    <input type="text" name="quotation_id" id="quotation_id" value="<?php echo htmlspecialchars((string) ($form['quotation_id'] ?? '')); ?>">
                    <button type="submit">Analizar NUC vs DB</button>
                </form>
                <p class="subtitle" style="margin-top:10px;margin-bottom:0;">
                    Valida fel_request_json (NUC), timbre de prensa, IVA, totales vs cotización e invoice_amount.
                    Si hay errores, use las correcciones sugeridas abajo del reporte.
                </p>
            </div>
            <?php
            break;

        case 'table':
            ?>
            <div class="tool-form">
                <form method="get" action="<?php echo htmlspecialchars(tsFormAction()); ?>">
                    <?php tsRenderHiddenRoutingFields(); ?>
                    <input type="hidden" name="tool" value="table">
                    <label for="table">Tabla (sin prefijo joomla_)</label>
                    <input type="text" name="table" id="table" placeholder="ordenproduccion_pre_cotizacion" value="<?php echo htmlspecialchars((string) ($form['table'] ?? '')); ?>">
                    <label for="limit">Filas de muestra (1–20)</label>
                    <input type="number" name="limit" id="limit" min="1" max="20" value="<?php echo htmlspecialchars((string) ($form['limit'] ?? '5')); ?>">
                    <button type="submit">Inspeccionar</button>
                </form>
            </div>
            <?php
            break;

        case 'payment':
            ?>
            <p class="subtitle">Ejecuta automáticamente al abrir esta pestaña. Opciones en <strong>Components → Orden Producción → Options</strong>.</p>
            <?php
            break;

        case 'schema':
            ?>
            <p class="subtitle">Lista tablas clave del componente con conteo de filas.</p>
            <?php
            break;

        default:
            ?>
            <p class="subtitle">Seleccione una herramienta arriba. Requiere sesión Super User.</p>
            <?php
    }
}

$fatalError          = null;
$componentBootError  = null;
$diagnosticError     = null;
$report              = null;
$componentVersion    = '';
$accessDenied        = false;
$currentTool         = 'home';

try {
    $app = tsGetApplication();
    $currentTool = tsCurrentTool();

    if (!tsCanAccess()) {
        $accessDenied = true;
    } else {
        try {
            $app->bootComponent('com_ordenproduccion');
        } catch (\Throwable $e) {
            $componentBootError = $e->getMessage();
        }

        if ($componentBootError === null) {
            $helperClass = 'Grimpsa\\Component\\Ordenproduccion\\Site\\Helper\\ComOrdenproduccionTroubleshootingHelper';
            if (class_exists($helperClass)) {
                try {
                    $report = (new $helperClass())->run($currentTool, tsCollectInput($currentTool));
                } catch (\Throwable $e) {
                    $diagnosticError = $e->getMessage();
                }
            } else {
                $diagnosticError = 'ComOrdenproduccionTroubleshootingHelper not found. Deploy com_ordenproduccion 3.119.346+ and clear cache.';
            }
        }
    }

    $versionFile = tsJoomlaRootPath() . '/components/com_ordenproduccion/VERSION';
    if (is_file($versionFile)) {
        $componentVersion = trim((string) file_get_contents($versionFile));
    }
} catch (\Throwable $e) {
    $fatalError = $e->getMessage();
}

tsRender(compact(
    'fatalError',
    'componentBootError',
    'diagnosticError',
    'report',
    'componentVersion',
    'accessDenied',
    'currentTool'
));
