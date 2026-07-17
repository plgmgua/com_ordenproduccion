<?php
/**
 * MT-940 match sub-rows for payment proof approvers (under each PA- block).
 *
 * @var \Grimpsa\Component\Ordenproduccion\Site\View\Paymentproof\HtmlView $this
 * @var int $proofId
 * @var int $orderId
 * @var array{request_id: int, lines: array<int, array<string, mixed>>}|null $mt940Approver
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

if (empty($mt940Approver) || empty($mt940Approver['lines']) || !\is_array($mt940Approver['lines'])) {
    return;
}

$requestId = (int) ($mt940Approver['request_id'] ?? 0);
if ($requestId < 1) {
    return;
}

$returnEncoded = base64_encode(
    Route::_('index.php?option=com_ordenproduccion&view=paymentproof&order_id=' . (int) $orderId . '&proof_id=' . (int) $proofId, false)
);

foreach ($mt940Approver['lines'] as $pl) {
    if (!\is_array($pl)) {
        continue;
    }
    $mt = \is_array($pl['mt940'] ?? null) ? $pl['mt940'] : [];
    $ref = trim((string) ($mt['reference'] ?? ''));
    $txDate = trim((string) ($mt['transaction_date'] ?? ''));
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
    $mtAmount = number_format((float) ($mt['amount'] ?? ($pl['amount'] ?? 0)), 2);
    $acct = trim((string) ($pl['account_number'] ?? ''));
    $desc = trim((string) ($mt['description'] ?? ''));
    ?>
<tr class="payment-proof-mt940-row">
    <td class="payment-proof-mt940-label ps-3"><i class="fas fa-university me-1" aria-hidden="true"></i>MT-940</td>
    <td class="payment-proof-doc-number"><?php echo htmlspecialchars($ref !== '' ? $ref : '—', ENT_QUOTES, 'UTF-8'); ?></td>
    <td class="text-nowrap"><?php echo htmlspecialchars($txDateDisplay, ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($this->labelMt940RowType ?? 'Movimiento bancario', ENT_QUOTES, 'UTF-8'); ?></td>
    <td><?php echo htmlspecialchars($acct !== '' ? $acct : '—', ENT_QUOTES, 'UTF-8'); ?></td>
    <td class="text-nowrap">Q <?php echo htmlspecialchars($mtAmount, ENT_QUOTES, 'UTF-8'); ?></td>
    <td class="payment-proof-mt940-estado">
        <span class="badge payment-proof-mt940-badge"><?php echo htmlspecialchars($this->labelMt940RowMatch ?? 'Coincidencia MT-940', ENT_QUOTES, 'UTF-8'); ?></span>
    </td>
    <td class="payment-proof-mt940-desc"><?php
        if ($desc !== '') {
            echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
        } else {
            echo '<span class="text-muted">—</span>';
        }
    ?></td>
    <td class="text-center text-muted">—</td>
    <td class="align-middle text-end payment-proof-mt940-actions">
        <form method="post" action="<?php echo htmlspecialchars($this->mt940ApproveAction ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="d-inline">
            <?php echo HTMLHelper::_('form.token'); ?>
            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>" />
            <input type="hidden" name="return" value="<?php echo htmlspecialchars($returnEncoded, ENT_QUOTES, 'UTF-8'); ?>" />
            <button type="submit"
                    class="btn btn-sm btn-success payment-proof-action-btn"
                    title="<?php echo htmlspecialchars($this->labelMt940Approve ?? 'Aprobar verificación', ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars($this->labelMt940Approve ?? 'Aprobar verificación', ENT_QUOTES, 'UTF-8'); ?>">
                <i class="fas fa-check" aria-hidden="true"></i>
            </button>
        </form>
    </td>
</tr>
    <?php
}
