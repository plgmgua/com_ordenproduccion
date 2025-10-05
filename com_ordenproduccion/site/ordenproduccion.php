<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Exception;

try {
    $component = Factory::getApplication()->bootComponent('com_ordenproduccion');
    $component->dispatch();
} catch (Exception $e) {
    echo 'Component Error: ' . $e->getMessage();
}
