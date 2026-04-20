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
use Grimpsa\Module\OrdopPendingApprovals\Helper\RecordLink as ApprovalRecordLink;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Database\DatabaseInterface;

$app  = Factory::getApplication();
$user = Factory::getUser();

if ($user->guest) {
    return;
}

$lang = $app->getLanguage();
$modPath = (is_object($module) && !empty($module->path))
    ? $module->path
    : JPATH_SITE . '/modules/mod_ordop_pending_approvals';
$lang->load('mod_ordop_pending_approvals', $modPath, null, true);
$lang->load('com_ordenproduccion', JPATH_SITE);
$lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');

try {
    $app->bootComponent('com_ordenproduccion');
} catch (\Throwable $e) {
    return;
}

require_once __DIR__ . '/helper/RecordLink.php';

if (!class_exists(ApprovalWorkflowService::class) || !class_exists(AccessHelper::class)) {
    return;
}

if (!AccessHelper::canViewApprovalWorkflowTab()) {
    return;
}

$approvalService = new ApprovalWorkflowService();
$schemaOk        = $approvalService->hasSchema();
$rows            = $schemaOk ? $approvalService->getMyPendingApprovalRows((int) $user->id) : [];

if ($rows !== []) {
    $db = Factory::getContainer()->get(DatabaseInterface::class);
    $preCotIds = [];
    foreach ($rows as $row) {
        if (($row->entity_type ?? '') === ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO) {
            $eid = (int) ($row->entity_id ?? 0);
            if ($eid > 0) {
                $preCotIds[$eid] = true;
            }
        }
    }
    $preCotNumberById = [];
    if ($preCotIds !== []) {
        $ids = array_values(array_filter(array_map('intval', array_keys($preCotIds)), static function ($v) {
            return $v > 0;
        }));
        if ($ids !== []) {
            $q = $db->getQuery(true)
                ->select($db->quoteName('id') . ', ' . $db->quoteName('number'))
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
                ->where($db->quoteName('id') . ' IN (' . implode(',', $ids) . ')');
            $db->setQuery($q);
            $numRows = $db->loadObjectList() ?: [];
            foreach ($numRows as $nr) {
                $preCotNumberById[(int) $nr->id] = (string) ($nr->number ?? '');
            }
        }
    }
    foreach ($rows as $row) {
        $row->record_link = ApprovalRecordLink::relativeUrl($db, $row);
        if (($row->entity_type ?? '') === ApprovalWorkflowService::ENTITY_SOLICITUD_DESCUENTO) {
            $eid = (int) ($row->entity_id ?? 0);
            $row->precotizacion_number = $eid > 0 && isset($preCotNumberById[$eid])
                ? $preCotNumberById[$eid]
                : '';
        }
    }
}

$pendingTotal  = count($rows);
$showFullLink  = (int) $params->get('show_full_link', 1) === 1;
$hideWhenEmpty = (int) $params->get('hide_when_empty', 0) === 1;

if ($hideWhenEmpty && $schemaOk && $rows === []) {
    return;
}

require ModuleHelper::getLayoutPath('mod_ordop_pending_approvals', $params->get('layout', 'default'));
