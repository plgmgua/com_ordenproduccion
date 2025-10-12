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
use Joomla\CMS\Language\Text;

$app = Factory::getApplication();
$input = $app->input;
$activeTab = $input->get('tab', 'statistics', 'string');
?>

<div class="admin-dashboard-container">
    <h1 style="margin-bottom: 30px;">
        <i class="fas fa-tachometer-alt"></i>
        <?php echo Text::_('COM_ORDENPRODUCCION_ADMINISTRACION_TITLE'); ?>
    </h1>
    
    <!-- Tabs -->
    <?php echo $this->loadTemplate('tabs'); ?>
</div>

<style>
.admin-dashboard-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}
</style>
