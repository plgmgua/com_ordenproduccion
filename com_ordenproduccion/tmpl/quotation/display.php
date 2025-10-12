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
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_VIEW_TITLE'); ?> - <?php echo htmlspecialchars($this->item->orden_de_trabajo); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .quotation-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .quotation-header {
            background: #007cba;
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .quotation-title {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        
        .order-info {
            text-align: right;
        }
        
        .order-number {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .order-client {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .quotation-content {
            padding: 20px;
        }
        
        .quotation-file-container {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #fafafa;
            margin-bottom: 20px;
        }
        
        .quotation-file-container.has-file {
            border-style: solid;
            border-color: #28a745;
            background: #f8fff8;
        }
        
        .quotation-file-container.no-file {
            border-color: #dc3545;
            background: #fff8f8;
        }
        
        .file-icon {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .file-icon.pdf {
            color: #dc3545;
        }
        
        .file-icon.image {
            color: #28a745;
        }
        
        .file-icon.document {
            color: #007cba;
        }
        
        .file-icon.unknown {
            color: #6c757d;
        }
        
        .file-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .file-url {
            font-size: 14px;
            color: #666;
            word-break: break-all;
            margin-bottom: 15px;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #007cba;
            color: white;
        }
        
        .btn-primary:hover {
            background: #005a8b;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .order-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .order-details h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .quotation-header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .order-info {
                text-align: center;
            }
            
            .file-actions {
                flex-direction: column;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="quotation-container">
        <div class="quotation-header">
            <h1 class="quotation-title">
                <i class="fas fa-file-invoice"></i>
                <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_VIEW_TITLE'); ?>
            </h1>
            <div class="order-info">
                <div class="order-number"><?php echo htmlspecialchars($this->item->orden_de_trabajo); ?></div>
                <div class="order-client"><?php echo htmlspecialchars($this->item->client_name); ?></div>
            </div>
        </div>
        
        <div class="quotation-content">
            <?php if ($this->isQuotationFileAccessible()): ?>
                <div class="quotation-file-container has-file">
                    <i class="fas fa-file-<?php echo $this->getQuotationFileType(); ?> file-icon <?php echo $this->getQuotationFileType(); ?>"></i>
                    <div class="file-title">
                        <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_FILE_AVAILABLE'); ?>
                    </div>
                    <div class="file-url">
                        <?php echo htmlspecialchars($this->getQuotationFileUrl()); ?>
                    </div>
                    <div class="file-actions">
                        <a href="<?php echo htmlspecialchars($this->getQuotationFileUrl()); ?>" 
                           target="_blank" 
                           class="btn btn-primary">
                            <i class="fas fa-external-link-alt"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_OPEN_QUOTATION'); ?>
                        </a>
                        
                        <?php if ($this->getQuotationFileType() === 'pdf'): ?>
                            <a href="<?php echo htmlspecialchars($this->getQuotationFileUrl()); ?>" 
                               download 
                               class="btn btn-success">
                                <i class="fas fa-download"></i>
                                <?php echo Text::_('COM_ORDENPRODUCCION_DOWNLOAD_PDF'); ?>
                            </a>
                        <?php endif; ?>
                        
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i>
                            <?php echo Text::_('COM_ORDENPRODUCCION_PRINT'); ?>
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <div class="quotation-file-container no-file">
                    <i class="fas fa-exclamation-triangle file-icon unknown"></i>
                    <div class="file-title">
                        <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_FILE_NOT_FOUND'); ?>
                    </div>
                    <div class="file-url">
                        <?php echo Text::_('COM_ORDENPRODUCCION_QUOTATION_FILE_NOT_FOUND_DESC'); ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Order Details -->
            <div class="order-details">
                <h3>
                    <i class="fas fa-info-circle"></i>
                    <?php echo Text::_('COM_ORDENPRODUCCION_ORDER_DETAILS'); ?>
                </h3>
                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_ORDER_NUMBER'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($this->item->orden_de_trabajo); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_CLIENT'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($this->item->client_name); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_SALES_AGENT'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($this->item->sales_agent); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_REQUEST_DATE'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($this->item->request_date) ? HTMLHelper::_('date', $this->item->request_date, 'Y-m-d') : '-'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_DELIVERY_DATE'); ?>:</span>
                        <span class="detail-value"><?php echo !empty($this->item->delivery_date) ? HTMLHelper::_('date', $this->item->delivery_date, 'Y-m-d') : '-'; ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_STATUS'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($this->item->status); ?></span>
                    </div>
                    <?php if (!empty($this->item->work_description)): ?>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label"><?php echo Text::_('COM_ORDENPRODUCCION_WORK_DESCRIPTION'); ?>:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($this->item->work_description); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</body>
</html>
