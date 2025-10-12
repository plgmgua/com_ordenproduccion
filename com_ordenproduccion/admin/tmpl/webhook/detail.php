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

// Function to beautify JSON with special handling for nested JSON
function beautifyJson($json) {
    if (is_string($json)) {
        $decoded = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // Check if there's a nested raw_body that's also JSON
            if (isset($decoded['raw_body']) && is_string($decoded['raw_body'])) {
                $nestedDecoded = json_decode($decoded['raw_body'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Replace raw_body with formatted nested JSON
                    $decoded['raw_body'] = $nestedDecoded;
                }
            }
            
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
        <div class="col-lg-2 col-md-6 mb-3">
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

        <div class="col-lg-2 col-md-6 mb-3">
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

        <div class="col-lg-2 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_ID'); ?></h6>
                    <h4>
                        <?php if (!empty($log->order_id)): ?>
                            <a href="<?php echo Route::_('index.php?option=com_ordenproduccion&view=orden&id=' . $log->order_id); ?>" 
                               target="_blank" class="badge bg-success">
                                ID: <?php echo (int) $log->order_id; ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_ORDEN_TRABAJO'); ?></h6>
                    <h4>
                        <?php if (!empty($log->orden_de_trabajo)): ?>
                            <span class="badge bg-primary">
                                <?php echo htmlspecialchars($log->orden_de_trabajo); ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </h4>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PROCESSING_TIME'); ?></h6>
                    <h4><?php echo $log->processing_time ? number_format($log->processing_time, 4) . 's' : 'N/A'; ?></h4>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-6 mb-3">
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
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="icon-code"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_BODY'); ?>
                    </h5>
                    <button type="button" class="btn btn-sm btn-primary" onclick="copyToClipboard('request-body-content', this)">
                        <i class="icon-copy"></i> Copy
                    </button>
                </div>
                <div class="card-body">
                    <pre class="json-display bg-dark text-light p-3 rounded" style="max-height: 600px; overflow-y: auto;" id="request-body-content"><code><?php echo htmlspecialchars(beautifyJson($log->request_body)); ?></code></pre>
                    <?php 
                    // Show raw body size
                    $requestBodySize = strlen($log->request_body);
                    $beautified = beautifyJson($log->request_body);
                    $beautifiedSize = strlen($beautified);
                    ?>
                    <small class="text-muted">
                        <strong>Size:</strong> <?php echo number_format($requestBodySize); ?> bytes (raw) | 
                        <?php echo number_format($beautifiedSize); ?> bytes (formatted)
                    </small>
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
    font-family: 'Courier New', 'Monaco', 'Menlo', monospace;
    font-size: 13px;
    line-height: 1.6;
    letter-spacing: 0.3px;
}

/* JSON Display Enhancements */
.json-display {
    background-color: #1e1e1e !important;
    color: #d4d4d4 !important;
    border: 1px solid #333;
    box-shadow: inset 0 2px 6px rgba(0,0,0,0.3);
}

.json-display code {
    color: #d4d4d4 !important;
    text-shadow: 0 1px 0 rgba(0,0,0,0.3);
}

/* Make scrollbars prettier */
.json-display::-webkit-scrollbar {
    width: 12px;
    height: 12px;
}

.json-display::-webkit-scrollbar-track {
    background: #2d2d2d;
    border-radius: 4px;
}

.json-display::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 4px;
}

.json-display::-webkit-scrollbar-thumb:hover {
    background: #666;
}

/* Copy button feedback */
.btn-success-flash {
    animation: flashSuccess 0.5s ease-in-out;
}

@keyframes flashSuccess {
    0%, 100% {
        background-color: #0d6efd;
    }
    50% {
        background-color: #28a745;
    }
}
</style>

<script>
// Copy to clipboard function
function copyToClipboard(elementId, button) {
    const element = document.getElementById(elementId);
    const text = element.textContent || element.innerText;
    
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            // Success feedback
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="icon-check"></i> Copied!';
            button.classList.add('btn-success-flash');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success-flash');
            }, 2000);
        }).catch(err => {
            console.error('Failed to copy:', err);
            alert('Failed to copy to clipboard');
        });
    } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        
        try {
            document.execCommand('copy');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="icon-check"></i> Copied!';
            button.classList.add('btn-success-flash');
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.classList.remove('btn-success-flash');
            }, 2000);
        } catch (err) {
            console.error('Fallback copy failed:', err);
            alert('Failed to copy to clipboard');
        }
        
        document.body.removeChild(textArea);
    }
}
</script>

