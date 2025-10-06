<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Ordenes controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenesController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_ORDENES';

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel  The model.
     *
     * @since   1.0.0
     */
    public function getModel($name = 'Ordenes', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Method to save the submitted ordering values for records via AJAX.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function saveOrderAjax()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->app->close();
        }

        $pks = $this->input->post->get('cid', [], 'array');
        $order = $this->input->post->get('order', [], 'array');

        // Sanitize the input
        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        // Get the model
        $model = $this->getModel();

        // Save the ordering
        $return = $model->saveorder($pks, $order);

        if ($return) {
            echo "1";
        }

        // Close the application
        $this->app->close();
    }

    /**
     * Method to publish a list of items
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function publish()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Get items to publish from the request.
        $cid = $this->input->get('cid', [], 'array');
        $data = ['publish' => 1, 'unpublish' => 0, 'archive' => 2, 'trash' => -2, 'report' => -3];
        $task = $this->getTask();
        $value = ArrayHelper::getValue($data, $task, 0, 'int');

        if (empty($cid)) {
            $this->app->enqueueMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Make sure the item ids are integers
            $cid = ArrayHelper::toInteger($cid);

            // Publish the items.
            if (!$model->publish($cid, $value)) {
                $this->app->enqueueMessage($model->getError(), 'error');
            } else {
                if ($value == 1) {
                    $ntext = $this->text_prefix . '_N_ITEMS_PUBLISHED';
                } elseif ($value == 0) {
                    $ntext = $this->text_prefix . '_N_ITEMS_UNPUBLISHED';
                } elseif ($value == 2) {
                    $ntext = $this->text_prefix . '_N_ITEMS_ARCHIVED';
                } else {
                    $ntext = $this->text_prefix . '_N_ITEMS_TRASHED';
                }

                $this->app->enqueueMessage(Text::plural($ntext, count($cid)));
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
    }

    /**
     * Method to delete a list of items
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function delete()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Get items to remove from the request.
        $cid = $this->input->get('cid', [], 'array');

        if (!is_array($cid) || count($cid) < 1) {
            $this->app->enqueueMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
        } else {
            // Get the model.
            $model = $this->getModel();

            // Make sure the item ids are integers
            $cid = ArrayHelper::toInteger($cid);

            // Remove the items.
            if ($model->delete($cid)) {
                $this->app->enqueueMessage(Text::plural($this->text_prefix . '_N_ITEMS_DELETED', count($cid)));
            } else {
                $this->app->enqueueMessage($model->getError(), 'error');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
    }

    /**
     * Method to duplicate items
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function duplicate()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $pks = $this->input->post->get('cid', [], 'array');

        ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            $this->app->enqueueMessage(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), 'warning');
        } else {
            $model = $this->getModel();
            $duplicates = 0;

            foreach ($pks as $pk) {
                if ($model->duplicate($pk)) {
                    $duplicates++;
                }
            }

            if ($duplicates) {
                $this->app->enqueueMessage(Text::plural($this->text_prefix . '_N_ITEMS_DUPLICATED', $duplicates));
            } else {
                $this->app->enqueueMessage(Text::_($this->text_prefix . '_NO_ITEMS_DUPLICATED'), 'warning');
            }
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
    }

    /**
     * Method to export orders to CSV
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function exportCsv()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel();
            $items = $model->getItems();

            $filename = 'ordenes_' . date('Y-m-d_H-i-s') . '.csv';

            // Set headers for CSV download
            $this->app->setHeader('Content-Type', 'text/csv');
            $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->app->setHeader('Cache-Control', 'no-cache, must-revalidate');
            $this->app->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

            $output = fopen('php://output', 'w');

            // Write CSV headers
            if (!empty($items)) {
                $headers = [
                    'ID',
                    'Order Number',
                    'Client Name',
                    'Work Description',
                    'Delivery Date',
                    'Status',
                    'Type',
                    'Created',
                    'Created By'
                ];
                fputcsv($output, $headers);

                // Write data
                foreach ($items as $item) {
                    $row = [
                        $item->id,
                        $item->orden_de_trabajo,
                        $item->nombre_del_cliente,
                        $item->descripcion_de_trabajo,
                        $item->fecha_de_entrega_formatted ?? $item->fecha_de_entrega,
                        $item->status,
                        $item->type,
                        $item->created_formatted ?? $item->created,
                        $item->created_by
                    ];
                    fputcsv($output, $row);
                }
            }

            fclose($output);
            $this->app->close();

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_EXPORTING_ORDERS', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }
    }

    /**
     * Method to export orders to Excel
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function exportExcel()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel();
            $items = $model->getItems();

            $filename = 'ordenes_' . date('Y-m-d_H-i-s') . '.xlsx';

            // For now, export as CSV with Excel extension
            // In a real implementation, you would use a library like PhpSpreadsheet
            $this->app->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            $this->app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $this->app->setHeader('Cache-Control', 'no-cache, must-revalidate');
            $this->app->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');

            $output = fopen('php://output', 'w');

            // Write CSV headers (Excel will interpret this correctly)
            if (!empty($items)) {
                $headers = [
                    'ID',
                    'Order Number',
                    'Client Name',
                    'Work Description',
                    'Delivery Date',
                    'Status',
                    'Type',
                    'Created',
                    'Created By'
                ];
                fputcsv($output, $headers);

                // Write data
                foreach ($items as $item) {
                    $row = [
                        $item->id,
                        $item->orden_de_trabajo,
                        $item->nombre_del_cliente,
                        $item->descripcion_de_trabajo,
                        $item->fecha_de_entrega_formatted ?? $item->fecha_de_entrega,
                        $item->status,
                        $item->type,
                        $item->created_formatted ?? $item->created,
                        $item->created_by
                    ];
                    fputcsv($output, $row);
                }
            }

            fclose($output);
            $this->app->close();

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_EXPORTING_ORDERS', $e->getMessage()),
                'error'
            );
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }
    }

    /**
     * Method to batch update order status
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function batchStatus()
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $cid = $this->input->get('cid', [], 'array');
        $status = $this->input->get('batch_status', '', 'string');

        if (empty($cid) || empty($status)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_NO_ITEMS_SELECTED'), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $model = $this->getModel();
            $updated = 0;

            foreach ($cid as $id) {
                if ($model->updateOrderStatus($id, $status)) {
                    $updated++;
                }
            }

            if ($updated > 0) {
                $this->app->enqueueMessage(
                    Text::plural('COM_ORDENPRODUCCION_N_ORDERS_STATUS_UPDATED', $updated),
                    'success'
                );
            } else {
                $this->app->enqueueMessage(
                    Text::_('COM_ORDENPRODUCCION_NO_ORDERS_UPDATED'),
                    'warning'
                );
            }

        } catch (\Exception $e) {
            $this->app->enqueueMessage(
                Text::sprintf('COM_ORDENPRODUCCION_ERROR_UPDATING_STATUS', $e->getMessage()),
                'error'
            );
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
    }
}
