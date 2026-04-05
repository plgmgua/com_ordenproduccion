<?php
/**
 * Mock FELplex "Crear DTE Síncrono" engine: build JSON from cotización, simulate certification,
 * save XML/PDF under media, persist debug payloads on the invoice row.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * FEL invoice issuance (mock) service.
 *
 * @since  3.101.50
 */
class FelInvoiceIssuanceService
{
    /** @var string Time (24h) for scheduled FEL run on the chosen billing date (site timezone) */
    public const SCHEDULED_ISSUE_HOUR = '08:00:00';

    /** @var DatabaseInterface */
    protected $db;

    /** Guatemala IVA rate (12%) for price breakdown when totals are tax-inclusive */
    public const IVA_RATE = 0.12;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Whether issuance columns exist (migration 3.101.50 applied).
     */
    public function isEngineAvailable(): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);

        return \is_array($cols) && isset($cols['fel_issue_status']);
    }

    /**
     * Whether quotation_id exists on invoices (3.101.47+).
     */
    public function hasQuotationIdColumn(): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);

        return \is_array($cols) && isset($cols['quotation_id']);
    }

    /**
     * Whether fel_scheduled_at exists (migration 3.101.51).
     */
    public function hasFelScheduledAtColumn(): bool
    {
        return $this->hasColumn('fel_scheduled_at');
    }

    /**
     * Load active invoice linked to quotation, if any.
     */
    public function getInvoiceByQuotationId(int $quotationId): ?object
    {
        if ($quotationId < 1 || !$this->hasQuotationIdColumn()) {
            return null;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');

        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Create pending invoice row for quotation (queue step 1).
     *
     * @return  int  New invoice id or 0 on failure
     */
    public function createPendingInvoiceFromQuotation(int $quotationId, int $userId): int
    {
        if (!$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return 0;
        }

        if ($quotationId < 1) {
            return 0;
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing) {
            return (int) $existing->id;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $quotation = $this->db->loadObject();
        if (!$quotation) {
            return 0;
        }

        $lines = $this->loadQuotationLines($quotationId);
        $invoiceNumber = $this->generateNextInvoiceNumber();
        $now = Factory::getDate()->toSql();
        $total = (float) ($quotation->total_amount ?? 0);
        $lineItemsJson = json_encode($this->buildLineItemsForStorage($lines));

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => '',
            'client_name'        => $quotation->client_name ?? '',
            'client_nit'         => $quotation->client_nit ?? null,
            'sales_agent'        => $quotation->sales_agent ?? null,
            'request_date'       => !empty($quotation->quote_date) ? $quotation->quote_date : null,
            'delivery_date'      => null,
            'invoice_date'       => $now,
            'invoice_amount'     => $total,
            'currency'           => $quotation->currency ?? 'Q',
            'work_description'   => $this->summarizeLines($lines),
            'material'           => null,
            'dimensions'         => null,
            'print_color'        => null,
            'line_items'         => $lineItemsJson,
            'quotation_file'     => null,
            'extraction_status'  => 'manual',
            'status'             => 'draft',
            'notes'              => 'FEL mock queue (cotización)',
            'state'              => 1,
            'version'            => '3.101.51',
            'created'            => $now,
            'created_by'         => $userId,
            'quotation_id'       => $quotationId,
            'invoice_source'     => 'cotizacion_fel',
            'fel_issue_status'   => 'pending',
            'fel_issue_error'    => null,
            'fel_request_json'   => null,
            'fel_response_json'  => null,
            'fel_local_pdf_path' => null,
            'fel_local_xml_path' => null,
            'felplex_uuid'       => null,
        ];

        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        $filtered = [];
        foreach ($row as $k => $v) {
            if (isset($cols[strtolower($k)])) {
                $filtered[$k] = $v;
            }
        }

        $o = (object) $filtered;
        $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');

        return (int) $o->id;
    }

    /**
     * Build UTC SQL datetime for 08:00 on the given local date (Joomla site offset).
     *
     * @param   string  $dateYmd  Y-m-d
     *
     * @return  string|null  SQL datetime or null if invalid
     */
    public function buildScheduledAtSql(string $dateYmd): ?string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }

        $offset = Factory::getApplication()->get('offset');
        if ($offset === null || $offset === '') {
            $offset = 'UTC';
        }

        try {
            $date = Factory::getDate($dateYmd . ' ' . self::SCHEDULED_ISSUE_HOUR, $offset);
        } catch (\Throwable $e) {
            return null;
        }

        return $date->toSql();
    }

    /**
     * Queue or update invoice row: issue FEL at fel_scheduled_at (08:00 local on billing date).
     *
     * @param   string  $billingDateYmd  Y-m-d from quotation facturación
     *
     * @return  int  Invoice id or 0
     */
    public function scheduleOrUpdateInvoiceFromQuotation(int $quotationId, int $userId, string $billingDateYmd): int
    {
        if (!$this->isEngineAvailable() || !$this->hasQuotationIdColumn() || !$this->hasFelScheduledAtColumn()) {
            return 0;
        }

        if ($quotationId < 1) {
            return 0;
        }

        $scheduledSql = $this->buildScheduledAtSql($billingDateYmd);
        if ($scheduledSql === null) {
            return 0;
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing && (string) ($existing->fel_issue_status ?? '') === 'completed') {
            return (int) $existing->id;
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $quotation = $this->db->loadObject();
        if (!$quotation) {
            return 0;
        }

        $lines = $this->loadQuotationLines($quotationId);
        $now     = Factory::getDate()->toSql();
        $total   = (float) ($quotation->total_amount ?? 0);
        $lineItemsJson = json_encode($this->buildLineItemsForStorage($lines));

        if ($existing) {
            $invoiceId = (int) $existing->id;
            $update    = [
                'fel_issue_status' => 'scheduled',
                'fel_scheduled_at' => $scheduledSql,
                'fel_issue_error'  => null,
                'invoice_amount'   => $total,
                'line_items'       => $lineItemsJson,
                'work_description' => $this->summarizeLines($lines),
                'notes'            => 'FEL scheduled queue (cotización, 08:00)',
                'modified'         => $now,
                'modified_by'      => $userId,
            ];
            $update = $this->filterToExistingColumns($update);
            $this->updateInvoiceFields($invoiceId, $update);

            return $invoiceId;
        }

        $invoiceNumber = $this->generateNextInvoiceNumber();

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => '',
            'client_name'        => $quotation->client_name ?? '',
            'client_nit'         => $quotation->client_nit ?? null,
            'sales_agent'        => $quotation->sales_agent ?? null,
            'request_date'       => !empty($quotation->quote_date) ? $quotation->quote_date : null,
            'delivery_date'      => null,
            'invoice_date'       => $now,
            'invoice_amount'     => $total,
            'currency'           => $quotation->currency ?? 'Q',
            'work_description'   => $this->summarizeLines($lines),
            'material'           => null,
            'dimensions'         => null,
            'print_color'        => null,
            'line_items'         => $lineItemsJson,
            'quotation_file'     => null,
            'extraction_status'  => 'manual',
            'status'             => 'draft',
            'notes'              => 'FEL scheduled queue (cotización, 08:00)',
            'state'              => 1,
            'version'            => '3.101.51',
            'created'            => $now,
            'created_by'         => $userId,
            'quotation_id'       => $quotationId,
            'invoice_source'     => 'cotizacion_fel',
            'fel_issue_status'   => 'scheduled',
            'fel_issue_error'    => null,
            'fel_request_json'   => null,
            'fel_response_json'  => null,
            'fel_local_pdf_path' => null,
            'fel_local_xml_path' => null,
            'felplex_uuid'       => null,
            'fel_scheduled_at'   => $scheduledSql,
        ];

        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        $filtered = [];
        foreach ($row as $k => $v) {
            if (isset($cols[strtolower($k)])) {
                $filtered[$k] = $v;
            }
        }

        $o = (object) $filtered;
        $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');

        return (int) $o->id;
    }

    /**
     * Process invoices whose scheduled time has passed (e.g. called from Control de ventas → cola).
     *
     * @return  int  Number of invoices that completed successfully
     */
    public function processDueScheduledInvoices(int $limit = 30): int
    {
        if (!$this->isEngineAvailable() || !$this->hasFelScheduledAtColumn()) {
            return 0;
        }

        $db = $this->db;
        $now = Factory::getDate()->toSql();
        $q   = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__ordenproduccion_invoices'))
            ->where($db->quoteName('state') . ' = 1')
            ->where($db->quoteName('fel_issue_status') . ' = ' . $db->quote('scheduled'))
            ->where($db->quoteName('fel_scheduled_at') . ' IS NOT NULL')
            ->where($db->quoteName('fel_scheduled_at') . ' <= ' . $db->quote($now))
            ->order($db->quoteName('fel_scheduled_at') . ' ASC')
            ->setLimit($limit);
        $db->setQuery($q);
        $ids = $db->loadColumn() ?: [];
        $ok  = 0;

        foreach ($ids as $id) {
            $r = $this->processInvoice((int) $id, false);
            if (!empty($r['success'])) {
                $ok++;
            }
        }

        return $ok;
    }

    /**
     * Run mock certification for a pending/processing invoice (queue step 2).
     *
     * @param   bool  $forceScheduled  If true, run even when fel_issue_status is scheduled and fel_scheduled_at is in the future.
     */
    public function processInvoice(int $invoiceId, bool $forceScheduled = false): array
    {
        if ($invoiceId < 1 || !$this->isEngineAvailable()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $inv = $this->loadInvoice($invoiceId);
        if (!$inv) {
            return ['success' => false, 'message' => 'Invoice not found'];
        }

        $status = (string) ($inv->fel_issue_status ?? 'none');
        if ($status === 'completed') {
            return ['success' => true, 'message' => 'Already completed', 'invoice_id' => $invoiceId];
        }

        if ($status === 'failed') {
            $this->updateInvoiceFields($invoiceId, [
                'fel_issue_status' => 'pending',
                'fel_issue_error'    => null,
            ]);
            $inv = $this->loadInvoice($invoiceId);
            $status = (string) ($inv->fel_issue_status ?? 'pending');
        }

        if ($status === 'processing' && !empty($inv->fel_response_json)) {
            return ['success' => true, 'message' => 'Already completed', 'invoice_id' => $invoiceId];
        }

        if ($status === 'scheduled' && !$forceScheduled) {
            $schedRaw = $inv->fel_scheduled_at ?? null;
            if ($schedRaw !== null && $schedRaw !== '') {
                $due = Factory::getDate($schedRaw);
                $now = Factory::getDate('now');
                if ($due->getTimestamp() > $now->getTimestamp()) {
                    return ['success' => false, 'message' => 'Scheduled for later'];
                }
            }
        }

        $quotationId = (int) ($inv->quotation_id ?? 0);
        if ($quotationId < 1) {
            $this->markFailed($invoiceId, 'Missing quotation_id');

            return ['success' => false, 'message' => 'Missing quotation'];
        }

        $this->setStatus($invoiceId, 'processing');

        try {
            $quotation = $this->loadQuotation($quotationId);
            if (!$quotation) {
                throw new \RuntimeException('Quotation not found');
            }

            $lines = $this->loadQuotationLines($quotationId);
            $payload = $this->buildFelplexPayload($quotation, $lines, $inv);

            $requestJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $this->updateInvoiceFields($invoiceId, ['fel_request_json' => $requestJson]);

            $response = $this->mockFelplexResponse($payload);

            $responseJson = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            $paths = $this->writeLocalArtifacts($invoiceId, $quotation, $lines, $response, $payload);

            $sat = $response['sat'] ?? [];
            $auth = isset($sat['authorization']) ? (string) $sat['authorization'] : '';
            $uuid = isset($response['uuid']) ? (string) $response['uuid'] : '';

            $update = [
                'fel_response_json'      => $responseJson,
                'felplex_uuid'           => $uuid,
                'fel_autorizacion_uuid'  => $auth !== '' ? $auth : null,
                'fel_tipo_dte'           => 'FACT',
                'fel_fecha_emision'      => Factory::getDate()->toSql(),
                'fel_receptor_id'        => $this->digitsOnly($quotation->client_nit ?? ''),
                'fel_receptor_nombre'    => $quotation->client_name ?? '',
                'fel_receptor_direccion' => $quotation->client_address ?? null,
                'fel_moneda'             => 'GTQ',
                'fel_issue_status'       => 'completed',
                'fel_issue_error'        => null,
                'fel_scheduled_at'       => null,
                'fel_local_pdf_path'     => $paths['pdf'],
                'fel_local_xml_path'     => $paths['xml'],
                'status'                 => 'created',
                'modified'               => Factory::getDate()->toSql(),
                'modified_by'            => Factory::getUser()->id,
            ];

            $update = $this->filterToExistingColumns($update);
            $this->updateInvoiceFields($invoiceId, $update);

            return [
                'success'    => true,
                'message'    => 'OK',
                'invoice_id' => $invoiceId,
                'uuid'       => $uuid,
            ];
        } catch (\Throwable $e) {
            $this->markFailed($invoiceId, $e->getMessage());

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Build POST body like FELplex "Crear DTE Síncrono".
     */
    public function buildFelplexPayload(object $quotation, array $lines, object $invoice): array
    {
        $total = round((float) ($quotation->total_amount ?? 0), 2);
        $base = $total > 0 ? round($total / (1 + self::IVA_RATE), 2) : 0.0;
        $tax = round($total - $base, 2);

        $items = [];
        foreach ($lines as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $r     = $this->resolveQuotationLineTotals($row);
            $qty   = $r['qty'];
            $price = $r['unit_price'];
            $desc = isset($row->descripcion) ? trim((string) $row->descripcion) : 'Item';
            if ($desc === '') {
                $desc = 'Item';
            }

            $items[] = [
                'qty'                   => (string) $qty,
                'type'                  => 'B',
                'price'                 => $price,
                'description'           => $desc,
                'without_iva'           => 0,
                'discount'              => 0,
                'is_discount_percentage' => 0,
                'taxes'                 => [
                    'quantity'        => null,
                    'tax_code'        => null,
                    'full_name'       => null,
                    'short_name'      => null,
                    'tax_amount'      => null,
                    'taxable_amount'  => null,
                ],
            ];
        }

        if ($items === []) {
            $items[] = [
                'qty'                   => '1',
                'type'                  => 'B',
                'price'                 => $total > 0 ? $total : 0,
                'description'           => $quotation->quotation_number ?? 'Cotización',
                'without_iva'           => 0,
                'discount'              => 0,
                'is_discount_percentage' => 0,
                'taxes'                 => [
                    'quantity' => null, 'tax_code' => null, 'full_name' => null,
                    'short_name' => null, 'tax_amount' => null, 'taxable_amount' => null,
                ],
            ];
        }

        $nit = $this->digitsOnly($quotation->client_nit ?? '');
        $addr = $this->splitAddress((string) ($quotation->client_address ?? ''));

        $mail = Factory::getApplication()->get('mailfrom') ?: 'noreply@localhost';

        return [
            'type'            => 'FACT',
            'currency'        => 'GTQ',
            'datetime_issue'  => Factory::getDate()->format('Y-m-d\TH:i:s'),
            'external_id'     => $quotation->quotation_number ?? ('COT-' . ((int) $quotation->id)),
            'items'           => $items,
            'total'           => $total,
            'total_tax'       => $tax,
            'emails'          => [['email' => $mail]],
            'to_cf'           => 0,
            'to'              => [
                'tax_code_type' => 'NIT',
                'tax_code'      => $nit,
                'tax_name'      => $quotation->client_name ?? '',
                'address'       => $addr,
            ],
            'exempt_phrase'   => null,
            'custom_fields'   => [
                ['name' => 'Cotización', 'value' => (string) ($quotation->quotation_number ?? '')],
                ['name' => 'Factura interna', 'value' => (string) ($invoice->invoice_number ?? '')],
            ],
        ];
    }

    /**
     * Simulated successful FELplex response (same keys as Postman example).
     */
    public function mockFelplexResponse(array $payload): array
    {
        $uuid = $this->randomUuid();
        $serie = strtoupper(substr(str_replace('-', '', $this->randomUuid()), 0, 8));
        $auth = $this->randomUuid();
        $no = random_int(1000000000, 9999999999);
        $base = Uri::root();

        return [
            'valid'        => true,
            'uuid'         => $uuid,
            'sat'          => [
                'serie'               => $serie,
                'no'                  => $no,
                'authorization'       => $auth,
                'certification_date'  => Factory::getDate()->format('Y-m-d\TH:i:s'),
            ],
            'certifier'    => [
                'name'      => 'MOCK CERTIFIER (FELplex emulator)',
                'tax_code'  => '00000000',
            ],
            'errors'       => [],
            'invoice_url'  => $base . 'media/com_ordenproduccion/fel_issued/' . $uuid . '/invoice.pdf',
            'invoice_xml'  => $base . 'media/com_ordenproduccion/fel_issued/' . $uuid . '/invoice.xml',
        ];
    }

    /**
     * @return  array{pdf: string, xml: string} Relative web paths under JPATH_ROOT
     */
    protected function writeLocalArtifacts(
        int $invoiceId,
        object $quotation,
        array $lines,
        array $response,
        array $payload
    ): array {
        $uuid = (string) ($response['uuid'] ?? $this->randomUuid());
        $relDir = 'media/com_ordenproduccion/fel_issued/' . $invoiceId . '/' . $uuid;
        $absDir = JPATH_ROOT . '/' . $relDir;

        if (!Folder::create($absDir)) {
            throw new \RuntimeException('Cannot create directory: ' . $absDir);
        }

        $xml = $this->buildMockXml($quotation, $lines, $response, $payload);
        $xmlPath = $absDir . '/invoice.xml';
        if (file_put_contents($xmlPath, $xml) === false) {
            throw new \RuntimeException('Cannot write XML');
        }

        $pdfPath = $absDir . '/invoice.pdf';
        $this->buildMockPdf($pdfPath, $quotation, $lines, $response);

        return [
            'pdf' => $relDir . '/invoice.pdf',
            'xml' => $relDir . '/invoice.xml',
        ];
    }

    protected function buildMockXml(object $quotation, array $lines, array $response, array $payload): string
    {
        $sat = $response['sat'] ?? [];
        $esc = static function ($s) {
            return htmlspecialchars((string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        };

        $linesXml = '';
        foreach ($lines as $row) {
            $d = isset($row->descripcion) ? $row->descripcion : '';
            $sub = isset($row->subtotal) ? (float) $row->subtotal : 0;
            $linesXml .= '<Linea descripcion="' . $esc($d) . '" total="' . $esc((string) $sub) . "\"/>\n";
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . "\n<MockDTE xmlns=\"urn:mock:felplex\" factura=\"FACT\" moneda=\"GTQ\">"
            . "\n  <Emisor>MOCK — not a legal SAT document</Emisor>"
            . "\n  <Receptor nit=\"" . $esc($this->digitsOnly($quotation->client_nit ?? '')) . "\">"
            . $esc($quotation->client_name ?? '') . '</Receptor>'
            . "\n  <Totales total=\"" . $esc((string) ($payload['total'] ?? '')) . "\" iva=\"" . $esc((string) ($payload['total_tax'] ?? '')) . "\"/>"
            . "\n  <Autorizacion uuid=\"" . $esc($response['uuid'] ?? '') . "\" serie=\"" . $esc($sat['serie'] ?? '') . "\" no=\"" . $esc((string) ($sat['no'] ?? '')) . "\" auth=\"" . $esc($sat['authorization'] ?? '') . "\"/>"
            . "\n  <Lineas>\n" . $linesXml . '  </Lineas>'
            . "\n</MockDTE>\n";
    }

    protected function buildMockPdf(string $absPath, object $quotation, array $lines, array $response): void
    {
        if ($this->ensureFpdfLoaded()) {
            $pdf = new \FPDF('P', 'mm', 'Letter');
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, $this->fpdfText('Factura electrónica (MOCK / pruebas)'), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $this->fpdfText('No documento legal — emulador FELplex'), 0, 1);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, $this->fpdfText('Cliente: ' . ($quotation->client_name ?? '')), 0, 1);
            $pdf->Cell(0, 6, $this->fpdfText('NIT: ' . ($quotation->client_nit ?? '')), 0, 1);
            $pdf->Cell(0, 6, $this->fpdfText('Cotización: ' . ($quotation->quotation_number ?? '')), 0, 1);
            $sat = $response['sat'] ?? [];
            $pdf->Cell(0, 6, $this->fpdfText('UUID: ' . ($response['uuid'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, $this->fpdfText('Autorización: ' . ($sat['authorization'] ?? '')), 0, 1);
            $pdf->Output('F', $absPath);

            return;
        }

        // No FPDF on server: write a minimal valid PDF (placeholder was not a real file — browsers reject it).
        $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);
    }

    /**
     * Load FPDF from site root (same path as OrdenController / CotizacionController).
     */
    protected function ensureFpdfLoaded(): bool
    {
        if (\class_exists('FPDF', false)) {
            return true;
        }
        $path = JPATH_ROOT . '/fpdf/fpdf.php';
        if (\is_file($path)) {
            require_once $path;
        }

        return \class_exists('FPDF', false);
    }

    /**
     * Single-page PDF without external libs (Helvetica, ASCII-safe text).
     *
     * @param   list<object>  $lines  Quotation line rows
     */
    protected function writeMinimalValidFelMockPdf(string $absPath, object $quotation, array $lines, array $response): void
    {
        $ascii = static function (string $s): string {
            $s = (string) \preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
            if (\function_exists('iconv')) {
                $t = @\iconv('UTF-8', 'ASCII//TRANSLIT', $s);
                if ($t !== false) {
                    $s = $t;
                }
            }

            return (string) \preg_replace('/[^\x20-\x7E]/', '?', $s);
        };
        $esc = static function (string $s): string {
            return \str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
        };

        $textLines = [
            'Factura electronica (MOCK / pruebas)',
            'No documento legal',
            'Cliente: ' . $ascii((string) ($quotation->client_name ?? '')),
            'NIT: ' . $ascii((string) ($quotation->client_nit ?? '')),
            'Cotizacion: ' . $ascii((string) ($quotation->quotation_number ?? '')),
            'UUID: ' . $ascii((string) ($response['uuid'] ?? '')),
        ];
        $sat = $response['sat'] ?? [];
        $textLines[] = 'Autorizacion: ' . $ascii((string) ($sat['authorization'] ?? ''));

        foreach ($lines as $row) {
            if (\count($textLines) >= 32) {
                break;
            }
            $d = isset($row->descripcion) ? $ascii(\trim((string) $row->descripcion)) : '';
            if ($d !== '') {
                $textLines[] = '- ' . \substr($d, 0, 120);
            }
        }

        $stream = "BT\n/F1 10 Tf\n";
        foreach ($textLines as $i => $l) {
            $line = \substr($l, 0, 220);
            if ($i === 0) {
                $stream .= \sprintf("50 750 Td (%s) Tj\n", $esc($line));
            } else {
                $stream .= \sprintf("0 -14 Td (%s) Tj\n", $esc($line));
            }
        }
        $stream .= 'ET';

        $streamBody = $stream . "\n";
        $streamLen  = \strlen($streamBody);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        $offsets[1] = \strlen($pdf);
        $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        $offsets[2] = \strlen($pdf);
        $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        $offsets[3] = \strlen($pdf);
        $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";

        $offsets[4] = \strlen($pdf);
        $pdf .= '4 0 obj' . "\n<< /Length {$streamLen} >>\nstream\n{$streamBody}endstream\nendobj\n";

        $offsets[5] = \strlen($pdf);
        $pdf .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $xrefPos = \strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= \sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";

        if (\file_put_contents($absPath, $pdf) === false) {
            throw new \RuntimeException('Cannot write mock PDF');
        }
    }

    protected function fpdfText(string $utf8): string
    {
        if (\function_exists('mb_convert_encoding')) {
            return (string) mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');
        }

        return $utf8;
    }

    protected function summarizeLines(array $lines): string
    {
        $parts = [];
        foreach ($lines as $row) {
            if (!empty($row->descripcion)) {
                $parts[] = trim((string) $row->descripcion);
            }
        }

        return implode('; ', \array_slice($parts, 0, 5));
    }

    /**
     * @return  list<object>
     */
    protected function loadQuotationLines(int $quotationId): array
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
            ->order($this->db->quoteName('id') . ' ASC');
        $this->db->setQuery($q);
        $rows = $this->db->loadObjectList();

        return \is_array($rows) ? $rows : [];
    }

    protected function loadQuotation(int $id): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . $id)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);

        $row = $this->db->loadObject();

        return $row ?: null;
    }

    protected function loadInvoice(int $id): ?object
    {
        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('id') . ' = ' . $id)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Resolve quantity, line total, and unit price from a quotation line row.
     * Prefer valor_final (pre-cot override) over subtotal; derive missing totals from valor_unitario * qty.
     *
     * @return  array{qty: float, line_total: float, unit_price: float}
     */
    protected function resolveQuotationLineTotals(object $row): array
    {
        $qty = isset($row->cantidad) ? (float) $row->cantidad : 1.0;
        if ($qty <= 0) {
            $qty = 1.0;
        }
        $sub = isset($row->subtotal) ? (float) $row->subtotal : 0.0;
        $vf  = isset($row->valor_final) ? (float) $row->valor_final : 0.0;
        $vu  = isset($row->valor_unitario) ? (float) $row->valor_unitario : 0.0;

        $lineTotal = ($vf > 0.00001) ? $vf : $sub;
        if ($lineTotal <= 0.00001 && $vu > 0) {
            $lineTotal = round($vu * $qty, 2);
        }
        if ($lineTotal <= 0.00001 && $sub > 0) {
            $lineTotal = $sub;
        }

        $unit = $vu > 0.00001 ? round($vu, 4) : ($qty > 0 ? round($lineTotal / $qty, 4) : 0.0);

        return [
            'qty'         => $qty,
            'line_total'  => $lineTotal,
            'unit_price'  => $unit,
        ];
    }

    /**
     * JSON stored on invoices.line_items: includes precio_unitario for invoice PDF/detail (template key).
     */
    protected function buildLineItemsForStorage(array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $r = $this->resolveQuotationLineTotals($row);
            $out[] = [
                'cantidad'          => $r['qty'],
                'descripcion'       => $row->descripcion ?? '',
                'subtotal'          => $r['line_total'],
                'valor_unitario'    => $r['unit_price'],
                'precio_unitario'   => $r['unit_price'],
            ];
        }

        return $out;
    }

    protected function generateNextInvoiceNumber(): string
    {
        $q = $this->db->getQuery(true)
            ->select('MAX(CAST(SUBSTRING(' . $this->db->quoteName('invoice_number') . ', 5) AS UNSIGNED))')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('invoice_number') . ' LIKE ' . $this->db->quote('FAC-%'));
        $this->db->setQuery($q);
        $max = (int) $this->db->loadResult();

        return 'FAC-' . str_pad((string) ($max + 1), 6, '0', STR_PAD_LEFT);
    }

    protected function setStatus(int $invoiceId, string $status): void
    {
        $this->updateInvoiceFields($invoiceId, [
            'fel_issue_status' => $status,
            'modified'         => Factory::getDate()->toSql(),
        ]);
    }

    protected function markFailed(int $invoiceId, string $msg): void
    {
        $this->updateInvoiceFields($invoiceId, [
            'fel_issue_status' => 'failed',
            'fel_issue_error'  => $msg,
            'modified'         => Factory::getDate()->toSql(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function updateInvoiceFields(int $invoiceId, array $fields): void
    {
        if ($invoiceId < 1 || $fields === []) {
            return;
        }

        $fields = $this->filterToExistingColumns($fields);
        if ($fields === []) {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_invoices'));

        foreach ($fields as $k => $v) {
            $q->set($this->db->quoteName($k) . ' = ' . ($v === null ? 'NULL' : $this->db->quote($v)));
        }

        $q->where($this->db->quoteName('id') . ' = ' . $invoiceId);
        $this->db->setQuery($q);
        $this->db->execute();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function filterToExistingColumns(array $fields): array
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        if (!\is_array($cols)) {
            return [];
        }
        $cols = array_change_key_case($cols, CASE_LOWER);
        $out = [];
        foreach ($fields as $k => $v) {
            if (isset($cols[strtolower($k)])) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    protected function hasColumn(string $name): bool
    {
        $cols = $this->db->getTableColumns('#__ordenproduccion_invoices', false);
        if (!\is_array($cols)) {
            return false;
        }
        $cols = array_change_key_case($cols, CASE_LOWER);

        return isset($cols[strtolower($name)]);
    }

    protected function digitsOnly(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }

    protected function splitAddress(string $full): array
    {
        $full = trim($full);
        if ($full === '') {
            return [
                'street'  => 'Guatemala',
                'city'    => 'Guatemala',
                'state'   => 'GU',
                'zip'     => '01001',
                'country' => 'GT',
            ];
        }

        return [
            'street'  => $full,
            'city'    => 'Guatemala',
            'state'   => 'GU',
            'zip'     => '01001',
            'country' => 'GT',
        ];
    }

    protected function randomUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff)
        );
    }
}
