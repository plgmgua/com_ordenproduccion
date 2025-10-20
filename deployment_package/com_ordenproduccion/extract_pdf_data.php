<?php
/**
 * PDF Data Extraction - AJAX Endpoint
 * 
 * Extracts quotation data from PDF files for invoice creation
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site
 * @subpackage  AJAX
 * @since       3.2.0
 */

// Define Joomla framework
define('_JEXEC', 1);

// Load Joomla framework
require_once dirname(__FILE__) . '/../../../libraries/import.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Initialize Joomla application
    $app = \Joomla\CMS\Factory::getApplication('site');
    
    // Get POST data
    $orderId = $app->input->post->getInt('order_id', 0);
    
    // Validate input
    if (empty($orderId)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid order ID'
        ]);
        exit;
    }
    
    // Get database
    $db = \Joomla\CMS\Factory::getDbo();
    
    // Get work order data including quotation files
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__ordenproduccion_ordenes')
        ->where($db->quoteName('id') . ' = ' . (int) $orderId);
    $db->setQuery($query);
    $workOrder = $db->loadObject();
    
    if (!$workOrder) {
        echo json_encode([
            'success' => false,
            'message' => 'Work order not found'
        ]);
        exit;
    }
    
    // Check if quotation files exist
    if (empty($workOrder->quotation_files)) {
        echo json_encode([
            'success' => false,
            'message' => 'No quotation files found for this work order'
        ]);
        exit;
    }
    
    // Parse quotation files (comma-separated list)
    $quotationFiles = explode(',', $workOrder->quotation_files);
    $extractedData = [];
    
    foreach ($quotationFiles as $filePath) {
        $filePath = trim($filePath);
        if (empty($filePath)) continue;
        
        // Convert relative path to absolute path
        $absolutePath = JPATH_ROOT . '/' . ltrim($filePath, '/');
        
        if (file_exists($absolutePath) && pathinfo($absolutePath, PATHINFO_EXTENSION) === 'pdf') {
            $pdfData = extractPDFData($absolutePath);
            if ($pdfData) {
                $extractedData[] = [
                    'file' => basename($absolutePath),
                    'path' => $filePath,
                    'data' => $pdfData
                ];
            }
        }
    }
    
    if (empty($extractedData)) {
        echo json_encode([
            'success' => false,
            'message' => 'No valid PDF files found or could not extract data'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'PDF data extracted successfully',
        'work_order' => [
            'id' => $workOrder->id,
            'orden_de_trabajo' => $workOrder->orden_de_trabajo,
            'client_name' => $workOrder->client_name,
            'sales_agent' => $workOrder->sales_agent
        ],
        'extracted_data' => $extractedData
    ]);
    
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Extract data from PDF file
 * 
 * @param   string  $filePath  Path to PDF file
 * 
 * @return  array|null  Extracted data or null on failure
 */
function extractPDFData($filePath) {
    try {
        // Check if smalot/pdfparser is available
        if (class_exists('\Smalot\PdfParser\Parser')) {
            return extractWithSmalotParser($filePath);
        } else {
            // Fallback to basic text extraction
            return extractWithBasicMethod($filePath);
        }
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Extract data using Smalot PDF Parser (if available)
 * 
 * @param   string  $filePath  Path to PDF file
 * 
 * @return  array|null  Extracted data
 */
function extractWithSmalotParser($filePath) {
    try {
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();
        
        return parseQuotationText($text);
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Extract data using basic method (fallback)
 * 
 * @param   string  $filePath  Path to PDF file
 * 
 * @return  array|null  Extracted data
 */
function extractWithBasicMethod($filePath) {
    try {
        // Try to extract text using pdftotext if available
        $outputFile = tempnam(sys_get_temp_dir(), 'pdf_extract_');
        $command = "pdftotext -layout \"$filePath\" \"$outputFile\" 2>/dev/null";
        
        $result = shell_exec($command);
        
        if (file_exists($outputFile)) {
            $text = file_get_contents($outputFile);
            unlink($outputFile);
            
            return parseQuotationText($text);
        }
        
        return null;
    } catch (\Exception $e) {
        return null;
    }
}

/**
 * Parse quotation text to extract table data
 * 
 * @param   string  $text  Extracted PDF text
 * 
 * @return  array  Parsed quotation data
 */
function parseQuotationText($text) {
    $lines = explode("\n", $text);
    $items = [];
    $inTable = false;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Look for table headers
        if (preg_match('/cantidad|descripci[oó]n|precio|total/i', $line)) {
            $inTable = true;
            continue;
        }
        
        // Skip empty lines
        if (empty($line)) {
            continue;
        }
        
        // Try to parse table rows
        if ($inTable) {
            // Look for patterns like: "1.00 Descripción del producto $100.00"
            // or "2 Descripción $50.00"
            if (preg_match('/^(\d+(?:\.\d+)?)\s+(.+?)\s+\$?(\d+(?:\.\d+)?)$/', $line, $matches)) {
                $items[] = [
                    'cantidad' => $matches[1],
                    'descripcion' => trim($matches[2]),
                    'precio' => $matches[3]
                ];
            }
            // Alternative pattern for different layouts
            elseif (preg_match('/^(\d+(?:\.\d+)?)\s+(.+?)\s+(\d+(?:\.\d+)?)\s*$/', $line, $matches)) {
                $items[] = [
                    'cantidad' => $matches[1],
                    'descripcion' => trim($matches[2]),
                    'precio' => $matches[3]
                ];
            }
        }
    }
    
    return [
        'items' => $items,
        'total_items' => count($items),
        'raw_text' => $text
    ];
}
