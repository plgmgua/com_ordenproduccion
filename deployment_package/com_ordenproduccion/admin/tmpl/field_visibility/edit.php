<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Grimpsa\Component\Ordenproduccion\Administrator\View\FieldVisibility\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$app = Factory::getApplication();
$input = $app->input;
?>

<form action="<?php echo Route::_('index.php?option=com_ordenproduccion&view=fieldvisibility'); ?>" 
      method="post" name="adminForm" id="field-visibility-form" class="form-validate">

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_TITLE'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_DESCRIPTION'); ?>
                    </div>

                    <?php echo $this->form->renderFieldset('ventas_group'); ?>
                    
                    <hr class="my-4">
                    
                    <?php echo $this->form->renderFieldset('produccion_group'); ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_INFO'); ?>
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h5><?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_VENTAS_GROUP'); ?></h5>
                        <p><?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_VENTAS_INFO'); ?></p>
                    </div>
                    
                    <div class="alert alert-info">
                        <h5><?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_PRODUCCION_GROUP'); ?></h5>
                        <p><?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_PRODUCCION_INFO'); ?></p>
                    </div>
                    
                    <div class="alert alert-success">
                        <h5><?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_BOTH_GROUPS'); ?></h5>
                        <p><?php echo Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_BOTH_INFO'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="return" value="<?php echo $input->getCmd('return'); ?>" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add some JavaScript to enhance the user experience
    const checkboxes = document.querySelectorAll('input[type="checkbox"]');
    
    checkboxes.forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            // You can add any additional logic here if needed
            console.log('Field visibility changed:', this.name, this.checked);
        });
    });
});
</script>
