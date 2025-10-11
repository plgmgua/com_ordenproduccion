<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('bootstrap.framework');
?>

<style>
.cotizaciones-container {
    min-height: 60vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    text-align: center;
}

.cotizaciones-icon {
    font-size: 120px;
    color: #0066cc;
    margin-bottom: 30px;
    animation: pulse 2s infinite;
}

.cotizaciones-title {
    font-size: 2.5rem;
    color: #333;
    margin-bottom: 20px;
    font-weight: 600;
}

.cotizaciones-message {
    font-size: 1.5rem;
    color: #666;
    max-width: 600px;
    line-height: 1.6;
    margin-bottom: 30px;
}

.cotizaciones-image {
    max-width: 400px;
    width: 100%;
    height: auto;
    margin-top: 20px;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.construction-badge {
    display: inline-block;
    background: #ffc107;
    color: #333;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    margin-top: 20px;
    font-size: 1.1rem;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.05);
        opacity: 0.8;
    }
}

.construction-details {
    margin-top: 40px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    max-width: 500px;
}

.construction-details h3 {
    color: #0066cc;
    margin-bottom: 15px;
}

.construction-details ul {
    list-style: none;
    padding: 0;
    text-align: left;
}

.construction-details li {
    padding: 8px 0;
    border-bottom: 1px solid #dee2e6;
}

.construction-details li:last-child {
    border-bottom: none;
}

.construction-details li::before {
    content: "✓";
    color: #28a745;
    font-weight: bold;
    margin-right: 10px;
}
</style>

<div class="cotizaciones-container">
    <div class="cotizaciones-icon">
        🏗️
    </div>
    
    <h1 class="cotizaciones-title">
        <?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_TITLE'); ?>
    </h1>
    
    <p class="cotizaciones-message">
        <?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_UNDER_CONSTRUCTION'); ?>
    </p>
    
    <span class="construction-badge">
        <?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_COMING_SOON'); ?>
    </span>
    
    <!-- Construction Image -->
    <svg class="cotizaciones-image" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300">
        <!-- Background -->
        <rect width="400" height="300" fill="#f0f4f8"/>
        
        <!-- Construction Crane -->
        <g>
            <!-- Crane Base -->
            <rect x="140" y="220" width="40" height="60" fill="#ff9800"/>
            <rect x="130" y="270" width="60" height="10" fill="#f57c00"/>
            
            <!-- Crane Tower -->
            <rect x="155" y="100" width="10" height="120" fill="#ff9800"/>
            
            <!-- Crane Arm -->
            <rect x="100" y="95" width="120" height="8" fill="#ffc107"/>
            <line x1="165" y1="100" x2="200" y2="140" stroke="#666" stroke-width="2"/>
            
            <!-- Hook -->
            <line x1="200" y1="100" x2="200" y2="160" stroke="#666" stroke-width="2" stroke-dasharray="3,3"/>
            <circle cx="200" cy="165" r="5" fill="#666"/>
        </g>
        
        <!-- Building Blocks -->
        <g>
            <rect x="240" y="200" width="60" height="40" fill="#2196F3" opacity="0.8"/>
            <rect x="240" y="240" width="60" height="40" fill="#2196F3" opacity="0.6"/>
            <rect x="180" y="240" width="60" height="40" fill="#64B5F6" opacity="0.6"/>
        </g>
        
        <!-- Worker -->
        <g transform="translate(280, 180)">
            <!-- Head -->
            <circle cx="0" cy="-10" r="8" fill="#FFD700"/>
            <circle cx="0" cy="-18" r="6" fill="#ffa726"/>
            <!-- Body -->
            <rect x="-6" y="-5" width="12" height="15" fill="#0066cc"/>
            <!-- Hard Hat -->
            <ellipse cx="0" cy="-20" rx="8" ry="3" fill="#FFD700"/>
        </g>
        
        <!-- Clouds -->
        <ellipse cx="80" cy="40" rx="25" ry="15" fill="#fff" opacity="0.8"/>
        <ellipse cx="100" cy="35" rx="30" ry="18" fill="#fff" opacity="0.8"/>
        <ellipse cx="320" cy="50" rx="28" ry="16" fill="#fff" opacity="0.8"/>
        <ellipse cx="340" cy="45" rx="25" ry="15" fill="#fff" opacity="0.8"/>
        
        <!-- Sun -->
        <circle cx="350" cy="60" r="20" fill="#ffc107" opacity="0.7"/>
        
        <!-- Text -->
        <text x="200" y="30" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="#0066cc" text-anchor="middle">
            En Construcción
        </text>
    </svg>
    
    <div class="construction-details">
        <h3><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_FEATURES_TITLE'); ?></h3>
        <ul>
            <li><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_FEATURE_1'); ?></li>
            <li><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_FEATURE_2'); ?></li>
            <li><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_FEATURE_3'); ?></li>
            <li><?php echo Text::_('COM_ORDENPRODUCCION_COTIZACIONES_FEATURE_4'); ?></li>
        </ul>
    </div>
</div>

