<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Proveedores;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;

/**
 * Proveedores (vendors) — standalone site view.
 *
 * @since  3.111.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var bool */
    protected $proveedoresSchemaOk = false;

    /** @var array<int, object> */
    protected $proveedoresList = [];

    /** @var object|null */
    protected $proveedorEdit = null;

    /** @var array<int, object> */
    protected $proveedorProductos = [];

    protected $proveedoresSearch = '';

    /** @var string  '' | '1' | '0' */
    protected $proveedoresStateFilter = '';

    /**
     * Create / edit / delete vendors (Administración or Admon).
     *
     * @var    bool
     * @since  3.111.2
     */
    public $canManageProveedores = false;

    /**
     * @param   string|null  $tpl  Template name
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        if (!AccessHelper::canViewProveedores()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

        $this->canManageProveedores = AccessHelper::isInAdministracionOrAdmonGroup();

        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $this->proveedoresSchemaOk    = false;
        $this->proveedoresList        = [];
        $this->proveedorEdit          = null;
        $this->proveedorProductos     = [];
        $this->proveedoresSearch      = $input->getString('proveedores_search', '');
        $this->proveedoresStateFilter = $input->getString('proveedores_state', '');

        if (!in_array($this->proveedoresStateFilter, ['', '1', '0'], true)) {
            $this->proveedoresStateFilter = '';
        }

        $proveedorId = $input->getInt('proveedor_id', -1);

        if (!$this->canManageProveedores && $proveedorId >= 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_LIST_ONLY'), 'notice');
            $proveedorId = -1;
        }

        try {
            $admModel = Factory::getApplication()
                ->bootComponent('com_ordenproduccion')
                ->getMVCFactory()
                ->createModel('Administracion', 'Site', ['ignore_request' => true]);

            if ($admModel === null || !method_exists($admModel, 'hasProveedoresSchema')) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_LOAD_ERROR'), 'error');
            } else {
                $this->proveedoresSchemaOk = $admModel->hasProveedoresSchema();
            }

            if ($admModel !== null && $this->proveedoresSchemaOk) {
                $stateFilter = $this->proveedoresStateFilter === '' ? null : (int) $this->proveedoresStateFilter;
                $this->proveedoresList = $admModel->getProveedoresList($this->proveedoresSearch, $stateFilter);

                if ($proveedorId >= 0) {
                    if ($proveedorId === 0) {
                        if (!AccessHelper::canCreateProveedores()) {
                            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_CREATE_DENIED'), 'warning');
                        } else {
                            $this->proveedorEdit = (object) [
                                'id'                => 0,
                                'name'              => '',
                                'nit'               => '',
                                'address'           => '',
                                'phone'             => '',
                                'contact_name'      => '',
                                'contact_cellphone' => '',
                                'contact_email'     => '',
                                'state'             => 1,
                            ];
                            $this->proveedorProductos = [];
                        }
                    } else {
                        $row = $admModel->getProveedorById($proveedorId);

                        if ($row !== null) {
                            $this->proveedorEdit      = $row;
                            $this->proveedorProductos = $admModel->getProveedorProductos($proveedorId);
                        } else {
                            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_NOT_FOUND'), 'warning');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_LOAD_ERROR') . ': ' . $e->getMessage(), 'error');
        }

        $this->_prepareDocument();
        parent::display($tpl);
    }

    /**
     * @return  void
     */
    protected function _prepareDocument(): void
    {
        $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_PROVEEDORES_HEADING'));
    }
}
