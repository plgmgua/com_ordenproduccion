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

$log = $this->log;

// Function to beautify JSON
function beautifyJson($json) {
    if (is_string($json)) {
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
    return $json;
}

// Function to determine badge color based on status
function getStatusBadgeColor($status) {
    $colors = [
        'success' => 'success',
        'Success' => 'success',
        'error' => 'danger',
        'Error' => 'danger',
        'pending' => 'warning',
        'Pending' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}

// Function to determine badge color based on endpoint type
function getEndpointBadgeColor($endpoint) {
    return $endpoint === 'production' ? 'primary' : 'info';
}
?>

<div class="com-ordenproduccion-webhook-detail">
    <!-- Header -->
    <div class="webhook-detail-header mb-4">
        <div class="row">
            <div class="col-md-8">
                <h1 class="webhook-detail-title">
                    <i class="icon-link"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_LOG_DETAIL'); ?>
                </h1>
                <p class="text-muted">
                    <strong><?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_ID'); ?>:</strong> 
                    <?php echo htmlspecialchars($log->webhook_id); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=webhook'); ?>" class="btn btn-secondary">
                    <i class="icon-arrow-left"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_BACK_TO_LOGS'); ?>
                </a>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?></h6>
                    <h4>
                        <span class="badge bg-<?php echo getStatusBadgeColor($log->status); ?>">
                            <?php echo htmlspecialchars($log->status); ?>
                        </span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ENDPOINT_TYPE'); ?></h6>
                    <h4>
                        <span class="badge bg-<?php echo getEndpointBadgeColor($log->endpoint_type); ?>">
                            <?php echo htmlspecialchars(strtoupper($log->endpoint_type)); ?>
                        </span>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PROCESSING_TIME'); ?></h6>
                    <h4><?php echo $log->processing_time ? number_format($log->processing_time, 4) . 's' : 'N/A'; ?></h4>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_RESPONSE_STATUS'); ?></h6>
                    <h4><?php echo htmlspecialchars($log->response_status ?? 'N/A'); ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Information -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-info"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_INFO'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <tbody>
                            <tr>
                                <th style="width: 40%;"><?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_METHOD'); ?></th>
                                <td><code><?php echo htmlspecialchars($log->request_method); ?></code></td>
                            </tr>
                            <tr>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_URL'); ?></th>
                                <td><small><?php echo htmlspecialchars($log->request_url); ?></small></td>
                            </tr>
                            <tr>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_LOG_DATE'); ?></th>
                                <td><?php echo Factory::getDate($log->created)->format('d/m/Y H:i:s'); ?></td>
                            </tr>
                            <?php if ($log->error_message): ?>
                            <tr>
                                <th><?php echo Text::_('COM_ORDENPRODUCCION_ERROR_MESSAGE'); ?></th>
                                <td><span class="text-danger"><?php echo htmlspecialchars($log->error_message); ?></span></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Request Headers -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-list"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_HEADERS'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="max-height: 300px; overflow-y: auto;"><code><?php echo htmlspecialchars(beautifyJson($log->request_headers)); ?></code></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Body -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-code"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_BODY'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?php echo htmlspecialchars(beautifyJson($log->request_body)); ?></code></pre>
                </div>
            </div>
        </div>

        <!-- Response Body -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-out"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RESPONSE_BODY'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <pre class="bg-light p-3 rounded" style="max-height: 400px; overflow-y: auto;"><code><?php echo htmlspecialchars(beautifyJson($log->response_body)); ?></code></pre>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.webhook-detail-header {
    border-bottom: 2px solid #e9ecef;
    padding-bottom: 1rem;
}

.card {
    box-shadow: 0 0 10px rgba(0,0,0,0.05);
    border: 1px solid #e9ecef;
}

pre code {
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    line-height: 1.5;
}
</style>

