<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * Ordenes table
 *
 * @since  1.0.0
 */
class OrdenesTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   1.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_ordenproduccion.orden';
        parent::__construct('#__ordenproduccion_ordenes', 'id', $db);
    }

    /**
     * Method to compute the default name of the asset.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function _getAssetName()
    {
        $k = $this->_tbl_key;
        return 'com_ordenproduccion.orden.' . (int) $this->$k;
    }

    /**
     * Method to return the title to use for the asset table.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    protected function _getAssetTitle()
    {
        return $this->orden_de_trabajo;
    }

    /**
     * Method to get the parent asset under which to register this one.
     *
     * @param   Table   $table  A Table object for the asset parent.
     * @param   integer $id     Id to look up
     *
     * @return  integer
     *
     * @since   1.0.0
     */
    protected function _getAssetParentId(Table $table = null, $id = null)
    {
        $assetId = null;

        // This is a article under a category.
        if ($this->id) {
            // Build the query to get the asset id for the parent category.
            $query = $this->_db->getQuery(true)
                ->select($this->_db->quoteName('asset_id'))
                ->from($this->_db->quoteName('#__assets'))
                ->where($this->_db->quoteName('id') . ' = ' . (int) $this->id);

            // Get the asset id from the database.
            $this->_db->setQuery($query);

            if ($result = $this->_db->loadResult()) {
                $assetId = (int) $result;
            }
        }

        // Return the asset id.
        if ($assetId) {
            return $assetId;
        } else {
            return parent::_getAssetParentId($table, $id);
        }
    }

    /**
     * Overloaded bind function
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  An optional array or space separated list of properties
     *                          to ignore while binding.
     *
     * @return  mixed  Null if operation was satisfactory, otherwise returns an error
     *
     * @since   1.0.0
     */
    public function bind($array, $ignore = '')
    {
        // Bind the rules.
        if (isset($array['rules']) && is_array($array['rules'])) {
            $rules = new \Joomla\CMS\Access\Rules($array['rules']);
            $this->setRules($rules);
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return  boolean  True on success, false on failure
     *
     * @since   1.0.0
     */
    public function check()
    {
        // Check for valid name
        if (trim($this->orden_de_trabajo) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_ORDER_NUMBER_REQUIRED');
            return false;
        }

        // Check for valid client name
        if (trim($this->nombre_del_cliente) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_CLIENT_NAME_REQUIRED');
            return false;
        }

        // Check for valid work description
        if (trim($this->descripcion_de_trabajo) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_WORK_DESCRIPTION_REQUIRED');
            return false;
        }

        // Check for duplicate order number
        $query = $this->_db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->_db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->_db->quoteName('orden_de_trabajo') . ' = ' . $this->_db->quote($this->orden_de_trabajo));

        if ($this->id) {
            $query->where($this->_db->quoteName('id') . ' != ' . (int) $this->id);
        }

        $this->_db->setQuery($query);

        if ($this->_db->loadResult() > 0) {
            $this->setError('COM_ORDENPRODUCCION_ERROR_DUPLICATE_ORDER_NUMBER');
            return false;
        }

        // Set created date if not set
        if (!$this->id) {
            $this->created = \Joomla\CMS\Factory::getDate()->toSql();
        }

        return true;
    }

    /**
     * Overloaded store function
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @since   1.0.0
     */
    public function store($updateNulls = false)
    {
        $date = \Joomla\CMS\Factory::getDate();

        if ($this->id) {
            // Existing item
            $this->modified = $date->toSql();
        } else {
            // New item
            if (!(int) $this->created) {
                $this->created = $date->toSql();
            }
        }

        return parent::store($updateNulls);
    }
}
