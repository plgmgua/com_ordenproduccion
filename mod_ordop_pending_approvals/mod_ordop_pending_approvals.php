<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_ordop_pending_approvals
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

$app  = Factory::getApplication();
$user = Factory::getUser();

if ($user->guest) {
    return;
}

$lang = $app->getLanguage();
$modPath = (is_object($module) && !empty($module->path))
    ? $module->path
    : JPATH_SITE . '/modules/mod_ordop_pending_approvals';
$lang->load('mod_ordop_pending_approvals', $modPath);
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

try {
    $app->bootComponent('com_ordenproduccion');
} catch (\Throwable $e) {
    return;
}

if (!class_exists(ApprovalWorkflowService::class) || !class_exists(AccessHelper::class)) {
    return;
}

if (!AccessHelper::canViewApprovalWorkflowTab()) {
    return;
}

$approvalService = new ApprovalWorkflowService();
$schemaOk        = $approvalService->hasSchema();
$rows            = $schemaOk ? $approvalService->getMyPendingApprovalRows((int) $user->id) : [];

$showHeading   = (int) $params->get('show_heading', 1) === 1;
$showIntro     = (int) $params->get('show_intro', 1) === 1;
$showFullLink  = (int) $params->get('show_full_link', 1) === 1;
$hideWhenEmpty = (int) $params->get('hide_when_empty', 0) === 1;

if ($hideWhenEmpty && $schemaOk && $rows === []) {
    return;
}

require ModuleHelper::getLayoutPath('mod_ordop_pending_approvals', $params->get('layout', 'default'));
