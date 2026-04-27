<?php
/**
 * @package     Grimpsa.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Router\Route;

// This is the default view for contact - redirect to edit layout
$contactId = isset($this->item->id) ? (int)$this->item->id : 0;
$editUrl = Route::_('index.php?option=com_ordenproduccion&view=cliente&layout=edit&id=' . $contactId);

// Redirect to edit layout
header('Location: ' . $editUrl);
exit;
?>