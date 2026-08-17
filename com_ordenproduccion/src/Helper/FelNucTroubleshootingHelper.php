<?php
/**
 * Validate stored Digifact NUC (fel_request_json) against cotización / invoice DB rows;
 * apply safe fixes (timbre recalc, NUC rebuild, missing TIMBRE Code).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.119.346
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * FEL NUC vs database diagnostics for troubleshooting.php.
 */
final class FelNucTroubleshootingHelper
{
    private const IVA_RATE = 0.12;

    private const TIMBRE_TAX_CODE = '1';

    private DatabaseInterface $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * @return array{invoice: ?object, quotation_id: int, quotation_number: string}
     */
    public function resolveInvoiceContext(array $input): array
    {
        $invoiceId     = (int) ($input['invoice_id'] ?? 0);
        $felUuid       = trim((string) ($input['fel_uuid'] ?? ''));
        $quotationId   = (int) ($input['quotation_id'] ?? 0);
        $cotNumber     = trim((string) ($input['cot_number'] ?? ''));

        if ($cotNumber !== '' && $quotationId < 1) {
            $quotationId = $this->lookupQuotationIdByNumber($cotNumber);
        }

        $inv = null;
        if ($invoiceId > 0) {
            $inv = $this->loadInvoiceById($invoiceId);
        } elseif ($felUuid !== '') {
            $inv = $this->loadInvoiceByUuid($felUuid);
        } elseif ($quotationId > 0) {
            $inv = $this->loadLatestInvoiceForQuotation($quotationId);
        }

        if ($inv && $quotationId < 1) {
            $quotationId = (int) ($inv->quotation_id ?? 0);
        }

        $quotationNumber = '';
        if ($quotationId > 0) {
            $q = $this->loadQuotation($quotationId);
            $quotationNumber = (string) ($q->quotation_number ?? ('COT-' . $quotationId));
        } elseif ($cotNumber !== '') {
            $quotationNumber = $cotNumber;
        }

        return [
            'invoice'           => $inv,
            'quotation_id'      => $quotationId,
            'quotation_number'  => $quotationNumber,
        ];
    }

    /**
     * Run NUC vs DB validation; returns sections compatible with ComOrdenproduccionTroubleshootingHelper.
     *
     * @return array{
     *   checks: list<array{label:string, status:string, detail:string}>,
     *   details: array<string, string>,
     *   tables: list<array{headers: list<string>, rows: list<list<string>>}>,
     *   suggested_fixes: list<array{id:string, label:string, detail:string}>,
     *   failures: int,
     *   warnings: int
     * }
     */
    public function analyze(?object $invoice, int $quotationId): array
    {
        $checks          = [];
        $details         = [];
        $tables          = [];
        $suggestedFixes  = [];
        $failures        = 0;
        $warnings        = 0;

        $addCheck = static function (string $label, string $status, string $detail) use (&$checks, &$failures, &$warnings): void {
            $checks[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
            if ($status === 'fail') {
                $failures++;
            } elseif ($status === 'warn') {
                $warnings++;
            }
        };

        $nucRaw = $invoice ? trim((string) ($invoice->fel_request_json ?? '')) : '';
        $nuc    = $nucRaw !== '' ? json_decode($nucRaw, true) : null;
        if ($nucRaw === '') {
            $addCheck('fel_request_json', $invoice ? 'warn' : 'info', $invoice ? 'Vacío — no hay NUC almacenado' : 'Sin factura; solo cotización');
        } elseif (!\is_array($nuc)) {
            $addCheck('fel_request_json', 'fail', 'JSON inválido');
        } else {
            $addCheck('fel_request_json', 'pass', 'JSON válido (' . strlen($nucRaw) . ' bytes)');
        }

        $impuestoPct = ImpuestoImprentaHelper::getParamPercent();
        $details['impuesto_imprenta_pct'] = (string) $impuestoPct;

        $quotationLines = $quotationId > 0 ? $this->loadQuotationItems($quotationId) : [];
        $productRows    = [];
        $timbreDbByPre  = [];
        $timbreDbTotal  = 0.0;

        foreach ($quotationLines as $row) {
            if (ImpuestoImprentaHelper::isImpuestoLineItem($row)) {
                $preId = (int) ($row->impuesto_for_pre_id ?? ImpuestoImprentaHelper::parseImpuestoLineForPreId((string) ($row->descripcion ?? '')));
                $amt   = $this->lineTotal($row);
                if ($preId > 0) {
                    $timbreDbByPre[$preId] = ($timbreDbByPre[$preId] ?? 0.0) + $amt;
                }
                $timbreDbTotal += $amt;
                continue;
            }
            if (ImpuestoImprentaHelper::shouldExcludeFromDigifactNucItems($row)) {
                continue;
            }
            $preId = (int) ($row->pre_cotizacion_id ?? 0);
            $total = $this->lineTotal($row);
            $expectedTimbre = 0.0;
            if ($preId > 0 && $impuestoPct > 0) {
                $pre = $this->loadPreCotizacion($preId);
                $expectedTimbre = ImpuestoImprentaHelper::computeImpuestoAmount(
                    $total,
                    (string) ($row->descripcion ?? ''),
                    (string) ($pre->descripcion ?? ''),
                    ''
                );
            }
            $productRows[] = [
                'pre_id'          => $preId,
                'descripcion'     => substr((string) ($row->descripcion ?? ''), 0, 60),
                'qty'             => (string) ($row->cantidad ?? ''),
                'line_total'      => number_format($total, 2, '.', ''),
                'expected_timbre' => number_format($expectedTimbre, 2, '.', ''),
                'db_timbre'       => number_format((float) ($timbreDbByPre[$preId] ?? 0), 2, '.', ''),
            ];
            $dbTimbre = (float) ($timbreDbByPre[$preId] ?? 0);
            if ($expectedTimbre > 0.000001 && abs($expectedTimbre - $dbTimbre) > 0.009) {
                $addCheck(
                    'Timbre cotización PRE-' . $preId,
                    'fail',
                    'DB Q ' . number_format($dbTimbre, 2) . ' vs esperado Q ' . number_format($expectedTimbre, 2)
                    . ' (' . $impuestoPct . '% de Q ' . number_format($total, 2) . ')'
                );
                $suggestedFixes['recalc_quotation_timbre'] = [
                    'id'     => 'recalc_quotation_timbre',
                    'label'  => 'Recalcular líneas timbre en cotización',
                    'detail' => 'Actualiza quotation_items impuesto según total producto y parámetro impuesto_imprenta.',
                ];
            }
        }

        if ($productRows !== []) {
            $tables[] = [
                'headers' => ['pre_id', 'descripcion', 'qty', 'line_total', 'expected_timbre', 'db_timbre'],
                'rows'    => array_map(static fn($r) => array_values($r), $productRows),
            ];
        }

        $details['quotation_timbre_db_total'] = number_format($timbreDbTotal, 2, '.', '');

        if (!\is_array($nuc)) {
            return compact('checks', 'details', 'tables', 'suggestedFixes', 'failures', 'warnings');
        }

        $nucProductTotal = 0.0;
        $nucIvaTotal     = 0.0;
        $nucTimbreTotal  = 0.0;
        $missingCodes    = 0;
        $nucItemRows     = [];

        foreach ($nuc['Items'] ?? [] as $idx => $item) {
            if (!\is_array($item)) {
                continue;
            }
            $itemTotal = (float) ($item['Totals']['TotalItem'] ?? 0);
            $qty       = (float) ($item['Qty'] ?? 0);
            $price     = (float) ($item['Price'] ?? 0);
            $ivaAmt    = 0.0;
            $timbreAmt = 0.0;
            $timbreHasCode = true;

            foreach ($item['Taxes']['Tax'] ?? [] as $tax) {
                if (!\is_array($tax)) {
                    continue;
                }
                $desc = strtoupper(trim((string) ($tax['Description'] ?? '')));
                $amt  = (float) ($tax['Amount'] ?? 0);
                if ($desc === 'IVA') {
                    $ivaAmt = $amt;
                } elseif (str_contains($desc, 'TIMBRE')) {
                    $timbreAmt = $amt;
                    $code      = trim((string) ($tax['Code'] ?? ''));
                    if ($code === '') {
                        $missingCodes++;
                        $timbreHasCode = false;
                    }
                }
            }

            $productOnly = $itemTotal - $timbreAmt;
            $nucProductTotal += $productOnly;
            $nucIvaTotal     += $ivaAmt;
            $nucTimbreTotal  += $timbreAmt;

            $nucItemRows[] = [
                (string) ($idx + 1),
                substr((string) ($item['Description'] ?? ''), 0, 50),
                number_format($qty, 2, '.', ''),
                number_format($price, 6, '.', ''),
                number_format($itemTotal, 2, '.', ''),
                number_format($ivaAmt, 2, '.', ''),
                number_format($timbreAmt, 2, '.', ''),
                $timbreAmt > 0.000001 ? ($timbreHasCode ? 'yes' : 'NO') : '—',
            ];
        }

        if ($nucItemRows !== []) {
            $tables[] = [
                'headers' => ['#', 'descripcion', 'qty', 'price', 'total_item', 'iva', 'timbre', 'timbre_code'],
                'rows'    => $nucItemRows,
            ];
        }

        if ($missingCodes > 0) {
            $addCheck(
                'NUC TIMBRE DE PRENSA Code',
                'fail',
                $missingCodes . ' impuesto(s) TIMBRE sin Code (Digifact D1011 — requiere Code "1")'
            );
            $suggestedFixes['patch_nuc_timbre_code'] = [
                'id'     => 'patch_nuc_timbre_code',
                'label'  => 'Agregar Code en TIMBRE DE PRENSA (NUC)',
                'detail' => 'Parchea fel_request_json sin reconstruir el resto del payload.',
            ];
        } else {
            $addCheck('NUC TIMBRE DE PRENSA Code', 'pass', 'Todos los TIMBRE tienen Code');
        }

        $nucGrand = (float) ($nuc['Totals']['GrandTotal']['InvoiceTotal'] ?? 0);
        $details['nuc_grand_total']      = number_format($nucGrand, 2, '.', '');
        $details['nuc_product_subtotal']  = number_format($nucProductTotal, 2, '.', '');
        $details['nuc_iva_total']         = number_format($nucIvaTotal, 2, '.', '');
        $details['nuc_timbre_total']      = number_format($nucTimbreTotal, 2, '.', '');

        $expectedGrand = round($nucProductTotal + $nucTimbreTotal, 2);
        if (abs($nucGrand - $expectedGrand) > 0.02) {
            $addCheck(
                'NUC GrandTotal coherencia',
                'fail',
                'InvoiceTotal Q ' . number_format($nucGrand, 2) . ' vs ítems Q ' . number_format($expectedGrand, 2)
            );
        } else {
            $addCheck('NUC GrandTotal coherencia', 'pass', 'Q ' . number_format($nucGrand, 2));
        }

        if ($timbreDbTotal > 0.000001 && abs($nucTimbreTotal - $timbreDbTotal) > 0.009) {
            $addCheck(
                'NUC timbre vs cotización DB',
                'fail',
                'NUC Q ' . number_format($nucTimbreTotal, 2) . ' vs cotización Q ' . number_format($timbreDbTotal, 2)
                . ' (Δ Q ' . number_format($nucTimbreTotal - $timbreDbTotal, 2) . ')'
            );
            $suggestedFixes['rebuild_nuc'] = [
                'id'     => 'rebuild_nuc',
                'label'  => 'Reconstruir NUC desde cotización / factura',
                'detail' => 'Regenera fel_request_json con FelInvoiceIssuanceService y sincroniza invoice_amount.',
            ];
        } elseif ($timbreDbTotal > 0.000001) {
            $addCheck('NUC timbre vs cotización DB', 'pass', 'Q ' . number_format($nucTimbreTotal, 2));
        }

        if ($invoice) {
            $invAmt = (float) ($invoice->invoice_amount ?? 0);
            $details['invoice_amount'] = number_format($invAmt, 2, '.', '');
            if ($nucGrand > 0.000001 && abs($invAmt - $nucGrand) > 0.009) {
                $addCheck(
                    'invoice_amount vs NUC',
                    'fail',
                    'Factura Q ' . number_format($invAmt, 2) . ' vs NUC Q ' . number_format($nucGrand, 2)
                );
                $suggestedFixes['sync_invoice_amount'] = [
                    'id'     => 'sync_invoice_amount',
                    'label'  => 'Sincronizar invoice_amount con NUC',
                    'detail' => 'Actualiza invoice_amount al GrandTotal del NUC.',
                ];
            } else {
                $addCheck('invoice_amount vs NUC', 'pass', 'Q ' . number_format($invAmt, 2));
            }

            $storedLines = $this->decodeLineItems($invoice);
            $storedSum   = 0.0;
            foreach ($storedLines as $ln) {
                $storedSum += (float) ($ln['subtotal'] ?? 0);
            }
            $pdfStyleTotal = round($storedSum + $timbreDbTotal, 2);
            if ($storedSum > 0.000001 && abs($pdfStyleTotal - $nucGrand) > 0.009) {
                $addCheck(
                    'PDF líneas vs NUC total',
                    'warn',
                    'Suma líneas factura + timbre cotización Q ' . number_format($pdfStyleTotal, 2)
                    . ' vs NUC Q ' . number_format($nucGrand, 2)
                    . ' (Δ Q ' . number_format($nucGrand - $pdfStyleTotal, 2) . ')'
                );
            }
        }

        if ($quotationId > 0 && $invoice && ($missingCodes > 0 || ($timbreDbTotal > 0 && abs($nucTimbreTotal - $timbreDbTotal) > 0.009))) {
            $suggestedFixes['rebuild_nuc'] = [
                'id'     => 'rebuild_nuc',
                'label'  => 'Reconstruir NUC desde cotización / factura',
                'detail' => 'Regenera fel_request_json con timbre y Code correctos; actualiza invoice_amount.',
            ];
        }

        $suggestedFixes = array_values($suggestedFixes);

        return compact('checks', 'details', 'tables', 'suggestedFixes', 'failures', 'warnings');
    }

    /**
     * @param   list<string>  $fixIds
     *
     * @return array{success: bool, messages: list<string>, analysis?: array}
     */
    public function applyFixes(?object $invoice, int $quotationId, array $fixIds, int $userId): array
    {
        $messages = [];
        $fixIds   = array_values(array_unique(array_filter(array_map('strval', $fixIds))));

        if ($fixIds === []) {
            return ['success' => false, 'messages' => ['No fix selected']];
        }

        foreach ($fixIds as $fixId) {
            switch ($fixId) {
                case 'recalc_quotation_timbre':
                    if ($quotationId < 1) {
                        $messages[] = 'recalc_quotation_timbre: sin quotation_id';
                        break;
                    }
                    $messages[] = $this->recalcQuotationTimbreLines($quotationId);
                    break;

                case 'patch_nuc_timbre_code':
                    if (!$invoice) {
                        $messages[] = 'patch_nuc_timbre_code: sin factura';
                        break;
                    }
                    $messages[] = $this->patchNucTimbreCode((int) $invoice->id);
                    break;

                case 'sync_invoice_amount':
                    if (!$invoice) {
                        $messages[] = 'sync_invoice_amount: sin factura';
                        break;
                    }
                    $messages[] = $this->syncInvoiceAmountFromNuc((int) $invoice->id);
                    break;

                case 'rebuild_nuc':
                    if (!$invoice) {
                        $messages[] = 'rebuild_nuc: sin factura';
                        break;
                    }
                    $messages[] = $this->rebuildNucForInvoice((int) $invoice->id, $userId);
                    break;

                default:
                    $messages[] = 'Fix desconocido: ' . $fixId;
            }
        }

        $invoice = $invoice && (int) ($invoice->id ?? 0) > 0
            ? $this->loadInvoiceById((int) $invoice->id)
            : $invoice;

        return [
            'success'  => true,
            'messages' => $messages,
            'analysis' => $this->analyze($invoice, $quotationId),
        ];
    }

    private function recalcQuotationTimbreLines(int $quotationId): string
    {
        $impuestoPct = ImpuestoImprentaHelper::getParamPercent();
        if ($impuestoPct <= 0) {
            return 'recalc_quotation_timbre: impuesto_imprenta param is 0 — nothing to do';
        }

        $lines     = $this->loadQuotationItems($quotationId);
        $byPreProduct = [];
        foreach ($lines as $row) {
            if (ImpuestoImprentaHelper::isImpuestoLineItem($row)) {
                continue;
            }
            if (ImpuestoImprentaHelper::shouldExcludeFromDigifactNucItems($row)) {
                continue;
            }
            $preId = (int) ($row->pre_cotizacion_id ?? 0);
            if ($preId > 0) {
                $byPreProduct[$preId] = $row;
            }
        }

        $updated = 0;
        foreach ($lines as $row) {
            if (!ImpuestoImprentaHelper::isImpuestoLineItem($row)) {
                continue;
            }
            $preId = (int) ($row->impuesto_for_pre_id ?? ImpuestoImprentaHelper::parseImpuestoLineForPreId((string) ($row->descripcion ?? '')));
            if ($preId < 1 || !isset($byPreProduct[$preId])) {
                continue;
            }
            $product   = $byPreProduct[$preId];
            $lineTotal = $this->lineTotal($product);
            $pre       = $this->loadPreCotizacion($preId);
            $newAmt    = ImpuestoImprentaHelper::computeImpuestoAmount(
                $lineTotal,
                (string) ($product->descripcion ?? ''),
                (string) ($pre->descripcion ?? ''),
                ''
            );
            if ($newAmt <= 0.000001) {
                continue;
            }
            $itemId = (int) ($row->id ?? 0);
            if ($itemId < 1) {
                continue;
            }
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_quotation_items'))
                    ->set($this->db->quoteName('subtotal') . ' = ' . round($newAmt, 2))
                    ->set($this->db->quoteName('valor_final') . ' = ' . round($newAmt, 2))
                    ->set($this->db->quoteName('valor_unitario') . ' = ' . round($newAmt, 2))
                    ->where($this->db->quoteName('id') . ' = ' . $itemId)
            );
            $this->db->execute();
            $updated++;
        }

        return 'recalc_quotation_timbre: ' . $updated . ' línea(s) actualizada(s)';
    }

    private function patchNucTimbreCode(int $invoiceId): string
    {
        $inv = $this->loadInvoiceById($invoiceId);
        if (!$inv) {
            return 'patch_nuc_timbre_code: factura no encontrada';
        }
        $raw = trim((string) ($inv->fel_request_json ?? ''));
        $nuc = json_decode($raw, true);
        if (!\is_array($nuc)) {
            return 'patch_nuc_timbre_code: NUC inválido';
        }

        $patched = 0;
        foreach ($nuc['Items'] ?? [] as &$item) {
            if (!\is_array($item)) {
                continue;
            }
            foreach ($item['Taxes']['Tax'] ?? [] as &$tax) {
                if (!\is_array($tax)) {
                    continue;
                }
                $desc = strtoupper(trim((string) ($tax['Description'] ?? '')));
                if (!str_contains($desc, 'TIMBRE')) {
                    continue;
                }
                if (trim((string) ($tax['Code'] ?? '')) === '') {
                    $tax['Code'] = self::TIMBRE_TAX_CODE;
                    $patched++;
                }
            }
            unset($tax);
        }
        unset($item);

        if ($patched === 0) {
            return 'patch_nuc_timbre_code: nada que parchear';
        }

        $json = json_encode($nuc, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return 'patch_nuc_timbre_code: error JSON encode';
        }

        $this->updateInvoiceField($invoiceId, 'fel_request_json', $json);

        return 'patch_nuc_timbre_code: Code agregado en ' . $patched . ' TIMBRE(s)';
    }

    private function syncInvoiceAmountFromNuc(int $invoiceId): string
    {
        $inv = $this->loadInvoiceById($invoiceId);
        if (!$inv) {
            return 'sync_invoice_amount: factura no encontrada';
        }
        $nuc = json_decode((string) ($inv->fel_request_json ?? ''), true);
        if (!\is_array($nuc)) {
            return 'sync_invoice_amount: NUC inválido';
        }
        $grand = (float) ($nuc['Totals']['GrandTotal']['InvoiceTotal'] ?? 0);
        if ($grand <= 0.000001) {
            return 'sync_invoice_amount: GrandTotal vacío';
        }
        $this->updateInvoiceField($invoiceId, 'invoice_amount', round($grand, 2));

        return 'sync_invoice_amount: invoice_amount = Q ' . number_format($grand, 2);
    }

    private function rebuildNucForInvoice(int $invoiceId, int $userId): string
    {
        $inv = $this->loadInvoiceById($invoiceId);
        if (!$inv) {
            return 'rebuild_nuc: factura no encontrada';
        }

        $quotationId = (int) ($inv->quotation_id ?? 0);
        if ($quotationId < 1) {
            return 'rebuild_nuc: factura sin quotation_id';
        }

        $fel = new FelInvoiceIssuanceService($this->db);
        if (!$fel->isEngineAvailable()) {
            return 'rebuild_nuc: motor FEL no disponible';
        }

        $manualLines = $this->manualLinesFromInvoice($inv);
        if ($manualLines === []) {
            $built = $fel->buildDigifactNucDirectPayloadForQuotation($quotationId);
            if (empty($built['success']) || !isset($built['payload'])) {
                return 'rebuild_nuc: direct payload failed — ' . (string) ($built['message'] ?? '');
            }
            $payload = $built['payload'];
        } else {
            $nucOptions = $this->nucOptionsFromInvoice($inv);
            $issueDate  = !empty($inv->invoice_date) ? (string) $inv->invoice_date : null;
            $built      = $fel->buildManualFelNucPayloadForQuotation(
                $quotationId,
                $manualLines,
                trim((string) ($inv->client_name ?? '')),
                trim((string) ($inv->client_nit ?? '')),
                trim((string) ($inv->client_address ?? 'Ciudad')),
                [],
                null,
                $issueDate,
                $nucOptions
            );
            if (empty($built['success']) || !isset($built['payload'])) {
                return 'rebuild_nuc: manual payload failed — ' . (string) ($built['message'] ?? '');
            }
            $payload = $built['payload'];
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return 'rebuild_nuc: JSON encode failed';
        }

        $grand = (float) ($payload['Totals']['GrandTotal']['InvoiceTotal'] ?? 0);
        $this->updateInvoiceField($invoiceId, 'fel_request_json', $json);
        if ($grand > 0.000001) {
            $this->updateInvoiceField($invoiceId, 'invoice_amount', round($grand, 2));
        }

        if ((string) ($inv->fel_issue_status ?? '') === 'failed') {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->update($this->db->quoteName('#__ordenproduccion_invoices'))
                    ->set($this->db->quoteName('fel_issue_status') . ' = ' . $this->db->quote('pending'))
                    ->set($this->db->quoteName('fel_issue_error') . ' = NULL')
                    ->where($this->db->quoteName('id') . ' = ' . $invoiceId)
            );
            $this->db->execute();
        }

        return 'rebuild_nuc: NUC regenerado — GrandTotal Q ' . number_format($grand, 2);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function manualLinesFromInvoice(object $inv): array
    {
        $out = [];
        foreach ($this->decodeLineItems($inv) as $line) {
            $desc = trim((string) ($line['descripcion'] ?? ''));
            if ($desc === '' || ImpuestoImprentaHelper::isFelExcludedLineDescription($desc)) {
                continue;
            }
            $qty  = (float) ($line['cantidad'] ?? 0);
            $unit = (float) ($line['precio_unitario'] ?? $line['valor_unitario'] ?? 0);
            if ($qty < 0.000001 || $unit < 0) {
                continue;
            }
            $row = [
                'descripcion'       => $desc,
                'cantidad'          => $qty,
                'precio_unitario'   => $unit,
                'item_type'         => (string) ($line['item_type'] ?? 'Bien'),
            ];
            if (!empty($line['pre_cotizacion_id'])) {
                $row['pre_cotizacion_id'] = (int) $line['pre_cotizacion_id'];
            }
            if (!empty($line['quotation_id'])) {
                $row['quotation_id'] = (int) $line['quotation_id'];
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function nucOptionsFromInvoice(object $inv): array
    {
        $felExtra = [];
        if (!empty($inv->fel_extra) && \is_string($inv->fel_extra)) {
            $decoded = json_decode($inv->fel_extra, true);
            if (\is_array($decoded)) {
                $felExtra = $decoded;
            }
        }

        $docType = strtoupper(trim((string) ($inv->fel_tipo_dte ?? 'FCAM')));
        if (!\in_array($docType, ['FACT', 'FCAM'], true)) {
            $docType = 'FCAM';
        }

        $currency = strtoupper(trim((string) ($inv->currency ?? 'Q')));
        $currency = ($currency === 'USD') ? 'USD' : 'GTQ';

        $opts = [
            'doc_type'      => $docType,
            'observaciones' => trim((string) ($felExtra['pdf_observaciones'] ?? '')),
            'currency'      => $currency,
        ];

        if ($docType === 'FCAM' && isset($felExtra['complemento_abonos']) && \is_array($felExtra['complemento_abonos'])) {
            $opts['fcam_abonos'] = $felExtra['complemento_abonos'];
        }
        if (isset($felExtra['exchange_rate'])) {
            $opts['exchange_rate'] = (float) $felExtra['exchange_rate'];
        }

        return $opts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeLineItems(object $inv): array
    {
        if (empty($inv->line_items) || !\is_string($inv->line_items)) {
            return [];
        }
        $decoded = json_decode($inv->line_items, true);

        return \is_array($decoded) ? array_values($decoded) : [];
    }

    private function lineTotal(object $row): float
    {
        $fel = new FelInvoiceIssuanceService($this->db);

        return round((float) $fel->getLineTotalsForFelRow($row)['line_total'], 2);
    }

    /**
     * @return list<object>
     */
    private function loadQuotationItems(int $quotationId): array
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_quotation_items'))
                ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
                ->order($this->db->quoteName('id') . ' ASC')
        );
        $rows = $this->db->loadObjectList() ?: [];
        foreach ($rows as $row) {
            if (ImpuestoImprentaHelper::isImpuestoLineItem($row)) {
                $forPreId = ImpuestoImprentaHelper::parseImpuestoLineForPreId((string) ($row->descripcion ?? ''));
                $row->is_impuesto_line    = true;
                $row->impuesto_for_pre_id = $forPreId;
            }
        }

        return $rows;
    }

    private function loadPreCotizacion(int $preId): ?object
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($this->db->quoteName('id') . ' = ' . $preId)
        );

        return $this->db->loadObject() ?: null;
    }

    private function loadQuotation(int $id): ?object
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                ->where($this->db->quoteName('id') . ' = ' . $id)
        );

        return $this->db->loadObject() ?: null;
    }

    private function loadInvoiceById(int $id): ?object
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('id') . ' = ' . $id)
        );

        return $this->db->loadObject() ?: null;
    }

    private function loadInvoiceByUuid(string $uuid): ?object
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('fel_autorizacion_uuid') . ' = ' . $this->db->quote($uuid))
        );

        return $this->db->loadObject() ?: null;
    }

    private function loadLatestInvoiceForQuotation(int $quotationId): ?object
    {
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId)
                ->order($this->db->quoteName('id') . ' DESC'),
            0,
            1
        );

        return $this->db->loadObject() ?: null;
    }

    private function lookupQuotationIdByNumber(string $ref): int
    {
        $ref = trim($ref);
        if ($ref === '') {
            return 0;
        }
        if (preg_match('/^COT-(\d+)$/i', $ref, $m)) {
            return (int) $m[1];
        }
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('id'))
                ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                ->where($this->db->quoteName('quotation_number') . ' = ' . $this->db->quote($ref))
        );

        return (int) $this->db->loadResult();
    }

    private function updateInvoiceField(int $invoiceId, string $field, $value): void
    {
        $allowed = ['fel_request_json', 'invoice_amount'];
        if (!\in_array($field, $allowed, true)) {
            return;
        }

        $query = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('id') . ' = ' . $invoiceId);

        if ($field === 'invoice_amount') {
            $query->set($this->db->quoteName($field) . ' = ' . round((float) $value, 2));
        } else {
            $query->set($this->db->quoteName($field) . ' = ' . $this->db->quote((string) $value));
        }

        $this->db->setQuery($query);
        $this->db->execute();
    }
}
