<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace YourCompany\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;

/**
 * Quotation model class
 */
class QuotationModel extends ItemModel
{
    /**
     * Model context string
     *
     * @var    string
     */
    protected $_context = 'com_ordenproduccion.quotation';

    /**
     * Method to get order data
     *
     * @param   integer  $pk  The id of the order
     *
     * @return  mixed  Object on success, false on failure
     */
    public function getOrder($pk = null)
    {
        if ($pk === null) {
            $pk = Factory::getApplication()->input->getInt('order_id');
        }

        if (!$pk) {
            return false;
        }

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true);

            $query->select([
                'id', 'orden_de_trabajo', 'order_number', 'client_name', 'nit',
                'invoice_value', 'work_description', 'print_color', 'dimensions',
                'delivery_date', 'material', 'request_date', 'sales_agent', 'status',
                'invoice_number', 'quotation_files', 'created', 'created_by',
                'modified', 'modified_by', 'state', 'version'
            ])
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . $db->quote($pk))
            ->where($db->quoteName('state') . ' = 1');

            $db->setQuery($query);
            $order = $db->loadObject();

            return $order;
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Method to get an item
     *
     * @param   integer  $pk  The id of the item
     *
     * @return  mixed  Object on success, false on failure
     */
    public function getItem($pk = null)
    {
        return $this->getOrder($pk);
    }
}
