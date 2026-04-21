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

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Proveedores (vendors) — standalone frontend view.
 *
 * @since  3.111.0
 */
class ProveedoresController extends BaseController
{
    /**
     * Save vendor (create or update).
     *
     * @return  void
     */
    public function save(): void
    {
        $app     = Factory::getApplication();
        $listUrl = Route::_('index.php?option=com_ordenproduccion&view=proveedores', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

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

        $model = $this->getModel('Administracion');

        if (!$model || !method_exists($model, 'hasProveedoresSchema') || !$model->hasProveedoresSchema()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_SCHEMA_MISSING'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $post = $app->input->post->get('proveedor', [], 'array');

        if ((int) ($post['id'] ?? 0) === 0 && !AccessHelper::canCreateProveedores()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_CREATE_DENIED'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $products = $app->input->post->get('proveedor_products', [], 'array');

        if (!is_array($products)) {
            $products = [];
        }

        $lines = [];

        foreach ($products as $p) {
            $lines[] = is_string($p) ? $p : (string) $p;
        }

        $id = $model->saveProveedor(
            [
                'id'                => (int) ($post['id'] ?? 0),
                'name'              => (string) ($post['name'] ?? ''),
                'nit'               => (string) ($post['nit'] ?? ''),
                'address'           => (string) ($post['address'] ?? ''),
                'phone'             => (string) ($post['phone'] ?? ''),
                'contact_name'      => (string) ($post['contact_name'] ?? ''),
                'contact_cellphone' => (string) ($post['contact_cellphone'] ?? ''),
                'contact_email'     => (string) ($post['contact_email'] ?? ''),
                'state'             => (int) ($post['state'] ?? 1),
            ],
            $lines,
            (int) $user->id
        );

        if ($id === false) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_SAVE_FAIL'), 'error');
            $editId = (int) ($post['id'] ?? 0);
            $redir  = $editId > 0
                ? Route::_('index.php?option=com_ordenproduccion&view=proveedores&proveedor_id=' . $editId, false)
                : Route::_('index.php?option=com_ordenproduccion&view=proveedores&proveedor_id=0', false);
            $app->redirect($redir);

            return;
        }

        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_SAVED'), 'success');
        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=proveedores&proveedor_id=' . (int) $id, false));
    }

    /**
     * Delete vendor.
     *
     * @return  void
     */
    public function delete(): void
    {
        $app     = Factory::getApplication();
        $listUrl = Route::_('index.php?option=com_ordenproduccion&view=proveedores', false);

        if (!AccessHelper::isInAdministracionOrAdmonGroup()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect($listUrl);

            return;
        }

        $model = $this->getModel('Administracion');
        $id    = $app->input->post->getInt('proveedor_id', 0);

        if (!$model || !method_exists($model, 'hasProveedoresSchema') || !$model->hasProveedoresSchema() || $id < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_DELETE_FAIL'), 'error');
            $app->redirect($listUrl);

            return;
        }

        if ($model->deleteProveedor($id)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_DELETED'), 'success');
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_DELETE_FAIL'), 'error');
        }

        $app->redirect($listUrl);
    }
}
