<?php
/**
 * @package     Joomla.Site
 * @subpackage  mod_acciones_produccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

// Get the application
$app = Factory::getApplication();
$user = Factory::getUser();

// Check if user is in produccion group
$userGroups = $user->getAuthorisedGroups();
$db = Factory::getDbo();
$query = $db->getQuery(true)
    ->select('id')
    ->from($db->quoteName('#__usergroups'))
    ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

$db->setQuery($query);
$produccionGroupId = $db->loadResult();

$hasProductionAccess = false;
if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
    $hasProductionAccess = true;
}

// Get current work order ID from URL
$orderId = $app->input->getInt('id', 0);

// Check if we're on the correct component and view
$option = $app->input->get('option', '');
$view = $app->input->get('view', '');

// Only show module on ordenproduccion component pages
if ($option !== 'com_ordenproduccion') {
    return; // Don't display the module
}

// Generate PDF action - redirect to component
if ($app->input->get('task') === 'generate_pdf' && $hasProductionAccess) {
    if (!Session::checkToken()) {
        $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
        $app->redirect(Uri::current());
        return;
    }
    
    if ($orderId) {
        // Redirect to component PDF generation
        $pdfUrl = Route::_('index.php?option=com_ordenproduccion&task=orden.generatePdf&id=' . $orderId);
        $app->redirect($pdfUrl);
        return;
    } else {
        $app->enqueueMessage(Text::_('MOD_ACCIONES_PRODUCCION_INVALID_ORDER'), 'error');
    }
    
    $app->redirect(Uri::current());
    return;
}

// Load the template
require ModuleHelper::getLayoutPath('mod_acciones_produccion', $params->get('layout', 'default'));
