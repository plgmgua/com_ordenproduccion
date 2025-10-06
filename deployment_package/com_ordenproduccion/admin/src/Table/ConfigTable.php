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
 * Config table
 *
 * @since  1.0.0
 */
class ConfigTable extends Table
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
        parent::__construct('#__ordenproduccion_config', 'id', $db);
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
        // Check for valid setting key
        if (trim($this->setting_key) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_SETTING_KEY_REQUIRED');
            return false;
        }

        // Check for duplicate setting key
        $query = $this->_db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->_db->quoteName('#__ordenproduccion_config'))
            ->where($this->_db->quoteName('setting_key') . ' = ' . $this->_db->quote($this->setting_key));

        if ($this->id) {
            $query->where($this->_db->quoteName('id') . ' != ' . (int) $this->id);
        }

        $this->_db->setQuery($query);

        if ($this->_db->loadResult() > 0) {
            $this->setError('COM_ORDENPRODUCCION_ERROR_DUPLICATE_SETTING_KEY');
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
