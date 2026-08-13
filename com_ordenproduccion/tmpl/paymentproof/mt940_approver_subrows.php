<?php
/**
 * MT-940 match sub-rows for payment proof workflow members (under each PA- block).
 *
 * @var \Grimpsa\Component\Ordenproduccion\Site\View\Paymentproof\HtmlView $this
 * @var int $proofId
 * @var int $orderId
 * @var array{request_id: int, lines: array<int, array<string, mixed>>, can_approve?: bool, can_show_mt940_actions?: bool, needs_manual_mt940_pick?: bool}|null $mt940Approver
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\PaymentProofCurrencyHelper;
use Joomla\CMS\HTML\HTMLHelper;

if (empty($mt940Approver) || empty($mt940Approver['lines']) || !\is_array($mt940Approver['lines'])) {
    return;
}

$requestId           = (int) ($mt940Approver['request_id'] ?? 0);
$canApprove          = !empty($mt940Approver['can_approve']) && $requestId > 0;
$canShowMt940Actions = !empty($mt940Approver['can_show_mt940_actions']) && $requestId > 0;
$needsManualPick     = !empty($mt940Approver['needs_manual_mt940_pick']);
$blockAttr           = ' data-pp-mt940-block="' . (int) $proofId . '"';

foreach ($mt940Approver['lines'] as $pl) {
    if (!\is_array($pl)) {
        continue;
    }
    $lineId        = (int) ($pl['line_id'] ?? 0);
    $bankAccountId = (int) ($pl['bank_account_id'] ?? 0);
    $lineDate      = trim((string) ($pl['document_date'] ?? ''));
    $lineAmount    = round((float) ($pl['amount'] ?? 0), 2);
    $txId          = (int) ($pl['mt940_transaction_id'] ?? 0);
    $mt            = \is_array($pl['mt940'] ?? null) ? $pl['mt940'] : [];
    $ref           = trim((string) ($mt['reference'] ?? ''));
    $txDate        = trim((string) ($mt['transaction_date'] ?? ''));
    if ($txDate === '' && !empty($mt['value_date'])) {
        $txDate = trim((string) $mt['value_date']);
    }
    $txDateDisplay = '—';
    if ($txDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $txDate)) {
        try {
            $txDateDisplay = \Joomla\CMS\Factory::getDate(substr($txDate, 0, 10))->format('d/m/Y');
        } catch (\Throwable $e) {
            $txDateDisplay = substr($txDate, 0, 10);
        }
    }
    $mtAmountRaw = (float) ($mt['amount'] ?? ($pl['amount'] ?? 0));
    $mtCurrency  = PaymentProofCurrencyHelper::normalizeCurrency(
        trim((string) ($mt['currency'] ?? ($pl['currency'] ?? PaymentProofCurrencyHelper::CURRENCY_GTQ)))
    );
    $mtAmountDisplay = PaymentProofCurrencyHelper::formatAmount($mtAmountRaw, $mtCurrency);
    $acct     = trim((string) ($pl['account_number'] ?? ''));
    $desc     = trim((string) ($mt['description'] ?? ''));
    $badgeLabel = $needsManualPick && $txId < 1
        ? ($this->labelMt940RowPending ?? 'Seleccione movimiento')
        : ($this->labelMt940RowMatch ?? 'Coincidencia MT-940');
    ?>
<tr class="payment-proof-mt940-row pp-mt940-line-row"<?php echo $blockAttr; ?> data-line-id="<?php echo $lineId; ?>">
    <td class="payment-proof-mt940-label ps-3"><i class="fas fa-university me-1" aria-hidden="true"></i>MT-940</td>
    <td class="payment-proof-doc-number"><span class="mt940-ref"><?php echo htmlspecialchars($ref !== '' ? $ref : '—', ENT_QUOTES, 'UTF-8'); ?></span></td>
    <td class="text-nowrap"><span class="mt940-date"><?php echo htmlspecialchars($txDateDisplay, ENT_QUOTES, 'UTF-8'); ?></span></td>
    <td><?php echo htmlspecialchars($this->labelMt940RowType ?? 'Movimiento bancario', ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($acct !== '' ? $acct : '—', ENT_QUOTES, 'UTF-8'); ?></td>
    <td class="text-nowrap"><span class="mt940-amount-display"><?php echo htmlspecialchars($mtAmountDisplay, ENT_QUOTES, 'UTF-8'); ?></span></td>
    <td class="payment-proof-mt940-estado">
        <span class="badge payment-proof-mt940-badge"><?php echo htmlspecialchars($badgeLabel, ENT_QUOTES, 'UTF-8'); ?></span>
    </td>
    <td class="payment-proof-mt940-desc mt940-suggestion-cell">
        <span class="mt940-desc d-block"><?php
            if ($desc !== '') {
                echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
            } else {
                echo '<span class="text-muted">—</span>';
            }
        ?></span>
        <?php if ($canShowMt940Actions && $lineId > 0) : ?>
        <input type="hidden" class="mt940-tx-id" value="<?php echo $txId; ?>" />
        <input type="hidden" class="mt940-bank-account-id" value="<?php echo $bankAccountId; ?>" />
        <input type="hidden" class="mt940-line-amount" value="<?php echo htmlspecialchars((string) $lineAmount, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" class="mt940-line-currency" value="<?php echo htmlspecialchars($mtCurrency, ENT_QUOTES, 'UTF-8'); ?>" />
        <input type="hidden" class="mt940-line-date" value="<?php echo htmlspecialchars($lineDate, ENT_QUOTES, 'UTF-8'); ?>" />
        <?php if (!empty($this->canUseMt940Picker)) : ?>
        <button type="button"
                class="btn btn-outline-secondary btn-sm mt-1 pp-mt940-search-btn"
                title="<?php echo htmlspecialchars($this->labelMt940Search ?? 'Buscar otro movimiento', ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($this->labelMt940Search ?? 'Buscar otro movimiento', ENT_QUOTES, 'UTF-8'); ?>
        </button>
        <div class="pp-mt940-search-results mt-1 d-none small"></div>
        <?php elseif ($needsManualPick && $txId < 1) : ?>
        <span class="small text-muted d-block mt-1"><?php echo htmlspecialchars($this->labelMt940PickInApprovals ?? 'Elija movimiento en Aprobaciones', ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <?php endif; ?>
    </td>
    <td class="text-center text-muted">—</td>
    <td class="text-muted">—</td>
</tr>
    <?php
}

if ($canShowMt940Actions) :
    ?>
<tr class="payment-proof-mt940-row payment-proof-mt940-approve-row"<?php echo $blockAttr; ?> data-needs-manual="<?php echo $needsManualPick ? '1' : '0'; ?>">
    <td colspan="9" class="text-end small text-muted pe-2"><?php
        if ($needsManualPick && empty($this->canUseMt940Picker)) {
            echo htmlspecialchars($this->labelMt940PickInApprovals ?? 'Elija movimiento en Aprobaciones', ENT_QUOTES, 'UTF-8');
        } elseif ($needsManualPick) {
            echo htmlspecialchars($this->labelMt940SearchThenApprove ?? 'Seleccione el movimiento MT-940 y apruebe.', ENT_QUOTES, 'UTF-8');
        }
    ?></td>
    <td class="align-middle text-end payment-proof-mt940-actions">
        <?php if ($needsManualPick && empty($this->canUseMt940Picker)) : ?>
        <span class="text-muted">—</span>
        <?php else : ?>
        <form method="post"
              action="<?php echo htmlspecialchars($this->mt940ApproveAction ?? '', ENT_QUOTES, 'UTF-8'); ?>"
              class="d-inline pp-mt940-approve-form">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>" />
            <input type="hidden" name="proof_id" value="<?php echo (int) $proofId; ?>" />
            <input type="hidden" name="order_id" value="<?php echo (int) $orderId; ?>" />
            <input type="hidden" name="mt940_manual_override" class="pp-mt940-manual-override" value="" />
            <button type="submit"
                    class="btn btn-sm btn-success payment-proof-action-btn pp-mt940-approve-btn"
                    title="<?php echo htmlspecialchars($this->labelMt940Approve ?? 'Aprobar verificación', ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars($this->labelMt940Approve ?? 'Aprobar verificación', ENT_QUOTES, 'UTF-8'); ?>"
                    <?php echo ($needsManualPick && !$canApprove) ? ' disabled="disabled"' : ''; ?>>
                <i class="fas fa-check" aria-hidden="true"></i>
            </button>
        </form>
        <?php endif; ?>
    </td>
</tr>
    <?php
endif;
