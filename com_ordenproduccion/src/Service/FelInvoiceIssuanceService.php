<?php
/**
 * Mock FELplex "Crear DTE Síncrono" engine (legacy): build JSON from cotización, optionally simulate certification
 * when Digifact URLs/token are unset; otherwise {@see FelInvoiceIssuanceService::executeDigifactCertificationForInvoice()}
 * persists real Digifact payloads and artifacts.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BanguatTipoCambioHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorFactNitLookupHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorDigifactAmbienteHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CertificadorDigifactLogHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\FelXmlHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\FelInvoiceHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceGrimpsaTemplatePdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\InvoiceListHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\QuotationEnvioFelHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\ApprovalWorkflowEntityHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
use Grimpsa\Component\Ordenproduccion\Site\Model\AdministracionModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * FEL invoice issuance service (Digifact when configured; else mock simulation).
 *
 * @since  3.101.50
 */
class FelInvoiceIssuanceService
{
    /** @var string Time (24h) for scheduled FEL run on the chosen billing date (site timezone) */
    public const SCHEDULED_ISSUE_HOUR = '08:00:00';

    /** Minutes before a stuck fel_issue_status=processing row may be reclaimed. */
    private const FEL_CERTIFY_STALE_MINUTES = 20;

    /** @var DatabaseInterface */
    protected $db;

    /** @var string Last DB error when manual invoice insert fails (admin diagnostics). */
    protected string $lastInvoiceInsertError = '';

    /** Guatemala IVA rate (12%) for price breakdown when totals are tax-inclusive */
    public const IVA_RATE = 0.12;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Active certifier environment from site config (test = prueba, prod = producción).
     *
     * @return  string  test|prod
     *
     * @since   3.118.5
     */
    public function getActiveCertificadorModo(): string
    {
        try {
            $model = new AdministracionModel();

            return $model->getCertificadorFactModo();
        } catch (\Throwable $e) {
            return 'test';
        }
    }

    /**
     * Full credential block for the active modo (includes clave). For server-side / API use only.
     *
     * @return  array<string, string>
     *
     * @since   3.118.6
     */
    public function getActiveCertificadorCredentials(): array
    {
        try {
            $model = new AdministracionModel();

            return $model->getCertificadorFactSettingsForActiveModo();
        } catch (\Throwable $e) {
            return [
                'url_autenticacion' => '',
                'url_info'          => '',
                'url_cert_cf'       => '',
                'url_cert_nit'      => '',
                'url_cert_cui'      => '',
                'branch_code'       => '',
                'branch_name'       => '',
                'branch_address'    => '',
                'branch_city'       => '',
                'branch_district'   => '',
                'branch_state'      => '',
                'branch_country'    => '',
                'nit'               => '',
                'usuario'           => '',
                'clave'             => '',
            ];
        }
    }

    /**
     * Non-empty bearer JWT for active modo when stored and not expired (does not auto-refresh).
     *
     * @since 3.118.8
     */
    public function getActiveCertificadorBearerToken(): string
    {
        try {
            $model = new AdministracionModel();
            $env = $model->getCertificadorFactModo();
            if ($model->isCertificadorBearerTokenExpiredForEnv($env)) {
                return '';
            }
            $b = $model->getCertificadorBearerTokenBundleForEnv($env);

            return trim((string) ($b['token'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * JWT for Digifact HTTP: refresh via {@see AdministracionModel::maintainCertificadorBearerTokens()} when Joomla
     * marks it expired, then return the stored token (even when Digifact accepts it despite bad expira_* metadata).
     *
     * @since 3.118.77
     */
    protected function resolveBearerJwtForDigifactPost(): string
    {
        try {
            $model = new AdministracionModel();
            $env = $model->getCertificadorFactModo();
            if ($model->isCertificadorBearerTokenExpiredForEnv($env)) {
                $model->maintainCertificadorBearerTokens(false);
            }
            $b = $model->getCertificadorBearerTokenBundleForEnv($env);

            return trim((string) ($b['token'] ?? ''));
        } catch (\Throwable $e) {
            return '';
        }
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
     * Whether the one-invoice-per-cotización unique index is still present (migration 3.119.68+ should remove it).
     */
    public function hasUniqueQuotationIdIndex(): bool
    {
        if (!$this->hasQuotationIdColumn()) {
            return false;
        }

        try {
            $table = $this->db->replacePrefix('#__ordenproduccion_invoices');
            $this->db->setQuery(
                'SELECT COUNT(*) FROM ' . $this->db->quoteName('INFORMATION_SCHEMA.STATISTICS')
                . ' WHERE ' . $this->db->quoteName('TABLE_SCHEMA') . ' = DATABASE()'
                . ' AND ' . $this->db->quoteName('TABLE_NAME') . ' = ' . $this->db->quote($table)
                . ' AND ' . $this->db->quoteName('INDEX_NAME') . ' = ' . $this->db->quote('uq_ordenproduccion_invoices_quotation_id')
                . ' AND ' . $this->db->quoteName('NON_UNIQUE') . ' = 0'
            );

            return (int) $this->db->loadResult() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Drop unique quotation_id index so multiple FEL invoices can reference one cotización.
     */
    public function dropUniqueQuotationIdIndexIfPresent(): bool
    {
        if (!$this->hasUniqueQuotationIdIndex()) {
            return true;
        }

        try {
            $this->db->setQuery(
                'ALTER TABLE ' . $this->db->quoteName('#__ordenproduccion_invoices')
                . ' DROP INDEX ' . $this->db->quoteName('uq_ordenproduccion_invoices_quotation_id')
            );
            $this->db->execute();

            if (!$this->hasIndexOnInvoicesTable('idx_ordenproduccion_invoices_quotation_id')) {
                $this->db->setQuery(
                    'ALTER TABLE ' . $this->db->quoteName('#__ordenproduccion_invoices')
                    . ' ADD KEY ' . $this->db->quoteName('idx_ordenproduccion_invoices_quotation_id')
                    . ' (' . $this->db->quoteName('quotation_id') . ')'
                );
                $this->db->execute();
            }

            return !$this->hasUniqueQuotationIdIndex();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Ensure schema allows multiple invoices per cotización (SQL migration 3.119.68 / auto-fix).
     */
    public function ensureMultipleInvoicesPerQuotationSchema(): bool
    {
        return $this->dropUniqueQuotationIdIndexIfPresent();
    }

    protected function hasIndexOnInvoicesTable(string $indexName): bool
    {
        try {
            $table = $this->db->replacePrefix('#__ordenproduccion_invoices');
            $this->db->setQuery(
                'SELECT COUNT(*) FROM ' . $this->db->quoteName('INFORMATION_SCHEMA.STATISTICS')
                . ' WHERE ' . $this->db->quoteName('TABLE_SCHEMA') . ' = DATABASE()'
                . ' AND ' . $this->db->quoteName('TABLE_NAME') . ' = ' . $this->db->quote($table)
                . ' AND ' . $this->db->quoteName('INDEX_NAME') . ' = ' . $this->db->quote($indexName)
            );

            return (int) $this->db->loadResult() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function isDuplicateQuotationIdConstraintError(\Throwable $e): bool
    {
        $msg = $e->getMessage();

        return stripos($msg, 'Duplicate entry') !== false
            && stripos($msg, 'uq_ordenproduccion_invoices_quotation_id') !== false;
    }

    /**
     * Whether fel_scheduled_at exists (migration 3.101.51).
     */
    public function hasFelScheduledAtColumn(): bool
    {
        return $this->hasColumn('fel_scheduled_at');
    }

    /**
     * Load latest active invoice linked to quotation, if any.
     */
    public function getInvoiceByQuotationId(int $quotationId): ?object
    {
        $rows = $this->getInvoicesByQuotationId($quotationId);

        return $rows !== [] ? $rows[0] : null;
    }

    /**
     * All published invoices for a cotización (newest first).
     *
     * @return  list<object>
     */
    public function getInvoicesByQuotationId(int $quotationId): array
    {
        if ($quotationId < 1 || !$this->hasQuotationIdColumn()) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_invoices'))
            ->where($this->db->quoteName('state') . ' = 1')
            ->order($this->db->quoteName('id') . ' DESC');

        if ($this->hasInvoiceQuotationsTable()) {
            $sub = $this->db->getQuery(true)
                ->select($this->db->quoteName('invoice_id'))
                ->from($this->db->quoteName('#__ordenproduccion_invoice_quotations'))
                ->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId);
            $q->where(
                '(' . $this->db->quoteName('quotation_id') . ' = ' . $quotationId
                . ' OR ' . $this->db->quoteName('id') . ' IN (' . (string) $sub . '))'
            );
        } else {
            $q->where($this->db->quoteName('quotation_id') . ' = ' . $quotationId);
        }

        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * Sum invoice_amount for completed FEL invoices on a cotización.
     */
    public function sumCompletedInvoiceAmountsForQuotation(int $quotationId): float
    {
        $sum = 0.0;
        foreach ($this->getInvoicesByQuotationId($quotationId) as $inv) {
            if ((string) ($inv->fel_issue_status ?? '') !== 'completed') {
                continue;
            }
            $sum += $this->sumCompletedInvoiceAmountForQuotationFromRow($inv, $quotationId);
        }

        return round($sum, 2);
    }

    /**
     * Allocated completed amount from one invoice row for a cotización (supports multi-cot line tagging).
     */
    public function sumCompletedInvoiceAmountForQuotationFromRow(object $inv, int $quotationId): float
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return 0.0;
        }

        $tagged = $this->sumTaggedLineSubtotalsForQuotation($inv, $quotationId);
        if ($tagged !== null) {
            return round($tagged, 2);
        }

        if ((int) ($inv->quotation_id ?? 0) === $quotationId) {
            return round((float) ($inv->invoice_amount ?? 0), 2);
        }

        return 0.0;
    }

    /**
     * @return  float|null  Sum when line_items tag quotation_id; null when no tags present.
     */
    protected function sumTaggedLineSubtotalsForQuotation(object $inv, int $quotationId): ?float
    {
        $raw = trim((string) ($inv->line_items ?? ''));
        if ($raw === '' || $raw === '[]') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!\is_array($decoded) || $decoded === []) {
            return null;
        }

        $hasTags = false;
        $sum     = 0.0;
        foreach ($decoded as $line) {
            if (!\is_array($line) || !isset($line['quotation_id'])) {
                continue;
            }
            $hasTags = true;
            if ((int) $line['quotation_id'] !== $quotationId) {
                continue;
            }
            if (isset($line['subtotal'])) {
                $sum += (float) $line['subtotal'];
            } elseif (isset($line['cantidad'], $line['precio_unitario'])) {
                $sum += round((float) $line['cantidad'] * (float) $line['precio_unitario'], 2);
            }
        }

        return $hasTags ? $sum : null;
    }

    public function hasInvoiceQuotationsTable(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $tables = $this->db->getTableList();
            $needle = $this->db->replacePrefix('#__ordenproduccion_invoice_quotations');
            $cached = \is_array($tables) && \in_array($needle, $tables, true);
        } catch (\Throwable $e) {
            $cached = false;
        }

        return $cached;
    }

    /**
     * All cotización ids linked to an invoice (primary quotation_id + junction rows).
     *
     * @return  int[]
     */
    public function getQuotationIdsLinkedToInvoice(int $invoiceId): array
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1) {
            return [];
        }

        $seen = [];
        $inv  = $this->loadInvoice($invoiceId);
        if ($inv) {
            $primary = (int) ($inv->quotation_id ?? 0);
            if ($primary > 0) {
                $seen[$primary] = true;
            }
        }

        if ($this->hasInvoiceQuotationsTable()) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('quotation_id'))
                    ->from($this->db->quoteName('#__ordenproduccion_invoice_quotations'))
                    ->where($this->db->quoteName('invoice_id') . ' = ' . $invoiceId)
            );
            foreach ($this->db->loadColumn() ?: [] as $qid) {
                $qid = (int) $qid;
                if ($qid > 0) {
                    $seen[$qid] = true;
                }
            }
        }

        return array_map('intval', array_keys($seen));
    }

    /**
     * @param   int[]  $quotationIds
     */
    public function linkInvoiceToQuotations(int $invoiceId, int $primaryQuotationId, array $quotationIds): void
    {
        $invoiceId = (int) $invoiceId;
        $primaryQuotationId = (int) $primaryQuotationId;
        if ($invoiceId < 1 || !$this->hasInvoiceQuotationsTable()) {
            return;
        }

        $seen = [];
        foreach ($quotationIds as $qid) {
            $qid = (int) $qid;
            if ($qid < 1 || isset($seen[$qid])) {
                continue;
            }
            $seen[$qid] = true;
            try {
                $this->db->setQuery(
                    'INSERT IGNORE INTO ' . $this->db->quoteName('#__ordenproduccion_invoice_quotations')
                    . ' (' . $this->db->quoteName('invoice_id') . ', ' . $this->db->quoteName('quotation_id') . ', ' . $this->db->quoteName('is_primary') . ')'
                    . ' VALUES (' . $invoiceId . ', ' . $qid . ', ' . ($qid === $primaryQuotationId ? 1 : 0) . ')'
                );
                $this->db->execute();
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * @param   int[]  $quotationIds
     */
    public function quotationsShareClientNit(array $quotationIds): bool
    {
        $quotationIds = array_values(array_unique(array_filter(array_map('intval', $quotationIds))));
        if ($quotationIds === []) {
            return false;
        }

        $digits = null;
        foreach ($quotationIds as $qid) {
            $q = $this->loadQuotation($qid);
            if (!$q) {
                return false;
            }
            $d = CertificadorFactNitLookupHelper::digitsOnlyBillingId((string) ($q->client_nit ?? ''));
            if ($d === '') {
                return false;
            }
            if ($digits === null) {
                $digits = $d;
            } elseif ($digits !== $d) {
                return false;
            }
        }

        return true;
    }

    public function getQuotationDisplayRef(object $quotation): string
    {
        $ref = trim((string) ($quotation->quotation_number ?? ''));
        if ($ref === '') {
            $ref = 'COT-' . str_pad((string) (int) ($quotation->id ?? 0), 5, '0', STR_PAD_LEFT);
        }

        return $ref;
    }

    /**
     * @return  list<array{descripcion:string, cantidad:float, precio_unitario:float, quotation_id:int}>
     */
    public function getManualFelLinePresetsForQuotation(int $quotationId): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return [];
        }

        $out = [];
        foreach ($this->loadQuotationLines($quotationId) as $row) {
            $t = $this->getLineTotalsForFelRow($row);
            $out[] = [
                'descripcion'       => (string) ($row->descripcion ?? ''),
                'cantidad'          => (float) $t['qty'],
                'precio_unitario'   => (float) $t['unit_price'],
                'quotation_id'      => $quotationId,
            ];
        }

        return $out;
    }

    /**
     * Parse manual issue date (Y-m-d). Defaults to today; rejects invalid or future dates.
     */
    public function resolveManualIssueDate(?string $dateYmd): ?\DateTimeImmutable
    {
        $dateYmd = trim((string) $dateYmd);
        if ($dateYmd === '') {
            $dateYmd = Factory::getDate('now', 'America/Guatemala')->format('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateYmd)) {
            return null;
        }

        try {
            $issued = new \DateTimeImmutable($dateYmd . ' 12:00:00', new \DateTimeZone('America/Guatemala'));
        } catch (\Throwable $e) {
            return null;
        }

        $today = Factory::getDate('now', 'America/Guatemala')->format('Y-m-d');
        if ($dateYmd > $today) {
            return null;
        }

        return $issued;
    }

    /**
     * SQL datetime for fel_fecha_emision from NUC Header.IssuedDateTime (manual issue date) or invoice row fallback.
     */
    protected function resolveFelFechaEmisionSqlFromNucPayload(array $nucPayload, ?object $invoice = null): string
    {
        $issuedRaw = '';
        if (isset($nucPayload['Header']) && \is_array($nucPayload['Header'])) {
            $issuedRaw = trim((string) ($nucPayload['Header']['IssuedDateTime'] ?? ''));
        }
        if ($issuedRaw !== '') {
            try {
                return Factory::getDate($issuedRaw)->toSql();
            } catch (\Throwable $e) {
            }
        }

        if ($invoice !== null) {
            $invDate = trim((string) ($invoice->invoice_date ?? ''));
            if ($invDate !== '') {
                try {
                    return Factory::getDate($invDate . (strpos($invDate, ':') === false ? ' 12:00:00' : ''))->toSql();
                } catch (\Throwable $e) {
                }
            }
        }

        return Factory::getDate()->toSql();
    }

    /**
     * Date-only invoice_date (Y-m-d) aligned with fel_fecha_emision / NUC IssuedDateTime.
     */
    protected function resolveInvoiceDateYmdFromNucPayload(array $nucPayload, ?object $invoice = null): string
    {
        $sql = $this->resolveFelFechaEmisionSqlFromNucPayload($nucPayload, $invoice);

        try {
            return Factory::getDate($sql)->format('Y-m-d');
        } catch (\Throwable $e) {
            return Factory::getDate()->format('Y-m-d');
        }
    }

    /**
     * Whether the cotización has at least one linked invoice with fel_issue_status completed.
     *
     * @since  3.119.107
     */
    public function hasCompletedInvoiceForQuotation(int $quotationId): bool
    {
        foreach ($this->getInvoicesByQuotationId($quotationId) as $inv) {
            if ((string) ($inv->fel_issue_status ?? '') === 'completed') {
                return true;
            }
        }

        return false;
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
        $otLabels       = $this->collectOrdenDisplayLabelsForQuotation($quotationId);

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => $otLabels !== [] ? implode(', ', $otLabels) : '',
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
        $newId = (int) $o->id;
        if ($newId > 0) {
            try {
                TelegramNotificationHelper::notifyInvoiceCreated($newId);
            } catch (\Throwable $e) {
            }
        }

        return $newId;
    }

    /**
     * Always insert a new pending invoice row for a cotización (manual additional FEL).
     *
     * @return  int  New invoice id or 0 on failure
     */
    public function createNewManualInvoiceFromQuotation(int $quotationId, int $userId, ?string $invoiceDateYmd = null, array $allQuotationIds = []): int
    {
        if (!$this->isEngineAvailable() || !$this->hasQuotationIdColumn() || $quotationId < 1) {
            return 0;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                ->where($this->db->quoteName('id') . ' = ' . $quotationId)
                ->where($this->db->quoteName('state') . ' = 1')
        );
        $quotation = $this->db->loadObject();
        if (!$quotation) {
            return 0;
        }

        $issuedAt = $this->resolveManualIssueDate($invoiceDateYmd);
        if ($issuedAt === null) {
            return 0;
        }

        $invoiceNumber = $this->generateNextInvoiceNumber();
        $now           = Factory::getDate()->toSql();
        $invoiceDate   = $issuedAt->format('Y-m-d');
        $quotationIds  = array_values(array_unique(array_filter(array_map('intval', $allQuotationIds))));
        if ($quotationIds === []) {
            $quotationIds = [$quotationId];
        }
        $otLabels = [];
        foreach ($quotationIds as $qid) {
            foreach ($this->collectOrdenDisplayLabelsForQuotation((int) $qid) as $label) {
                $otLabels[$label] = $label;
            }
        }
        $cotRefs = [$this->getQuotationDisplayRef($quotation)];
        foreach ($quotationIds as $qid) {
            if ((int) $qid === $quotationId) {
                continue;
            }
            $qExtra = $this->loadQuotation((int) $qid);
            if ($qExtra) {
                $cotRefs[] = $this->getQuotationDisplayRef($qExtra);
            }
        }
        $notes = \count($quotationIds) > 1
            ? 'FEL manual multi-cotización (' . implode(', ', $cotRefs) . ')'
            : 'FEL manual adicional (cotización)';

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => $otLabels !== [] ? implode(', ', array_values($otLabels)) : '',
            'client_name'        => $quotation->client_name ?? '',
            'client_nit'         => $quotation->client_nit ?? null,
            'sales_agent'        => $quotation->sales_agent ?? null,
            'request_date'       => !empty($quotation->quote_date) ? $quotation->quote_date : null,
            'delivery_date'      => null,
            'invoice_date'       => $invoiceDate,
            'invoice_amount'     => 0,
            'currency'           => $quotation->currency ?? 'Q',
            'work_description'   => null,
            'material'           => null,
            'dimensions'         => null,
            'print_color'        => null,
            'line_items'         => '[]',
            'quotation_file'     => null,
            'extraction_status'  => 'manual',
            'status'             => 'draft',
            'notes'              => $notes,
            'state'              => 1,
            'version'            => '3.119.68',
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

        $this->ensureMultipleInvoicesPerQuotationSchema();

        $o = (object) $filtered;
        try {
            $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');
        } catch (\Throwable $e) {
            if ($this->isDuplicateQuotationIdConstraintError($e) && $this->dropUniqueQuotationIdIndexIfPresent()) {
                $o = (object) $filtered;
                try {
                    $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');
                } catch (\Throwable $retryEx) {
                    $this->lastInvoiceInsertError = $retryEx->getMessage();

                    return 0;
                }
            } else {
                $this->lastInvoiceInsertError = $e->getMessage();

                return 0;
            }
        }

        $newId = (int) $o->id;
        if ($newId > 0) {
            try {
                TelegramNotificationHelper::notifyInvoiceCreated($newId);
            } catch (\Throwable $e) {
            }
        }

        return $newId;
    }

    /**
     * Brief Odoo partner snippet (credit limit + payment terms) for FEL invoice notes.
     *
     * @param   object  $quotation  Quotation row with optional client_id (Odoo res.partner).
     *
     * @return  string  Empty or "Límite crédito Odoo: … · …" style fragment
     */
    private function buildOdooFinanceNoteFragment(object $quotation): string
    {
        if (!isset($quotation->client_id)) {
            return '';
        }

        $partnerId = (int) $quotation->client_id;

        if ($partnerId < 1) {
            return '';
        }

        try {
            $helper = new OdooHelper();
            $info   = $helper->getPartnerSalesAccountingInfo($partnerId);
        } catch (\Throwable $e) {
            return '';
        }

        $parts = [];

        if ($info['credit_limit'] !== null && (float) $info['credit_limit'] >= 0) {
            $parts[] = 'Límite crédito Odoo: ' . number_format((float) $info['credit_limit'], 2, '.', '');
        }

        $ptn = isset($info['payment_term_name']) ? trim((string) $info['payment_term_name']) : '';

        if ($ptn !== '') {
            $tid = $info['payment_term_id'] !== null ? (int) $info['payment_term_id'] : 0;
            $parts[] = $tid > 0
                ? sprintf('Términos pago Odoo: %s (#%d)', $ptn, $tid)
                : ('Términos pago Odoo: ' . $ptn);
        }

        return $parts === [] ? '' : implode(' · ', $parts);
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

        $felNoteBase  = 'FEL scheduled queue (cotización, 08:00)';
        $odooNoteFrag = $this->buildOdooFinanceNoteFragment($quotation);
        $invoiceNotes = $odooNoteFrag !== '' ? $felNoteBase . ' | ' . $odooNoteFrag : $felNoteBase;

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
                'notes'            => $invoiceNotes,
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
            'notes'              => $invoiceNotes,
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
        $newId = (int) $o->id;
        if ($newId > 0) {
            try {
                TelegramNotificationHelper::notifyInvoiceCreated($newId);
            } catch (\Throwable $e) {
            }
        }

        return $newId;
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
     * Run FEL for a pending/processing invoice (queue step 2): Digifact NUC POST when URLs, bearer token, and modo/ambiente
     * are valid; otherwise the legacy FELplex mock (local XML/PDF).
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
                'fel_issue_status'   => 'pending',
                'fel_issue_error'    => null,
                'fel_response_json'  => null,
            ]);
            $inv = $this->loadInvoice($invoiceId);
            $status = (string) ($inv->fel_issue_status ?? 'pending');
        }

        if ($status === 'processing' && !empty($inv->fel_response_json)) {
            return ['success' => true, 'message' => 'Already completed', 'invoice_id' => $invoiceId];
        }

        if ($status === 'processing') {
            $block = $this->getFelCertificationBlockReason($inv);
            if ($block !== '') {
                return [
                    'success'              => false,
                    'message'              => $block,
                    'invoice_id'           => $invoiceId,
                    'duplicate_prevented'  => true,
                ];
            }
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

        $waitOt = $this->getPrecotOrdenWaitMessageIfBlocking($quotationId);
        if ($waitOt !== '') {
            return ['success' => false, 'message' => $waitOt, 'wait_precot_ot' => true];
        }

        try {
            if ($this->isDigifactIssuanceConfiguredForQueue()) {
                $built = $this->buildDigifactNucDirectPayloadForQuotation($quotationId);
                if (!$built['success']) {
                    throw new \RuntimeException((string) ($built['message'] ?? 'Digifact payload invalid'));
                }
                $actingUserId = $this->resolveFelIssuanceActorUserId($inv);
                if ($actingUserId < 1) {
                    throw new \RuntimeException('Missing user context for FEL issuance (invoice created_by)');
                }

                /** @var array<string, mixed> $nucPayload */
                $nucPayload = $built['payload'];

                return $this->executeDigifactCertificationForInvoice(
                    $invoiceId,
                    $quotationId,
                    $nucPayload,
                    $actingUserId
                );
            }

            $lock = $this->tryAcquireFelCertificationLock($invoiceId);
            if (!$lock['acquired']) {
                return [
                    'success'             => false,
                    'message'             => (string) $lock['message'],
                    'invoice_id'          => $invoiceId,
                    'duplicate_prevented' => true,
                ];
            }

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

            $otLabelsJoined = implode(', ', $this->collectOrdenDisplayLabelsForQuotation($quotationId));

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
                'orden_de_trabajo'       => $otLabelsJoined,
            ];

            $update = $this->filterToExistingColumns($update);
            $this->updateInvoiceFields($invoiceId, $update);

            $amb = $this->getActiveCertificadorModo();
            $this->appendCertificadorAmbienteToInvoiceFelExtra($invoiceId, ($amb === 'prod') ? 'prod' : 'test');

            $this->tryAutoLinkInvoiceOrdensForCotizacionFel($invoiceId, $quotationId);

            $actorId = (int) Factory::getUser()->id;
            if ($actorId < 1) {
                $actorId = (int) ($inv->created_by ?? 0);
            }
            $this->completeFacturacionManualApprovalsForInvoice($invoiceId, $actorId);

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
        if (!FpdfHelper::register()) {
            $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);

            return;
        }

        $enc = static function (string $s): string {
            return CotizacionPdfHelper::encodeTextForFpdf($s);
        };

        @\ob_start();
        try {
            // Same setup as CotizacionController cotización PDF (Letter mm, Arial, Latin-1 via helper).
            $pdf = new \FPDF('P', 'mm', [215.9, 279.4]);
            $pdf->AddPage();
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(true, 15);

            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, $enc('Factura electrónica (MOCK / pruebas)'), 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 6, $enc('No documento legal — emulador FELplex'), 0, 1);
            $pdf->Ln(2);
            $pdf->Cell(0, 6, $enc('Cliente: ' . ($quotation->client_name ?? '')), 0, 1);
            $pdf->Cell(0, 6, $enc('NIT: ' . ($quotation->client_nit ?? '')), 0, 1);
            $pdf->Cell(0, 6, $enc('Cotización: ' . ($quotation->quotation_number ?? '')), 0, 1);
            $sat = $response['sat'] ?? [];
            $pdf->Cell(0, 6, $enc('UUID: ' . ($response['uuid'] ?? '')), 0, 1);
            $pdf->Cell(0, 6, $enc('Autorización: ' . ($sat['authorization'] ?? '')), 0, 1);
            $pdf->Ln(4);

            $currency = (string) ($quotation->currency ?? 'Q');
            $colDesc   = 102;
            $colCant   = 16;
            $colUnit   = 28;
            $colSub    = 28;
            $lineH     = 6;

            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell($colCant, $lineH, $enc('Cant.'), 1, 0, 'L');
            $pdf->Cell($colDesc, $lineH, $enc('Descripción'), 1, 0, 'L');
            $pdf->Cell($colUnit, $lineH, $enc('P. unit.'), 1, 0, 'R');
            $pdf->Cell($colSub, $lineH, $enc('Subtotal'), 1, 1, 'R');
            $pdf->SetFont('Arial', '', 9);

            foreach ($lines as $row) {
                if (!\is_object($row)) {
                    continue;
                }
                $r    = $this->resolveQuotationLineTotals($row);
                $rawDesc = \trim((string) ($row->descripcion ?? ''));
                if (\function_exists('mb_substr')) {
                    $rawDesc = \mb_substr($rawDesc, 0, 4000, 'UTF-8');
                } else {
                    $rawDesc = \substr($rawDesc, 0, 4000);
                }
                $desc = $enc($rawDesc);
                $qty  = $r['qty'];
                $qtyStr = (\abs($qty - \round($qty)) < 0.0001)
                    ? (string) (int) \round($qty)
                    : \number_format($qty, 2, '.', '');
                $unitStr = $currency . ' ' . \number_format($r['unit_price'], 4);
                $subStr  = $currency . ' ' . \number_format($r['line_total'], 2);

                $rowX = $pdf->GetX();
                $rowY = $pdf->GetY();

                $pdf->SetXY($rowX + $colCant, $rowY);
                $pdf->MultiCell($colDesc, $lineH, $desc, 1, 'L');
                $newY = $pdf->GetY();
                $rowH = \max($lineH, $newY - $rowY);

                $pdf->SetXY($rowX, $rowY);
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell($colCant, $rowH, $qtyStr, 1, 0, 'C');

                $pdf->SetXY($rowX + $colCant + $colDesc, $rowY);
                $pdf->Cell($colUnit, $rowH, $unitStr, 1, 0, 'R');
                $pdf->Cell($colSub, $rowH, $subStr, 1, 1, 'R');
                $pdf->SetXY($rowX, $newY);
            }

            $total = \round((float) ($quotation->total_amount ?? 0), 2);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell($colCant + $colDesc + $colUnit, $lineH, $enc('Total:'), 1, 0, 'R');
            $pdf->Cell($colSub, $lineH, $currency . ' ' . \number_format($total, 2), 1, 1, 'R');

            $pdf->Output('F', $absPath);
        } catch (\Throwable $e) {
            @\ob_end_clean();
            $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);

            return;
        }
        @\ob_end_clean();

        $head = @\file_get_contents($absPath, false, null, 0, 5);
        $len  = (int) @\filesize($absPath);
        if ($head !== '%PDF-' || $len < 64) {
            $this->writeMinimalValidFelMockPdf($absPath, $quotation, $lines, $response);
        }
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
            $line = \preg_replace('/[\r\n\x00-\x08\x0B\x0C\x0E-\x1F]+/', ' ', \substr($l, 0, 220));
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
    /**
     * Whether {@see finalizeDigifactCertificationSuccessFromInterpret()} should skip auto OT linkage (manual FEL screen).
     */
    private bool $skipAutoLinkOrdensOnFinalize = false;

    /**
     * Public accessor for quotation/FEL line math (cotización manual invoice UI).
     *
     * @return  array{qty: float, line_total: float, unit_price: float}
     */
    public function getLineTotalsForFelRow(object $row): array
    {
        return $this->resolveQuotationLineTotals($row);
    }

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
     * Sum line totals using the same rules as invoice/FEL payloads ({@see resolveQuotationLineTotals}).
     *
     * @param   list<object|\stdClass>  $lineRows  Rows from #__ordenproduccion_quotation_items
     *
     * @return float Rounded to 2 decimals
     *
     * @since   3.119.34
     */
    public function sumQuotationLinesTotals(array $lineRows): float
    {
        $s = 0.0;
        foreach ($lineRows as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $s += $this->resolveQuotationLineTotals($row)['line_total'];
        }

        return \round($s, 2);
    }

    /**
     * Columns to persist when editing cantidad/descripcion before Digifact timbrado.
     * Preserves valor_unitario when set; recomputes subtotal (and caller may mirror into valor_final).
     *
     * @param   object|\stdClass  $lineRow  Loaded quotation item row (before applying edits)
     *
     * @return  array<string, float|string>
     *
     * @since   3.119.34
     */
    public function computeUpdatedLineColumnsForFelEdit(object $lineRow, float $newQty, string $newDescTrimmed): array
    {
        $qty = \round($newQty, 4);
        $vu  = isset($lineRow->valor_unitario) ? (float) $lineRow->valor_unitario : 0.0;
        if ($vu <= 0.00001) {
            $meta = $this->resolveQuotationLineTotals($lineRow);
            $vu   = (float) $meta['unit_price'];
        }
        if ($vu <= 0.00001) {
            $vu = 0.0001;
        }

        $subtotal = \round($vu * $qty, 2);

        return [
            'cantidad'       => $qty,
            'descripcion'    => $newDescTrimmed,
            'valor_unitario' => \round($vu, 4),
            'subtotal'       => $subtotal,
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

    /**
     * User-facing reason not to certify again (empty = OK to attempt).
     *
     * @since  3.119.202
     */
    protected function getFelCertificationBlockReason(?object $inv): string
    {
        if (!$inv) {
            return '';
        }

        if ((string) ($inv->fel_issue_status ?? '') === 'completed') {
            return Text::_('COM_ORDENPRODUCCION_FEL_CERTIFY_ALREADY_COMPLETED');
        }

        if (trim((string) ($inv->fel_autorizacion_uuid ?? '')) !== '') {
            return Text::_('COM_ORDENPRODUCCION_FEL_CERTIFY_ALREADY_COMPLETED');
        }

        if ((string) ($inv->fel_issue_status ?? '') === 'processing' && !$this->isStaleFelProcessing($inv)) {
            return Text::_('COM_ORDENPRODUCCION_FEL_CERTIFY_ALREADY_IN_PROGRESS');
        }

        return '';
    }

    /**
     * @since  3.119.202
     */
    protected function isStaleFelProcessing(object $inv): bool
    {
        $modified = trim((string) ($inv->modified ?? ''));
        if ($modified === '') {
            return true;
        }

        try {
            $ageSeconds = Factory::getDate()->toUnix() - Factory::getDate($modified)->toUnix();
        } catch (\Throwable $e) {
            return true;
        }

        return $ageSeconds >= (self::FEL_CERTIFY_STALE_MINUTES * 60);
    }

    /**
     * Atomically claim an invoice for Digifact certification (prevents duplicate POST certify_nuc).
     *
     * @return  array{acquired:bool, message:string, invoice_id:int}
     *
     * @since  3.119.202
     */
    protected function tryAcquireFelCertificationLock(int $invoiceId): array
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1 || !$this->isEngineAvailable()) {
            return ['acquired' => false, 'message' => 'Invalid invoice', 'invoice_id' => $invoiceId];
        }

        $inv = $this->loadInvoice($invoiceId);
        if (!$inv || (int) ($inv->state ?? 0) !== 1) {
            return ['acquired' => false, 'message' => Text::_('COM_ORDENPRODUCCION_FEL_ISSUE_INVOICE_INVALID'), 'invoice_id' => $invoiceId];
        }

        $block = $this->getFelCertificationBlockReason($inv);
        if ($block !== '') {
            return ['acquired' => false, 'message' => $block, 'invoice_id' => $invoiceId];
        }

        $db = $this->db;
        $now = Factory::getDate()->toSql();
        $staleCutoff = Factory::getDate('now - ' . self::FEL_CERTIFY_STALE_MINUTES . ' minutes')->toSql();
        $allowed = ['none', 'pending', 'scheduled', 'failed'];
        $quotedAllowed = implode(',', array_map([$db, 'quote'], $allowed));

        $statusCol = $db->quoteName('fel_issue_status');
        $uuidCol   = $db->quoteName('fel_autorizacion_uuid');
        $modifiedCol = $db->quoteName('modified');

        $whereAllowed = $statusCol . ' IN (' . $quotedAllowed . ')';
        $whereStale = '(' . $statusCol . ' = ' . $db->quote('processing')
            . ' AND ' . $modifiedCol . ' < ' . $db->quote($staleCutoff)
            . ' AND (' . $uuidCol . ' IS NULL OR ' . $uuidCol . ' = ' . $db->quote('') . '))';

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_invoices'))
            ->set($statusCol . ' = ' . $db->quote('processing'))
            ->set($modifiedCol . ' = ' . $db->quote($now))
            ->where($db->quoteName('id') . ' = ' . $invoiceId)
            ->where($db->quoteName('state') . ' = 1')
            ->where('(' . $whereAllowed . ' OR ' . $whereStale . ')');

        $db->setQuery($query);
        $db->execute();

        if ((int) $db->getAffectedRows() > 0) {
            return ['acquired' => true, 'message' => '', 'invoice_id' => $invoiceId];
        }

        $inv = $this->loadInvoice($invoiceId);
        $block = $this->getFelCertificationBlockReason($inv);

        return [
            'acquired'   => false,
            'message'    => $block !== '' ? $block : Text::_('COM_ORDENPRODUCCION_FEL_CERTIFY_ALREADY_IN_PROGRESS'),
            'invoice_id' => $invoiceId,
        ];
    }

    /**
     * Remove invoice from FEL processing queue (scheduled / pending / processing).
     *
     * @since  3.119.16
     */
    public function cancelQueuedFelIssue(int $invoiceId): bool
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1) {
            return false;
        }
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select([
                    $this->db->quoteName('fel_issue_status'),
                    $this->db->quoteName('state'),
                ])
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('id') . ' = ' . $invoiceId)
        );
        $row = $this->db->loadObject();
        if (!$row || (int) ($row->state ?? 0) !== 1) {
            return false;
        }
        $st = (string) ($row->fel_issue_status ?? 'none');
        if (!\in_array($st, ['scheduled', 'pending', 'processing'], true)) {
            return false;
        }
        $fields = [
            'fel_issue_status' => 'none',
            'fel_issue_error'  => null,
            'modified'         => Factory::getDate()->toSql(),
        ];
        if ($this->hasColumn('fel_scheduled_at')) {
            $fields['fel_scheduled_at'] = null;
        }
        $this->updateInvoiceFields($invoiceId, $this->filterToExistingColumns($fields));

        return true;
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
     * Merge Digifact SAT Certificacion fields into invoice fel_extra (Serie, Número DTE, certificador block).
     *
     * @param  array<string, mixed>  $certificacionMeta  From interpretDigifactCertificarResponse certificacion_meta
     *
     * @since  3.118.52
     */
    protected function mergeInvoiceFelExtraWithCertificacionMeta(int $invoiceId, array $certificacionMeta): ?string
    {
        if ($invoiceId < 1 || !$this->hasColumn('fel_extra')) {
            return null;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('fel_extra'))
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('id') . ' = ' . $invoiceId)
        );
        $prev = $this->db->loadResult();
        $felExtra = [];
        if (\is_string($prev) && $prev !== '') {
            $decoded = json_decode($prev, true);
            if (\is_array($decoded)) {
                $felExtra = $decoded;
            }
        }

        $serie  = trim((string) ($certificacionMeta['autorizacion_serie'] ?? ''));
        $numDte = trim((string) ($certificacionMeta['autorizacion_numero_dte'] ?? ''));
        $touched = false;
        if ($serie !== '') {
            $felExtra['autorizacion_serie'] = $serie;
            $touched = true;
        }
        if ($numDte !== '') {
            $felExtra['autorizacion_numero_dte'] = $numDte;
            $touched = true;
        }

        $certIn = $certificacionMeta['certificacion'] ?? [];
        if (\is_array($certIn)) {
            $certFiltered = array_filter([
                'nit_certificador'         => trim((string) ($certIn['nit_certificador'] ?? '')),
                'nombre_certificador'      => trim((string) ($certIn['nombre_certificador'] ?? '')),
                'fecha_hora_certificacion' => trim((string) ($certIn['fecha_hora_certificacion'] ?? '')),
            ]);
            if ($certFiltered !== []) {
                $prevCert = \is_array($felExtra['certificacion'] ?? null) ? $felExtra['certificacion'] : [];
                $felExtra['certificacion'] = array_merge($prevCert, $certFiltered);
                $touched = true;
            }
        }

        if (!$touched && ($prev === null || trim((string) $prev) === '')) {
            return null;
        }

        $json = json_encode($felExtra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json !== false ? $json : null;
    }

    /**
     * Sets fel_extra.certificador_ambiente (test|prod) for Facturas list / export.
     *
     * @param   string|null  $felExtraJson  Existing JSON or null
     */
    protected function injectCertificadorAmbienteIntoFelExtraJson(?string $felExtraJson, string $modo): string
    {
        $modo     = ($modo === 'prod') ? 'prod' : 'test';
        $felExtra = [];
        if ($felExtraJson !== null && $felExtraJson !== '') {
            $d = json_decode($felExtraJson, true);
            if (\is_array($d)) {
                $felExtra = $d;
            }
        }
        $felExtra['certificador_ambiente'] = $modo;
        $json = json_encode($felExtra, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return $json !== false ? $json : '{"certificador_ambiente":"' . $modo . '"}';
    }

    /**
     * Merge certificador ambiente into stored fel_extra (mock engine path).
     */
    protected function appendCertificadorAmbienteToInvoiceFelExtra(int $invoiceId, string $modo): void
    {
        if ($invoiceId < 1 || !$this->hasColumn('fel_extra')) {
            return;
        }
        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('fel_extra'))
                ->from($this->db->quoteName('#__ordenproduccion_invoices'))
                ->where($this->db->quoteName('id') . ' = ' . $invoiceId)
        );
        $prev = $this->db->loadResult();
        $json = $this->injectCertificadorAmbienteIntoFelExtraJson(\is_string($prev) ? $prev : null, $modo);
        $this->updateInvoiceFields($invoiceId, ['fel_extra' => $json]);
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

    /**
     * TAXID in Digifact query string: up to 12 digits, zero-padded (see {@see CertificadorFactNitLookupHelper}).
     */
    public static function padIssuerTaxIdForDigifactUrl(string $nit): string
    {
        $d = preg_replace('/\D/', '', $nit) ?? '';
        if ($d === '') {
            return '';
        }
        if (\strlen($d) >= 12) {
            return $d;
        }

        return str_pad($d, 12, '0', STR_PAD_LEFT);
    }

    /**
     * NUC JSON body for Digifact transform (GT FACT): Buyer + Items from cotización; Seller name/email from site;
     * Seller.BranchInfo from certificador branch_* keys (active modo) with legacy defaults when empty.
     * AdditionalDocumentInfo: compact AdditionalInfo entry with @Name Cotizacion and #text = trimmed quotation_number, or COT-{id} if blank (Xml-to-JSON style keys for Digifact NUC). Work order numbers are not sent in NUC metadata.
     * Line amounts are IVA-inclusive; TaxableAmount = lineTotal/1.12, IVA Amount = lineTotal − TaxableAmount (12%).
     * **Consumidor final (CF / C/F):** `Buyer.TaxID` is **CF** unless `$nucBuyerTaxIdOverride` provides validated CUI digits; then `TaxID` is those digits and **`Buyer.TaxIDType` must be `CUI`** (Digifact FACT CUI / NUC; see Digifact docs). Omitting `TaxIDType` makes SAT validate the id as NIT → FEL_RCP309.
     *
     * @param   list<object>  $lines  From {@see loadQuotationLines()}
     * @param   string|null   $nucBuyerTaxIdOverride  When billing is CF/C/F: optional digits-only CUI for `Buyer.TaxID`; adds `Buyer.TaxIDType` = `CUI` (FACT CUI).
     * @param   string|null   $buyerNameOverride        Optional Buyer.Name (invoice receptor nombre).
     *
     * @return  array<string, mixed>
     *
     * @since   3.118.27
     */
    public function buildDigifactNucJsonPayload(
        object $quotation,
        array $lines,
        array $creds,
        ?string $nucBuyerTaxIdOverride = null,
        ?string $buyerNameOverride = null,
        ?\DateTimeInterface $issuedAt = null,
        array $additionalCotizacionRefs = [],
        array $nucOptions = []
    ): array {
        $buyerTaxIdRaw = trim((string) ($quotation->client_nit ?? ''));
        $isCfBuyer     = CertificadorFactNitLookupHelper::billingIdIndicatesConsumidorFinal($buyerTaxIdRaw);
        $overrideDigits = CertificadorFactNitLookupHelper::digitsOnlyBillingId(trim((string) ($nucBuyerTaxIdOverride ?? '')));

        $addrParts = $this->splitAddress((string) ($quotation->client_address ?? ''));
        $buyerStreet = trim((string) ($quotation->client_address ?? '')) !== ''
            ? trim((string) $quotation->client_address) : $addrParts['street'];
        $buyerNit = $buyerTaxIdRaw;
        if ($isCfBuyer && $overrideDigits !== '') {
            $buyerNit = $overrideDigits;
        } elseif ($isCfBuyer) {
            $buyerNit = 'CF';
        } elseif ($buyerNit !== '') {
            $buyerNit = CertificadorFactNitLookupHelper::normalizeNitForDigifactNuc($buyerNit);
        }
        $buyerName = trim((string) ($buyerNameOverride ?? ''));
        if ($buyerName === '') {
            $buyerName = trim((string) ($quotation->client_name ?? ''));
        }
        if ($buyerName === '') {
            $buyerName = 'Cliente';
        }

        $issued = $issuedAt instanceof \DateTimeInterface
            ? $issuedAt->format('c')
            : Factory::getDate('now')->format('c');

        $items   = [];
        $grand   = 0.0;
        $totalIva = 0.0;
        $lineNum = 1;
        foreach ($lines as $row) {
            if (!\is_object($row)) {
                continue;
            }
            $r         = $this->resolveQuotationLineTotals($row);
            $lineTotal = round((float) $r['line_total'], 2);
            if ($lineTotal <= 0.000001) {
                continue;
            }
            $grand += $lineTotal;
            $taxable = $lineTotal / (1 + self::IVA_RATE);
            $ivaLine = $lineTotal - $taxable;
            $totalIva += $ivaLine;

            $desc = isset($row->descripcion) ? trim((string) $row->descripcion) : '';
            if ($desc === '') {
                $desc = 'Item';
            }

            $items[] = [
                'Number'         => (string) $lineNum,
                'Codes'          => null,
                'Type'           => 'Bien',
                'Description'    => $desc,
                'Qty'            => sprintf('%.6f', $r['qty']),
                'UnitOfMeasure'  => 'UNI',
                'Price'          => sprintf('%.6f', $r['unit_price']),
                'Discounts'      => null,
                'Taxes'          => [
                    'Tax' => [
                        [
                            'Code'          => '1',
                            'Description'   => 'IVA',
                            'TaxableAmount' => sprintf('%.6f', $taxable),
                            'Amount'        => sprintf('%.6f', $ivaLine),
                        ],
                    ],
                ],
                'Totals' => [
                    'TotalItem' => sprintf('%.6f', $lineTotal),
                ],
            ];
            $lineNum++;
        }

        if ($items === []) {
            $t = round((float) ($quotation->total_amount ?? 0), 2);
            if ($t > 0) {
                $taxable = $t / (1 + self::IVA_RATE);
                $ivaLine = $t - $taxable;
                $grand    = $t;
                $totalIva = $ivaLine;
                $items[] = [
                    'Number'        => '1',
                    'Codes'         => null,
                    'Type'          => 'Bien',
                    'Description'   => (string) ($quotation->quotation_number ?? 'Cotizacion'),
                    'Qty'            => '1.000000',
                    'UnitOfMeasure' => 'UNI',
                    'Price'          => sprintf('%.6f', $t),
                    'Discounts'      => null,
                    'Taxes'          => [
                        'Tax' => [
                            [
                                'Code'          => '1',
                                'Description'   => 'IVA',
                                'TaxableAmount' => sprintf('%.6f', $taxable),
                                'Amount'        => sprintf('%.6f', $ivaLine),
                            ],
                        ],
                    ],
                    'Totals' => ['TotalItem' => sprintf('%.6f', $t)],
                ];
            }
        }

        $app  = Factory::getApplication();
        $site = trim((string) $app->get('sitename')) ?: 'Emisor';
        $mail = trim((string) $app->get('mailfrom')) ?: 'noreply@localhost';
        $nitSellerDigits = $this->digitsOnly($creds['nit'] ?? '');
        $nitSellerJson   = $nitSellerDigits !== '' ? ltrim($nitSellerDigits, '0') ?: $nitSellerDigits : '000000000';

        $branchCode = trim((string) ($creds['branch_code'] ?? ''));
        if ($branchCode === '') {
            $branchCode = '1';
        }
        $branchName = trim((string) ($creds['branch_name'] ?? ''));
        if ($branchName === '') {
            $branchName = $site;
        }
        $branchAddr = trim((string) ($creds['branch_address'] ?? ''));
        if ($branchAddr === '') {
            $branchAddr = 'Ciudad';
        }
        $branchCity = trim((string) ($creds['branch_city'] ?? ''));
        if ($branchCity === '') {
            $branchCity = '01001';
        }
        $branchDistrict = trim((string) ($creds['branch_district'] ?? ''));
        if ($branchDistrict === '') {
            $branchDistrict = 'Guatemala';
        }
        $branchState = trim((string) ($creds['branch_state'] ?? ''));
        if ($branchState === '') {
            $branchState = 'Guatemala';
        }
        $branchCountry = strtoupper(trim((string) ($creds['branch_country'] ?? '')));
        if ($branchCountry === '') {
            $branchCountry = 'GT';
        }

        $cotRef = trim((string) ($quotation->quotation_number ?? ''));
        if ($cotRef === '') {
            $cotRef = 'COT-' . (int) ($quotation->id ?? 0);
        }

        $additionalInfos = [
            [
                '@Name' => 'Cotizacion',
                '#text' => $cotRef,
            ],
        ];
        foreach ($additionalCotizacionRefs as $extraRef) {
            $extraRef = trim((string) $extraRef);
            if ($extraRef === '' || $extraRef === $cotRef) {
                continue;
            }
            $additionalInfos[] = [
                '@Name' => 'Cotizacion',
                '#text' => $extraRef,
            ];
        }

        $docType      = 'FACT';
        $currency     = 'GTQ';
        $exchangeRate = null;
        if ($nucOptions !== []) {
            $normalizedOpts = $this->normalizeManualFelNucOptions($nucOptions, $grand);
            $docType        = (string) $normalizedOpts['doc_type'];
            $currency       = (string) ($normalizedOpts['currency'] ?? 'GTQ');
            if ($currency === 'USD') {
                $exchangeRate = (float) ($normalizedOpts['exchange_rate'] ?? 0);
                if ($exchangeRate <= 0.000001) {
                    $exchangeRate = null;
                }
            }
            $this->appendNucAdditionalDocumentBlocks(
                $additionalInfos,
                $cotRef,
                (int) ($quotation->id ?? 0),
                $normalizedOpts,
                (int) ($nucOptions['source_invoice_id'] ?? 0)
            );
        }

        $header = [
            'DocType'        => $docType,
            'IssuedDateTime' => $issued,
            'Currency'       => $currency,
        ];
        if ($currency === 'USD' && $exchangeRate !== null) {
            $header['ExchangeRate'] = round((float) $exchangeRate, 6);
        }

        $buyerPayload = [
            'TaxID'       => $buyerNit !== '' ? $buyerNit : 'CF',
            'Name'        => $buyerName,
            'AddressInfo' => [
                'Address'  => $buyerStreet,
                'City'     => '01010',
                'District' => 'GUATEMALA',
                'State'    => 'GUATEMALA',
                'Country'  => 'GT',
            ],
        ];
        // FACT CUI (Digifact NUC): CUI in TaxID must be paired with TaxIDType or SAT treats it as NIT (FEL_RCP309).
        if ($isCfBuyer && $overrideDigits !== '') {
            $buyerPayload['TaxIDType'] = 'CUI';
        }

        return [
            'Version'     => '1.00',
            'CountryCode' => 'GT',
            'Header'      => $header,
            'Seller' => [
                'TaxID'               => $nitSellerJson,
                'TaxIDAdditionalInfo' => [
                    ['Name' => 'AfiliacionIVA', 'Data' => null, 'Value' => 'GEN'],
                ],
                'Name'    => $site,
                'Contact' => [
                    'EmailList' => ['Email' => [$mail]],
                ],
                'AdditionlInfo' => [
                    ['Name' => 'TipoFrase', 'Data' => '1', 'Value' => '1'],
                    ['Name' => 'Escenario', 'Data' => '1', 'Value' => '2'],
                ],
                'BranchInfo' => [
                    'Code' => $branchCode,
                    'Name' => $branchName,
                    'AddressInfo' => [
                        'Address'  => $branchAddr,
                        'City'     => $branchCity,
                        'District' => $branchDistrict,
                        'State'    => $branchState,
                        'Country'  => $branchCountry,
                    ],
                ],
            ],
            'Buyer'        => $buyerPayload,
            'ThirdParties' => null,
            'Items'        => $items,
            'Totals'       => [
                'TotalTaxes' => [
                    'TotalTax' => [
                        [
                            'Description' => 'IVA',
                            'Amount'      => sprintf('%.6f', $totalIva),
                        ],
                    ],
                ],
                'GrandTotal' => [
                    'InvoiceTotal' => sprintf('%.6f', $grand),
                ],
            ],
            'AdditionalDocumentInfo' => [
                'AdditionalInfo' => $additionalInfos,
            ],
        ];
    }

    /**
     * Build Digifact NUC from manually edited buyer/lines (cotización «Factura manual»).
     *
     * @param   list<array{descripcion:string, cantidad:float, precio_unitario:float}>  $manualLines
     *
     * @return  array<string, mixed>
     */
    public function buildDigifactNucJsonPayloadFromManualInput(
        object $quotation,
        array $manualLines,
        array $creds,
        string $buyerName,
        string $buyerNitRaw,
        string $buyerAddress,
        ?string $nucBuyerTaxIdOverride = null,
        ?\DateTimeInterface $issuedAt = null,
        array $additionalCotizacionRefs = [],
        array $nucOptions = []
    ): array {
        $lineRows = [];
        foreach ($manualLines as $line) {
            if (!\is_array($line)) {
                continue;
            }
            $desc = trim((string) ($line['descripcion'] ?? ''));
            $qty  = (float) ($line['cantidad'] ?? 0);
            $unit = (float) ($line['precio_unitario'] ?? 0);
            if ($desc === '' || $qty < 0.000001 || $unit < 0) {
                continue;
            }
            $row = new \stdClass();
            $row->descripcion     = $desc;
            $row->cantidad        = $qty;
            $row->valor_unitario  = $unit;
            $row->subtotal        = round($qty * $unit, 2);
            $lineRows[]           = $row;
        }

        $payload = $this->buildDigifactNucJsonPayload(
            $quotation,
            $lineRows,
            $creds,
            $nucBuyerTaxIdOverride,
            $buyerName !== '' ? $buyerName : null,
            $issuedAt,
            $additionalCotizacionRefs,
            $nucOptions
        );

        $addr = trim($buyerAddress);
        if ($addr === '') {
            $addr = 'Ciudad';
        }
        if (isset($payload['Buyer']) && \is_array($payload['Buyer'])) {
            $payload['Buyer']['TaxID'] = $this->resolveManualBuyerTaxIdForNuc($buyerNitRaw, $nucBuyerTaxIdOverride);
            if ($buyerName !== '') {
                $payload['Buyer']['Name'] = $buyerName;
            }
            $payload['Buyer']['AddressInfo'] = [
                'Address'  => $addr,
                'City'     => '01010',
                'District' => 'GUATEMALA',
                'State'    => 'GUATEMALA',
                'Country'  => 'GT',
            ];
            $isCf = CertificadorFactNitLookupHelper::billingIdIndicatesConsumidorFinal($buyerNitRaw);
            $cui  = CertificadorFactNitLookupHelper::digitsOnlyBillingId(trim((string) ($nucBuyerTaxIdOverride ?? '')));
            if ($isCf && $cui !== '') {
                $payload['Buyer']['TaxIDType'] = 'CUI';
            } elseif (isset($payload['Buyer']['TaxIDType'])) {
                unset($payload['Buyer']['TaxIDType']);
            }
        }

        return $payload;
    }

    /**
     * @param   list<array{descripcion:string, cantidad:float, precio_unitario:float}>  $manualLines
     * @param   int[]                                                                      $ordenIdsToLink
     *
     * @return  array{success:bool, message:string, invoice_id?:int, uuid?:string}
     */
    public function issueDigifactNucManualFromQuotation(
        int $quotationId,
        int $userId,
        array $manualLines,
        string $buyerName,
        string $buyerNit,
        string $buyerAddress,
        array $ordenIdsToLink = [],
        ?string $nucBuyerTaxIdOverride = null,
        array $additionalQuotationIds = [],
        ?string $issueDateYmd = null,
        array $nucOptions = []
    ): array {
        $quotationId = (int) $quotationId;
        $userId      = (int) $userId;
        if ($quotationId < 1 || $userId < 1 || !$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $issuedAt = $this->resolveManualIssueDate($issueDateYmd);
        if ($issuedAt === null) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_ISSUE_DATE_INVALID')];
        }

        $nucOptionsResolved = $this->resolveManualFelNucOptionsForIssuance($nucOptions, $issuedAt);
        if (!empty($nucOptionsResolved['_error'])) {
            return ['success' => false, 'message' => (string) $nucOptionsResolved['_error']];
        }
        $nucOptions = $nucOptionsResolved;

        $allQuotationIds = array_values(array_unique(array_filter(array_merge(
            [$quotationId],
            array_map('intval', $additionalQuotationIds)
        ))));
        if (!$this->quotationsShareClientNit($allQuotationIds)) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_QUOTATIONS_SAME_CLIENT')];
        }

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $additionalRefs = [];
        foreach ($allQuotationIds as $qid) {
            if ((int) $qid === $quotationId) {
                continue;
            }
            $qExtra = $this->loadQuotation((int) $qid);
            if ($qExtra) {
                $additionalRefs[] = $this->getQuotationDisplayRef($qExtra);
            }
        }

        $creds = $this->getActiveCertificadorCredentials();
        if ($this->buildDigifactCertificarRequestUrl($creds) === '') {
            return ['success' => false, 'message' => 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).'];
        }
        if ($this->resolveBearerJwtForDigifactPost() === '') {
            return ['success' => false, 'message' => 'Digifact bearer token missing or expired (renew in Ajustes → Certificador).'];
        }

        $buyerName = trim($buyerName);
        $buyerNit  = trim($buyerNit);
        if ($buyerName === '' || $buyerNit === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_REQUIRED')];
        }

        $payload = $this->buildDigifactNucJsonPayloadFromManualInput(
            $quotation,
            $manualLines,
            $creds,
            $buyerName,
            $buyerNit,
            $buyerAddress,
            $nucBuyerTaxIdOverride,
            $issuedAt,
            $additionalRefs,
            $nucOptions
        );
        if (($payload['Items'] ?? []) === []) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_LINES_REQUIRED')];
        }

        $grand = 0.0;
        if (isset($payload['Totals']['GrandTotal']['InvoiceTotal'])) {
            $grand = (float) $payload['Totals']['GrandTotal']['InvoiceTotal'];
        }

        $normalizedOpts = $this->normalizeManualFelNucOptions($nucOptions, $grand);
        if ($normalizedOpts['doc_type'] === 'FCAM') {
            $fcamErr = $this->validateManualFelFcamAbonos($normalizedOpts['fcam_abonos'], $grand);
            if ($fcamErr !== null) {
                return ['success' => false, 'message' => $fcamErr];
            }
        }

        $invoiceId = $this->createNewManualInvoiceFromQuotation($quotationId, $userId, $issuedAt->format('Y-m-d'), $allQuotationIds);
        if ($invoiceId < 1) {
            $message = $this->hasUniqueQuotationIdIndex()
                ? Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_MIGRATION_REQUIRED')
                : $this->formatManualInvoiceCreateFailureMessage();

            return ['success' => false, 'message' => $message];
        }

        $storageLines = [];
        foreach ($manualLines as $line) {
            if (!\is_array($line)) {
                continue;
            }
            $qty  = (float) ($line['cantidad'] ?? 0);
            $unit = (float) ($line['precio_unitario'] ?? 0);
            $desc = trim((string) ($line['descripcion'] ?? ''));
            if ($desc === '' || $qty < 0.000001) {
                continue;
            }
            $sub = round($qty * $unit, 2);
            $storageLines[] = [
                'cantidad'          => $qty,
                'descripcion'       => $desc,
                'precio_unitario'   => $unit,
                'subtotal'          => $sub,
                'quotation_id'      => (int) ($line['quotation_id'] ?? $quotationId),
            ];
        }

        $felExtraPre = [
            'pdf_observaciones' => trim((string) $normalizedOpts['observaciones']),
        ];
        if ($normalizedOpts['doc_type'] === 'FCAM') {
            $felExtraPre['complemento_abonos'] = $normalizedOpts['fcam_abonos'];
        }
        if (($normalizedOpts['currency'] ?? 'GTQ') === 'USD' && isset($payload['Header']['ExchangeRate'])) {
            $felExtraPre['exchange_rate'] = (float) $payload['Header']['ExchangeRate'];
        }

        $invoiceCurrency = $this->nucCurrencyToInvoiceCurrency((string) ($payload['Header']['Currency'] ?? 'GTQ'));

        $this->updateInvoiceFields($invoiceId, [
            'client_name'    => $buyerName,
            'client_nit'     => $buyerNit,
            'invoice_amount' => $grand,
            'currency'       => $invoiceCurrency,
            'line_items'     => json_encode($storageLines, JSON_UNESCAPED_UNICODE),
            'notes'          => \count($allQuotationIds) > 1
                ? 'FEL manual multi-cotización (' . implode(', ', array_merge([$this->getQuotationDisplayRef($quotation)], $additionalRefs)) . ')'
                : 'FEL manual desde cotización',
            'fel_extra'      => json_encode($felExtraPre, JSON_UNESCAPED_UNICODE),
            'fel_tipo_dte'   => (string) $normalizedOpts['doc_type'],
        ]);

        $this->linkInvoiceToQuotations($invoiceId, $quotationId, $allQuotationIds);

        $this->skipAutoLinkOrdensOnFinalize = true;
        $result = $this->executeDigifactCertificationForInvoice($invoiceId, $quotationId, $payload, $userId);
        $this->skipAutoLinkOrdensOnFinalize = false;

        if (!empty($result['success'])) {
            $this->linkInvoiceToSelectedOrdens($invoiceId, $ordenIdsToLink);
        }

        return $result;
    }

    /**
     * @param   int[]  $ordenIds
     */
    protected function linkInvoiceToSelectedOrdens(int $invoiceId, array $ordenIds): void
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1 || $ordenIds === []) {
            return;
        }
        try {
            $match = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            if (!$match || !\is_callable([$match, 'addManualInvoiceOrdenAssociation']) || !$match->isTableAvailable()) {
                return;
            }
            $seen = [];
            foreach ($ordenIds as $oid) {
                $oid = (int) $oid;
                if ($oid < 1 || isset($seen[$oid])) {
                    continue;
                }
                $seen[$oid] = true;
                try {
                    $match->addManualInvoiceOrdenAssociation(
                        $invoiceId,
                        $oid,
                        false,
                        ['manual', 'cotizacion_fel_manual']
                    );
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
    }

    protected function resolveManualBuyerTaxIdForNuc(string $buyerNitRaw, ?string $nucBuyerTaxIdOverride): string
    {
        $isCf = CertificadorFactNitLookupHelper::billingIdIndicatesConsumidorFinal($buyerNitRaw);
        $cui  = CertificadorFactNitLookupHelper::digitsOnlyBillingId(trim((string) ($nucBuyerTaxIdOverride ?? '')));
        if ($isCf && $cui !== '') {
            return $cui;
        }
        if ($isCf) {
            return 'CF';
        }
        $normalized = CertificadorFactNitLookupHelper::normalizeNitForDigifactNuc($buyerNitRaw);

        return $normalized !== '' ? $normalized : trim($buyerNitRaw);
    }

    /**
     * Normalize manual FEL NUC options (document type, observaciones, FCAM abonos).
     *
     * @param   array<string, mixed>  $nucOptions
     *
     * @return  array{doc_type: string, observaciones: string, fcam_abonos: list<array{numero: int, fecha: string, monto: float}>, currency: string, exchange_rate: ?float}
     *
     * @since   3.119.169
     */
    protected function normalizeManualFelNucOptions(array $nucOptions, float $grandTotal = 0.0): array
    {
        $docType = strtoupper(trim((string) ($nucOptions['doc_type'] ?? 'FACT')));
        if (!\in_array($docType, ['FACT', 'FCAM'], true)) {
            $docType = 'FACT';
        }

        $currency = strtoupper(trim((string) ($nucOptions['currency'] ?? 'GTQ')));
        if (!\in_array($currency, ['GTQ', 'USD'], true)) {
            $currency = 'GTQ';
        }

        $exchangeRate = null;
        if ($currency === 'USD' && isset($nucOptions['exchange_rate'])) {
            $rate = (float) $nucOptions['exchange_rate'];
            if ($rate > 0.000001) {
                $exchangeRate = $rate;
            }
        }

        $obs    = trim((string) ($nucOptions['observaciones'] ?? ''));
        $abonos = [];
        if ($docType === 'FCAM') {
            $raw = $nucOptions['fcam_abonos'] ?? [];
            if (\is_array($raw)) {
                foreach ($raw as $i => $ab) {
                    if (!\is_array($ab)) {
                        continue;
                    }
                    $fecha = trim((string) ($ab['fecha'] ?? $ab['fecha_vencimiento'] ?? ''));
                    $monto = (float) ($ab['monto'] ?? $ab['monto_abono'] ?? 0);
                    $num   = (int) ($ab['numero'] ?? $ab['numero_abono'] ?? ($i + 1));
                    if ($fecha === '' || $monto <= 0.00001) {
                        continue;
                    }
                    $abonos[] = [
                        'numero' => $num > 0 ? $num : ($i + 1),
                        'fecha'  => $fecha,
                        'monto'  => \round($monto, 2),
                    ];
                }
            }
        }

        return [
            'doc_type'       => $docType,
            'observaciones'  => $obs,
            'fcam_abonos'    => $abonos,
            'currency'       => $currency,
            'exchange_rate'  => $exchangeRate,
        ];
    }

    /**
     * Build manual FEL modal seed data from an existing invoice (super-user duplicate flow).
     *
     * @return  array<string, mixed>|null
     *
     * @since   3.119.173
     */
    public function buildManualFelSeedFromInvoice(object $invoice): ?array
    {
        $lines = $this->resolveManualFelLinesFromInvoice($invoice, 0);
        if ($lines === []) {
            return null;
        }

        $buyerName = trim((string) ($invoice->fel_receptor_nombre ?? ''));
        if ($buyerName === '') {
            $buyerName = trim((string) ($invoice->client_name ?? ''));
        }
        $buyerNit = trim((string) ($invoice->client_nit ?? ''));
        if ($buyerNit === '' && !empty($invoice->fel_receptor_id)) {
            $buyerNit = trim((string) $invoice->fel_receptor_id);
        }
        $buyerAddr = trim((string) ($invoice->fel_receptor_direccion ?? ''));
        if ($buyerAddr === '') {
            $buyerAddr = trim((string) ($invoice->client_address ?? ''));
        }
        if ($buyerAddr === '') {
            $buyerAddr = 'Ciudad';
        }

        $felExtra = [];
        if (!empty($invoice->fel_extra) && \is_string($invoice->fel_extra)) {
            $decoded = \json_decode($invoice->fel_extra, true);
            if (\is_array($decoded)) {
                $felExtra = $decoded;
            }
        }

        $docType = strtoupper(trim((string) ($invoice->fel_tipo_dte ?? 'FACT')));
        if (!\in_array($docType, ['FACT', 'FCAM'], true)) {
            $docType = 'FACT';
        }

        $currency = strtoupper(trim((string) ($invoice->fel_moneda ?? '')));
        if ($currency === '') {
            $curSym = strtoupper(trim((string) ($invoice->currency ?? 'Q')));
            $currency = ($curSym === 'USD') ? 'USD' : 'GTQ';
        }
        if (!\in_array($currency, ['GTQ', 'USD'], true)) {
            $currency = 'GTQ';
        }

        $observaciones = '';

        $fcamAbonos = [];
        if ($docType === 'FCAM' && isset($felExtra['complemento_abonos']) && \is_array($felExtra['complemento_abonos'])) {
            foreach ($felExtra['complemento_abonos'] as $i => $ab) {
                if (!\is_array($ab)) {
                    continue;
                }
                $fecha = trim((string) ($ab['fecha'] ?? $ab['fecha_vencimiento'] ?? ''));
                $monto = (float) ($ab['monto'] ?? $ab['monto_abono'] ?? 0);
                if ($fecha === '' || $monto <= 0.00001) {
                    continue;
                }
                $fcamAbonos[] = [
                    'numero' => (int) ($ab['numero'] ?? $ab['numero_abono'] ?? ($i + 1)),
                    'fecha'  => $fecha,
                    'monto'  => \round($monto, 2),
                ];
            }
        }

        $ordenIds = [];
        try {
            $app = Factory::getApplication();
            $matchModel = $app->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            if ($matchModel && \is_callable([$matchModel, 'getAssociatedOrdenLinksForInvoice'])) {
                $links = $matchModel->getAssociatedOrdenLinksForInvoice((int) ($invoice->id ?? 0));
                if (\is_array($links)) {
                    foreach ($links as $link) {
                        $oid = (int) ($link['orden_id'] ?? 0);
                        if ($oid > 0) {
                            $ordenIds[] = $oid;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $cuiDigits = '';
        if (CertificadorFactNitLookupHelper::billingIdIndicatesConsumidorFinal($buyerNit)) {
            $receptorDigits = CertificadorFactNitLookupHelper::digitsOnlyBillingId(
                trim((string) ($invoice->fel_receptor_id ?? ''))
            );
            if ($receptorDigits !== '' && \strlen($receptorDigits) >= 10) {
                $cuiDigits = $receptorDigits;
                $buyerNit  = 'CF';
            }
        }

        $issueDate = Factory::getDate('now', 'America/Guatemala')->format('Y-m-d');

        return [
            'quotation_id'      => 0,
            'source_invoice_id' => (int) ($invoice->id ?? 0),
            'buyer_name'      => $buyerName,
            'buyer_nit'       => $buyerNit,
            'buyer_address'   => $buyerAddr,
            'doc_type'        => $docType,
            'currency'        => $currency,
            'observaciones'   => $observaciones,
            'fcam_abonos'     => $fcamAbonos,
            'lines'           => $lines,
            'orden_ids'       => array_values(array_unique($ordenIds)),
            'cui_digits'      => $cuiDigits,
            'issue_date'      => $issueDate,
            'auto_open'       => true,
        ];
    }

    /**
     * Whether a super-user can duplicate this invoice into manual FEL.
     *
     * @since  3.119.174
     */
    public function canDuplicateInvoiceToManualFel(object $invoice): bool
    {
        if (!$this->isEngineAvailable()) {
            return false;
        }

        return $this->resolveManualFelLinesFromInvoice($invoice, 0) !== [];
    }

    /**
     * Resolve cotización id for duplicate flow (invoice links, FEL adenda, ordens; NIT last resort).
     *
     * @since  3.119.174
     */
    public function resolveQuotationIdForInvoiceDuplicate(object $invoice): int
    {
        $invoiceId = (int) ($invoice->id ?? 0);

        $qid = (int) ($invoice->quotation_id ?? 0);
        if ($qid > 0) {
            return $qid;
        }

        $linked = $this->getQuotationIdsLinkedToInvoice($invoiceId);
        if ($linked !== []) {
            if ($this->hasInvoiceQuotationsTable() && $invoiceId > 0) {
                $this->db->setQuery(
                    $this->db->getQuery(true)
                        ->select($this->db->quoteName('quotation_id'))
                        ->from($this->db->quoteName('#__ordenproduccion_invoice_quotations'))
                        ->where($this->db->quoteName('invoice_id') . ' = ' . $invoiceId)
                        ->where($this->db->quoteName('is_primary') . ' = 1')
                        ->order($this->db->quoteName('id') . ' ASC')
                );
                $primary = (int) $this->db->loadResult();
                if ($primary > 0) {
                    return $primary;
                }
            }

            return (int) $linked[0];
        }

        foreach ($this->collectCotizacionRefsFromInvoice($invoice) as $ref) {
            $found = $this->lookupQuotationIdByReference($ref);
            if ($found > 0) {
                return $found;
            }
        }

        $ordenQids = $this->resolveQuotationIdsFromInvoiceOrdens($invoiceId);
        if ($ordenQids !== []) {
            return (int) $ordenQids[0];
        }

        $lineQid = $this->resolveQuotationIdFromInvoiceLineItems($invoice);
        if ($lineQid > 0) {
            return $lineQid;
        }

        $prefer = array_values(array_unique(array_merge($linked, $ordenQids)));

        return $this->lookupQuotationIdByClientNit($invoice, $prefer);
    }

    /**
     * Cotización ids from work orders linked to this invoice.
     *
     * @return  int[]
     *
     * @since  3.119.176
     */
    protected function resolveQuotationIdsFromInvoiceOrdens(int $invoiceId): array
    {
        $invoiceId = (int) $invoiceId;
        if ($invoiceId < 1) {
            return [];
        }

        $ordenIds = [];
        try {
            $app = Factory::getApplication();
            $matchModel = $app->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            if ($matchModel && \is_callable([$matchModel, 'getAssociatedOrdenLinksForInvoice'])) {
                foreach ($matchModel->getAssociatedOrdenLinksForInvoice($invoiceId) as $link) {
                    $oid = (int) ($link['orden_id'] ?? 0);
                    if ($oid > 0) {
                        $ordenIds[] = $oid;
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $inv = $this->loadInvoice($invoiceId);
        if ($inv) {
            $legacy = (int) ($inv->orden_id ?? 0);
            if ($legacy > 0) {
                $ordenIds[] = $legacy;
            }
        }

        $ordenIds = array_values(array_unique(array_filter($ordenIds, static function ($id) {
            return (int) $id > 0;
        })));
        if ($ordenIds === []) {
            return [];
        }

        $seen = [];
        foreach ($ordenIds as $oid) {
            foreach (QuotationEnvioFelHelper::getQuotationIdsForOrden((int) $oid, $this->db) as $qid) {
                $qid = (int) $qid;
                if ($qid > 0) {
                    $seen[$qid] = $qid;
                }
            }
            $jsonQid = $this->resolveQuotationIdFromOrdenSourceJson((int) $oid);
            if ($jsonQid > 0) {
                $seen[$jsonQid] = $jsonQid;
            }
        }

        $qids = array_values($seen);
        rsort($qids, SORT_NUMERIC);

        return $qids;
    }

    /**
     * quotation_id from orden_source_json snapshot when present.
     *
     * @since  3.119.177
     */
    protected function resolveQuotationIdFromOrdenSourceJson(int $ordenId): int
    {
        $ordenId = (int) $ordenId;
        if ($ordenId < 1) {
            return 0;
        }

        $oCols = $this->db->getTableColumns('#__ordenproduccion_ordenes', false);
        $oCols = \is_array($oCols) ? array_change_key_case($oCols, CASE_LOWER) : [];
        if (!isset($oCols['orden_source_json'])) {
            return 0;
        }

        $this->db->setQuery(
            $this->db->getQuery(true)
                ->select($this->db->quoteName('orden_source_json'))
                ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
                ->where($this->db->quoteName('id') . ' = ' . $ordenId)
                ->where($this->db->quoteName('state') . ' = 1')
        );
        $raw = trim((string) $this->db->loadResult());
        if ($raw === '') {
            return 0;
        }

        $decoded = \json_decode($raw, true);
        if (!\is_array($decoded)) {
            return 0;
        }

        return (int) ($decoded['quotation_id'] ?? 0);
    }

    /**
     * First quotation_id stored on invoice line_items JSON, if any.
     *
     * @since  3.119.176
     */
    protected function resolveQuotationIdFromInvoiceLineItems(object $invoice): int
    {
        $lineItems = $invoice->line_items ?? [];
        if (!\is_array($lineItems)) {
            $lineItems = \json_decode((string) $lineItems, true);
        }
        if (!\is_array($lineItems)) {
            return 0;
        }

        foreach ($lineItems as $line) {
            if (!\is_array($line)) {
                continue;
            }
            $qid = (int) ($line['quotation_id'] ?? 0);
            if ($qid > 0) {
                return $qid;
            }
        }

        return 0;
    }

    /**
     * Match cotización by invoice client / receptor NIT (prefer invoice-linked ids when provided).
     *
     * @param   int[]  $preferQuotationIds
     *
     * @since  3.119.175
     */
    protected function lookupQuotationIdByClientNit(object $invoice, array $preferQuotationIds = []): int
    {
        $candidates = [];
        foreach ([$invoice->client_nit ?? '', $invoice->fel_receptor_id ?? ''] as $raw) {
            $raw = trim((string) $raw);
            if ($raw === '' || CertificadorFactNitLookupHelper::billingIdIndicatesConsumidorFinal($raw)) {
                continue;
            }
            $candidates[] = $raw;
            $normalized = CertificadorFactNitLookupHelper::normalizeNitForDigifactNuc($raw);
            if ($normalized !== '') {
                $candidates[] = $normalized;
            }
            $digits = CertificadorFactNitLookupHelper::digitsOnlyBillingId($raw);
            if ($digits !== '') {
                $candidates[] = $digits;
            }
        }

        $candidates = array_values(array_unique(array_filter($candidates, static function ($v) {
            return trim((string) $v) !== '';
        })));

        foreach ($candidates as $nit) {
            $digits = CertificadorFactNitLookupHelper::digitsOnlyBillingId((string) $nit);
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('id'))
                    ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                    ->where($this->db->quoteName('state') . ' = 1')
                    ->where('('
                        . $this->db->quoteName('client_nit') . ' = ' . $this->db->quote((string) $nit)
                        . ($digits !== '' && $digits !== (string) $nit
                            ? ' OR ' . $this->db->quoteName('client_nit') . ' = ' . $this->db->quote($digits)
                            : '')
                        . ')')
                    ->order($this->db->quoteName('id') . ' DESC')
            );
            $foundIds = array_map('intval', $this->db->loadColumn() ?: []);
            foreach ($foundIds as $found) {
                if ($found > 0 && $preferQuotationIds !== [] && \in_array($found, $preferQuotationIds, true)) {
                    return $found;
                }
            }
            if ($foundIds !== [] && (int) $foundIds[0] > 0) {
                return (int) $foundIds[0];
            }
        }

        return 0;
    }

    /**
     * Normalize invoice line rows for manual FEL seeding.
     *
     * @return  list<array{descripcion: string, cantidad: float, precio_unitario: float, quotation_id: int}>
     *
     * @since  3.119.174
     */
    public function resolveManualFelLinesFromInvoice(object $invoice, ?int $quotationId = null): array
    {
        if ($quotationId === null || $quotationId < 1) {
            $quotationId = $this->resolveQuotationIdForInvoiceDuplicate($invoice);
        }
        if ($quotationId < 1) {
            $quotationId = (int) ($invoice->quotation_id ?? 0);
        }

        $lineItems = $invoice->line_items ?? [];
        if (!\is_array($lineItems)) {
            $lineItems = \json_decode((string) $lineItems, true);
        }
        if (!\is_array($lineItems) || $lineItems === []) {
            $lineItems = $this->extractLineItemsFromInvoiceFelSources($invoice);
        }

        $lines = [];
        foreach ($lineItems as $line) {
            if (!\is_array($line)) {
                continue;
            }
            $desc = trim((string) ($line['descripcion'] ?? $line['description'] ?? ''));
            $qty  = (float) ($line['cantidad'] ?? $line['qty'] ?? $line['quantity'] ?? 0);
            $unit = (float) ($line['precio_unitario'] ?? $line['valor_unitario'] ?? $line['unit_price'] ?? $line['price'] ?? 0);
            $sub  = (float) ($line['subtotal'] ?? $line['line_total'] ?? $line['total'] ?? 0);
            if ($qty >= 0.000001 && $unit <= 0.000001 && $sub > 0) {
                $unit = \round($sub / $qty, 4);
            }
            if ($desc === '' || $qty < 0.000001) {
                continue;
            }
            $lines[] = [
                'descripcion'       => $desc,
                'cantidad'          => $qty,
                'precio_unitario'   => $unit,
                'quotation_id'      => (int) ($line['quotation_id'] ?? $quotationId),
            ];
        }

        if ($lines === []) {
            $amount = (float) ($invoice->invoice_amount ?? 0);
            if ($amount > 0.0001) {
                $desc = trim((string) ($invoice->work_description ?? ''));
                if ($desc === '') {
                    $desc = 'Factura ' . InvoiceListHelper::resolveInvoiceHeadingNumber($invoice);
                }
                $lines[] = [
                    'descripcion'       => $desc,
                    'cantidad'          => 1.0,
                    'precio_unitario'   => $amount,
                    'quotation_id'      => $quotationId > 0 ? $quotationId : 0,
                ];
            }
        }

        return $lines;
    }

    /**
     * Build cotización URL to open manual FEL seeded from this invoice (empty when not possible).
     *
     * @since  3.119.175
     */
    public function buildDuplicateManualFelUrlForInvoice(object $invoice): string
    {
        if (!$this->isEngineAvailable()) {
            return '';
        }

        if ($this->resolveManualFelLinesFromInvoice($invoice, 0) === []) {
            return '';
        }

        return 'index.php?option=com_ordenproduccion&view=invoice&id='
            . (int) ($invoice->id ?? 0)
            . '&manual_fel_duplicate=1';
    }

    /**
     * Synthetic cotización context for manual FEL when issuing from invoice data only.
     *
     * @since  3.119.178
     */
    protected function buildSyntheticQuotationStubForManualFel(
        string $buyerName,
        string $buyerNit,
        string $buyerAddress,
        string $referenceLabel,
        string $currency = 'Q'
    ): object {
        $stub                     = new \stdClass();
        $stub->id                 = 0;
        $stub->quotation_number   = $referenceLabel !== '' ? $referenceLabel : '-';
        $stub->client_name        = $buyerName;
        $stub->client_nit         = $buyerNit;
        $stub->client_address     = $buyerAddress;
        $stub->total_amount       = 0;
        $stub->currency           = $currency;
        $stub->sales_agent        = null;
        $stub->quote_date         = null;

        return $stub;
    }

    /**
     * Create draft invoice row for manual FEL duplicated from an existing invoice (no cotización).
     *
     * @since  3.119.178
     */
    public function createNewManualInvoiceFromSourceInvoice(int $sourceInvoiceId, int $userId, ?string $invoiceDateYmd = null): int
    {
        $sourceInvoiceId = (int) $sourceInvoiceId;
        $userId          = (int) $userId;
        if (!$this->isEngineAvailable() || $sourceInvoiceId < 1 || $userId < 1) {
            return 0;
        }

        $source = $this->loadInvoice($sourceInvoiceId);
        if (!$source) {
            return 0;
        }

        $issuedAt = $this->resolveManualIssueDate($invoiceDateYmd);
        if ($issuedAt === null) {
            return 0;
        }

        $otLabels = [];
        try {
            $matchModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            if ($matchModel && \is_callable([$matchModel, 'getAssociatedOrdenLinksForInvoice'])) {
                foreach ($matchModel->getAssociatedOrdenLinksForInvoice($sourceInvoiceId) as $link) {
                    $label = trim((string) ($link['orden_num'] ?? ''));
                    if ($label !== '') {
                        $otLabels[$label] = $label;
                    }
                }
            }
        } catch (\Throwable $e) {
        }
        if ($otLabels === []) {
            $legacyOt = trim((string) ($source->orden_de_trabajo ?? ''));
            if ($legacyOt !== '') {
                foreach (preg_split('/\s*,\s*/', $legacyOt) ?: [] as $part) {
                    $part = trim((string) $part);
                    if ($part !== '') {
                        $otLabels[$part] = $part;
                    }
                }
            }
        }

        $srcNum        = InvoiceListHelper::resolveInvoiceHeadingNumber($source);
        $invoiceNumber = $this->generateNextInvoiceNumber();
        $now           = Factory::getDate()->toSql();
        $invoiceDate   = $issuedAt->format('Y-m-d');

        $row = [
            'invoice_number'     => $invoiceNumber,
            'orden_id'           => null,
            'orden_de_trabajo'   => $otLabels !== [] ? implode(', ', array_values($otLabels)) : '',
            'client_name'        => $source->client_name ?? '',
            'client_nit'         => $source->client_nit ?? null,
            'sales_agent'        => $source->sales_agent ?? null,
            'request_date'       => !empty($source->request_date) ? $source->request_date : null,
            'delivery_date'      => null,
            'invoice_date'       => $invoiceDate,
            'invoice_amount'     => 0,
            'currency'           => $source->currency ?? 'Q',
            'work_description'   => $source->work_description ?? null,
            'material'           => null,
            'dimensions'         => null,
            'print_color'        => null,
            'line_items'         => '[]',
            'quotation_file'     => null,
            'extraction_status'  => 'manual',
            'status'             => 'draft',
            'notes'              => 'FEL manual duplicada desde factura ' . $srcNum,
            'state'              => 1,
            'version'            => '3.119.178',
            'created'            => $now,
            'created_by'         => $userId,
            'quotation_id'       => null,
            'invoice_source'     => 'invoice_fel_duplicate',
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

        $this->ensureMultipleInvoicesPerQuotationSchema();

        $o = (object) $filtered;
        try {
            $this->db->insertObject('#__ordenproduccion_invoices', $o, 'id');
        } catch (\Throwable $e) {
            $this->lastInvoiceInsertError = $e->getMessage();

            return 0;
        }

        return (int) ($o->id ?? 0);
    }

    /**
     * User-facing message when draft invoice row insert fails.
     *
     * @since  3.119.188
     */
    protected function formatManualInvoiceCreateFailureMessage(): string
    {
        $msg = Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_CREATE_INVOICE_FAILED');
        $err = trim($this->lastInvoiceInsertError);
        if ($err !== '') {
            $msg .= ' ' . $err;
        }

        return $msg;
    }

    /**
     * Build manual FEL NUC preview payload from invoice duplicate modal (invoice data only).
     *
     * @param   list<array{descripcion: string, cantidad: float, precio_unitario: float}>  $manualLines
     *
     * @return  array{success: bool, message: string, payload?: array<string, mixed>}
     *
     * @since   3.119.178
     */
    public function buildManualFelNucPayloadFromInvoiceDuplicate(
        int $sourceInvoiceId,
        array $manualLines,
        string $buyerName,
        string $buyerNit,
        string $buyerAddress,
        ?string $nucBuyerTaxIdOverride = null,
        ?string $issueDateYmd = null,
        array $nucOptions = []
    ): array {
        $sourceInvoiceId = (int) $sourceInvoiceId;
        if ($sourceInvoiceId < 1 || !$this->isEngineAvailable()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $source = $this->loadInvoice($sourceInvoiceId);
        if (!$source) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_INVOICE_NOT_FOUND')];
        }

        $issuedAt = $this->resolveManualIssueDate($issueDateYmd);
        if ($issuedAt === null) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_ISSUE_DATE_INVALID')];
        }

        $nucOptionsResolved = $this->resolveManualFelNucOptionsForIssuance($nucOptions, $issuedAt);
        if (!empty($nucOptionsResolved['_error'])) {
            return ['success' => false, 'message' => (string) $nucOptionsResolved['_error']];
        }
        $nucOptions = $nucOptionsResolved;

        $buyerName = trim($buyerName);
        $buyerNit  = trim($buyerNit);
        if ($buyerName === '' || $buyerNit === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_REQUIRED')];
        }

        $cotRef = trim((string) ($nucOptions['observaciones'] ?? ''));
        if ($cotRef === '') {
            $cotRef = '-';
        }
        $currencySym = $this->nucCurrencyToInvoiceCurrency((string) ($nucOptions['currency'] ?? 'GTQ'));
        $stub        = $this->buildSyntheticQuotationStubForManualFel($buyerName, $buyerNit, $buyerAddress, $cotRef, $currencySym);

        $creds = $this->getActiveCertificadorCredentials();
        $payload = $this->buildDigifactNucJsonPayloadFromManualInput(
            $stub,
            $manualLines,
            $creds,
            $buyerName,
            $buyerNit,
            $buyerAddress,
            $nucBuyerTaxIdOverride,
            $issuedAt,
            [],
            $nucOptions
        );
        if (($payload['Items'] ?? []) === []) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_LINES_REQUIRED')];
        }

        $grand = 0.0;
        if (isset($payload['Totals']['GrandTotal']['InvoiceTotal'])) {
            $grand = (float) $payload['Totals']['GrandTotal']['InvoiceTotal'];
        }

        $normalizedOpts = $this->normalizeManualFelNucOptions($nucOptions, $grand);
        if ($normalizedOpts['doc_type'] === 'FCAM') {
            $fcamErr = $this->validateManualFelFcamAbonos($normalizedOpts['fcam_abonos'], $grand);
            if ($fcamErr !== null) {
                return ['success' => false, 'message' => $fcamErr];
            }
        }

        return ['success' => true, 'message' => 'OK', 'payload' => $payload];
    }

    /**
     * Issue manual FEL from invoice duplicate modal (invoice data only, no cotización).
     *
     * @param   list<array{descripcion:string, cantidad:float, precio_unitario:float}>  $manualLines
     * @param   int[]  $ordenIdsToLink
     *
     * @return  array{success:bool, message:string, invoice_id?:int, uuid?:string, invoice_url?:string}
     *
     * @since   3.119.178
     */
    public function issueDigifactNucManualFromInvoiceDuplicate(
        int $sourceInvoiceId,
        int $userId,
        array $manualLines,
        string $buyerName,
        string $buyerNit,
        string $buyerAddress,
        array $ordenIdsToLink = [],
        ?string $nucBuyerTaxIdOverride = null,
        ?string $issueDateYmd = null,
        array $nucOptions = []
    ): array {
        $sourceInvoiceId = (int) $sourceInvoiceId;
        $userId          = (int) $userId;
        if ($sourceInvoiceId < 1 || $userId < 1 || !$this->isEngineAvailable()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $built = $this->buildManualFelNucPayloadFromInvoiceDuplicate(
            $sourceInvoiceId,
            $manualLines,
            $buyerName,
            $buyerNit,
            $buyerAddress,
            $nucBuyerTaxIdOverride,
            $issueDateYmd,
            array_merge($nucOptions, ['source_invoice_id' => $sourceInvoiceId])
        );
        if (empty($built['success']) || !isset($built['payload']) || !\is_array($built['payload'])) {
            return ['success' => false, 'message' => (string) ($built['message'] ?? 'Invalid payload')];
        }

        $payload = $built['payload'];
        $creds   = $this->getActiveCertificadorCredentials();
        if ($this->buildDigifactCertificarRequestUrl($creds) === '') {
            return ['success' => false, 'message' => 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).'];
        }
        if ($this->resolveBearerJwtForDigifactPost() === '') {
            return ['success' => false, 'message' => 'Digifact bearer token missing or expired (renew in Ajustes → Certificador).'];
        }

        $invoiceId = $this->createNewManualInvoiceFromSourceInvoice($sourceInvoiceId, $userId, $issueDateYmd);
        if ($invoiceId < 1) {
            return ['success' => false, 'message' => $this->formatManualInvoiceCreateFailureMessage()];
        }

        $normalizedOpts = $this->normalizeManualFelNucOptions(
            $nucOptions,
            (float) ($payload['Totals']['GrandTotal']['InvoiceTotal'] ?? 0)
        );

        $storageLines = [];
        foreach ($manualLines as $line) {
            if (!\is_array($line)) {
                continue;
            }
            $qty  = (float) ($line['cantidad'] ?? 0);
            $unit = (float) ($line['precio_unitario'] ?? 0);
            $desc = trim((string) ($line['descripcion'] ?? ''));
            if ($desc === '' || $qty < 0.000001) {
                continue;
            }
            $sub = round($qty * $unit, 2);
            $storageLines[] = [
                'cantidad'        => $qty,
                'descripcion'     => $desc,
                'precio_unitario' => $unit,
                'subtotal'        => $sub,
            ];
        }

        $felExtraPre = [
            'pdf_observaciones'  => trim((string) $normalizedOpts['observaciones']),
            'source_invoice_id'  => $sourceInvoiceId,
        ];
        if ($normalizedOpts['doc_type'] === 'FCAM') {
            $felExtraPre['complemento_abonos'] = $normalizedOpts['fcam_abonos'];
        }
        if (($normalizedOpts['currency'] ?? 'GTQ') === 'USD' && isset($payload['Header']['ExchangeRate'])) {
            $felExtraPre['exchange_rate'] = (float) $payload['Header']['ExchangeRate'];
        }

        $invoiceCurrency = $this->nucCurrencyToInvoiceCurrency((string) ($payload['Header']['Currency'] ?? 'GTQ'));
        $grand           = (float) ($payload['Totals']['GrandTotal']['InvoiceTotal'] ?? 0);

        $this->updateInvoiceFields($invoiceId, [
            'client_name'    => trim($buyerName),
            'client_nit'     => trim($buyerNit),
            'invoice_amount' => $grand,
            'currency'       => $invoiceCurrency,
            'line_items'     => json_encode($storageLines, JSON_UNESCAPED_UNICODE),
            'fel_extra'      => json_encode($felExtraPre, JSON_UNESCAPED_UNICODE),
            'fel_tipo_dte'   => (string) $normalizedOpts['doc_type'],
        ]);

        $this->skipAutoLinkOrdensOnFinalize = true;
        $result = $this->executeDigifactCertificationForInvoice($invoiceId, 0, $payload, $userId);
        $this->skipAutoLinkOrdensOnFinalize = false;

        if (!empty($result['success'])) {
            $this->linkInvoiceToSelectedOrdens($invoiceId, $ordenIdsToLink);
            $result['invoice_url'] = 'index.php?option=com_ordenproduccion&view=invoice&id=' . $invoiceId;
        }

        return $result;
    }

    /**
     * @return  list<string>
     *
     * @since  3.119.174
     */
    protected function collectCotizacionRefsFromInvoice(object $invoice): array
    {
        $refs = [];
        $json = (string) ($invoice->fel_request_json ?? '');

        foreach (FelInvoiceHelper::parseNucAdditionalDocumentRowsFromFelRequest($json) as $row) {
            $label = strtoupper(trim((string) ($row['label'] ?? '')));
            if (!\in_array($label, ['COTIZACION', 'COTIZACIÓN'], true)) {
                continue;
            }
            $v = trim((string) ($row['value'] ?? ''));
            if ($v !== '' && $v !== '-') {
                $refs[] = $v;
            }
        }

        $payload = \json_decode($json, true);
        if (\is_array($payload)) {
            $cotFromPayload = $this->extractCotizacionReferenceFromNucPayload($payload);
            if ($cotFromPayload !== '') {
                $refs[] = $cotFromPayload;
            }
            $list = $payload['AdditionalDocumentInfo']['AdditionalInfo'] ?? null;
            if (\is_array($list)) {
                foreach ($list as $entry) {
                    if (!\is_array($entry)) {
                        continue;
                    }
                    $code = trim((string) ($entry['Code'] ?? ''));
                    if (preg_match('/^COT-(\d+)$/i', $code, $m)) {
                        $refs[] = 'COT-' . (int) $m[1];
                    }
                    if (isset($entry['#text'])) {
                        $text = trim((string) $entry['#text']);
                        if ($text !== '' && $text !== '-') {
                            $refs[] = $text;
                        }
                    }
                }
            }
        }

        foreach ($this->collectCotizacionRefsFromInvoiceText($invoice) as $textRef) {
            $refs[] = $textRef;
        }

        return array_values(array_unique(array_filter($refs, static function ($r) {
            return trim((string) $r) !== '';
        })));
    }

    /**
     * @return  list<string>
     *
     * @since  3.119.176
     */
    protected function collectCotizacionRefsFromInvoiceText(object $invoice): array
    {
        $refs = [];
        $chunks = [
            (string) ($invoice->notes ?? ''),
            (string) ($invoice->work_description ?? ''),
            (string) ($invoice->fel_extra ?? ''),
        ];
        if (!empty($invoice->fel_extra) && \is_string($invoice->fel_extra)) {
            $decoded = \json_decode($invoice->fel_extra, true);
            if (\is_array($decoded)) {
                $chunks[] = \json_encode($decoded, JSON_UNESCAPED_UNICODE);
            }
        }
        foreach ($chunks as $text) {
            if ($text === '') {
                continue;
            }
            if (preg_match_all('/\bCOT-0*(\d+)\b/i', $text, $m)) {
                foreach ($m[1] as $num) {
                    $refs[] = 'COT-' . (int) $num;
                }
            }
        }

        return array_values(array_unique($refs));
    }

    /**
     * @since  3.119.174
     */
    protected function lookupQuotationIdByReference(string $ref): int
    {
        $ref = trim($ref);
        if ($ref === '' || $ref === '-') {
            return 0;
        }

        if (preg_match('/^COT-(\d+)$/i', $ref, $m)) {
            $n = (int) $m[1];
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('id'))
                    ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                    ->where($this->db->quoteName('id') . ' = ' . $n)
                    ->where($this->db->quoteName('state') . ' = 1')
            );
            $byId = (int) $this->db->loadResult();
            if ($byId > 0) {
                return $byId;
            }
        }

        $candidates = [$ref];
        if (preg_match('/^COT-(\d+)$/i', $ref, $m)) {
            $n = (int) $m[1];
            $candidates[] = 'COT-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
            $candidates[] = 'COT-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
        }
        $candidates = array_values(array_unique($candidates));

        foreach ($candidates as $cand) {
            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('id'))
                    ->from($this->db->quoteName('#__ordenproduccion_quotations'))
                    ->where($this->db->quoteName('quotation_number') . ' = ' . $this->db->quote($cand))
                    ->where($this->db->quoteName('state') . ' = 1')
            );
            $found = (int) $this->db->loadResult();
            if ($found > 0) {
                return $found;
            }
        }

        return 0;
    }

    /**
     * @return  list<array<string, mixed>>
     *
     * @since  3.119.174
     */
    protected function extractLineItemsFromInvoiceFelSources(object $invoice): array
    {
        $json    = (string) ($invoice->fel_request_json ?? '');
        $payload = \json_decode($json, true);
        if (\is_array($payload) && isset($payload['Items']) && \is_array($payload['Items'])) {
            $out = [];
            foreach ($payload['Items'] as $it) {
                if (!\is_array($it)) {
                    continue;
                }
                $desc = trim((string) ($it['Description'] ?? ''));
                $qty  = (float) ($it['Qty'] ?? 1);
                $unit = (float) ($it['Price'] ?? 0);
                $sub  = (float) ($it['Totals']['TotalItem'] ?? 0);
                if ($desc === '') {
                    continue;
                }
                $out[] = [
                    'descripcion'       => $desc,
                    'cantidad'          => $qty,
                    'precio_unitario'   => $unit,
                    'subtotal'          => $sub > 0 ? $sub : ($qty * $unit),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        $xml = '';
        $relXml = trim((string) ($invoice->fel_local_xml_path ?? ''));
        if ($relXml !== '' && \is_file(JPATH_ROOT . '/' . $relXml)) {
            $fromDisk = @\file_get_contents(JPATH_ROOT . '/' . $relXml);
            if ($fromDisk !== false && $fromDisk !== '') {
                $xml = $fromDisk;
            }
        }
        if ($xml === '' && !empty($invoice->fel_response_json) && \is_string($invoice->fel_response_json)) {
            $xml = FelXmlHelper::tryExtractXmlFromDigifactResponseBody($invoice->fel_response_json);
        }
        if ($xml !== '') {
            return FelXmlHelper::extractLineItemsFromFelXmlString($xml);
        }

        return [];
    }

    /**
     * Resolve currency and BANGUAT exchange rate for manual FEL issuance.
     *
     * @param   array<string, mixed>  $nucOptions
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.172
     */
    protected function resolveManualFelNucOptionsForIssuance(array $nucOptions, \DateTimeInterface $issuedAt): array
    {
        $currency = strtoupper(trim((string) ($nucOptions['currency'] ?? 'GTQ')));
        if (!\in_array($currency, ['GTQ', 'USD'], true)) {
            $currency = 'GTQ';
        }
        $nucOptions['currency'] = $currency;

        if ($currency !== 'USD') {
            $nucOptions['exchange_rate'] = null;

            return $nucOptions;
        }

        $dateYmd      = $issuedAt->format('Y-m-d');
        $postedRate   = null;
        if (isset($nucOptions['exchange_rate'])) {
            $candidate = (float) $nucOptions['exchange_rate'];
            if ($candidate > 0.000001) {
                $postedRate = $candidate;
            }
        }

        $helper = new BanguatTipoCambioHelper();
        $rate   = $helper->getUsdReferenciaForDate($dateYmd);
        if ($rate === null || $rate <= 0.000001) {
            $rate = $postedRate;
        }

        if ($rate === null || $rate <= 0.000001) {
            $nucOptions['_error'] = Text::sprintf(
                'COM_ORDENPRODUCCION_MANUAL_FEL_EXCHANGE_RATE_UNAVAILABLE',
                $dateYmd
            );

            return $nucOptions;
        }

        $nucOptions['exchange_rate'] = $rate;

        return $nucOptions;
    }

    /**
     * Map Digifact NUC currency code to invoice display/storage currency.
     *
     * @since  3.119.172
     */
    protected function nucCurrencyToInvoiceCurrency(string $nucCurrency): string
    {
        return strtoupper(trim($nucCurrency)) === 'USD' ? 'USD' : 'Q';
    }

    /**
     * @param   list<array{numero: int, fecha: string, monto: float}>  $abonos
     *
     * @since   3.119.169
     */
    protected function validateManualFelFcamAbonos(array $abonos, float $grand): ?string
    {
        if ($abonos === []) {
            return Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_ABONO_REQUIRED');
        }

        $sum = 0.0;
        foreach ($abonos as $ab) {
            if (trim((string) ($ab['fecha'] ?? '')) === '') {
                return Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_DATE_REQUIRED');
            }
            $sum += (float) ($ab['monto'] ?? 0);
        }

        if (\abs($sum - $grand) > 0.05) {
            return Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_FCAM_TOTAL_MISMATCH');
        }

        return null;
    }

    /**
     * Append ADENDA (observaciones) and optional FCAMB complement blocks to NUC AdditionalInfo list.
     *
     * @param   list<array<string, mixed>>  $additionalInfos
     * @param   array{doc_type: string, observaciones: string, fcam_abonos: list<array{numero: int, fecha: string, monto: float}>}  $normalizedOpts
     *
     * @since   3.119.169
     */
    protected function appendNucAdditionalDocumentBlocks(
        array &$additionalInfos,
        string $cotRef,
        int $quotationId,
        array $normalizedOpts,
        int $sourceInvoiceId = 0
    ): void {
        $obsDigifact = trim((string) ($normalizedOpts['observaciones'] ?? ''));
        if ($obsDigifact === '') {
            $obsDigifact = '-';
        }

        if ($quotationId > 0) {
            $adendaCode = 'COT-' . $quotationId;
        } elseif ($sourceInvoiceId > 0) {
            $adendaCode = 'INV-' . $sourceInvoiceId;
        } else {
            $adendaCode = 'MANUAL';
        }
        $cotValue = $cotRef !== '' ? $cotRef : '-';

        $additionalInfos[] = [
            'Code'          => $adendaCode,
            'Type'          => 'ADENDA',
            'AditionalData' => [
                'Data' => [
                    [
                        'Name' => 'INFORMACION_ADICIONAL',
                        'Info' => [
                            ['Name' => 'OBSERVACIONES', 'Data' => null, 'Value' => $obsDigifact],
                            ['Name' => 'COTIZACION', 'Data' => null, 'Value' => $cotValue],
                        ],
                    ],
                ],
            ],
            'AditionalInfo' => [
                ['Name' => 'VALIDAR_REFERENCIA_INTERNA', 'Data' => null, 'Value' => 'NO_VALIDAR'],
            ],
        ];

        if (($normalizedOpts['doc_type'] ?? '') !== 'FCAM') {
            return;
        }

        $fcamData = [];
        foreach ($normalizedOpts['fcam_abonos'] as $ab) {
            $fcamData[] = [
                'Info' => [
                    ['Name' => 'NumeroAbono', 'Data' => null, 'Value' => (string) (int) ($ab['numero'] ?? 1)],
                    ['Name' => 'FechaVencimiento', 'Data' => null, 'Value' => (string) ($ab['fecha'] ?? '')],
                    ['Name' => 'MontoAbono', 'Data' => null, 'Value' => \sprintf('%.2f', (float) ($ab['monto'] ?? 0))],
                ],
            ];
        }
        if ($fcamData === []) {
            return;
        }

        $additionalInfos[] = [
            'Code'          => 'FCAMB',
            'Type'          => 'COMPLEMENTO',
            'AditionalData' => ['Data' => $fcamData],
        ];
    }

    /**
     * Build manual FEL NUC payload without persisting or certifying (preview / validation).
     *
     * @param   list<array{descripcion: string, cantidad: float, precio_unitario: float}>  $manualLines
     * @param   int[]  $additionalQuotationIds
     * @param   array<string, mixed>  $nucOptions
     *
     * @return  array{success: bool, message: string, payload?: array<string, mixed>}
     *
     * @since   3.119.169
     */
    public function buildManualFelNucPayloadForQuotation(
        int $quotationId,
        array $manualLines,
        string $buyerName,
        string $buyerNit,
        string $buyerAddress,
        array $additionalQuotationIds = [],
        ?string $nucBuyerTaxIdOverride = null,
        ?string $issueDateYmd = null,
        array $nucOptions = []
    ): array {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1 || !$this->isEngineAvailable()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $issuedAt = $this->resolveManualIssueDate($issueDateYmd);
        if ($issuedAt === null) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_ISSUE_DATE_INVALID')];
        }

        $nucOptionsResolved = $this->resolveManualFelNucOptionsForIssuance($nucOptions, $issuedAt);
        if (!empty($nucOptionsResolved['_error'])) {
            return ['success' => false, 'message' => (string) $nucOptionsResolved['_error']];
        }
        $nucOptions = $nucOptionsResolved;

        $allQuotationIds = array_values(array_unique(array_filter(array_merge(
            [$quotationId],
            array_map('intval', $additionalQuotationIds)
        ))));
        if (!$this->quotationsShareClientNit($allQuotationIds)) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_QUOTATIONS_SAME_CLIENT')];
        }

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $additionalRefs = [];
        foreach ($allQuotationIds as $qid) {
            if ((int) $qid === $quotationId) {
                continue;
            }
            $qExtra = $this->loadQuotation((int) $qid);
            if ($qExtra) {
                $additionalRefs[] = $this->getQuotationDisplayRef($qExtra);
            }
        }

        $creds = $this->getActiveCertificadorCredentials();
        $buyerName = trim($buyerName);
        $buyerNit  = trim($buyerNit);
        if ($buyerName === '' || $buyerNit === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_BUYER_REQUIRED')];
        }

        $payload = $this->buildDigifactNucJsonPayloadFromManualInput(
            $quotation,
            $manualLines,
            $creds,
            $buyerName,
            $buyerNit,
            $buyerAddress,
            $nucBuyerTaxIdOverride,
            $issuedAt,
            $additionalRefs,
            $nucOptions
        );
        if (($payload['Items'] ?? []) === []) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_MANUAL_FEL_LINES_REQUIRED')];
        }

        $grand = 0.0;
        if (isset($payload['Totals']['GrandTotal']['InvoiceTotal'])) {
            $grand = (float) $payload['Totals']['GrandTotal']['InvoiceTotal'];
        }

        $normalizedOpts = $this->normalizeManualFelNucOptions($nucOptions, $grand);
        if ($normalizedOpts['doc_type'] === 'FCAM') {
            $fcamErr = $this->validateManualFelFcamAbonos($normalizedOpts['fcam_abonos'], $grand);
            if ($fcamErr !== null) {
                return ['success' => false, 'message' => $fcamErr];
            }
        }

        return ['success' => true, 'message' => 'OK', 'payload' => $payload];
    }

    /**
     * Build PDF + HTML preview artifacts from a manual FEL NUC payload (no certification).
     *
     * @param   array<string, mixed>  $payload
     *
     * @return  array{success: bool, message: string, pdf_base64?: string, html?: string, doc_type?: string}
     *
     * @since   3.119.169
     */
    public function buildManualFelPreviewArtifacts(array $payload): array
    {
        try {
            $previewItem = $this->buildInvoicePreviewItemFromNucPayload($payload);
            $previewQuotationRef = $this->extractCotizacionReferenceFromNucPayload($payload);

            if (!InvoiceGrimpsaTemplatePdfHelper::isTemplateAvailable()) {
                return ['success' => false, 'message' => 'PDF engine unavailable'];
            }

            $pdfBinary = InvoiceGrimpsaTemplatePdfHelper::build($previewItem);

            $fragment = JPATH_SITE . '/components/com_ordenproduccion/tmpl/invoice/preview_digifact_fragment.php';
            $html     = '';
            if (\is_file($fragment)) {
                \ob_start();
                include $fragment;
                $html = (string) \ob_get_clean();
            }

            $docType = 'FACT';
            if (isset($payload['Header']['DocType'])) {
                $dt = strtoupper(trim((string) $payload['Header']['DocType']));
                if (\in_array($dt, ['FACT', 'FCAM'], true)) {
                    $docType = $dt;
                }
            }

            return [
                'success'     => true,
                'message'     => 'OK',
                'pdf_base64'  => \base64_encode($pdfBinary),
                'html'        => $html,
                'doc_type'    => $docType,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Extract OBSERVACIONES from NUC AdditionalDocumentInfo ADENDA block.
     *
     * @param   array<string, mixed>  $payload
     *
     * @since   3.119.169
     */
    public function extractObservacionesFromNucPayload(array $payload): string
    {
        $list = $payload['AdditionalDocumentInfo']['AdditionalInfo'] ?? null;
        if (!\is_array($list)) {
            return '';
        }

        foreach ($list as $entry) {
            if (!\is_array($entry) || strtoupper((string) ($entry['Type'] ?? '')) !== 'ADENDA') {
                continue;
            }
            $dataBlocks = $entry['AditionalData']['Data'] ?? null;
            if (!\is_array($dataBlocks)) {
                continue;
            }
            foreach ($dataBlocks as $block) {
                if (!\is_array($block)) {
                    continue;
                }
                $infos = $block['Info'] ?? null;
                if (!\is_array($infos)) {
                    continue;
                }
                foreach ($infos as $info) {
                    if (!\is_array($info)) {
                        continue;
                    }
                    if (strtoupper(trim((string) ($info['Name'] ?? ''))) === 'OBSERVACIONES') {
                        $v = trim((string) ($info['Value'] ?? ''));

                        return ($v === '' || $v === '-') ? '' : $v;
                    }
                }
            }
        }

        return '';
    }

    /**
     * Extract FCAM abonos from NUC FCAMB complement block.
     *
     * @param   array<string, mixed>  $payload
     *
     * @return  list<array{numero_abono: int, fecha_vencimiento: string, monto_abono: float}>
     *
     * @since   3.119.169
     */
    public function extractFcamAbonosFromNucPayload(array $payload): array
    {
        $list = $payload['AdditionalDocumentInfo']['AdditionalInfo'] ?? null;
        if (!\is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $code = strtoupper(trim((string) ($entry['Code'] ?? '')));
            $type = strtoupper(trim((string) ($entry['Type'] ?? '')));
            if ($code !== 'FCAMB' && $type !== 'COMPLEMENTO') {
                continue;
            }
            $dataBlocks = $entry['AditionalData']['Data'] ?? null;
            if (!\is_array($dataBlocks)) {
                continue;
            }
            foreach ($dataBlocks as $block) {
                if (!\is_array($block)) {
                    continue;
                }
                $infos = $block['Info'] ?? null;
                if (!\is_array($infos)) {
                    continue;
                }
                $num = 0;
                $fecha = '';
                $monto = 0.0;
                foreach ($infos as $info) {
                    if (!\is_array($info)) {
                        continue;
                    }
                    $n = strtoupper(trim((string) ($info['Name'] ?? '')));
                    $v = trim((string) ($info['Value'] ?? ''));
                    if ($n === 'NUMEROABONO') {
                        $num = (int) $v;
                    } elseif ($n === 'FECHAVENCIMIENTO') {
                        $fecha = $v;
                    } elseif ($n === 'MONTOABONO') {
                        $monto = (float) $v;
                    }
                }
                if ($fecha !== '' && $monto > 0) {
                    $out[] = [
                        'numero_abono'       => $num > 0 ? $num : (\count($out) + 1),
                        'fecha_vencimiento'  => $fecha,
                        'monto_abono'        => $monto,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Merge TAXID (padded), USERNAME, FORMAT into certificación URL from config.
     * Uses **URL certificación / FACT** (`url_cert_cf`); if empty or invalid, falls back to **URL certificación NIT**.
     */
    public function buildDigifactCertificarRequestUrl(array $creds): string
    {
        $base = trim((string) ($creds['url_cert_cf'] ?? ''));
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            $base = trim((string) ($creds['url_cert_nit'] ?? ''));
        }
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) {
            return '';
        }
        $taxPadded = self::padIssuerTaxIdForDigifactUrl((string) ($creds['nit'] ?? ''));
        $user      = trim((string) ($creds['usuario'] ?? ''));
        if ($taxPadded === '' || $user === '') {
            return '';
        }
        $parts = parse_url($base);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        $q = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $q);
        }
        $q['TAXID']    = $taxPadded;
        $q['USERNAME'] = $user;
        if (!isset($q['FORMAT']) || trim((string) $q['FORMAT']) === '') {
            $q['FORMAT'] = 'XML';
        }
        $path = isset($parts['path']) ? $parts['path'] : '/';

        return $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . $path . '?' . http_build_query($q);
    }

    /**
     * Validate a CUI against Digifact SHARED (SHARED_GETINFOCUI). Used before direct FEL when cotización billing ID is CF/C/F.
     *
     * @return  array{success:bool, message:string, data?:array{name:string, vat:string, street:string, city:string}}
     *
     * @since   3.119.46
     */
    public function verifyDigifactCui(string $cuiRaw): array
    {
        $cui = trim($cuiRaw);
        if ($cui === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_CLIENTE_DIGIFACT_CUI_REQUIRED')];
        }

        $bearer = $this->resolveBearerJwtForDigifactPost();
        if ($bearer === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_CLIENTE_DIGIFACT_NO_TOKEN')];
        }

        try {
            $cfgModel = new AdministracionModel();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_CLIENTE_DIGIFACT_CUI_NOT_FOUND')];
        }

        $creds  = $cfgModel->getCertificadorFactSettingsForActiveModo();
        $urlCui = trim((string) ($creds['url_cert_cui'] ?? ''));
        $urlNit = trim((string) ($creds['url_cert_nit'] ?? ''));
        $shared = $urlCui !== '' ? $urlCui : $urlNit;
        if ($shared === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_DIGIFACT_CUI_SHARED_NOT_CONFIGURED')];
        }

        $taxId = trim((string) ($creds['nit'] ?? ''));
        $usr   = trim((string) ($creds['usuario'] ?? ''));
        $digifactEnv = $cfgModel->getCertificadorFactModo();
        $digifactEnv = ($digifactEnv === 'prod') ? 'prod' : 'test';
        $r = CertificadorFactNitLookupHelper::fetchCuiInfo(
            $cui,
            $shared,
            $taxId,
            $usr,
            $bearer,
            45,
            false,
            ['environment' => $digifactEnv, 'operation' => 'shared_cui_direct_fel']
        );

        if (empty($r['ok'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_CLIENTE_DIGIFACT_CUI_NOT_FOUND')];
        }

        return [
            'success' => true,
            'message' => Text::_('COM_ORDENPRODUCCION_CLIENTE_DIGIFACT_VERIFY_OK'),
            'data'    => [
                'name'   => (string) ($r['name'] ?? ''),
                'vat'    => (string) ($r['nit'] ?? ''),
                'street' => (string) ($r['street'] ?? ''),
                'city'   => (string) ($r['city'] ?? ''),
            ],
        ];
    }

    /**
     * POST JSON to Digifact NUC transform; Authorization must be raw JWT (no "Bearer ").
     *
     * @param   array<string, mixed>|null  $digifactLogContext  When not null, full request/response bodies are stored in the Digifact log table
     *                                                        (Authorization redacted in headers JSON only).
     *
     * @return  array{http_code:int, body:string, error:string}
     */
    public function postDigifactCertificarJson(string $requestUrl, string $jsonBody, string $bearerJwt, ?array $digifactLogContext = null): array
    {
        $bearerJwt = trim($bearerJwt);
        if ($requestUrl === '' || $bearerJwt === '') {
            return ['http_code' => 0, 'body' => '', 'error' => 'missing_url_or_token'];
        }
        $t0 = microtime(true);
        try {
            $http     = HttpFactory::getHttp();
            $response = $http->post(
                $requestUrl,
                $jsonBody,
                [
                    'Authorization' => $bearerJwt,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json, text/xml, application/xml, */*',
                ],
                120
            );
            $code = (int) ($response->code ?? 0);

            $out = [
                'http_code' => $code,
                'body'      => (string) ($response->body ?? ''),
                'error'     => '',
            ];
        } catch (\Throwable $e) {
            $out = ['http_code' => 0, 'body' => '', 'error' => $e->getMessage()];
        }

        if ($digifactLogContext !== null) {
            $duration = (int) round((microtime(true) - $t0) * 1000);
            $env      = (($digifactLogContext['environment'] ?? 'test') === 'prod') ? 'prod' : 'test';
            $inv      = isset($digifactLogContext['invoice_id']) ? (int) $digifactLogContext['invoice_id'] : 0;
            $quo      = isset($digifactLogContext['quotation_id']) ? (int) $digifactLogContext['quotation_id'] : 0;
            CertificadorDigifactLogHelper::record([
                'environment'          => $env,
                'operation'            => (string) ($digifactLogContext['operation'] ?? 'certify_nuc'),
                'request_method'       => 'POST',
                'request_url'          => $requestUrl,
                'request_headers_json' => json_encode(
                    [
                        'Authorization' => '***REDACTED***',
                        'Content-Type'  => 'application/json',
                        'Accept'        => 'application/json, text/xml, application/xml, */*',
                    ],
                    JSON_UNESCAPED_UNICODE
                ),
                'request_body'         => $jsonBody,
                'response_http_code'  => (int) $out['http_code'],
                'response_body'        => (string) $out['body'],
                'client_error'         => (string) $out['error'],
                'duration_ms'          => $duration,
                'invoice_id'           => $inv > 0 ? $inv : null,
                'quotation_id'         => $quo > 0 ? $quo : null,
            ]);
        }

        return $out;
    }

    /**
     * @return  array{uuid?: string, autorizacion?: string}
     */
    public function parseDigifactCertificarResponseBody(string $body): array
    {
        $body = trim($body);
        $out  = [];
        if ($body === '') {
            return $out;
        }
        $j = json_decode($body, true);
        if (\is_array($j)) {
            foreach (['uuid', 'UUID', 'Uuid'] as $k) {
                if (!empty($j[$k]) && \is_string($j[$k])) {
                    $out['uuid'] = $j[$k];
                    break;
                }
            }
            if (!isset($out['uuid']) && !empty($j['response']) && \is_string($j['response'])) {
                $j2 = json_decode($j['response'], true);
                if (\is_array($j2) && !empty($j2['uuid'])) {
                    $out['uuid'] = (string) $j2['uuid'];
                }
            }
            if (!isset($out['uuid']) && isset($j['Sat']['Uuid']) && \is_string($j['Sat']['Uuid'])) {
                $out['uuid'] = $j['Sat']['Uuid'];
            }
        }
        if (strpos(ltrim($body), '<') === 0) {
            $satUuid = $this->extractSatAutorizacionUuidFromFelXml($body);
            if ($satUuid !== '') {
                $out['uuid'] = $satUuid;
            }
        }
        if (!isset($out['uuid']) && preg_match('/uuid["\']?\s*[:=]\s*["\']([A-F0-9a-f\-]{30,80})/i', $body, $m)) {
            $out['uuid'] = $m[1];
        }
        if (preg_match('/Autorizacion[_a-z]*["\']?\s*[:=]\s*["\']([A-F0-9a-f\-]{20,})/i', $body, $m2)) {
            $out['autorizacion'] = $m2[1];
        }

        return $out;
    }

    /**
     * SAT-certified FEL XML: authorization UUID is the text inside NumeroAutorizacion (same as SAT export filename).
     *
     * @since 3.118.35
     */
    public function extractSatAutorizacionUuidFromFelXml(string $xml): string
    {
        $xml = trim($xml);
        if ($xml === '' || strpos(ltrim($xml), '<') !== 0) {
            return '';
        }
        if (preg_match('/<(?:[\w.-]+:)?NumeroAutorizacion\b[^>]*>\s*([A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12})\s*</us', $xml, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Safe file base name for FEL artifacts (SAT style: uppercase UUID with hyphens).
     *
     * @since 3.118.35
     */
    public function sanitizeFelArtifactBasename(string $name, string $fallback): string
    {
        $name = trim($name);
        if ($name === '') {
            $name = $fallback;
        }
        $name = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $name);
        $name = trim((string) $name, '.-_');

        return substr($name !== '' ? $name : $fallback, 0, 180);
    }

    /**
     * Build Digifact NUC JSON payload for preview/issue (no HTTP POST, no invoice row). Validates cert URL + quotation lines.
     *
     * @param   string|null  $nucBuyerTaxIdOverride  CF/C/F: CUI digits for Buyer.TaxID.
     * @param   string|null  $buyerNameOverride      Optional Buyer.Name / receptor nombre.
     *
     * @return  array{success:bool, message?:string, payload?:array<string, mixed>, payload_json?:string}
     *
     * @since   3.118.34
     */
    public function buildDigifactNucDirectPayloadForQuotation(
        int $quotationId,
        ?string $nucBuyerTaxIdOverride = null,
        ?string $buyerNameOverride = null
    ): array {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1 || !$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $creds = $this->getActiveCertificadorCredentials();
        if ($this->buildDigifactCertificarRequestUrl($creds) === '') {
            return ['success' => false, 'message' => 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).'];
        }

        $ambErr = CertificadorDigifactAmbienteHelper::nucCertifyCredsViolateModo($creds, $this->getActiveCertificadorModo());
        if ($ambErr !== null) {
            return ['success' => false, 'message' => Text::_($ambErr)];
        }

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing) {
            $block = $this->getFelCertificationBlockReason($existing);
            if ($block !== '') {
                return ['success' => false, 'message' => $block];
            }
        }

        $lines   = $this->loadQuotationLines($quotationId);
        $payload = $this->buildDigifactNucJsonPayload($quotation, $lines, $creds, $nucBuyerTaxIdOverride, $buyerNameOverride);
        if (($payload['Items'] ?? []) === []) {
            return ['success' => false, 'message' => 'No billable lines'];
        }

        $pretty = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
        if ($pretty === false) {
            return ['success' => false, 'message' => 'JSON encode failed'];
        }

        return ['success' => true, 'payload' => $payload, 'payload_json' => $pretty];
    }

    /**
     * Build a pseudo-invoice object + cotización reference from a Digifact NUC JSON payload (for HTML preview only).
     *
     * @param   array<string, mixed>  $payload  From {@see buildDigifactNucJsonPayload()}
     *
     * @return  \stdClass  Item shaped like an FEL invoice row for {@see tmpl/invoice/preview_digifact_fragment.php}
     *
     * @since   3.118.42
     */
    public function buildInvoicePreviewItemFromNucPayload(array $payload): \stdClass
    {
        $seller = \is_array($payload['Seller'] ?? null) ? $payload['Seller'] : [];
        $buyer  = \is_array($payload['Buyer'] ?? null) ? $payload['Buyer'] : [];
        $branch = \is_array($seller['BranchInfo'] ?? null) ? $seller['BranchInfo'] : [];
        $bAddr  = \is_array($branch['AddressInfo'] ?? null) ? $branch['AddressInfo'] : [];
        $rAddr  = \is_array($buyer['AddressInfo'] ?? null) ? $buyer['AddressInfo'] : [];

        $felExtra = [];
        if ($bAddr !== []) {
            $felExtra['emisor_direccion'] = [
                'direccion'     => (string) ($bAddr['Address'] ?? ''),
                'codigo_postal' => (string) ($bAddr['City'] ?? ''),
                'municipio'     => (string) ($bAddr['District'] ?? ''),
                'departamento'  => (string) ($bAddr['State'] ?? ''),
                'pais'          => (string) ($bAddr['Country'] ?? ''),
            ];
        }

        $lineItems = [];
        $items = $payload['Items'] ?? [];
        if (\is_array($items)) {
            foreach ($items as $idx => $it) {
                if (!\is_array($it)) {
                    continue;
                }
                $lineItems[] = [
                    'numero_linea'    => (int) ($it['Number'] ?? ($idx + 1)),
                    'cantidad'        => (float) ($it['Qty'] ?? 0),
                    'descripcion'     => (string) ($it['Description'] ?? ''),
                    'subtotal'        => (float) ($it['Totals']['TotalItem'] ?? 0),
                    'precio_unitario' => (float) ($it['Price'] ?? 0),
                    'valor_unitario'  => (float) ($it['Price'] ?? 0),
                ];
            }
        }

        $grand = 0.0;
        if (isset($payload['Totals']['GrandTotal']['InvoiceTotal'])) {
            $grand = (float) $payload['Totals']['GrandTotal']['InvoiceTotal'];
        }

        $observaciones = $this->extractObservacionesFromNucPayload($payload);
        if ($observaciones !== '') {
            $felExtra['pdf_observaciones'] = $observaciones;
        }
        $fcamAbonos = $this->extractFcamAbonosFromNucPayload($payload);
        if ($fcamAbonos !== []) {
            $felExtra['complemento_abonos'] = $fcamAbonos;
        }

        $issuerName = trim((string) ($branch['Name'] ?? ''));
        if ($issuerName === '') {
            $issuerName = (string) ($seller['Name'] ?? '');
        }

        $item                   = new \stdClass();
        $item->id               = 0;
        $item->invoice_source = 'fel_import';
        $item->invoice_number = '';
        $item->fel_tipo_dte   = (string) ($payload['Header']['DocType'] ?? 'FACT');
        $item->fel_emisor_nombre = $issuerName;
        $item->fel_emisor_nit    = (string) ($seller['TaxID'] ?? '');
        $item->fel_autorizacion_uuid = '';
        $item->fel_fecha_emision     = null;
        $item->client_nit         = (string) ($buyer['TaxID'] ?? '');
        $item->client_name        = (string) ($buyer['Name'] ?? '');
        $item->client_address     = (string) ($rAddr['Address'] ?? '');
        $item->invoice_amount     = $grand;
        $item->currency           = (($payload['Header']['Currency'] ?? '') === 'GTQ') ? 'Q' : (string) ($payload['Header']['Currency'] ?? 'Q');
        $item->fel_extra          = $felExtra !== [] ? json_encode($felExtra, JSON_UNESCAPED_UNICODE) : '';
        $item->line_items         = $lineItems;

        return $item;
    }

    /**
     * Cotización reference string from NUC AdditionalDocumentInfo (compact @Name / #text or empty).
     *
     * @param   array<string, mixed>  $payload
     *
     * @since   3.118.42
     */
    public function extractCotizacionReferenceFromNucPayload(array $payload): string
    {
        $list = $payload['AdditionalDocumentInfo']['AdditionalInfo'] ?? null;
        if (!\is_array($list)) {
            return '';
        }
        foreach ($list as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            if (isset($entry['#text'])) {
                return trim((string) $entry['#text']);
            }
        }

        return '';
    }

    /**
     * Interpret Digifact certification HTTP body: JSON envelope with base64 responseData*, raw XML, or legacy text.
     *
     * @return  array{xml:string, pdf:string, uuid:string, autorizacion:string, digifact_code:?int, digifact_msg:string, success:bool, certificacion_meta:array<string, mixed>}
     *
     * @since   3.118.34
     */
    public function interpretDigifactCertificarResponse(string $body, int $httpCode): array
    {
        $body = trim($body);
        $out  = [
            'xml'                 => '',
            'pdf'                 => '',
            'uuid'                => '',
            'autorizacion'        => '',
            'digifact_code'       => null,
            'digifact_msg'        => '',
            'success'             => false,
            'certificacion_meta'  => [
                'autorizacion_serie'       => '',
                'autorizacion_numero_dte'  => '',
                'numero_autorizacion_text' => '',
                'certificacion'            => [
                    'nit_certificador'          => '',
                    'nombre_certificador'       => '',
                    'fecha_hora_certificacion'  => '',
                ],
            ],
        ];

        if ($body === '') {
            return $out;
        }

        $j = json_decode($body, true);
        if (\is_array($j)) {
            $out['digifact_code'] = \array_key_exists('code', $j) ? (int) $j['code'] : null;
            $out['digifact_msg']  = trim((string) ($j['message'] ?? ''));
            $auth                 = trim((string) ($j['authNumber'] ?? ''));
            $out['autorizacion']  = $auth;

            foreach (['responseData1', 'responseData2', 'responseData3'] as $dataKey) {
                if (empty($j[$dataKey]) || !\is_string($j[$dataKey])) {
                    continue;
                }
                $raw = trim($j[$dataKey]);
                if ($raw === '') {
                    continue;
                }
                $decoded = base64_decode($raw, true);
                if ($decoded === false || $decoded === '') {
                    continue;
                }
                $trimDec = ltrim($decoded);
                if ($out['xml'] === '' && $trimDec !== '' && strpos($trimDec, '<') === 0) {
                    $out['xml'] = $decoded;

                    continue;
                }
                if ($out['pdf'] === '' && strncmp($decoded, '%PDF', 4) === 0) {
                    $out['pdf'] = $decoded;
                }
            }

            if ($out['xml'] !== '') {
                $out['certificacion_meta'] = FelXmlHelper::extractCertificacionDisplayMeta($out['xml']);
                $satFileUuid = $this->extractSatAutorizacionUuidFromFelXml($out['xml']);
                if ($satFileUuid !== '') {
                    $out['uuid'] = $satFileUuid;
                }
                $parsedXml = $this->parseDigifactCertificarResponseBody($out['xml']);
                if ($out['uuid'] === '' && !empty($parsedXml['uuid'])) {
                    $out['uuid'] = $parsedXml['uuid'];
                }
                if (!empty($parsedXml['autorizacion'])) {
                    $out['autorizacion'] = $parsedXml['autorizacion'];
                }
                $metaText = trim((string) ($out['certificacion_meta']['numero_autorizacion_text'] ?? ''));
                if ($metaText !== '') {
                    if ($out['uuid'] === '') {
                        $out['uuid'] = $metaText;
                    }
                    if ($out['autorizacion'] === '') {
                        $out['autorizacion'] = $metaText;
                    }
                }
            }
            if ($out['uuid'] === '' && $auth !== '') {
                $out['uuid'] = $auth;
            }

            $codeOk     = ($out['digifact_code'] === 1);
            $hasPayload = ($out['xml'] !== '' || $out['autorizacion'] !== '');
            $out['success'] = $httpCode >= 200 && $httpCode < 300 && $codeOk && $hasPayload;

            return $out;
        }

        if (strpos(ltrim($body), '<') === 0) {
            $out['xml']         = $body;
            $out['certificacion_meta'] = FelXmlHelper::extractCertificacionDisplayMeta($body);
            $satFileUuid        = $this->extractSatAutorizacionUuidFromFelXml($body);
            $parsed             = $this->parseDigifactCertificarResponseBody($body);
            $out['uuid']        = $satFileUuid !== '' ? $satFileUuid : ($parsed['uuid'] ?? '');
            $out['autorizacion'] = $parsed['autorizacion'] ?? '';
            $metaText = trim((string) ($out['certificacion_meta']['numero_autorizacion_text'] ?? ''));
            if ($metaText !== '') {
                if ($out['uuid'] === '') {
                    $out['uuid'] = $metaText;
                }
                if ($out['autorizacion'] === '') {
                    $out['autorizacion'] = $metaText;
                }
            }
            $legacyOk           = $out['uuid'] !== '' || $out['autorizacion'] !== ''
                || (strlen($body) > 30 && stripos($body, 'dte') !== false);
            $out['success']     = $httpCode >= 200 && $httpCode < 300 && $legacyOk;

            return $out;
        }

        $parsed             = $this->parseDigifactCertificarResponseBody($body);
        $out['uuid']        = $parsed['uuid'] ?? '';
        $out['autorizacion'] = $parsed['autorizacion'] ?? '';
        $legacyOk           = $out['uuid'] !== '' || $out['autorizacion'] !== ''
            || (strlen($body) > 30 && stripos($body, 'dte') !== false);
        $out['success']     = $httpCode >= 200 && $httpCode < 300 && $legacyOk;

        return $out;
    }

    /**
     * When true, queued processing ({@see processInvoice()}) uses Digifact NUC instead of the FELplex mock.
     * Bearer token freshness is enforced in {@see executeDigifactCertificationForInvoice()} so the queue never
     * silently falls back to mock just because JWT leeway/expiry metadata disagrees with a still-valid token.
     *
     * @since   3.118.76
     */
    protected function isDigifactIssuanceConfiguredForQueue(): bool
    {
        $creds = $this->getActiveCertificadorCredentials();
        if ($this->buildDigifactCertificarRequestUrl($creds) === '') {
            return false;
        }
        if (CertificadorDigifactAmbienteHelper::nucCertifyCredsViolateModo($creds, $this->getActiveCertificadorModo()) !== null) {
            return false;
        }

        return true;
    }

    /**
     * Prefer current session user when present; otherwise invoice modified_by / created_by (scheduled / cron).
     *
     * @since   3.118.76
     */
    protected function resolveFelIssuanceActorUserId(object $invoice): int
    {
        $sessionUid = (int) Factory::getUser()->id;
        if ($sessionUid >= 1) {
            return $sessionUid;
        }
        $fb = (int) ($invoice->modified_by ?? 0);
        if ($fb >= 1) {
            return $fb;
        }
        $fb = (int) ($invoice->created_by ?? 0);

        return $fb >= 1 ? $fb : 0;
    }

    /**
     * POST NUC certification JSON for an existing invoice and persist Digifact artifacts (caller sets fel_issue_status processing).
     *
     * @param   array<string, mixed>  $payload  Built from {@see buildDigifactNucJsonPayload()}
     *
     * @return  array{success:bool, message:string, invoice_id?:int, uuid?:string}
     *
     * @since   3.118.76
     */
    protected function executeDigifactCertificationForInvoice(int $invoiceId, int $quotationId, array $payload, int $userId): array
    {
        $invoiceId = (int) $invoiceId;
        $quotationId = (int) $quotationId;
        $userId = (int) $userId;
        if ($invoiceId < 1 || $userId < 1) {
            return ['success' => false, 'message' => 'Invalid ids'];
        }

        $lock = $this->tryAcquireFelCertificationLock($invoiceId);
        if (!$lock['acquired']) {
            return [
                'success'             => false,
                'message'             => (string) $lock['message'],
                'invoice_id'          => $invoiceId,
                'duplicate_prevented' => true,
            ];
        }

        $creds = $this->getActiveCertificadorCredentials();
        $url   = $this->buildDigifactCertificarRequestUrl($creds);
        if ($url === '') {
            $msg = 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }
        $bearer = $this->resolveBearerJwtForDigifactPost();
        if ($bearer === '') {
            $msg = 'Digifact bearer token missing or expired (renew in Ajustes → Certificador).';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($jsonBody === false) {
            $msg = 'JSON encode failed';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $this->updateInvoiceFields($invoiceId, [
            'fel_request_json' => $jsonBody,
            'fel_issue_error'  => null,
        ]);

        $env = $this->getActiveCertificadorModo();
        $env = ($env === 'prod') ? 'prod' : 'test';
        $logCtx = [
            'environment'  => $env,
            'operation'    => 'certify_nuc',
            'invoice_id'   => $invoiceId,
            'quotation_id' => $quotationId,
        ];

        $httpResult = $this->postDigifactCertificarJson($url, $jsonBody, $bearer, $logCtx);
        if ($httpResult['error'] !== '') {
            $this->markFailed($invoiceId, $httpResult['error']);

            return ['success' => false, 'message' => $httpResult['error']];
        }

        $body = $httpResult['body'];
        $code = $httpResult['http_code'];
        $this->updateInvoiceFields($invoiceId, ['fel_response_json' => $body]);

        $interpret = $this->interpretDigifactCertificarResponse($body, $code);

        return $this->finalizeDigifactCertificationSuccessFromInterpret(
            $invoiceId,
            $quotationId,
            $interpret,
            $userId,
            $code,
            $body,
            $env,
            $payload
        );
    }

    /**
     * Persist completed invoice fields from Digifact interpret result (extracted for reuse by queue/direct issue).
     *
     * @param   array{xml:string, pdf:string, uuid:string, autorizacion:string, digifact_code:?int, digifact_msg:string, success:bool, certificacion_meta:array<string, mixed>}  $interpret
     * @param   string  $certificadorModo  test|prod (active Ajustes modo at certification time)
     * @param   array<string, mixed>  $nucPayload  NUC JSON sent to Digifact (used to persist receptor id when Buyer.TaxID is CUI, not quotation CF text)
     *
     * @return  array{success:bool, message:string, invoice_id:int, uuid?:string}
     *
     * @since   3.118.76
     */
    protected function finalizeDigifactCertificationSuccessFromInterpret(
        int $invoiceId,
        int $quotationId,
        array $interpret,
        int $userId,
        int $httpCode,
        string $rawBody,
        string $certificadorModo = 'test',
        array $nucPayload = []
    ): array {
        $uuid = $interpret['uuid'];
        $auth = $interpret['autorizacion'];

        if (!$interpret['success']) {
            $errBits = ['HTTP ' . $httpCode];
            if ($interpret['digifact_msg'] !== '') {
                $errBits[] = $interpret['digifact_msg'];
            }
            if ($interpret['digifact_code'] !== null && $interpret['digifact_code'] !== 1) {
                $errBits[] = 'code ' . $interpret['digifact_code'];
            }
            $snippet = strlen($rawBody) > 400 ? substr($rawBody, 0, 400) . '…' : $rawBody;
            if ($snippet !== '' && stripos(implode(' ', $errBits), $snippet) === false) {
                $errBits[] = $snippet;
            }
            $failMsg = implode(' — ', array_filter($errBits));
            $this->markFailed($invoiceId, $failMsg);

            return ['success' => false, 'message' => 'Digifact error: ' . $failMsg];
        }

        $invoiceRow = $this->loadInvoice($invoiceId);
        if (!$invoiceRow) {
            $msg = 'Invoice not found';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $quotation = $quotationId > 0 ? $this->loadQuotation($quotationId) : null;
        if ($quotationId > 0 && !$quotation) {
            $msg = 'Quotation not found';
            $this->markFailed($invoiceId, $msg);

            return ['success' => false, 'message' => $msg];
        }

        $xmlRel = null;
        $pdfRel = null;
        if ($interpret['xml'] !== '' || $interpret['pdf'] !== '') {
            $relDir = 'media/com_ordenproduccion/fel_issued/' . $invoiceId . '/digifact';
            $absDir = JPATH_ROOT . '/' . $relDir;
            if (Folder::create($absDir)) {
                $base = '';
                if ($interpret['xml'] !== '') {
                    $base = $this->extractSatAutorizacionUuidFromFelXml($interpret['xml']);
                }
                if ($base === '') {
                    $base = $interpret['uuid'] !== '' ? $interpret['uuid']
                        : ($interpret['autorizacion'] !== '' ? $interpret['autorizacion'] : ('invoice-' . $invoiceId));
                }
                $base = $this->sanitizeFelArtifactBasename($base, 'invoice-' . $invoiceId);
                if ($interpret['xml'] !== '') {
                    $xmlForDisk = $interpret['xml'];
                    $norm         = FelXmlHelper::normalizeFelXmlForImport($xmlForDisk);
                    if (!empty($norm['success']) && isset($norm['xml']) && \is_string($norm['xml']) && $norm['xml'] !== '') {
                        $xmlForDisk = $norm['xml'];
                    }
                    $xmlPath = $absDir . '/' . $base . '.xml';
                    if (file_put_contents($xmlPath, $xmlForDisk) !== false) {
                        $xmlRel = $relDir . '/' . $base . '.xml';
                    }
                }
                if ($interpret['pdf'] !== '') {
                    $pdfPath = $absDir . '/' . $base . '.pdf';
                    if (file_put_contents($pdfPath, $interpret['pdf']) !== false) {
                        $pdfRel = $relDir . '/' . $base . '.pdf';
                    }
                }
            }
        }

        $now = Factory::getDate()->toSql();
        $felFechaEmision = $this->resolveFelFechaEmisionSqlFromNucPayload($nucPayload, $invoiceRow);
        $invoiceDateYmd  = $this->resolveInvoiceDateYmdFromNucPayload($nucPayload, $invoiceRow);
        $felplex = $uuid !== '' ? $uuid : null;
        $felAuth = $auth !== '' ? $auth : ($uuid !== '' ? $uuid : null);
        $felExtraMerged = $this->mergeInvoiceFelExtraWithCertificacionMeta(
            $invoiceId,
            \is_array($interpret['certificacion_meta'] ?? null) ? $interpret['certificacion_meta'] : []
        );
        $modoNorm = ($certificadorModo === 'prod') ? 'prod' : 'test';
        $felExtraOut = $this->injectCertificadorAmbienteIntoFelExtraJson($felExtraMerged, $modoNorm);
        $otLabelsJoined = $quotationId > 0
            ? implode(', ', $this->collectOrdenDisplayLabelsForQuotation($quotationId))
            : trim((string) ($invoiceRow->orden_de_trabajo ?? ''));
        $clientNitForReceptor = $quotation
            ? (string) ($quotation->client_nit ?? '')
            : (string) ($invoiceRow->client_nit ?? $invoiceRow->fel_receptor_id ?? '');
        $receptorDigits = CertificadorFactNitLookupHelper::normalizeNitForDigifactNuc($clientNitForReceptor);
        $buyerTaxFromPayload = '';
        if (isset($nucPayload['Buyer']) && \is_array($nucPayload['Buyer'])) {
            $buyerTaxFromPayload = trim((string) ($nucPayload['Buyer']['TaxID'] ?? ''));
        }
        if ($buyerTaxFromPayload !== '' && strtoupper($buyerTaxFromPayload) !== 'CF') {
            $receptorDigits = CertificadorFactNitLookupHelper::normalizeNitForDigifactNuc($buyerTaxFromPayload);
        }
        $receptorNombre = $quotation ? (string) ($quotation->client_name ?? '') : (string) ($invoiceRow->client_name ?? '');
        $buyerNameFromPayload = '';
        if (isset($nucPayload['Buyer']) && \is_array($nucPayload['Buyer'])) {
            $buyerNameFromPayload = trim((string) ($nucPayload['Buyer']['Name'] ?? ''));
        }
        if ($buyerNameFromPayload !== '') {
            $receptorNombre = $buyerNameFromPayload;
        }
        $receptorDireccion = $quotation ? (string) ($quotation->client_address ?? '') : (string) ($invoiceRow->client_address ?? '');
        if (isset($nucPayload['Buyer']['AddressInfo']) && \is_array($nucPayload['Buyer']['AddressInfo'])) {
            $addrFromPayload = trim((string) ($nucPayload['Buyer']['AddressInfo']['Address'] ?? ''));
            if ($addrFromPayload !== '') {
                $receptorDireccion = $addrFromPayload;
            }
        }
        $felTipoDte = 'FACT';
        if (isset($nucPayload['Header']['DocType'])) {
            $dt = strtoupper(trim((string) $nucPayload['Header']['DocType']));
            if (\in_array($dt, ['FACT', 'FCAM'], true)) {
                $felTipoDte = $dt;
            }
        }
        $felMoneda = strtoupper(trim((string) ($nucPayload['Header']['Currency'] ?? 'GTQ')));
        if (!\in_array($felMoneda, ['GTQ', 'USD'], true)) {
            $felMoneda = 'GTQ';
        }
        $update = [
            'fel_issue_status'       => 'completed',
            'fel_issue_error'        => null,
            'felplex_uuid'           => $felplex,
            'fel_autorizacion_uuid'  => $felAuth,
            'fel_tipo_dte'           => $felTipoDte,
            'fel_fecha_emision'      => $felFechaEmision,
            'invoice_date'           => $invoiceDateYmd,
            'fel_receptor_id'        => $receptorDigits,
            'fel_receptor_nombre'    => $receptorNombre,
            'fel_receptor_direccion' => $receptorDireccion !== '' ? $receptorDireccion : null,
            'fel_moneda'             => $felMoneda,
            'currency'               => $this->nucCurrencyToInvoiceCurrency($felMoneda),
            'fel_scheduled_at'       => null,
            'fel_local_xml_path'     => $xmlRel,
            'fel_local_pdf_path'     => $pdfRel,
            'status'                 => 'created',
            'modified'               => $now,
            'modified_by'            => $userId,
            'fel_extra'              => $felExtraOut,
            'orden_de_trabajo'       => $otLabelsJoined,
        ];
        $update = $this->filterToExistingColumns($update);
        $this->updateInvoiceFields($invoiceId, $update);

        if (!$this->skipAutoLinkOrdensOnFinalize && $quotationId > 0) {
            $this->tryAutoLinkInvoiceOrdensForCotizacionFel($invoiceId, $quotationId);
        }

        $this->completeFacturacionManualApprovalsForInvoice($invoiceId, $userId);

        $outUuid = '';
        if ($felplex !== null && $felplex !== '') {
            $outUuid = (string) $felplex;
        }

        return [
            'success'    => true,
            'message'    => 'OK',
            'invoice_id' => $invoiceId,
            'uuid'       => $outUuid,
        ];
    }

    /**
     * Close facturación manual approval when completed invoices cover the cotización total.
     */
    protected function maybeCompleteFacturacionManualApproval(int $quotationId, int $actorUserId): void
    {
        if ($quotationId < 1 || $actorUserId < 1) {
            return;
        }

        try {
            ApprovalWorkflowEntityHelper::tryCompleteFacturacionManualApprovalWhenFullyInvoiced(
                $this->db,
                $quotationId,
                $actorUserId
            );
        } catch (\Throwable $e) {
            // Non-blocking: FEL issuance already succeeded.
        }
    }

    /**
     * Close open Fact.Man. approvals for every cotización linked to a completed invoice (multi-cot manual FEL).
     */
    protected function completeFacturacionManualApprovalsForInvoice(int $invoiceId, int $actorUserId): void
    {
        $invoiceId   = (int) $invoiceId;
        $actorUserId = (int) $actorUserId;
        if ($invoiceId < 1 || $actorUserId < 1) {
            return;
        }

        $quotationIds = $this->getQuotationIdsLinkedToInvoice($invoiceId);
        if ($quotationIds === []) {
            return;
        }

        $isMultiCot = \count($quotationIds) > 1;
        foreach ($quotationIds as $qid) {
            try {
                if ($isMultiCot) {
                    ApprovalWorkflowEntityHelper::tryCompleteFacturacionManualApprovalForSharedInvoice(
                        $this->db,
                        (int) $qid,
                        $invoiceId,
                        $actorUserId
                    );
                } else {
                    $this->maybeCompleteFacturacionManualApproval((int) $qid, $actorUserId);
                }
            } catch (\Throwable $e) {
            }
        }
    }

    /**
     * Issue FEL via Digifact transform API (bypasses mock queue). Requires url_cert_cf or url_cert_nit + valid stored bearer token.
     * Does not require órdenes de trabajo (manual advance-payment flow); association can be done later on the invoice screen.
     *
     * @return  array{success:bool, message:string, invoice_id?:int}
     *
     * @since   3.118.27
     */
    public function issueDigifactNucDirectFromQuotation(
        int $quotationId,
        int $userId,
        ?string $nucBuyerTaxIdOverride = null,
        ?string $buyerNameOverride = null
    ): array {
        $quotationId = (int) $quotationId;
        $userId      = (int) $userId;
        if ($quotationId < 1 || $userId < 1 || !$this->isEngineAvailable() || !$this->hasQuotationIdColumn()) {
            return ['success' => false, 'message' => 'Engine unavailable'];
        }

        $quotation = $this->loadQuotation($quotationId);
        if (!$quotation) {
            return ['success' => false, 'message' => 'Quotation not found'];
        }

        $creds = $this->getActiveCertificadorCredentials();
        $url   = $this->buildDigifactCertificarRequestUrl($creds);
        if ($url === '') {
            return ['success' => false, 'message' => 'Digifact cert URL or credentials incomplete (URL certificación FACT or NIT, NIT emisor, usuario).'];
        }
        $bearer = $this->resolveBearerJwtForDigifactPost();
        if ($bearer === '') {
            return ['success' => false, 'message' => 'Digifact bearer token missing or expired (renew in Ajustes → Certificador).'];
        }

        $built = $this->buildDigifactNucDirectPayloadForQuotation($quotationId, $nucBuyerTaxIdOverride, $buyerNameOverride);
        if (!$built['success']) {
            return ['success' => false, 'message' => (string) ($built['message'] ?? 'Invalid payload')];
        }

        // Manual "Emitir FEL por Digifact (directo)" may run before OTs exist (e.g. advance payment).
        // Queued {@see processInvoice()} still enforces getPrecotOrdenWaitMessageIfBlocking().

        /** @var array<string, mixed> $payload */
        $payload = $built['payload'];

        $existing = $this->getInvoiceByQuotationId($quotationId);
        if ($existing) {
            $block = $this->getFelCertificationBlockReason($existing);
            if ($block !== '') {
                return [
                    'success'             => false,
                    'message'             => $block,
                    'invoice_id'          => (int) $existing->id,
                    'duplicate_prevented' => true,
                ];
            }
            $invoiceId = (int) $existing->id;
        } else {
            $invoiceId = $this->createPendingInvoiceFromQuotation($quotationId, $userId);
        }
        if ($invoiceId < 1) {
            return ['success' => false, 'message' => 'Could not create invoice row'];
        }

        return $this->executeDigifactCertificationForInvoice($invoiceId, $quotationId, $payload, $userId);
    }

    /**
     * When facturar pre-cotizaciones exist on the quotation, each must have at least one published OT
     * before queued FEL runs. {@see processInvoice()} blocks on this; the manual action
     * {@see issueDigifactNucDirectFromQuotation()} skips it (e.g. advance payment before OT exists).
     *
     * @return  string  Empty when OK to process; otherwise a user-facing message.
     *
     * @since   3.118.79
     */
    protected function getPrecotOrdenWaitMessageIfBlocking(int $quotationId): string
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return '';
        }
        try {
            $precot = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
            if (!$precot || !\is_callable([$precot, 'getFacturarPreCotizacionesForQuotation'])) {
                return '';
            }
            /** @var array<int, array{id:int, number:string}> $facturar */
            $facturar = $precot->getFacturarPreCotizacionesForQuotation($quotationId);
            if ($facturar === []) {
                return '';
            }
            $db = $this->db;
            foreach ($facturar as $pc) {
                $pid = (int) ($pc['id'] ?? 0);
                if ($pid < 1) {
                    continue;
                }
                $db->setQuery(
                    $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $pid)
                        ->where($db->quoteName('state') . ' = 1')
                );
                if ((int) $db->loadResult() < 1) {
                    $label = trim((string) ($pc['number'] ?? ''));
                    if ($label === '') {
                        $label = 'PRE-' . $pid;
                    }

                    return Text::sprintf('COM_ORDENPRODUCCION_FEL_WAIT_PRECOT_ORDEN', $label);
                }
            }
        } catch (\Throwable $e) {
            return '';
        }

        return '';
    }

    /**
     * Distinct pre-cotización IDs linked to quotation lines (drivers for OT linkage).
     *
     * @return  list<int>
     *
     * @since   3.119.24
     */
    protected function loadDistinctPreCotizacionIdsForQuotation(int $quotationId): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return [];
        }

        $db     = $this->db;
        $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $qiCols = \is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];
        if (!isset($qiCols['pre_cotizacion_id'])) {
            return [];
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('pre_cotizacion_id'))
                ->from($db->quoteName('#__ordenproduccion_quotation_items'))
                ->where($db->quoteName('quotation_id') . ' = ' . $quotationId)
                ->where($db->quoteName('pre_cotizacion_id') . ' IS NOT NULL')
                ->where($db->quoteName('pre_cotizacion_id') . ' > 0')
                ->order($db->quoteName('pre_cotizacion_id') . ' ASC')
        );
        $ids = array_map('intval', $db->loadColumn() ?: []);

        return array_values(array_filter($ids, static fn ($id) => $id > 0));
    }

    /**
     * Display labels for órdenes de trabajo tied to this cotización (active orden rows + optional línea orden_de_trabajo snapshot).
     *
     * @return  list<string>
     *
     * @since   3.119.24
     */
    protected function collectOrdenDisplayLabelsForQuotation(int $quotationId): array
    {
        $quotationId = (int) $quotationId;
        if ($quotationId < 1) {
            return [];
        }

        $db     = $this->db;
        $labels = [];
        $seen   = [];

        $push = static function (string $label) use (&$labels, &$seen): void {
            $label = trim($label);
            if ($label === '') {
                return;
            }
            $k = strtolower($label);
            if (isset($seen[$k])) {
                return;
            }
            $seen[$k] = true;
            $labels[] = $label;
        };

        $preIds = $this->loadDistinctPreCotizacionIdsForQuotation($quotationId);

        $oCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $oCols = \is_array($oCols) ? array_change_key_case($oCols, CASE_LOWER) : [];

        if ($preIds !== [] && isset($oCols['pre_cotizacion_id'])) {
            $qq = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('pre_cotizacion_id') . ' IN (' . implode(',', $preIds) . ')')
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('id') . ' ASC');
            if (isset($oCols['order_number'])) {
                $qq->select($db->quoteName('order_number'));
            }

            if (isset($oCols['orden_de_trabajo'])) {
                $qq->select($db->quoteName('orden_de_trabajo'));
            }

            $db->setQuery($qq);
            $rows = $db->loadObjectList() ?: [];

            foreach ($rows as $row) {
                $lb = '';
                if (isset($row->order_number)) {
                    $lb = trim((string) $row->order_number);
                }

                if ($lb === '' && isset($row->orden_de_trabajo)) {
                    $lb = trim((string) $row->orden_de_trabajo);
                }

                if ($lb === '') {
                    $lb = '#' . (int) ($row->id ?? 0);
                }

                $push($lb);
            }
        }

        $qiCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $qiCols = \is_array($qiCols) ? array_change_key_case($qiCols, CASE_LOWER) : [];

        if (isset($qiCols['orden_de_trabajo'])) {
            try {
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('orden_de_trabajo'))
                        ->from($db->quoteName('#__ordenproduccion_quotation_items'))
                        ->where($db->quoteName('quotation_id') . ' = ' . $quotationId)
                        ->where($db->quoteName('orden_de_trabajo') . ' IS NOT NULL')
                        ->where($db->quoteName('orden_de_trabajo') . ' <> ' . $db->quote(''))
                );
                foreach ($db->loadColumn() ?: [] as $cell) {
                    $push((string) $cell);
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return $labels;
    }

    /**
     * After FEL completes, link this invoice to órdenes for every pre-cotización line on the quotation
     * (same as manual Administración associate, using {@see \Grimpsa\Component\Ordenproduccion\Site\Model\InvoiceOrdenMatchModel::addManualInvoiceOrdenAssociation()}).
     *
     * @since   3.118.79
     */
    protected function tryAutoLinkInvoiceOrdensForCotizacionFel(int $invoiceId, int $quotationId): void
    {
        $invoiceId   = (int) $invoiceId;
        $quotationId = (int) $quotationId;
        if ($invoiceId < 1 || $quotationId < 1) {
            return;
        }
        try {
            $match = Factory::getApplication()->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('InvoiceOrdenMatch', 'Site', ['ignore_request' => true]);
            if (!$match || !\is_callable([$match, 'addManualInvoiceOrdenAssociation']) || !$match->isTableAvailable()) {
                return;
            }
            $preIds = $this->loadDistinctPreCotizacionIdsForQuotation($quotationId);
            if ($preIds === []) {
                return;
            }
            $db   = $this->db;
            $seen = [];
            foreach ($preIds as $pid) {
                $pid = (int) $pid;
                if ($pid < 1) {
                    continue;
                }
                $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('id'))
                        ->from($db->quoteName('#__ordenproduccion_ordenes'))
                        ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $pid)
                        ->where($db->quoteName('state') . ' = 1')
                        ->order($db->quoteName('id') . ' ASC')
                );
                $oids = $db->loadColumn() ?: [];
                foreach ($oids as $oid) {
                    $oid = (int) $oid;
                    if ($oid < 1 || isset($seen[$oid])) {
                        continue;
                    }
                    $seen[$oid] = true;
                    try {
                        $match->addManualInvoiceOrdenAssociation(
                            $invoiceId,
                            $oid,
                            false,
                            ['auto', 'cotizacion_fel_pre_lines']
                        );
                    } catch (\Throwable $e) {
                    }
                }
            }
        } catch (\Throwable $e) {
        }
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
