<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\Mt940ImportHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\Mt940PaymentMatchLogHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Cron-based MT-940 matching for ingresado payment proofs (Verificar pago).
 *
 * @since  3.119.228
 */
class Mt940PaymentMatchService
{
    /** @var string[] */
    public const BANK_PAYMENT_TYPES = ['transferencia', 'deposito'];

    /** @var DatabaseInterface */
    protected $db;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
    }

    /**
     * Run scheduled matching for all eligible ingresado proofs.
     *
     * @return  array{success: bool, scanned: int, matched: int, ambiguous: int, no_match: int, skipped: int, approvals_created: int, message: string}
     *
     * @since   3.119.228
     */
    public function runScheduledMatching(): array
    {
        $result = [
            'success'           => true,
            'scanned'           => 0,
            'matched'           => 0,
            'ambiguous'         => 0,
            'no_match'          => 0,
            'skipped'           => 0,
            'approvals_created' => 0,
            'message'           => '',
        ];

        if (!Mt940PaymentMatchLogHelper::isMt940VerificationEnabled()) {
            $result['success'] = false;
            $result['message'] = 'MT-940 payment verification disabled (component params).';

            return $result;
        }

        if (!Mt940ImportHelper::tablesAvailable()) {
            $result['success'] = false;
            $result['message'] = 'MT-940 tables not available.';

            return $result;
        }

        if (!$this->hasMatchColumns()) {
            $result['success'] = false;
            $result['message'] = 'Payment proof MT-940 match columns missing (run SQL 3.119.228).';

            return $result;
        }

        $wfSvc = new ApprovalWorkflowService();
        if (!$wfSvc->hasSchema()) {
            $result['success'] = false;
            $result['message'] = 'Approval workflow schema missing.';

            return $result;
        }

        $linkedIds = $this->getLinkedMt940TransactionIds();
        $proofs    = $this->getIngresadoProofsForMatching();

        foreach ($proofs as $proof) {
            $proofId = (int) ($proof->id ?? 0);
            if ($proofId < 1) {
                continue;
            }

            if ($wfSvc->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_PAYMENT_PROOF, $proofId) !== null) {
                $result['skipped']++;
                continue;
            }

            $result['scanned']++;
            $outcome = $this->processProof($proof, $linkedIds, $wfSvc);

            if ($outcome['status'] === 'matched') {
                $result['matched']++;
                if (!empty($outcome['approval_created'])) {
                    $result['approvals_created']++;
                }
            } elseif ($outcome['status'] === 'ambiguous') {
                $result['ambiguous']++;
            } else {
                $result['no_match']++;
            }
        }

        $result['message'] = \sprintf(
            'Scanned %d proofs; matched %d; ambiguous %d; no_match %d; skipped %d; approvals %d.',
            $result['scanned'],
            $result['matched'],
            $result['ambiguous'],
            $result['no_match'],
            $result['skipped'],
            $result['approvals_created']
        );

        return $result;
    }

    /**
     * Search MT-940 transactions for manual approver selection.
     *
     * @param   array<string, mixed>  $filters  bank_account_id, date, amount
     * @param   int                   $limit
     *
     * @return  array<int, object>
     *
     * @since   3.119.228
     */
    public function searchMt940Transactions(array $filters, int $limit = 25): array
    {
        if (!Mt940ImportHelper::tablesAvailable()) {
            return [];
        }

        $bankAccountId = max(0, (int) ($filters['bank_account_id'] ?? 0));
        $date          = trim((string) ($filters['date'] ?? ''));
        $amount        = isset($filters['amount']) ? round((float) $filters['amount'], 2) : 0.0;
        $limit         = max(1, min(50, $limit));

        $q = $this->db->getQuery(true)
            ->select([
                't.' . $this->db->quoteName('id'),
                't.' . $this->db->quoteName('bank_account_id'),
                't.' . $this->db->quoteName('transaction_date'),
                't.' . $this->db->quoteName('value_date'),
                't.' . $this->db->quoteName('reference'),
                't.' . $this->db->quoteName('amount'),
                't.' . $this->db->quoteName('currency'),
                't.' . $this->db->quoteName('debit_credit'),
                't.' . $this->db->quoteName('description'),
                'ba.' . $this->db->quoteName('account_number'),
                'ba.' . $this->db->quoteName('name', 'bank_account_name'),
            ])
            ->from($this->db->quoteName('#__ordenproduccion_mt940_transactions', 't'))
            ->join(
                'LEFT',
                $this->db->quoteName('#__ordenproduccion_bank_accounts', 'ba'),
                'ba.' . $this->db->quoteName('id') . ' = t.' . $this->db->quoteName('bank_account_id')
            )
            ->where('t.' . $this->db->quoteName('debit_credit') . ' = ' . $this->db->quote('C'));

        if ($bankAccountId > 0) {
            $q->where('t.' . $this->db->quoteName('bank_account_id') . ' = ' . $bankAccountId);
        }
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $q->where(
                '(' . 't.' . $this->db->quoteName('transaction_date') . ' = ' . $this->db->quote($date)
                . ' OR t.' . $this->db->quoteName('value_date') . ' = ' . $this->db->quote($date) . ')'
            );
        }
        if ($amount > 0) {
            $q->where('ABS(t.' . $this->db->quoteName('amount') . ' - ' . $amount . ') < 0.005');
        }

        $q->order('t.' . $this->db->quoteName('transaction_date') . ' DESC')
            ->order('t.' . $this->db->quoteName('id') . ' DESC');

        $this->db->setQuery($q, 0, $limit);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * @param   object  $proof
     * @param   int[]   $linkedIds
     * @param   ApprovalWorkflowService  $wfSvc
     *
     * @return  array{status: string, approval_created: bool}
     */
    protected function processProof(object $proof, array $linkedIds, ApprovalWorkflowService $wfSvc): array
    {
        $proofId = (int) ($proof->id ?? 0);
        $lines   = $this->getBankLinesForProof($proofId);

        if ($lines === []) {
            Mt940PaymentMatchLogHelper::log($proofId, 0, Mt940PaymentMatchLogHelper::STATUS_SKIPPED, 'No transferencia/deposito lines with bank account.');

            return ['status' => 'no_match', 'approval_created' => false];
        }

        $lineMatches = [];
        $now         = Factory::getDate()->toSql();

        foreach ($lines as $line) {
            $lineId = (int) ($line->id ?? 0);
            $candidates = $this->findCandidatesForLine($line, $linkedIds);

            $this->touchLineCheckedAt($lineId, $now);

            if (\count($candidates) === 0) {
                $this->updateLineMatchState($lineId, null, 'no_match');
                Mt940PaymentMatchLogHelper::log(
                    $proofId,
                    $lineId,
                    Mt940PaymentMatchLogHelper::STATUS_NO_MATCH,
                    $this->describeLine($line) . ' — no MT-940 credit on same day/amount/account.'
                );

                return ['status' => 'no_match', 'approval_created' => false];
            }

            if (\count($candidates) > 1) {
                $this->updateLineMatchState($lineId, null, 'ambiguous');
                Mt940PaymentMatchLogHelper::log(
                    $proofId,
                    $lineId,
                    Mt940PaymentMatchLogHelper::STATUS_AMBIGUOUS,
                    $this->describeLine($line) . ' — ' . \count($candidates) . ' MT-940 candidates; skipped.'
                );

                return ['status' => 'ambiguous', 'approval_created' => false];
            }

            $tx = $candidates[0];
            $txId = (int) ($tx->id ?? 0);
            $this->updateLineMatchState($lineId, $txId, 'matched');
            $linkedIds[] = $txId;

            $lineMatches[] = $this->buildLineMatchPayload($line, $tx);
        }

        $metadata = $this->buildApprovalMetadata($proofId, $lineMatches);
        $submitterId = (int) ($proof->created_by ?? 0);
        if ($submitterId < 1) {
            $submitterId = (int) Factory::getUser()->id;
        }

        $requestId = $wfSvc->createRequest(
            ApprovalWorkflowService::ENTITY_PAYMENT_PROOF,
            $proofId,
            $submitterId > 0 ? $submitterId : 1,
            $metadata
        );

        if ($requestId < 1) {
            Mt940PaymentMatchLogHelper::log($proofId, 0, Mt940PaymentMatchLogHelper::STATUS_ERROR, 'Matched but failed to create approval request.');

            return ['status' => 'matched', 'approval_created' => false];
        }

        Mt940PaymentMatchLogHelper::log($proofId, 0, Mt940PaymentMatchLogHelper::STATUS_MATCHED, 'Approval request #' . $requestId . ' created.');

        return ['status' => 'matched', 'approval_created' => true];
    }

    /**
     * @param   object       $line
     * @param   array<int>   $excludeIds
     *
     * @return  array<int, object>
     */
    public function findCandidatesForLine(object $line, array $excludeIds = []): array
    {
        $bankAccountId = (int) ($line->bank_account_id ?? 0);
        $amount        = round((float) ($line->amount ?? 0), 2);
        $docDate       = $this->normalizeLineDate($line);

        if ($bankAccountId < 1 || $amount <= 0 || $docDate === '') {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select('t.*')
            ->from($this->db->quoteName('#__ordenproduccion_mt940_transactions', 't'))
            ->where('t.' . $this->db->quoteName('bank_account_id') . ' = ' . $bankAccountId)
            ->where('t.' . $this->db->quoteName('debit_credit') . ' = ' . $this->db->quote('C'))
            ->where('ABS(t.' . $this->db->quoteName('amount') . ' - ' . $amount . ') < 0.005')
            ->where(
                '(' . 't.' . $this->db->quoteName('transaction_date') . ' = ' . $this->db->quote($docDate)
                . ' OR t.' . $this->db->quoteName('value_date') . ' = ' . $this->db->quote($docDate) . ')'
            );

        if ($excludeIds !== []) {
            $excludeIds = array_values(array_filter(array_map('intval', $excludeIds)));
            if ($excludeIds !== []) {
                $q->where('t.' . $this->db->quoteName('id') . ' NOT IN (' . implode(',', $excludeIds) . ')');
            }
        }

        $this->db->setQuery($q);
        $rows = $this->db->loadObjectList() ?: [];

        if (\count($rows) <= 1) {
            return $rows;
        }

        // Prefer unique by amount+date+account; if still multiple, return all (caller skips).
        return $rows;
    }

    /**
     * @param   int            $proofId
     * @param   array<int, array<string, mixed>>  $lineMatches
     *
     * @return  string
     */
    public function buildApprovalMetadata(int $proofId, array $lineMatches): string
    {
        $payload = [
            'mt940_verification' => true,
            'payment_proof_id'   => $proofId,
            'matched_at'         => Factory::getDate()->toSql(),
            'lines'              => $lineMatches,
        ];

        return json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    /**
     * @param   object  $line
     * @param   object  $tx
     *
     * @return  array<string, mixed>
     */
    public function buildLineMatchPayload(object $line, object $tx): array
    {
        $docNum = trim((string) ($line->document_number ?? ''));
        $ref    = trim((string) ($tx->reference ?? ''));
        $desc   = trim((string) ($tx->description ?? ''));

        return [
            'line_id'              => (int) ($line->id ?? 0),
            'payment_type'         => trim((string) ($line->payment_type ?? '')),
            'document_number'      => $docNum,
            'document_date'        => $this->normalizeLineDate($line),
            'amount'               => round((float) ($line->amount ?? 0), 2),
            'bank_account_id'      => (int) ($line->bank_account_id ?? 0),
            'account_number'       => $this->getAccountNumber((int) ($line->bank_account_id ?? 0)),
            'mt940_transaction_id' => (int) ($tx->id ?? 0),
            'doc_match_hint'       => $this->documentMatchHint($docNum, $ref, $desc),
            'mt940'                => [
                'transaction_date' => (string) ($tx->transaction_date ?? ''),
                'value_date'       => (string) ($tx->value_date ?? ''),
                'reference'        => $ref,
                'description'      => $desc,
                'amount'           => round((float) ($tx->amount ?? 0), 2),
                'currency'         => trim((string) ($tx->currency ?? 'GTQ')),
                'debit_credit'     => trim((string) ($tx->debit_credit ?? '')),
            ],
        ];
    }

    /**
     * @return  array<int, object>
     */
    protected function getIngresadoProofsForMatching(): array
    {
        $ppCols = $this->db->getTableColumns('#__ordenproduccion_payment_proofs', false);
        $ppCols = \is_array($ppCols) ? array_change_key_case($ppCols, CASE_LOWER) : [];

        if (!isset($ppCols['verification_status'])) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select(['pp.' . $this->db->quoteName('id'), 'pp.' . $this->db->quoteName('created_by')])
            ->from($this->db->quoteName('#__ordenproduccion_payment_proofs', 'pp'))
            ->where('pp.' . $this->db->quoteName('state') . ' = 1')
            ->where(
                '(' . 'pp.' . $this->db->quoteName('verification_status') . ' IS NULL'
                . ' OR pp.' . $this->db->quoteName('verification_status') . ' = ' . $this->db->quote('')
                . ' OR LOWER(TRIM(pp.' . $this->db->quoteName('verification_status') . ')) = ' . $this->db->quote('ingresado') . ')'
            )
            ->order('pp.' . $this->db->quoteName('id') . ' ASC');

        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * @return  array<int, object>
     */
    protected function getBankLinesForProof(int $proofId): array
    {
        if (!$this->hasPaymentProofLinesTable()) {
            return [];
        }

        $types = array_map(fn ($t) => $this->db->quote($t), self::BANK_PAYMENT_TYPES);

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_payment_proof_lines'))
            ->where($this->db->quoteName('payment_proof_id') . ' = ' . $proofId)
            ->where($this->db->quoteName('payment_type') . ' IN (' . implode(',', $types) . ')')
            ->where($this->db->quoteName('bank_account_id') . ' > 0')
            ->order($this->db->quoteName('ordering') . ' ASC, id ASC');

        $this->db->setQuery($q);

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * @return  int[]
     */
    protected function getLinkedMt940TransactionIds(): array
    {
        if (!$this->hasMatchColumns()) {
            return [];
        }

        $q = $this->db->getQuery(true)
            ->select('DISTINCT ' . $this->db->quoteName('mt940_transaction_id'))
            ->from($this->db->quoteName('#__ordenproduccion_payment_proof_lines'))
            ->where($this->db->quoteName('mt940_transaction_id') . ' IS NOT NULL')
            ->where($this->db->quoteName('mt940_transaction_id') . ' > 0');

        $this->db->setQuery($q);
        $ids = $this->db->loadColumn() ?: [];

        return array_values(array_filter(array_map('intval', $ids)));
    }

    protected function updateLineMatchState(int $lineId, ?int $txId, string $status): void
    {
        if ($lineId < 1 || !$this->hasMatchColumns()) {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_payment_proof_lines'))
            ->set($this->db->quoteName('mt940_match_status') . ' = ' . $this->db->quote($status));

        if ($txId !== null && $txId > 0) {
            $q->set($this->db->quoteName('mt940_transaction_id') . ' = ' . $txId);
        } else {
            $q->set($this->db->quoteName('mt940_transaction_id') . ' = NULL');
        }

        $q->where($this->db->quoteName('id') . ' = ' . $lineId);
        $this->db->setQuery($q);
        $this->db->execute();
    }

    protected function touchLineCheckedAt(int $lineId, string $sqlDatetime): void
    {
        if ($lineId < 1 || !$this->hasMatchColumns()) {
            return;
        }

        $q = $this->db->getQuery(true)
            ->update($this->db->quoteName('#__ordenproduccion_payment_proof_lines'))
            ->set($this->db->quoteName('mt940_match_checked_at') . ' = ' . $this->db->quote($sqlDatetime))
            ->where($this->db->quoteName('id') . ' = ' . $lineId);
        $this->db->setQuery($q);
        $this->db->execute();
    }

    protected function normalizeLineDate(object $line): string
    {
        $raw = trim((string) ($line->document_date ?? ''));
        if ($raw === '' || $raw === '0000-00-00') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return Factory::getDate($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    protected function getAccountNumber(int $bankAccountId): string
    {
        if ($bankAccountId < 1) {
            return '';
        }

        $q = $this->db->getQuery(true)
            ->select($this->db->quoteName('account_number'))
            ->from($this->db->quoteName('#__ordenproduccion_bank_accounts'))
            ->where($this->db->quoteName('id') . ' = ' . $bankAccountId);
        $this->db->setQuery($q);

        return trim((string) $this->db->loadResult());
    }

    protected function describeLine(object $line): string
    {
        return \sprintf(
            'line #%d type=%s doc=%s date=%s amount=%.2f account=%d',
            (int) ($line->id ?? 0),
            trim((string) ($line->payment_type ?? '')),
            trim((string) ($line->document_number ?? '')),
            $this->normalizeLineDate($line),
            (float) ($line->amount ?? 0),
            (int) ($line->bank_account_id ?? 0)
        );
    }

    protected function documentMatchHint(string $docNum, string $reference, string $description): string
    {
        if ($docNum === '') {
            return '';
        }

        $docNorm = ltrim(preg_replace('/\D/', '', $docNum) ?? '', '0');
        $refNorm = ltrim(preg_replace('/\D/', '', $reference) ?? '', '0');

        if ($docNorm !== '' && $refNorm !== '' && ($docNorm === $refNorm || str_ends_with($refNorm, $docNorm) || str_ends_with($docNorm, $refNorm))) {
            return 'reference_partial';
        }

        if ($description !== '' && stripos($description, $docNum) !== false) {
            return 'description_contains';
        }

        return '';
    }

    /**
     * Rebuild display payloads from payment lines linked to MT-940 transactions (post-approval).
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   3.119.244
     */
    public function buildDisplayLinesFromLinkedTransactions(int $proofId): array
    {
        $proofId = (int) $proofId;
        if ($proofId < 1 || !$this->hasMatchColumns() || !$this->hasPaymentProofLinesTable()) {
            return [];
        }

        try {
            $q = $this->db->getQuery(true)
                ->select([
                    'l.*',
                    $this->db->quoteName('t.id', 'tx_id'),
                    $this->db->quoteName('t.transaction_date'),
                    $this->db->quoteName('t.value_date'),
                    $this->db->quoteName('t.reference'),
                    $this->db->quoteName('t.description', 'tx_description'),
                    $this->db->quoteName('t.amount', 'tx_amount'),
                    $this->db->quoteName('t.currency'),
                    $this->db->quoteName('t.debit_credit'),
                ])
                ->from($this->db->quoteName('#__ordenproduccion_payment_proof_lines', 'l'))
                ->innerJoin(
                    $this->db->quoteName('#__ordenproduccion_mt940_transactions', 't')
                    . ' ON ' . $this->db->quoteName('t.id') . ' = ' . $this->db->quoteName('l.mt940_transaction_id')
                )
                ->where($this->db->quoteName('l.payment_proof_id') . ' = ' . $proofId)
                ->where($this->db->quoteName('l.mt940_transaction_id') . ' > 0')
                ->order($this->db->quoteName('l.ordering') . ' ASC, ' . $this->db->quoteName('l.id') . ' ASC');
            $this->db->setQuery($q);
            $rows = $this->db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $tx = (object) [
                'id'               => (int) ($row->tx_id ?? 0),
                'transaction_date' => (string) ($row->transaction_date ?? ''),
                'value_date'       => (string) ($row->value_date ?? ''),
                'reference'        => (string) ($row->reference ?? ''),
                'description'      => (string) ($row->tx_description ?? ''),
                'amount'           => (float) ($row->tx_amount ?? 0),
                'currency'         => (string) ($row->currency ?? 'GTQ'),
                'debit_credit'     => (string) ($row->debit_credit ?? ''),
            ];
            $out[] = $this->buildLineMatchPayload($row, $tx);
        }

        return $out;
    }

    public function hasMatchColumns(): bool
    {
        try {
            $cols = $this->db->getTableColumns('#__ordenproduccion_payment_proof_lines', false);
            $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];

            return isset($cols['mt940_transaction_id'], $cols['mt940_match_status']);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function hasPaymentProofLinesTable(): bool
    {
        try {
            $prefix = $this->db->getPrefix();
            $tables = $this->db->setQuery('SHOW TABLES LIKE ' . $this->db->quote($prefix . 'ordenproduccion_payment_proof_lines'))->loadColumn();

            return !empty($tables);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Load MT-940 verification metadata from an approval request row.
     *
     * @return  array<string, mixed>|null
     */
    public static function decodeVerificationMetadata(?string $json): ?array
    {
        if ($json === null || trim($json) === '') {
            return null;
        }

        $data = json_decode($json, true);

        return (\is_array($data) && !empty($data['mt940_verification'])) ? $data : null;
    }
}
