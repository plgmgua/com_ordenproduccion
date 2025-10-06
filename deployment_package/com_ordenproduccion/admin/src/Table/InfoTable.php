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
 * Info table (EAV data)
 *
 * @since  1.0.0
 */
class InfoTable extends Table
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
        parent::__construct('#__ordenproduccion_info', 'id', $db);
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
        // Check for valid order number
        if (trim($this->numero_de_orden) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_ORDER_NUMBER_REQUIRED');
            return false;
        }

        // Check for valid field type
        if (trim($this->tipo_de_campo) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_FIELD_TYPE_REQUIRED');
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
