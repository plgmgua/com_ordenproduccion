<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\OutboundEmailLogHelper;
use Joomla\CMS\Language\Text;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$rows        = is_array($this->outboundEmailLogRows ?? null) ? $this->outboundEmailLogRows : [];
$pagination  = $this->outboundEmailLogPagination ?? null;
$schemaOk    = (bool) ($this->outboundEmailLogTableAvailable ?? false);
$seeAll      = (bool) ($this->outboundEmailLogSeeAllUsers ?? false);

$ctxLabels = [
    OutboundEmailLogHelper::CONTEXT_VENDOR_QUOTE_REQUEST   => Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_CONTEXT_VENDOR_QUOTE'),
    OutboundEmailLogHelper::CONTEXT_PAYMENTPROOF_MISMATCH  => Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_CONTEXT_PAYMENTPROOF_MISMATCH'),
    OutboundEmailLogHelper::CONTEXT_ORDENCOMPRA_APPROVED   => Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_CONTEXT_ORDENCOMPRA_APPROVED'),
];

?>
<div class="admin-dashboard outbound-email-log px-2 py-1 mx-auto" style="max-width: 1400px; font-size: 0.8125rem;">
    <h2 class="h5 mb-2">
        <i class="fas fa-envelope-open-text"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_TITLE'); ?>
    </h2>
    <?php if (!$seeAll) : ?>
        <p class="text-muted mb-2" style="font-size: 0.75rem;"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_VENTAS_HINT'); ?></p>
    <?php endif; ?>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning py-2 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_SCHEMA_MISSING'); ?></div>
    <?php elseif ($rows === []) : ?>
        <div class="alert alert-info py-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_EMPTY'); ?></div>
    <?php else : ?>
        <div class="table-responsive mb-1">
            <table class="table table-hover table-sm align-middle mb-0 small">
                <thead class="table-light">
                    <tr class="text-muted" style="font-size: 0.75rem;">
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_DATE'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_STATUS'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_CONTEXT'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_TO'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_SUBJECT'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_USER'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_ERROR'); ?></th>
                        <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_META'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) : ?>
                        <?php
                        $ok    = (int) ($row->status ?? 0) === 1;
                        $ctx   = (string) ($row->context_type ?? '');
                        $ctxLb = (string) ($ctxLabels[$ctx] ?? $ctx);
                        $actor = trim((string) ($row->actor_name ?? ''));
                        if ($actor === '') {
                            $actor = (int) ($row->user_id ?? 0) > 0 ? ('#' . (int) $row->user_id) : '—';
                        } else {
                            $u = (string) ($row->actor_username ?? '');
                            $actor = htmlspecialchars($actor, ENT_QUOTES, 'UTF-8')
                                . ($u !== '' ? ' <span class="text-muted">(' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . ')</span>' : '');
                        }
                        $metaRaw = (string) ($row->meta ?? '');
                        $metaOut = '—';
                        $metaTitle = '';
                        if ($metaRaw !== '') {
                            $decoded = json_decode($metaRaw, true);
                            if (is_array($decoded)) {
                                $metaCompact = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                                $metaOut     = htmlspecialchars((string) $metaCompact, ENT_QUOTES, 'UTF-8');
                                $metaTitle   = htmlspecialchars((string) $metaCompact, ENT_QUOTES, 'UTF-8');
                            } else {
                                $metaOut   = htmlspecialchars($metaRaw, ENT_QUOTES, 'UTF-8');
                                $metaTitle = $metaOut;
                            }
                        }
                        $errRaw = trim((string) ($row->error_message ?? ''));
                        $errDisp = $errRaw === '' ? '' : htmlspecialchars($errRaw, ENT_QUOTES, 'UTF-8');
                        $errLen  = function_exists('mb_strlen') ? mb_strlen($errRaw, 'UTF-8') : strlen($errRaw);
                        if ($errLen > 120) {
                            $cut = function_exists('mb_substr') ? mb_substr($errRaw, 0, 117, 'UTF-8') : substr($errRaw, 0, 117);
                            $errShort = htmlspecialchars($cut, ENT_QUOTES, 'UTF-8') . '…';
                            $errTitle = htmlspecialchars($errRaw, ENT_QUOTES, 'UTF-8');
                        } else {
                            $errShort = $errDisp;
                            $errTitle = '';
                        }
                        ?>
                        <tr class="py-0">
                            <td class="py-1 text-nowrap"><?php echo htmlspecialchars((string) ($row->created ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-1">
                                <?php if ($ok) : ?>
                                    <span class="badge bg-success" style="font-size: 0.65rem;"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_STATUS_OK'); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-danger" style="font-size: 0.65rem;"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_STATUS_FAIL'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1"><?php echo htmlspecialchars($ctxLb, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-1 text-break"><?php echo htmlspecialchars((string) ($row->to_email ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-1 text-break"><?php echo htmlspecialchars((string) ($row->subject ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="py-1"><?php echo $actor; ?></td>
                            <td class="py-1 text-break"<?php echo $errTitle !== '' ? ' title="' . $errTitle . '"' : ''; ?>><?php echo $errShort === '' ? '—' : $errShort; ?></td>
                            <td class="py-1">
                                <?php if ($metaOut !== '—') : ?>
                                <div class="text-muted mb-0 text-break outbound-email-meta-cell" style="max-width: 14rem; max-height: 3.6em; overflow: auto; line-height: 1.25; font-size: 0.75rem;" title="<?php echo $metaTitle; ?>"><?php echo $metaOut; ?></div>
                                <?php else : ?>
                                —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && (int) ($this->outboundEmailLogTotal ?? 0) > 0) : ?>
            <div class="com-content-pagination outbound-email-log-pagination mt-1 pt-1 border-top small"><?php echo $pagination->getListFooter(); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
