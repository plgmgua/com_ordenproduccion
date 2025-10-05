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

// Set headers for JSON output
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Unknown error', 'deployment_info' => []];

try {
    // 1. Check if Joomla environment is loaded
    if (!defined('_JEXEC')) {
        throw new Exception('Joomla _JEXEC constant NOT defined. Joomla environment not loaded.');
    }
    $app = Factory::getApplication();
    $response['deployment_info']['joomla_environment'] = 'Joomla environment loaded. Application type: ' . $app->getName();

    // 2. Check component file versions
    $componentFiles = [
        'OrdenModel' => JPATH_ROOT . '/components/com_ordenproduccion/site/src/Model/OrdenModel.php',
        'OrdenView' => JPATH_ROOT . '/components/com_ordenproduccion/site/src/View/Orden/HtmlView.php',
        'OrdenTemplate' => JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/orden/default.php',
        'OrdenesModel' => JPATH_ROOT . '/components/com_ordenproduccion/site/src/Model/OrdenesModel.php',
        'OrdenesTemplate' => JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/ordenes/default.php'
    ];

    foreach ($componentFiles as $name => $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            $modified = date('Y-m-d H:i:s', filemtime($path));
            $size = filesize($path);
            
            // Check for specific fixes
            $hasTranslateStatus = strpos($content, 'translateStatus') !== false;
            $hasEAVFix = strpos($content, 'numero_de_orden') !== false;
            
            $response['deployment_info']['files'][$name] = [
                'exists' => true,
                'modified' => $modified,
                'size' => $size,
                'has_translate_status' => $hasTranslateStatus,
                'has_eav_fix' => $hasEAVFix
            ];
        } else {
            $response['deployment_info']['files'][$name] = [
                'exists' => false
            ];
        }
    }

    // 3. Check if OrdenModel has the translateStatus method
    $ordenModelFile = JPATH_ROOT . '/components/com_ordenproduccion/site/src/Model/OrdenModel.php';
    if (file_exists($ordenModelFile)) {
        $content = file_get_contents($ordenModelFile);
        $hasEAVFix = strpos($content, 'numero_de_orden') !== false;
        $hasStatusFix = strpos($content, "'New' => 'New'") !== false;
        
        $response['deployment_info']['orden_model_fixes'] = [
            'has_eav_fix' => $hasEAVFix,
            'has_status_fix' => $hasStatusFix
        ];
    }

    // 4. Check if Orden view has translateStatus method
    $ordenViewFile = JPATH_ROOT . '/components/com_ordenproduccion/site/src/View/Orden/HtmlView.php';
    if (file_exists($ordenViewFile)) {
        $content = file_get_contents($ordenViewFile);
        $hasTranslateStatus = strpos($content, 'translateStatus') !== false;
        
        $response['deployment_info']['orden_view_fixes'] = [
            'has_translate_status' => $hasTranslateStatus
        ];
    }

    // 5. Check if template uses translateStatus
    $ordenTemplateFile = JPATH_ROOT . '/components/com_ordenproduccion/site/tmpl/orden/default.php';
    if (file_exists($ordenTemplateFile)) {
        $content = file_get_contents($ordenTemplateFile);
        $usesTranslateStatus = strpos($content, '$this->translateStatus($item->status)') !== false;
        
        $response['deployment_info']['orden_template_fixes'] = [
            'uses_translate_status' => $usesTranslateStatus
        ];
    }

    // 6. Check VERSION file
    $versionFile = JPATH_ROOT . '/components/com_ordenproduccion/VERSION';
    if (file_exists($versionFile)) {
        $version = trim(file_get_contents($versionFile));
        $response['deployment_info']['version'] = $version;
    } else {
        $response['deployment_info']['version'] = 'VERSION file not found';
    }

    // 7. Check component manifest version
    $manifestFile = JPATH_ROOT . '/administrator/components/com_ordenproduccion/com_ordenproduccion.xml';
    if (file_exists($manifestFile)) {
        $content = file_get_contents($manifestFile);
        if (preg_match('/<version>([^<]+)<\/version>/', $content, $matches)) {
            $response['deployment_info']['manifest_version'] = $matches[1];
        } else {
            $response['deployment_info']['manifest_version'] = 'Version not found in manifest';
        }
    } else {
        $response['deployment_info']['manifest_version'] = 'Manifest file not found';
    }

    $response['success'] = true;
    $response['message'] = 'Deployment check completed successfully';

} catch (Exception $e) {
    $response['message'] = 'Error during deployment check: ' . $e->getMessage();
    $response['error_trace'] = $e->getTraceAsString();
}

echo json_encode($response, JSON_PRETTY_PRINT);
