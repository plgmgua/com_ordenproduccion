<?php
/**
 * Payment Info Modal - Reusable popup for viewing payment information
 * @since 3.54.0
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$baseUrl = Uri::base() . 'index.php?option=com_ordenproduccion&task=ajax.getOrderPayments&format=raw';
$paymentProofBaseUrl = Uri::base() . 'index.php?option=com_ordenproduccion&view=paymentproof&order_id=';
$token = Session::getFormToken();
?>
<!-- Payment Info Modal -->
<div class="modal fade" id="paymentInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card"></i> <?php echo Text::_('COM_ORDENPRODUCCION_PAYMENT_INFO_TITLE'); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="paymentInfoBody">
                <div id="paymentInfoLoader" style="display:block; text-align:center; padding: 2rem;">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2"><?php echo Text::_('COM_ORDENPRODUCCION_LOADING'); ?></p>
                </div>
                <div id="paymentInfoError" class="alert alert-danger" style="display:none;"></div>
                <div id="paymentInfoContent" style="display:none;"></div>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    window.paymentInfoBaseUrl = <?php echo json_encode($baseUrl); ?>;
    window.paymentProofBaseUrl = <?php echo json_encode($paymentProofBaseUrl); ?>;
    window.paymentInfoToken = <?php echo json_encode($token); ?>;
});
</script>
