<?php
/**
 * Orden de compra — list/detail and cancel pending (Administración).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * @since  3.113.47
 */
class OrdencompraController extends BaseController
{
    /**
     * Delete a pending-approval orden de compra (removes lines).
     *
     * @return  void
     */
    public function delete(): void
    {
        $app = Factory::getApplication();

        if (!AccessHelper::canViewOrdenCompra()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

        $listUrl = Route::_('index.php?option=com_ordenproduccion&view=ordencompra', false);

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('JGLOBAL_AUTH_ALERT'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));

            return;
        }

        $id = (int) $app->input->post->getInt('id', 0);
        if ($id < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_INVALID_ID'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);

        if (!$model || !method_exists($model, 'deleteOrden') || !$model->deleteOrden($id)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETE_FAILED'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_DELETED'));
        $app->redirect($listUrl);
    }
}
