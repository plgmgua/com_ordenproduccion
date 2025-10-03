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
 * WebhookLog table
 *
 * @since  1.0.0
 */
class WebhookLogTable extends Table
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
        parent::__construct('#__ordenproduccion_webhook_logs', 'id', $db);
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
        // Check for valid webhook type
        if (trim($this->webhook_type) == '') {
            $this->setError('COM_ORDENPRODUCCION_ERROR_WEBHOOK_TYPE_REQUIRED');
            return false;
        }

        // Set created date if not set
        if (!$this->id) {
            $this->created = \Joomla\CMS\Factory::getDate()->toSql();
        }

        return true;
    }
}
