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
    OutboundEmailLogHelper::CONTEXT_VENDOR_QUOTE_REQUEST  => Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_CONTEXT_VENDOR_QUOTE'),
    OutboundEmailLogHelper::CONTEXT_PAYMENTPROOF_MISMATCH => Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_CONTEXT_PAYMENTPROOF_MISMATCH'),
];

?>
<div class="admin-dashboard" style="padding: 20px; max-width: 1400px; margin: 0 auto;">
    <h2 class="mb-3">
        <i class="fas fa-envelope-open-text"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_TITLE'); ?>
    </h2>
    <?php if (!$seeAll) : ?>
        <p class="text-muted small"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_VENTAS_HINT'); ?></p>
    <?php endif; ?>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_SCHEMA_MISSING'); ?></div>
    <?php elseif ($rows === []) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_EMPTY'); ?></div>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped table-sm align-middle">
                <thead>
                    <tr>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_DATE'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_STATUS'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_CONTEXT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_TO'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_SUBJECT'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_USER'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_ERROR'); ?></th>
                        <th><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_COL_META'); ?></th>
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
                        if ($metaRaw !== '') {
                            $decoded = json_decode($metaRaw, true);
                            $metaOut = is_array($decoded)
                                ? htmlspecialchars(json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars($metaRaw, ENT_QUOTES, 'UTF-8');
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($row->created ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td>
                                <?php if ($ok) : ?>
                                    <span class="badge bg-success"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_STATUS_OK'); ?></span>
                                <?php else : ?>
                                    <span class="badge bg-danger"><?php echo Text::_('COM_ORDENPRODUCCION_OUTBOUND_EMAIL_STATUS_FAIL'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($ctxLb, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->to_email ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->subject ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo $actor; ?></td>
                            <td><?php echo htmlspecialchars((string) ($row->error_message ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><pre class="small mb-0" style="max-width: 280px; white-space: pre-wrap;"><?php echo $metaOut; ?></pre></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($pagination && (int) ($this->outboundEmailLogTotal ?? 0) > (int) ($this->outboundEmailLogLimit ?? 20)) : ?>
            <div class="com-content-pagination mt-2"><?php echo $pagination->getListFooter(); ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
