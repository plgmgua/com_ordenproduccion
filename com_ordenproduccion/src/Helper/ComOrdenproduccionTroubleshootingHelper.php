<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Model\PrecotizacionModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Multi-tool diagnostics for com_ordenproduccion (load via /troubleshooting.php).
 *
 * @since  3.119.319
 */
class ComOrdenproduccionTroubleshootingHelper
{
    /** @var array<int, array<string, mixed>> */
    private array $sections = [];

    private int $failures = 0;

    private int $warnings = 0;

    /**
     * @param   string               $tool   Tool slug
     * @param   array<string, mixed>  $input  Request parameters
     *
     * @return  array<string, mixed>
     *
     * @since  3.119.319
     */
    public function run(string $tool, array $input = []): array
    {
        $this->sections = [];
        $this->failures = 0;
        $this->warnings = 0;

        $tool = strtolower(trim($tool));
        if ($tool === '' || $tool === 'home') {
            return $this->buildHomeReport();
        }

        switch ($tool) {
            case 'payment':
                return $this->runPaymentTool();

            case 'precot':
                return $this->runPrecotCotizacionTool($input);

            case 'invoice':
                return $this->runInvoiceTool($input);

            case 'table':
                return $this->runTableTool($input);

            case 'schema':
                return $this->runSchemaTool();

            default:
                $this->addCheck('Tool', 'fail', 'Unknown tool: ' . $tool);
                return $this->buildReport('Unknown tool', $tool);
        }
    }

    /**
     * @return  array<string, mixed>
     */
    private function buildHomeReport(): array
    {
        $this->startSection('Environment');
        $root = defined('JPATH_ROOT') ? JPATH_ROOT : '';
        $version = $this->componentVersion();
        $this->addCheck('Component version', $version !== '' ? 'pass' : 'warn', $version !== '' ? $version : 'VERSION file not found');
        $this->addDetail('joomla_root', $root);
        $this->addDetail('db_prefix', $this->db()->getPrefix());
        $this->addDetail('php', PHP_VERSION);
        $this->addDetail('user', Factory::getUser()->guest ? 'guest' : Factory::getUser()->username . ' (id ' . Factory::getUser()->id . ')');

        $this->startSection('Available tools');
        $tools = [
            ['payment', 'Verificar pago (MT-940)', '?tool=payment'],
            ['precot', 'Pre-cotización → Cotización (oferta, vínculos)', '?tool=precot'],
            ['invoice', 'Factura: NUC vs DB + timbre + fixes', '?tool=invoice'],
            ['table', 'Inspeccionar tabla', '?tool=table'],
            ['schema', 'Tablas clave del componente', '?tool=schema'],
        ];
        foreach ($tools as [$slug, $label, $qs]) {
            $this->addCheck($label, 'info', $qs . ' — slug: ' . $slug);
        }

        return $this->buildReport('com_ordenproduccion — Troubleshooting', 'home');
    }

    /**
     * @return  array<string, mixed>
     */
    private function runPaymentTool(): array
    {
        $class = PaymentVerificationDiagnosticHelper::class;
        if (!class_exists($class)) {
            $this->startSection('Payment verification');
            $this->addCheck('PaymentVerificationDiagnosticHelper', 'fail', 'Class not found — deploy 3.119.229+');

            return $this->buildReport('Verificar pago (MT-940)', 'payment');
        }

        return (new $class())->run();
    }

    /**
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>
     */
    private function runPrecotCotizacionTool(array $input): array
    {
        $preId     = (int) ($input['pre_id'] ?? 0);
        $preNumber = trim((string) ($input['pre_number'] ?? ''));

        $this->startSection('Lookup');
        if ($preId < 1 && $preNumber === '') {
            $this->addCheck('Input', 'warn', 'Provide pre_id or pre_number (e.g. PRE-01235)');

            return $this->buildReport('Pre-cotización → Cotización', 'precot', [
                'pre_id'     => '',
                'pre_number' => '',
            ]);
        }

        $row = $this->loadPreCotizacionRow($preId, $preNumber);
        if ($row === null) {
            $this->addCheck('Pre-cotización', 'fail', 'Not found or unpublished');

            return $this->buildReport('Pre-cotización → Cotización', 'precot', [
                'pre_id'     => $preId > 0 ? (string) $preId : '',
                'pre_number' => $preNumber,
            ]);
        }

        $preId = (int) $row->id;
        $this->addDetail('id', (string) $preId);
        $this->addDetail('number', (string) ($row->number ?? ''));
        $this->addDetail('descripcion', (string) ($row->descripcion ?? ''));
        $this->addDetail('oferta', !empty($row->oferta) ? '1 (SÍ — plantilla)' : '0 (no)');
        if (isset($row->oferta_expires)) {
            $this->addDetail('oferta_expires', (string) $row->oferta_expires);
        }
        $this->addDetail('facturar', isset($row->facturar) ? (string) $row->facturar : '—');
        $this->addDetail('created_by', (string) ($row->created_by ?? ''));
        $this->addDetail('state', (string) ($row->state ?? ''));

        $this->startSection('Why it may not appear on Cotización');

        $isOferta = !empty($row->oferta);
        if ($isOferta) {
            $this->addCheck(
                'Oferta = 1',
                'fail',
                'Las ofertas plantilla NO se pueden agregar como línea en cotización. Cree una nueva PRE desde plantilla (Pre-Cotizaciones → Nueva → seleccionar plantilla) o desmarque Oferta.'
            );
        } else {
            $this->addCheck('Oferta = 0', 'pass', 'No es plantilla — puede usarse en cotización');
        }

        if ($isOferta && isset($row->oferta_expires) && PrecotizacionModel::isOfertaExpired($row)) {
            $this->addCheck('Vencimiento oferta', 'warn', 'Plantilla vencida — no aparece en selector de nueva PRE desde plantilla');
        }

        $user = Factory::getUser();
        $ownerId = (int) ($row->created_by ?? 0);
        if (!$user->guest && $ownerId !== (int) $user->id && !AccessHelper::isSuperUser()) {
            $this->addCheck('Propietario', 'fail', 'Solo el autor (id ' . $ownerId . ') o Super User ve esta PRE en el selector de cotización');
        } elseif ($ownerId > 0) {
            $this->addCheck('Propietario', 'pass', 'created_by = ' . $ownerId);
        }

        try {
            $precotModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            if ($precotModel) {
                if ($precotModel->isAssociatedWithQuotation($preId)) {
                    $links = $precotModel->getLinkedQuotationsForPreCotizacion($preId);
                    $refs = [];
                    foreach ($links as $lq) {
                        $refs[] = ($lq->quotation_number ?? '') !== '' ? $lq->quotation_number : ('COT-' . (int) ($lq->id ?? 0));
                    }
                    $this->addCheck(
                        'Ya vinculada a cotización',
                        'fail',
                        'No aparece en selector porque ya está en: ' . implode(', ', $refs)
                    );
                } else {
                    $this->addCheck('Vínculo cotización', 'pass', 'No está en ninguna cotización aún');
                }

                if (!$isOferta) {
                    $err = $precotModel->validatePreCotizacionIdsForQuotationLine([$preId]);
                    if ($err === null) {
                        $this->addCheck('validatePreCotizacionIdsForQuotationLine', 'pass', 'OK — puede agregarse como línea');
                    } else {
                        $this->addCheck('validatePreCotizacionIdsForQuotationLine', 'fail', $err);
                    }
                }

                $inSelector = false;
                foreach ($precotModel->getItemsForQuotationLineSelector() as $item) {
                    if ((int) ($item->id ?? 0) === $preId) {
                        $inSelector = true;
                        break;
                    }
                }
                $this->addCheck(
                    'En dropdown Cotización (usuario actual)',
                    $inSelector ? 'pass' : ($isOferta ? 'warn' : 'fail'),
                    $inSelector ? 'Sí — visible para el usuario logueado' : 'No — revisar checks arriba'
                );
            }
        } catch (\Throwable $e) {
            $this->addCheck('PrecotizacionModel', 'fail', $e->getMessage());
        }

        $this->startSection('Líneas y totales');
        $this->addPreLinesTable($preId);

        $this->startSection('Cotizaciones vinculadas (quotation_items)');
        $this->addLinkedQuotationsTable($preId);

        return $this->buildReport('Pre-cotización → Cotización', 'precot', [
            'pre_id'     => (string) $preId,
            'pre_number' => (string) ($row->number ?? ''),
        ]);
    }

    /**
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>
     */
    private function runInvoiceTool(array $input): array
    {
        $nucHelper = new FelNucTroubleshootingHelper($this->db());
        $ctx       = $nucHelper->resolveInvoiceContext($input);
        $inv       = $ctx['invoice'];
        $quotationId = (int) $ctx['quotation_id'];

        $invoiceId = (int) ($input['invoice_id'] ?? 0);
        $felUuid   = trim((string) ($input['fel_uuid'] ?? ''));
        $cotNumber = trim((string) ($input['cot_number'] ?? ''));

        if ($inv === null && $invoiceId < 1 && $felUuid === '' && $cotNumber === '' && $quotationId < 1) {
            $this->startSection('Lookup');
            $this->addCheck('Input', 'warn', 'Provide invoice_id, fel_uuid, cot_number (e.g. COT-001032), or quotation_id');

            return $this->buildReport('Factura — NUC vs DB', 'invoice', [
                'invoice_id'   => '',
                'fel_uuid'     => '',
                'cot_number'   => '',
                'quotation_id' => '',
            ]);
        }

        if ($inv === null && ($quotationId > 0 || $cotNumber !== '')) {
            $fixMessages = [];
            $fixIds      = $input['fix'] ?? [];
            if (!\is_array($fixIds)) {
                $fixIds = [$fixIds];
            }
            $fixIds = array_values(array_filter(array_map('strval', $fixIds)));
            if ($fixIds !== [] && !empty($input['apply_fix'])) {
                $fixResult   = $nucHelper->applyFixes(null, $quotationId, $fixIds, (int) Factory::getUser()->id);
                $fixMessages = $fixResult['messages'] ?? [];
            }

            $this->startSection('Lookup');
            $this->addCheck(
                'Factura',
                'info',
                'Sin factura vinculada — solo diagnóstico de cotización'
                . ($ctx['quotation_number'] !== '' ? ' (' . $ctx['quotation_number'] . ')' : '')
            );
            if ($fixMessages !== []) {
                $this->startSection('Fixes applied');
                foreach ($fixMessages as $msg) {
                    $this->addCheck('Fix', 'info', (string) $msg);
                }
            }
            $this->runFelNucAnalysisSection($nucHelper, null, $quotationId);
            $analysisOnly = $nucHelper->analyze(null, $quotationId);

            return $this->buildReport('Factura — NUC vs DB (cotización)', 'invoice', [
                'invoice_id'      => '',
                'fel_uuid'        => '',
                'cot_number'      => $cotNumber !== '' ? $cotNumber : ($ctx['quotation_number'] ?? ''),
                'quotation_id'    => $quotationId > 0 ? (string) $quotationId : '',
                'suggested_fixes' => $analysisOnly['suggested_fixes'] ?? [],
            ]);
        }

        if ($inv === null) {
            $this->startSection('Lookup');
            $this->addCheck('Factura', 'fail', 'Not found');

            return $this->buildReport('Factura — NUC vs DB', 'invoice', [
                'invoice_id'   => $invoiceId > 0 ? (string) $invoiceId : '',
                'fel_uuid'     => $felUuid,
                'cot_number'   => $cotNumber,
                'quotation_id' => $quotationId > 0 ? (string) $quotationId : '',
            ]);
        }

        $fixMessages = [];
        $fixIds      = $input['fix'] ?? [];
        if (!\is_array($fixIds)) {
            $fixIds = [$fixIds];
        }
        $fixIds = array_values(array_filter(array_map('strval', $fixIds)));
        if ($fixIds !== [] && !empty($input['apply_fix'])) {
            $fixResult   = $nucHelper->applyFixes($inv, $quotationId, $fixIds, (int) Factory::getUser()->id);
            $fixMessages = $fixResult['messages'] ?? [];
            $inv         = $nucHelper->resolveInvoiceContext([
                'invoice_id' => (string) ($inv->id ?? ''),
            ])['invoice'] ?? $inv;
            if ($quotationId < 1) {
                $quotationId = (int) ($inv->quotation_id ?? 0);
            }
        }

        $invoiceId = (int) $inv->id;
        $db = $this->db();
        $this->startSection('Factura');
        $this->addDetail('id', (string) $invoiceId);
        $this->addDetail('invoice_number', (string) ($inv->invoice_number ?? ''));
        $this->addDetail('invoice_source', (string) ($inv->invoice_source ?? ''));
        $this->addDetail('fel_issue_status', (string) ($inv->fel_issue_status ?? ''));
        $this->addDetail('notes', (string) ($inv->notes ?? ''));
        $this->addDetail('quotation_id', (string) ($inv->quotation_id ?? ''));

        $origin = $this->classifyInvoiceOrigin($inv);
        $this->addCheck('Origen facturación', $origin['status'], $origin['label']);

        $qid = (int) ($inv->quotation_id ?? 0);
        if ($qid > 0) {
            $this->startSection('Cotización relacionada');
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_quotations'))
                    ->where($db->quoteName('id') . ' = ' . $qid)
            );
            $q = $db->loadObject();
            if ($q) {
                $this->addDetail('quotation_number', (string) ($q->quotation_number ?? ''));
                $this->addDetail('facturacion_modo', (string) ($q->facturacion_modo ?? ''));
                $this->addDetail('facturar_cotizacion_exacta', (string) ($q->facturar_cotizacion_exacta ?? ''));
                $this->addDetail('cotizacion_confirmada', (string) ($q->cotizacion_confirmada ?? ''));
                $instr = trim((string) ($q->instrucciones_facturacion ?? ''));
                $this->addCheck(
                    'instrucciones_facturacion (cotización)',
                    $instr !== '' ? 'pass' : 'info',
                    $instr !== '' ? $instr : '(vacío — normal si facturar_cotizacion_exacta = 1)'
                );
            }
        }

        $this->startSection('Vínculos OT (invoice_orden_suggestions)');
        if ($this->tableExists('ordenproduccion_invoice_orden_suggestions')) {
            $linkQuery = $db->getQuery(true)
                ->select(['s.orden_id', 's.status', 's.score', 's.reasons'])
                ->from($db->quoteName('#__ordenproduccion_invoice_orden_suggestions', 's'))
                ->where($db->quoteName('s.invoice_id') . ' = ' . $invoiceId);
            $this->applyPublishedState($linkQuery, 'ordenproduccion_invoice_orden_suggestions', 's');
            $db->setQuery($linkQuery);
            $links = $db->loadObjectList() ?: [];
            if ($links === []) {
                $this->addCheck('OT links', 'info', 'Ninguno');
            } else {
                $this->addDataTable(
                    ['orden_id', 'status', 'score', 'reasons'],
                    array_map(static function ($r) {
                        return [
                            (string) ($r->orden_id ?? ''),
                            (string) ($r->status ?? ''),
                            (string) ($r->score ?? ''),
                            (string) ($r->reasons ?? ''),
                        ];
                    }, $links)
                );
                foreach ($links as $link) {
                    $reasons = (string) ($link->reasons ?? '');
                    if (str_contains($reasons, '"auto"')) {
                        $this->addCheck('Link OT #' . (int) $link->orden_id, 'pass', 'Automático (envío completo / pre_lines)');
                    } elseif (str_contains($reasons, 'cotizacion_fel_manual')) {
                        $this->addCheck('Link OT #' . (int) $link->orden_id, 'info', 'Manual desde cotización');
                    }
                }
            }
        } else {
            $this->addCheck('Tabla invoice_orden_suggestions', 'warn', 'No existe');
        }

        if ($fixMessages !== []) {
            $this->startSection('Fixes applied');
            foreach ($fixMessages as $msg) {
                $this->addCheck('Fix', str_starts_with((string) $msg, 'rebuild_nuc:') ? 'pass' : 'info', (string) $msg);
            }
        }

        $nucAnalysis = $this->runFelNucAnalysisSection($nucHelper, $inv, $quotationId);

        return $this->buildReport('Factura — NUC vs DB', 'invoice', [
            'invoice_id'      => (string) $invoiceId,
            'fel_uuid'        => (string) ($inv->fel_autorizacion_uuid ?? ''),
            'cot_number'      => $cotNumber !== '' ? $cotNumber : ($ctx['quotation_number'] ?? ''),
            'quotation_id'    => $quotationId > 0 ? (string) $quotationId : '',
            'suggested_fixes' => $nucAnalysis['suggested_fixes'] ?? [],
        ]);
    }

    /**
     * @return array{suggested_fixes: list<array{id:string, label:string, detail:string}>}
     */
    private function runFelNucAnalysisSection(FelNucTroubleshootingHelper $helper, ?object $inv, int $quotationId): array
    {
        $this->startSection('NUC vs base de datos');
        $analysis = $helper->analyze($inv, $quotationId);

        foreach ($analysis['details'] ?? [] as $key => $val) {
            $this->addDetail((string) $key, (string) $val);
        }
        foreach ($analysis['checks'] ?? [] as $check) {
            $this->addCheck(
                (string) ($check['label'] ?? ''),
                (string) ($check['status'] ?? 'info'),
                (string) ($check['detail'] ?? '')
            );
        }
        foreach ($analysis['tables'] ?? [] as $tbl) {
            if (!empty($tbl['headers']) && !empty($tbl['rows'])) {
                $this->addDataTable($tbl['headers'], $tbl['rows']);
            }
        }

        return ['suggested_fixes' => $analysis['suggested_fixes'] ?? []];
    }

    /**
     * @param   array<string, mixed>  $input
     *
     * @return  array<string, mixed>
     */
    private function runTableTool(array $input): array
    {
        $table = trim((string) ($input['table'] ?? ''));
        $table = preg_replace('/[^a-z0-9_]/', '', strtolower($table));

        if ($table === '') {
            $this->startSection('Input');
            $this->addCheck('table', 'warn', 'Provide table name without prefix, e.g. ordenproduccion_pre_cotizacion');

            return $this->buildReport('Inspeccionar tabla', 'table', ['table' => '']);
        }

        $full = $this->db()->getPrefix() . $table;
        if (!$this->tableExists($table)) {
            $this->addCheck('Tabla', 'fail', $full . ' — not found');

            return $this->buildReport('Inspeccionar tabla', 'table', ['table' => $table]);
        }

        $this->startSection('Tabla: ' . $full);
        $this->addCheck('Exists', 'pass', $full);

        $cols = $this->db()->getTableColumns('#__' . $table, false);
        $colRows = [];
        if (is_array($cols)) {
            foreach ($cols as $name => $meta) {
                $colRows[] = [
                    (string) $name,
                    is_object($meta) ? (string) ($meta->Type ?? '') : '',
                    is_object($meta) ? (string) ($meta->Null ?? '') : '',
                    is_object($meta) ? (string) ($meta->Default ?? '') : '',
                ];
            }
        }
        $this->addDataTable(['Column', 'Type', 'Null', 'Default'], $colRows);

        try {
            $db = $this->db();
            $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__' . $table));
            $count = (int) $db->loadResult();
            $this->addCheck('Row count', 'info', (string) $count);

            $limit = max(1, min(20, (int) ($input['limit'] ?? 5)));
            $db->setQuery('SELECT * FROM ' . $db->quoteName('#__' . $table) . ' ORDER BY id DESC', 0, $limit);
            $sample = $db->loadAssocList() ?: [];
            if ($sample !== []) {
                $this->startSection('Sample rows (latest ' . $limit . ')');
                $headers = array_keys($sample[0]);
                $rows = [];
                foreach ($sample as $r) {
                    $line = [];
                    foreach ($headers as $h) {
                        $v = $r[$h] ?? '';
                        if (is_string($v) && strlen($v) > 120) {
                            $v = substr($v, 0, 117) . '...';
                        }
                        $line[] = (string) $v;
                    }
                    $rows[] = $line;
                }
                $this->addDataTable($headers, $rows);
            }
        } catch (\Throwable $e) {
            $this->addCheck('Query', 'fail', $e->getMessage());
        }

        return $this->buildReport('Inspeccionar tabla', 'table', ['table' => $table]);
    }

    /**
     * @return  array<string, mixed>
     */
    private function runSchemaTool(): array
    {
        $tables = [
            'ordenproduccion_pre_cotizacion',
            'ordenproduccion_pre_cotizacion_line',
            'ordenproduccion_quotations',
            'ordenproduccion_quotation_items',
            'ordenproduccion_invoices',
            'ordenproduccion_invoice_quotations',
            'ordenproduccion_invoice_orden_suggestions',
            'ordenproduccion_pliego_sheet_processes',
            'ordenproduccion_barniz_prices',
            'ordenproduccion_approval_requests',
        ];

        $this->startSection('Core tables');
        $rows = [];
        foreach ($tables as $t) {
            $exists = $this->tableExists($t);
            $count  = '';
            if ($exists) {
                try {
                    $db = $this->db();
                    $db->setQuery('SELECT COUNT(*) FROM ' . $db->quoteName('#__' . $t));
                    $count = (string) (int) $db->loadResult();
                } catch (\Throwable $e) {
                    $count = 'err';
                }
            }
            $rows[] = [$this->db()->getPrefix() . $t, $exists ? 'yes' : 'NO', $count];
            if (!$exists) {
                $this->failures++;
            }
        }
        $this->addDataTable(['Table', 'Exists', 'Rows'], $rows);

        return $this->buildReport('Schema — tablas clave', 'schema');
    }

    /**
     * @return  array{status: string, label: string}
     */
    private function classifyInvoiceOrigin(object $inv): array
    {
        $notes  = (string) ($inv->notes ?? '');
        $source = (string) ($inv->invoice_source ?? '');

        if ($source === 'fel_import') {
            return ['status' => 'info', 'label' => 'Importada (XML/FEL externo)'];
        }
        if ($source === 'invoice_fel_duplicate') {
            return ['status' => 'info', 'label' => 'Manual — duplicada desde otra factura'];
        }
        if (str_contains($notes, 'FEL manual')) {
            return ['status' => 'info', 'label' => 'Manual — emitida desde cotización (modal FEL)'];
        }
        if (str_contains($notes, 'FEL scheduled queue') || !empty($inv->fel_scheduled_at)) {
            return ['status' => 'pass', 'label' => 'AUTOMATIZADA — fecha programada (08:00)'];
        }
        if (str_contains($notes, 'FEL mock queue') || $source === 'cotizacion_fel') {
            return ['status' => 'pass', 'label' => 'AUTOMATIZADA — cotización FEL (envío completo / cola)'];
        }

        return ['status' => 'warn', 'label' => 'Indeterminada — revisar notes e invoice_source'];
    }

    private function loadPreCotizacionRow(int $preId, string $preNumber): ?object
    {
        $db = $this->db();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'));
        $this->applyPublishedState($query, 'ordenproduccion_pre_cotizacion');
        if ($preId > 0) {
            $query->where($db->quoteName('id') . ' = ' . $preId);
        } else {
            $query->where($db->quoteName('number') . ' = ' . $db->quote($preNumber));
        }
        $db->setQuery($query, 0, 1);

        return $db->loadObject() ?: null;
    }

    private function addPreLinesTable(int $preId): void
    {
        if (!$this->tableExists('ordenproduccion_pre_cotizacion_line')) {
            $this->addCheck('pre_cotizacion_line', 'warn', 'Table missing');

            return;
        }
        $db = $this->db();
        $lineQuery = $db->getQuery(true)
            ->select(['id', 'line_type', 'quantity', 'total', 'elemento_id', 'paper_type_id', 'size_id'])
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
            ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $preId)
            ->order($db->quoteName('id') . ' ASC');
        $db->setQuery($lineQuery);
        $lines = $db->loadObjectList() ?: [];
        if ($lines === []) {
            $this->addCheck('Líneas', 'warn', 'Sin líneas publicadas');

            return;
        }
        $this->addDataTable(
            ['id', 'line_type', 'quantity', 'total', 'elemento_id', 'paper_type_id', 'size_id'],
            array_map(static function ($l) {
                return [
                    (string) ($l->id ?? ''),
                    (string) ($l->line_type ?? ''),
                    (string) ($l->quantity ?? ''),
                    (string) ($l->total ?? ''),
                    (string) ($l->elemento_id ?? ''),
                    (string) ($l->paper_type_id ?? ''),
                    (string) ($l->size_id ?? ''),
                ];
            }, $lines)
        );
    }

    private function addLinkedQuotationsTable(int $preId): void
    {
        if (!$this->tableExists('ordenproduccion_quotation_items')) {
            $this->addCheck('quotation_items', 'warn', 'Table missing');

            return;
        }
        $db = $this->db();
        $linkQuery = $db->getQuery(true)
            ->select(['qi.id', 'qi.quotation_id', 'q.quotation_number', 'qi.descripcion', 'qi.valor_final'])
            ->from($db->quoteName('#__ordenproduccion_quotation_items', 'qi'))
            ->leftJoin(
                $db->quoteName('#__ordenproduccion_quotations', 'q') . ' ON ' . $db->quoteName('q.id') . ' = ' . $db->quoteName('qi.quotation_id')
            )
            ->where($db->quoteName('qi.pre_cotizacion_id') . ' = ' . $preId);
        $this->applyPublishedState($linkQuery, 'ordenproduccion_quotations', 'q');
        $db->setQuery($linkQuery);
        $rows = $db->loadObjectList() ?: [];
        if ($rows === []) {
            $this->addCheck('Cotizaciones', 'info', 'No vinculada a ninguna cotización');

            return;
        }
        $this->addDataTable(
            ['item_id', 'quotation_id', 'quotation_number', 'descripcion', 'valor_final'],
            array_map(static function ($r) {
                return [
                    (string) ($r->id ?? ''),
                    (string) ($r->quotation_id ?? ''),
                    (string) ($r->quotation_number ?? ''),
                    substr((string) ($r->descripcion ?? ''), 0, 80),
                    (string) ($r->valor_final ?? ''),
                ];
            }, $rows)
        );
    }

    private function tableExists(string $suffix): bool
    {
        $db     = $this->db();
        $needle = $db->getPrefix() . $suffix;
        foreach ($db->getTableList() as $name) {
            if (strcasecmp($name, $needle) === 0) {
                return true;
            }
        }

        return false;
    }

    private function tableHasColumn(string $suffix, string $column): bool
    {
        if (!$this->tableExists($suffix)) {
            return false;
        }

        $cols = $this->db()->getTableColumns('#__' . $suffix, false);
        if (!is_array($cols)) {
            return false;
        }

        $cols = array_change_key_case($cols, CASE_LOWER);

        return isset($cols[strtolower($column)]);
    }

    /**
     * Append `alias.state = 1` only when the column exists (legacy tables omit state).
     *
     * @param   \Joomla\Database\Query\QueryInterface  $query
     */
    private function applyPublishedState($query, string $tableSuffix, string $alias = ''): void
    {
        if (!$this->tableHasColumn($tableSuffix, 'state')) {
            return;
        }

        $db = $this->db();
        $col = ($alias !== '' ? $alias . '.' : '') . 'state';
        $query->where($db->quoteName($col) . ' = 1');
    }

    private function componentVersion(): string
    {
        $root = defined('JPATH_ROOT') ? JPATH_ROOT : '';
        if ($root !== '') {
            $versionFile = $root . '/components/com_ordenproduccion/VERSION';
            if (is_file($versionFile)) {
                return trim((string) file_get_contents($versionFile));
            }

            foreach ([
                $root . '/administrator/components/com_ordenproduccion/com_ordenproduccion.xml',
                $root . '/components/com_ordenproduccion/com_ordenproduccion.xml',
            ] as $manifest) {
                if (!is_file($manifest)) {
                    continue;
                }
                $xml = @simplexml_load_file($manifest);
                if ($xml !== false && isset($xml->version)) {
                    $v = trim((string) $xml->version);
                    if ($v !== '') {
                        return $v;
                    }
                }
            }
        }

        try {
            $db = $this->db();
            $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'));
            $db->setQuery($query);
            $cache = $db->loadResult();
            if (is_string($cache) && $cache !== '') {
                $data = json_decode($cache, true);
                if (is_array($data) && !empty($data['version'])) {
                    return trim((string) $data['version']);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    private function db(): DatabaseInterface
    {
        return Factory::getContainer()->get(DatabaseInterface::class);
    }

    private function startSection(string $title): void
    {
        $this->sections[] = [
            'title'   => $title,
            'checks'  => [],
            'details' => [],
            'tables'  => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function &currentSection(): array
    {
        if ($this->sections === []) {
            $this->startSection('Results');
        }
        $idx = count($this->sections) - 1;

        return $this->sections[$idx];
    }

    private function addCheck(string $label, string $status, string $detail): void
    {
        if ($status === 'fail') {
            $this->failures++;
        } elseif ($status === 'warn') {
            $this->warnings++;
        }
        $sec = &$this->currentSection();
        $sec['checks'][] = [
            'label'  => $label,
            'status' => $status,
            'detail' => $detail,
        ];
    }

    private function addDetail(string $key, string $value): void
    {
        $sec = &$this->currentSection();
        $sec['details'][$key] = $value;
    }

    /**
     * @param   array<int, string>           $headers
     * @param   array<int, array<int, string>>  $rows
     */
    private function addDataTable(array $headers, array $rows): void
    {
        $sec = &$this->currentSection();
        $sec['tables'][] = [
            'headers' => $headers,
            'rows'    => $rows,
        ];
    }

    /**
     * @param   array<string, string>  $formDefaults
     *
     * @return  array<string, mixed>
     */
    private function buildReport(string $title, string $tool, array $formDefaults = []): array
    {
        $status = $this->failures > 0 ? 'fail' : ($this->warnings > 0 ? 'warn' : 'ok');

        return [
            'title'    => $title,
            'tool'     => $tool,
            'meta'     => [
                'status'   => $status,
                'failures' => $this->failures,
                'warnings' => $this->warnings,
                'time'     => Factory::getDate()->toSql(),
            ],
            'sections' => $this->sections,
            'form'     => $formDefaults,
        ];
    }
}
