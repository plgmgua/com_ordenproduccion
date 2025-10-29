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
 * Employee Table class
 *
 * @since  3.3.0
 */
class EmployeeTable extends Table
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
        parent::__construct('joomla_ordenproduccion_employees', 'id', $db);
    }
}

