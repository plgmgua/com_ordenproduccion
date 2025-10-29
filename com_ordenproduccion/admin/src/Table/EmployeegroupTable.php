<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

defined('_JEXEC') or die;

/**
 * Employee Group Table class
 *
 * @since  3.3.0
 */
class EmployeegroupTable extends Table
{
    /**
     * Constructor
     *
     * @param   DatabaseDriver  $db  Database connector object
     *
     * @since   3.3.0
     */
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__ordenproduccion_employee_groups', 'id', $db);
    }
}

