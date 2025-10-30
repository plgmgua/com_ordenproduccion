<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

?>
<div class="com-ordenproduccion-timesheets">
    <h1 class="page-title"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_TITLE'); ?></h1>

    <div class="alert alert-info">
        <?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_VIEW_DEFAULT_DESC'); ?>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_FILTERS'); ?></strong>
        </div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_WEEK_START'); ?></label>
                    <input type="date" class="form-control" />
                </div>
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_GROUP'); ?></label>
                    <select class="form-select"><option>—</option></select>
                </div>
                <div class="col-sm-3">
                    <label class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_EMPLOYEE'); ?></label>
                    <input type="text" class="form-control" placeholder="<?php echo Text::_('COM_ORDENPRODUCCION_SEARCH'); ?>" />
                </div>
                <div class="col-sm-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_WEEKLY_SUMMARY'); ?></strong>
            <div>
                <button class="btn btn-success btn-sm" disabled><?php echo Text::_('COM_ORDENPRODUCCION_APPROVE_SELECTED'); ?></button>
                <button class="btn btn-danger btn-sm" disabled><?php echo Text::_('COM_ORDENPRODUCCION_REJECT_SELECTED'); ?></button>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted mb-0"><?php echo Text::_('COM_ORDENPRODUCCION_TIMESHEETS_PLACEHOLDER'); ?></p>
        </div>
    </div>
</div>


