<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\View\Quotation;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Quotation view class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The model state
     *
     * @var    \Joomla\Registry\Registry
     */
    protected $state;

    /**
     * The item object
     *
     * @var    \stdClass
     */
    protected $item;

    /**
     * The quotation file path
     *
     * @var    string
     */
    protected $quotationFile;

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     */
    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $input = $app->input;

        // Get order information from URL parameters
        $orderId = $input->getInt('order_id', 0);
        $orderNumber = $input->getString('order_number', '');
        $quotationFiles = $input->getString('quotation_files', '');

        // Validate required parameters
        if (empty($orderId) || empty($quotationFiles)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_MISSING_PARAMETERS'), 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=administracion&tab=workorders');
            return;
        }

        // Load the order data
        $model = $this->getModel();
        $this->item = $model->getOrder($orderId);

        if (!$this->item) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ORDER_NOT_FOUND'), 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=administracion&tab=workorders');
            return;
        }

        // Process quotation file path
        $this->quotationFile = $this->processQuotationFilePath($quotationFiles);

        // Store order data for template access
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;

        // Check if this is an AJAX request (for modal display)
        $format = $input->get('format', '', 'string');
        $isAjax = $format === 'raw' || 
                  (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
        
        // Debug logging
        error_log("Quotation View Debug - Format: " . $format . ", IsAjax: " . ($isAjax ? 'YES' : 'NO'));
        error_log("HTTP_X_REQUESTED_WITH: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'NOT_SET'));

        if (!$isAjax) {
            // Set page title only for full page requests
            $this->setDocumentTitle(Text::_('COM_ORDENPRODUCCION_QUOTATION_FORM_TITLE') . ' - ' . $orderNumber);

            // Add CSS only for full page requests
            HTMLHelper::_('bootstrap.framework');
            $wa = $this->document->getWebAssetManager();
            $wa->registerAndUseStyle('com_ordenproduccion.quotation', 'media/com_ordenproduccion/css/quotation.css', [], ['version' => 'auto']);
        }

        parent::display($tpl);
    }

    /**
     * Process quotation file path
     *
     * @param   string  $quotationFiles  The quotation files string
     *
     * @return  string  The processed file path
     */
    protected function processQuotationFilePath($quotationFiles)
    {
        // Handle JSON array format: ["\/media\/com_convertforms\/uploads\/file.pdf"]
        if (strpos($quotationFiles, '[') === 0 && strpos($quotationFiles, ']') !== false) {
            $decoded = json_decode($quotationFiles, true);
            if (is_array($decoded) && !empty($decoded[0])) {
                $filePath = $decoded[0];
                // Remove escaped slashes
                $filePath = str_replace('\\/', '/', $filePath);
                // Make it a full URL - construct from current request
                $uri = Uri::getInstance();
                $baseUrl = $uri->toString(['scheme', 'host', 'port']);
                return $baseUrl . $filePath;
            }
        }

        // If it's already a full URL, return as is
        if (strpos($quotationFiles, 'http') === 0) {
            return $quotationFiles;
        }

        // If it's a relative path, make it absolute
        if (strpos($quotationFiles, '/') === 0) {
            return Factory::getApplication()->get('live_site') . $quotationFiles;
        }

        // If it's a relative path without leading slash, add media path
        return Factory::getApplication()->get('live_site') . '/media/' . $quotationFiles;
    }

    /**
     * Get the quotation file URL
     *
     * @return  string
     */
    public function getQuotationFileUrl()
    {
        return $this->quotationFile;
    }

    /**
     * Check if the quotation file exists and is accessible
     *
     * @return  bool
     */
    public function isQuotationFileAccessible()
    {
        if (empty($this->quotationFile)) {
            return false;
        }

        // For HTTP URLs, we can't easily check if file exists
        if (strpos($this->quotationFile, 'http') === 0) {
            return true; // Assume it exists
        }

        // For local files, check if file exists
        $localPath = str_replace(Factory::getApplication()->get('live_site'), JPATH_ROOT, $this->quotationFile);
        return file_exists($localPath);
    }

    /**
     * Get the quotation file type (PDF, image, etc.)
     *
     * @return  string
     */
    public function getQuotationFileType()
    {
        if (empty($this->quotationFile)) {
            return 'unknown';
        }

        $extension = strtolower(pathinfo($this->quotationFile, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'pdf':
                return 'pdf';
            case 'jpg':
            case 'jpeg':
            case 'png':
            case 'gif':
                return 'image';
            case 'doc':
            case 'docx':
                return 'document';
            default:
                return 'unknown';
        }
    }

    /**
     * Get order data for the form
     *
     * @return  object  The order data object
     */
    public function getOrderData()
    {
        return $this->item;
    }
    
    public function getQuotationImageUrl()
    {
        if (empty($this->quotationFile)) {
            return null;
        }
        
        try {
            // Convert PDF to JPG if it's a PDF file
            if (strpos($this->quotationFile, '.pdf') !== false) {
                return $this->convertPdfToJpg($this->quotationFile);
            }
            
            // Return original file if it's already an image
            return $this->quotationFile;
        } catch (Exception $e) {
            // Log error and return original file as fallback
            error_log('Error in getQuotationImageUrl: ' . $e->getMessage());
            return $this->quotationFile;
        }
    }
    
    private function convertPdfToJpg($pdfPath)
    {
        // Check if ImageMagick is available
        if (!extension_loaded('imagick')) {
            error_log('ImageMagick extension not loaded - falling back to PDF');
            return $pdfPath; // Fallback to original PDF
        }
        
        // Check if Imagick class exists
        if (!class_exists('Imagick')) {
            error_log('Imagick class not available - falling back to PDF');
            return $pdfPath;
        }
        
        try {
            // Create cache directory for images
            $cacheDir = JPATH_ROOT . '/cache/com_ordenproduccion/quotation_images/';
            if (!is_dir($cacheDir)) {
                if (!mkdir($cacheDir, 0755, true)) {
                    error_log('Failed to create cache directory: ' . $cacheDir);
                    return $pdfPath;
                }
            }
            
            // Generate cache filename
            $cacheFile = md5($pdfPath) . '.jpg';
            $cachePath = $cacheDir . $cacheFile;
            
            // Check if cached image exists and is recent (less than 1 hour old)
            if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 3600) {
                return '/cache/com_ordenproduccion/quotation_images/' . $cacheFile;
            }
            
            // Check if PDF file exists
            $fullPdfPath = JPATH_ROOT . $pdfPath;
            if (!file_exists($fullPdfPath)) {
                error_log('PDF file not found: ' . $fullPdfPath);
                return $pdfPath;
            }
            
            // Convert PDF to JPG
            $imagick = new \Imagick();
            $imagick->setResolution(150, 150); // Set resolution for better quality
            $imagick->readImage($fullPdfPath . '[0]'); // Read first page only
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(85); // Good quality
            $imagick->writeImage($cachePath);
            $imagick->clear();
            $imagick->destroy();
            
            return '/cache/com_ordenproduccion/quotation_images/' . $cacheFile;
            
        } catch (Exception $e) {
            // Log error and return original PDF
            error_log('PDF to JPG conversion failed: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            return $pdfPath;
        }
    }
}
