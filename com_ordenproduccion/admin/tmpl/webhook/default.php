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
use Joomla\CMS\Session\Session;

?>

<div class="com-ordenproduccion-webhook">
    <!-- Webhook Header -->
    <div class="webhook-header mb-4">
        <div class="row">
            <div class="col-md-8">
                <h1 class="webhook-title">
                    <i class="icon-link"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_CONFIG'); ?>
                </h1>
                <p class="webhook-subtitle">
                    <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_SUBTITLE'); ?>
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="webhook-status">
                    <span class="badge bg-success">
                        <i class="icon-checkmark"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_ACTIVE'); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-primary">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-link"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo number_format($this->statistics['total_requests']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_TOTAL_REQUESTS'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-success">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo number_format($this->statistics['recent_requests']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_RECENT_REQUESTS'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-info">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-list"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo number_format($this->statistics['recent_orders']); ?></h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_RECENT_ORDERS'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card stat-card stat-card-warning">
                <div class="card-body">
                    <div class="stat-icon">
                        <i class="icon-chart"></i>
                    </div>
                    <div class="stat-content">
                        <h3 class="stat-number"><?php echo $this->statistics['success_rate']; ?>%</h3>
                        <p class="stat-label"><?php echo Text::_('COM_ORDENPRODUCCION_SUCCESS_RATE'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Webhook Configuration -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-cog"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_ENDPOINTS'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="webhook-endpoints">
                        <div class="endpoint-item mb-3">
                            <label class="form-label">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_MAIN_ENDPOINT'); ?></strong>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($this->endpoints['main']); ?>" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($this->endpoints['main']); ?>')">
                                    <i class="icon-copy"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">
                                <?php echo Text::_('COM_ORDENPRODUCCION_MAIN_ENDPOINT_DESC'); ?>
                            </small>
                        </div>

                        <div class="endpoint-item mb-3">
                            <label class="form-label">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_TEST_ENDPOINT'); ?></strong>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($this->endpoints['test']); ?>" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($this->endpoints['test']); ?>')">
                                    <i class="icon-copy"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">
                                <?php echo Text::_('COM_ORDENPRODUCCION_TEST_ENDPOINT_DESC'); ?>
                            </small>
                        </div>

                        <div class="endpoint-item mb-3">
                            <label class="form-label">
                                <strong><?php echo Text::_('COM_ORDENPRODUCCION_HEALTH_ENDPOINT'); ?></strong>
                            </label>
                            <div class="input-group">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($this->endpoints['health']); ?>" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($this->endpoints['health']); ?>')">
                                    <i class="icon-copy"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">
                                <?php echo Text::_('COM_ORDENPRODUCCION_HEALTH_ENDPOINT_DESC'); ?>
                            </small>
                        </div>
                    </div>

                    <!-- Test Webhook Form -->
                    <div class="test-webhook mt-4">
                        <h6><?php echo Text::_('COM_ORDENPRODUCCION_TEST_WEBHOOK'); ?></h6>
                        <form id="testWebhookForm">
                            <div class="mb-3">
                                <label for="test_webhook_url" class="form-label"><?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_URL'); ?></label>
                                <input type="url" class="form-control" id="test_webhook_url" name="webhook_url" 
                                       value="<?php echo htmlspecialchars($this->endpoints['main']); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="icon-play"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_SEND_TEST'); ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Webhook Information -->
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-info"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_INFO'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="webhook-info">
                        <div class="info-item mb-3">
                            <h6><?php echo Text::_('COM_ORDENPRODUCCION_PAYLOAD_FORMAT'); ?></h6>
                            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_PAYLOAD_FORMAT_DESC'); ?></p>
                            <pre class="bg-light p-3 rounded"><code>{
  "request_title": "Solicitud Ventas a Produccion",
  "form_data": {
    "cliente": "Client Name",
    "descripcion_trabajo": "Work Description",
    "fecha_entrega": "15/10/2025",
    ...
  }
}</code></pre>
                        </div>

                        <div class="info-item mb-3">
                            <h6><?php echo Text::_('COM_ORDENPRODUCCION_RESPONSE_FORMAT'); ?></h6>
                            <p class="text-muted"><?php echo Text::_('COM_ORDENPRODUCCION_RESPONSE_FORMAT_DESC'); ?></p>
                            <pre class="bg-light p-3 rounded"><code>{
  "success": true,
  "message": "Order created successfully",
  "timestamp": "2025-01-27 16:00:00",
  "data": {
    "order_id": 123,
    "order_number": "CLIENT-20250127-160000"
  }
}</code></pre>
                        </div>

                        <div class="info-item">
                            <h6><?php echo Text::_('COM_ORDENPRODUCCION_REQUIRED_FIELDS'); ?></h6>
                            <ul class="list-unstyled">
                                <li><code>request_title</code> - <?php echo Text::_('COM_ORDENPRODUCCION_REQUIRED_REQUEST_TITLE'); ?></li>
                                <li><code>form_data.cliente</code> - <?php echo Text::_('COM_ORDENPRODUCCION_REQUIRED_CLIENT'); ?></li>
                                <li><code>form_data.descripcion_trabajo</code> - <?php echo Text::_('COM_ORDENPRODUCCION_REQUIRED_WORK_DESC'); ?></li>
                                <li><code>form_data.fecha_entrega</code> - <?php echo Text::_('COM_ORDENPRODUCCION_REQUIRED_DELIVERY_DATE'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Webhook Logs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="icon-list"></i>
                        <?php echo Text::_('COM_ORDENPRODUCCION_RECENT_WEBHOOK_LOGS'); ?>
                    </h5>
                    <div class="card-tools">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshLogs">
                            <i class="icon-refresh"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_REFRESH'); ?>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($this->logs)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_LOG_TYPE'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_LOG_DATA'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_LOG_IP'); ?></th>
                                        <th><?php echo Text::_('COM_ORDENPRODUCCION_LOG_DATE'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($this->logs as $log): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-<?php echo $this->getLogTypeColor($log->type); ?>">
                                                    <?php echo $this->getLogTypeText($log->type); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="log-data">
                                                    <?php if ($log->type === 'webhook_request'): ?>
                                                        <?php 
                                                        $data = json_decode($log->data, true);
                                                        if (isset($data['form_data']['cliente'])) {
                                                            echo '<strong>' . htmlspecialchars($data['form_data']['cliente']) . '</strong><br>';
                                                            echo '<small class="text-muted">' . htmlspecialchars($data['form_data']['descripcion_trabajo']) . '</small>';
                                                        } else {
                                                            echo '<small class="text-muted">' . htmlspecialchars(substr($log->data, 0, 100)) . '...</small>';
                                                        }
                                                        ?>
                                                    <?php else: ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars(substr($log->data, 0, 100)); ?>...</small>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($log->ip_address); ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted"><?php echo $this->formatDate($log->created); ?></small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="icon-list icon-3x text-muted"></i>
                            <p class="text-muted mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_NO_WEBHOOK_LOGS'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for AJAX requests -->
<form id="webhookForm" style="display: none;">
    <input type="hidden" name="option" value="com_ordenproduccion">
    <input type="hidden" name="task" value="">
    <input type="hidden" name="format" value="json">
    <input type="hidden" name="<?php echo Factory::getSession()->getFormToken(); ?>" value="1">
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test webhook form
    const testForm = document.getElementById('testWebhookForm');
    if (testForm) {
        testForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const webhookUrl = formData.get('webhook_url');
            
            if (!webhookUrl) {
                alert('<?php echo Text::_('COM_ORDENPRODUCCION_WEBHOOK_URL_REQUIRED'); ?>');
                return;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="icon-spinner icon-spin"></i> <?php echo Text::_('COM_ORDENPRODUCCION_TESTING'); ?>...';
            submitBtn.disabled = true;
            
            // Submit test request
            const form = document.getElementById('webhookForm');
            form.task.value = 'webhook.testWebhook';
            form.appendChild(document.createElement('input')).name = 'webhook_url';
            form.querySelector('input[name="webhook_url"]').value = webhookUrl;
            form.submit();
        });
    }
    
    // Copy to clipboard function
    window.copyToClipboard = function(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            toast.innerHTML = '<?php echo Text::_('COM_ORDENPRODUCCION_COPIED_TO_CLIPBOARD'); ?>';
            toast.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 10px 20px; border-radius: 4px; z-index: 9999;';
            document.body.appendChild(toast);
            
            setTimeout(function() {
                document.body.removeChild(toast);
            }, 3000);
        });
    };
});
</script>
