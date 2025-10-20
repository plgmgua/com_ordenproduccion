<?php
/**
 * PDF Parser Library Installer
 * 
 * Installs smalot/pdfparser library for PDF text extraction
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  Installer
 * @since       3.2.0
 */

// Define Joomla framework
define('_JEXEC', 1);

// Load Joomla framework
require_once dirname(__FILE__) . '/../../../libraries/import.php';

// Set JSON header
header('Content-Type: application/json');

try {
    $app = \Joomla\CMS\Factory::getApplication('site');
    
    // Check if composer is available
    $composerPath = JPATH_ROOT . '/composer.phar';
    $vendorPath = JPATH_ROOT . '/vendor';
    
    $response = [
        'success' => false,
        'message' => '',
        'steps' => [],
        'recommendations' => []
    ];
    
    // Step 1: Check if library already exists
    if (file_exists($vendorPath . '/smalot/pdfparser/src/Parser.php')) {
        $response['success'] = true;
        $response['message'] = 'PDF Parser library is already installed';
        $response['steps'][] = '✅ Library already exists at: ' . $vendorPath . '/smalot/pdfparser';
        echo json_encode($response);
        exit;
    }
    
    $response['steps'][] = '❌ PDF Parser library not found';
    
    // Step 2: Check if composer is available
    if (file_exists($composerPath)) {
        $response['steps'][] = '✅ Composer found at: ' . $composerPath;
        
        // Try to install the library
        $command = 'cd ' . JPATH_ROOT . ' && php composer.phar require smalot/pdfparser:^2.0';
        $output = shell_exec($command . ' 2>&1');
        
        if (file_exists($vendorPath . '/smalot/pdfparser/src/Parser.php')) {
            $response['success'] = true;
            $response['message'] = 'PDF Parser library installed successfully';
            $response['steps'][] = '✅ Successfully installed smalot/pdfparser';
        } else {
            $response['steps'][] = '❌ Installation failed: ' . $output;
            $response['recommendations'][] = 'Try running manually: php composer.phar require smalot/pdfparser:^2.0';
        }
    } else {
        $response['steps'][] = '❌ Composer not found at: ' . $composerPath;
        $response['recommendations'][] = 'Download composer.phar to Joomla root directory';
        $response['recommendations'][] = 'Run: curl -sS https://getcomposer.org/installer | php';
    }
    
    // Alternative: Manual installation instructions
    if (!$response['success']) {
        $response['recommendations'][] = 'Manual installation:';
        $response['recommendations'][] = '1. Download smalot/pdfparser from GitHub';
        $response['recommendations'][] = '2. Extract to: ' . $vendorPath . '/smalot/pdfparser';
        $response['recommendations'][] = '3. Ensure autoloading is configured';
    }
    
    echo json_encode($response);
    
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'steps' => ['❌ Installation error occurred']
    ]);
}
