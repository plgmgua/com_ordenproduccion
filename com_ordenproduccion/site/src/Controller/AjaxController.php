<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Ajax controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class AjaxController extends BaseController
{
    /**
     * Method to change work order status via AJAX
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function changeStatus()
    {
        // Set proper headers for JSON response
        header('Content-Type: application/json');
        
        $app = Factory::getApplication();
        $user = Factory::getUser();
        
        // Check CSRF token
        if (!Session::checkToken()) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            exit;
        }
        
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

        if (!$hasProductionAccess) {
            echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
            exit;
        }
        
        $orderId = $app->input->getInt('order_id', 0);
        $newStatus = $app->input->getString('new_status', '');
        
        if ($orderId > 0 && !empty($newStatus)) {
            try {
                $db = Factory::getDbo();
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_ordenes'))
                    ->set($db->quoteName('status') . ' = ' . $db->quote($newStatus))
                    ->set($db->quoteName('modified') . ' = NOW()')
                    ->set($db->quoteName('modified_by') . ' = ' . (int)$user->id)
                    ->where($db->quoteName('id') . ' = ' . (int)$orderId);

                $db->setQuery($query);
                $result = $db->execute();
                
                if ($result) {
                    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
                }
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Datos inválidos']);
        }
        
        exit;
    }
}
