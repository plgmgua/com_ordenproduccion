<?php
/**
 * Orden de compra — standalone list / detail (Administración).
 *
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Ordencompra;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * @since  3.113.47
 */
class HtmlView extends BaseHtmlView
{
    /** @var bool */
    protected $schemaOk = false;

    /** @var array<int, object> */
    protected $items = [];

    /** @var object|null */
    protected $item = null;

    /** @var array<int, object> */
    protected $lines = [];

    /** @var string  Public URL to vendor quote file (PDF or image) for detail view */
    protected $vendorQuoteUrl = '';

    /** @var string  pdf|image|'' */
    protected $vendorQuoteKind = '';

    /**
     * @param   string|null  $tpl
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $app   = Factory::getApplication();
        $input = $app->input;

        if (!AccessHelper::canViewOrdenCompra()) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=administracion&tab=resumen', false));

            return;
        }

        $lang = $app->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
        $lang->load('com_ordenproduccion', JPATH_ADMINISTRATOR . '/components/com_ordenproduccion');

        $id = $input->getInt('id', 0);

        $model = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Ordencompra', 'Site', ['ignore_request' => true]);

        $this->schemaOk = $model && method_exists($model, 'hasSchema') && $model->hasSchema();
        $this->items    = [];
        $this->item     = null;
        $this->lines    = [];

        if (!$this->schemaOk) {
            parent::display($tpl);

            return;
        }

        if ($id > 0) {
            $this->item = $model->getItemById($id);
            if (!$this->item) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_NOT_FOUND'), 'warning');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=ordencompra', false));

                return;
            }
            $this->lines = $model->getLines($id);

            $this->vendorQuoteUrl  = '';
            $this->vendorQuoteKind = '';
            $evtId                 = (int) ($this->item->vendor_quote_event_id ?? 0);
            $precotId              = (int) ($this->item->precotizacion_id ?? 0);
            if ($evtId > 0 && $precotId > 0) {
                $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
                    ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
                if ($precotModel && method_exists($precotModel, 'getVendorQuoteEvent')) {
                    $ev = $precotModel->getVendorQuoteEvent($evtId);
                    if ($ev && (int) ($ev->pre_cotizacion_id ?? 0) === $precotId) {
                        $rel = trim((string) ($ev->vendor_quote_attachment ?? ''));
                        if ($rel !== '' && strpos($rel, '..') === false) {
                            $ext = strtolower((string) pathinfo($rel, PATHINFO_EXTENSION));
                            if ($ext === 'pdf') {
                                $this->vendorQuoteKind = 'pdf';
                            } elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
                                $this->vendorQuoteKind = 'image';
                            }
                            if ($this->vendorQuoteKind !== '') {
                                $this->vendorQuoteUrl = rtrim(Uri::root(), '/') . '/' . str_replace('\\', '/', ltrim($rel, '/'));
                            }
                        }
                    }
                }
            }
        } else {
            $this->items = $model->getListItems();
        }

        if ($this->item) {
            $this->document->setTitle(
                Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_TITLE') . ' ' . ($this->item->number ?? '')
            );
        } else {
            $this->document->setTitle(Text::_('COM_ORDENPRODUCCION_ORDENCOMPRA_LIST_TITLE'));
        }

        parent::display($tpl);
    }
}
