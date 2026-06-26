<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\UserSessionAuditHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$rows       = is_array($this->userSessionAuditRows ?? null) ? $this->userSessionAuditRows : [];
$pagination = $this->userSessionAuditPagination ?? null;
$schemaOk   = (bool) ($this->userSessionAuditTableAvailable ?? false);
$userOpts   = is_array($this->userSessionAuditUserFilterOptions ?? null) ? $this->userSessionAuditUserFilterOptions : [];
$impersonateOpts = is_array($this->impersonatableUserOptions ?? null) ? $this->impersonatableUserOptions : [];
$filterUid  = (int) ($this->userSessionAuditFilterUserId ?? 0);
$filterIp   = htmlspecialchars((string) ($this->userSessionAuditFilterIp ?? ''), ENT_QUOTES, 'UTF-8');
$filterFrom = htmlspecialchars((string) ($this->userSessionAuditFilterDateFrom ?? ''), ENT_QUOTES, 'UTF-8');
$filterTo   = htmlspecialchars((string) ($this->userSessionAuditFilterDateTo ?? ''), ENT_QUOTES, 'UTF-8');
$listUrl    = Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=user_audit');
$impersonateStartUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.startImpersonation');
$canShowImpersonateUi = AccessHelper::isSuperUser() && !AccessHelper::isImpersonating();

$deviceLabels = [
    'desktop' => Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DEVICE_DESKTOP'),
    'mobile'  => Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DEVICE_MOBILE'),
    'tablet'  => Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DEVICE_TABLET'),
    'bot'     => Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DEVICE_BOT'),
    'unknown' => Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DEVICE_UNKNOWN'),
];

if ($schemaOk && $rows !== []) {
    HTMLHelper::_('bootstrap.collapse');
}

?>
<div class="admin-dashboard user-session-audit px-2 py-1 mx-auto" style="max-width: 1500px; font-size: 0.8125rem;">
    <h2 class="h5 mb-2">
        <i class="fas fa-user-shield"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_TITLE'); ?>
    </h2>
    <p class="text-muted mb-3" style="font-size: 0.75rem;"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_INTRO'); ?></p>

    <?php if ($canShowImpersonateUi) : ?>
        <div class="card border-warning mb-3">
            <div class="card-body py-3">
                <h3 class="h6 mb-2">
                    <i class="fas fa-user-secret"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_TITLE'); ?>
                </h3>
                <p class="text-muted small mb-3"><?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_INTRO'); ?></p>
                <?php if ($impersonateOpts === []) : ?>
                    <div class="alert alert-info py-2 mb-0 small"><?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_EMPTY_LIST'); ?></div>
                <?php else : ?>
                    <form method="post" action="<?php echo $impersonateStartUrl; ?>" class="row g-2 align-items-end">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <div class="col-md-6">
                            <label class="form-label small mb-0" for="impersonate_user_id"><?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_SELECT_USER'); ?></label>
                            <select name="impersonate_user_id" id="impersonate_user_id" class="form-select form-select-sm" required>
                                <option value=""><?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_SELECT_PLACEHOLDER'); ?></option>
                                <?php foreach ($impersonateOpts as $uid => $label) : ?>
                                    <option value="<?php echo (int) $uid; ?>"><?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-auto">
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="fas fa-user-secret"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_START'); ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$schemaOk) : ?>
        <div class="alert alert-warning py-2 mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_SCHEMA_MISSING'); ?></div>
    <?php else : ?>
        <form method="get" action="<?php echo $listUrl; ?>" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="option" value="com_ordenproduccion" />
            <input type="hidden" name="view" value="administracion" />
            <input type="hidden" name="tab" value="user_audit" />
            <div class="col-md-3">
                <label class="form-label small mb-0" for="user_audit_user_id"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_FILTER_USER'); ?></label>
                <select name="user_audit_user_id" id="user_audit_user_id" class="form-select form-select-sm">
                    <option value="0"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_FILTER_ALL_USERS'); ?></option>
                    <?php foreach ($userOpts as $uid => $label) : ?>
                        <option value="<?php echo (int) $uid; ?>"<?php echo $filterUid === (int) $uid ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="user_audit_ip"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_IP'); ?></label>
                <input type="text" class="form-control form-control-sm" name="user_audit_ip" id="user_audit_ip" value="<?php echo $filterIp; ?>" />
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="user_audit_date_from"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_FILTER_FROM'); ?></label>
                <input type="date" class="form-control form-control-sm" name="user_audit_date_from" id="user_audit_date_from" value="<?php echo $filterFrom; ?>" />
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-0" for="user_audit_date_to"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_FILTER_TO'); ?></label>
                <input type="date" class="form-control form-control-sm" name="user_audit_date_to" id="user_audit_date_to" value="<?php echo $filterTo; ?>" />
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('JFILTER'); ?></button>
                <a href="<?php echo $listUrl; ?>" class="btn btn-outline-secondary btn-sm"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
            </div>
        </form>

        <?php if ($rows === []) : ?>
            <div class="alert alert-info py-2 mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_EMPTY'); ?></div>
        <?php else : ?>
            <div class="table-responsive mb-1">
                <table class="table table-hover table-sm align-middle mb-0 small">
                    <thead class="table-light">
                        <tr class="text-muted" style="font-size: 0.75rem;">
                            <th class="py-1" scope="col" style="width: 2.25rem;">
                                <span class="visually-hidden"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_EXPAND'); ?></span>
                            </th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_USER'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_FIRST_SEEN'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_LAST_SEEN'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_IP'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_DEVICE'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_BROWSER'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_PLATFORM'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_HITS'); ?></th>
                            <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_LAST_VIEW'); ?></th>
                            <?php if ($canShowImpersonateUi && $impersonateOpts !== []) : ?>
                                <th class="py-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_ACTIONS'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $idx => $row) : ?>
                            <?php
                            $rowId = (int) ($row->id ?? 0);
                            $collapseId = 'usa-detail-' . ($rowId > 0 ? $rowId : ('n' . (int) $idx));

                            $name = trim((string) ($row->user_name ?? ''));
                            $username = trim((string) ($row->user_username ?? ''));
                            if ($name === '') {
                                $name = (int) ($row->user_id ?? 0) > 0 ? ('#' . (int) $row->user_id) : '—';
                            }
                            $userDisp = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                            if ($username !== '') {
                                $userDisp .= ' <span class="text-muted">(' . htmlspecialchars($username, ENT_QUOTES, 'UTF-8') . ')</span>';
                            }

                            $deviceKey = (string) ($row->device_type ?? 'unknown');
                            $deviceLb  = (string) ($deviceLabels[$deviceKey] ?? $deviceKey);

                            $metaRaw = (string) ($row->meta ?? '');
                            $decoded = $metaRaw !== '' ? json_decode($metaRaw, true) : null;
                            $metaPretty = '';
                            if (is_array($decoded)) {
                                $metaPretty = htmlspecialchars(
                                    (string) json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            }

                            $ua = htmlspecialchars((string) ($row->user_agent ?? ''), ENT_QUOTES, 'UTF-8');
                            $uri = htmlspecialchars((string) ($row->request_uri ?? ''), ENT_QUOTES, 'UTF-8');
                            $referer = htmlspecialchars((string) ($row->referer ?? ''), ENT_QUOTES, 'UTF-8');
                            $lang = htmlspecialchars((string) ($row->accept_language ?? ''), ENT_QUOTES, 'UTF-8');
                            $sessionId = htmlspecialchars((string) ($row->session_id ?? ''), ENT_QUOTES, 'UTF-8');
                            $task = htmlspecialchars((string) ($row->task_name ?? ''), ENT_QUOTES, 'UTF-8');
                            $view = htmlspecialchars((string) ($row->view_name ?? ''), ENT_QUOTES, 'UTF-8');
                            $rowUserId = (int) ($row->user_id ?? 0);
                            $canImpersonateRow = $canShowImpersonateUi
                                && $rowUserId > 0
                                && isset($impersonateOpts[$rowUserId]);
                            ?>
                            <tr>
                                <td class="py-1">
                                    <button class="btn btn-sm btn-link p-0 text-secondary" type="button"
                                            data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>"
                                            aria-expanded="false" aria-controls="<?php echo $collapseId; ?>"
                                            title="<?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_COL_EXPAND'); ?>">
                                        <i class="fas fa-chevron-down"></i>
                                    </button>
                                </td>
                                <td class="py-1"><?php echo $userDisp; ?></td>
                                <td class="py-1 text-nowrap"><?php echo htmlspecialchars((string) ($row->first_seen ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-1 text-nowrap"><?php echo htmlspecialchars((string) ($row->last_seen ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-1 text-nowrap"><code><?php echo htmlspecialchars((string) ($row->ip_address ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                                <td class="py-1"><?php echo htmlspecialchars($deviceLb, ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-1"><?php echo htmlspecialchars((string) ($row->browser ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-1"><?php echo htmlspecialchars((string) ($row->platform ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="py-1 text-end"><?php echo (int) ($row->hit_count ?? 0); ?></td>
                                <td class="py-1"><?php echo $view !== '' ? $view : '—'; ?><?php echo $task !== '' ? ' / ' . $task : ''; ?></td>
                                <?php if ($canShowImpersonateUi && $impersonateOpts !== []) : ?>
                                    <td class="py-1 text-nowrap">
                                        <?php if ($canImpersonateRow) : ?>
                                            <form method="post" action="<?php echo $impersonateStartUrl; ?>" class="d-inline">
                                                <?php echo HTMLHelper::_('form.token'); ?>
                                                <input type="hidden" name="impersonate_user_id" value="<?php echo $rowUserId; ?>" />
                                                <button type="submit" class="btn btn-warning btn-sm py-0 px-2" title="<?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_START'); ?>">
                                                    <i class="fas fa-user-secret"></i>
                                                    <?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_ROW'); ?>
                                                </button>
                                            </form>
                                        <?php else : ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <tr class="collapse bg-light" id="<?php echo $collapseId; ?>">
                                <td colspan="<?php echo ($canShowImpersonateUi && $impersonateOpts !== []) ? '11' : '10'; ?>" class="py-2 px-3">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="fw-semibold mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_SESSION'); ?></div>
                                            <dl class="row mb-0 small">
                                                <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_SESSION_ID'); ?></dt>
                                                <dd class="col-sm-8"><code><?php echo $sessionId !== '' ? $sessionId : '—'; ?></code></dd>
                                                <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_URI'); ?></dt>
                                                <dd class="col-sm-8"><code class="text-break"><?php echo $uri !== '' ? $uri : '—'; ?></code></dd>
                                                <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_REFERER'); ?></dt>
                                                <dd class="col-sm-8 text-break"><?php echo $referer !== '' ? $referer : '—'; ?></dd>
                                                <dt class="col-sm-4"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_LANGUAGE'); ?></dt>
                                                <dd class="col-sm-8"><?php echo $lang !== '' ? $lang : '—'; ?></dd>
                                            </dl>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="fw-semibold mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_USER_AGENT'); ?></div>
                                            <pre class="small mb-2 p-2 bg-white border rounded" style="max-height: 120px; overflow: auto; white-space: pre-wrap;"><?php echo $ua !== '' ? $ua : '—'; ?></pre>
                                            <?php if ($metaPretty !== '') : ?>
                                                <div class="fw-semibold mb-1"><?php echo Text::_('COM_ORDENPRODUCCION_USER_AUDIT_DETAIL_META'); ?></div>
                                                <pre class="small mb-0 p-2 bg-white border rounded" style="max-height: 220px; overflow: auto;"><?php echo $metaPretty; ?></pre>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($pagination !== null) : ?>
                <div class="d-flex justify-content-center"><?php echo $pagination->getListFooter(); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
