<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * Impersonation panel (User Audit tab). Loaded from default_tabs.php so it is visible
 * even when default_user_audit.php on the server is stale.
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\UserImpersonationHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Site\View\Administracion\HtmlView $this */

$lang = \Joomla\CMS\Factory::getApplication()->getLanguage();
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

$canShowImpersonateUi = AccessHelper::isRealSuperUser() && !AccessHelper::isImpersonating();

if (!$canShowImpersonateUi) {
    return;
}

$impersonateOpts = is_array($this->impersonatableUserOptions ?? null) && $this->impersonatableUserOptions !== []
    ? $this->impersonatableUserOptions
    : UserImpersonationHelper::getImpersonatableUserOptions();

$impersonateStartUrl = Route::_('index.php?option=com_ordenproduccion&task=administracion.startImpersonation');

$versionFile = JPATH_SITE . '/components/com_ordenproduccion/VERSION';
$componentVersion = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '?';

?>
<div class="user-audit-impersonate-panel px-2 py-1 mx-auto" style="max-width: 1500px; font-size: 0.8125rem;">
    <div class="card border-warning mb-3 shadow-sm">
        <div class="card-body py-3">
            <h3 class="h6 mb-2">
                <i class="fas fa-user-secret text-warning"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_TITLE'); ?>
            </h3>
            <p class="text-muted small mb-2"><?php echo Text::_('COM_ORDENPRODUCCION_IMPERSONATE_INTRO'); ?></p>
            <p class="small text-muted mb-3 mb-md-2">
                <span class="badge bg-secondary"><?php echo htmlspecialchars(Text::sprintf('COM_ORDENPRODUCCION_IMPERSONATE_BUILD', $componentVersion), ENT_QUOTES, 'UTF-8'); ?></span>
            </p>
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
</div>
