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
        try {
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
        try {
            $this->quotationFile = $this->processQuotationFilePath($quotationFiles);
        } catch (Exception $e) {
            error_log('Error processing quotation file path: ' . $e->getMessage());
            $this->quotationFile = $quotationFiles; // Fallback to original value
        }

        // Store order data for template access
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;

        // Set page title for full page display
        $this->setDocumentTitle(Text::_('COM_ORDENPRODUCCION_QUOTATION_FORM_TITLE') . ' - ' . $orderNumber);

        // Add CSS for full page display
        HTMLHelper::_('bootstrap.framework');
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.quotation', 'media/com_ordenproduccion/css/quotation.css', [], ['version' => 'auto']);

            parent::display($tpl);
        } catch (Exception $e) {
            error_log('Fatal error in Quotation view display: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            // Try to show a simple error message
            if (isset($app)) {
                $app->enqueueMessage('Error loading quotation form: ' . $e->getMessage(), 'error');
            }
            
            // If we can't redirect, show a basic error
            if (!headers_sent()) {
                http_response_code(500);
                echo '<div style="padding: 20px; text-align: center; color: red;">Error loading quotation form. Please try again.</div>';
            }
        }
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
        try {
            $filePath = '';
            
            // Handle JSON array format: ["\/media\/com_convertforms\/uploads\/file.pdf"]
            if (strpos($quotationFiles, '[') === 0 && strpos($quotationFiles, ']') !== false) {
                $decoded = json_decode($quotationFiles, true);
                if (is_array($decoded) && !empty($decoded[0])) {
                    $filePath = $decoded[0];
                    // Remove escaped slashes
                    $filePath = str_replace('\\/', '/', $filePath);
                }
            } else {
                // Handle plain string format: media/com_ordenproduccion/cotizaciones/2025/05/COT-000000.pdf
                $filePath = $quotationFiles;
            }
            
            // If we have a file path, process it
            if (!empty($filePath)) {
                // Handle Google Drive URLs
                if (strpos($filePath, 'drive.google.com') !== false) {
                    return $this->convertGoogleDriveUrl($filePath);
                }
                
                // Handle OneDrive URLs
                if (strpos($filePath, 'onedrive.live.com') !== false || strpos($filePath, '1drv.ms') !== false) {
                    return $this->convertOneDriveUrl($filePath);
                }
                
                // Handle full URLs
                if (strpos($filePath, 'http') === 0) {
                    return $filePath;
                }
                
                // Handle absolute paths starting with /
                if (strpos($filePath, '/') === 0) {
                    $uri = Uri::getInstance();
                    $baseUrl = $uri->toString(['scheme', 'host', 'port']);
                    return $baseUrl . $filePath;
                }
                
                // Handle relative paths starting with media/ (new format)
                if (strpos($filePath, 'media/') === 0) {
                    $uri = Uri::getInstance();
                    $baseUrl = $uri->toString(['scheme', 'host', 'port']);
                    return $baseUrl . '/' . $filePath;
                }
                
                // Fallback: add /media/ prefix for backwards compatibility
                $uri = Uri::getInstance();
                $baseUrl = $uri->toString(['scheme', 'host', 'port']);
                return $baseUrl . '/media/' . $filePath;
            }
            
            // Handle Google Drive URLs directly if not processed above
            if (strpos($quotationFiles, 'drive.google.com') !== false) {
                return $this->convertGoogleDriveUrl($quotationFiles);
            }
            
            // Handle OneDrive URLs directly if not processed above
            if (strpos($quotationFiles, 'onedrive.live.com') !== false || strpos($quotationFiles, '1drv.ms') !== false) {
                return $this->convertOneDriveUrl($quotationFiles);
            }

            // If it's already a full URL, return as is
            if (strpos($quotationFiles, 'http') === 0) {
                return $quotationFiles;
            }

            // Final fallback - return original value
            return $quotationFiles;
        } catch (Exception $e) {
            error_log('Error in processQuotationFilePath: ' . $e->getMessage());
            // Return the original value as fallback
            return $quotationFiles;
        }
    }

    /**
     * Convert Google Drive sharing URL to direct view URL
     *
     * @param   string  $url  Google Drive URL
     *
     * @return  string  Direct view URL
     *
     * @since   3.52.12
     */
    protected function convertGoogleDriveUrl($url)
    {
        // Extract file ID from various Google Drive URL formats
        $fileId = '';
        
        // Format: https://drive.google.com/open?id=FILE_ID
        if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileId = $matches[1];
        }
        // Format: https://drive.google.com/file/d/FILE_ID/view
        elseif (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            $fileId = $matches[1];
        }
        
        if ($fileId) {
            // Return the direct preview URL for immediate embedding
            // JavaScript will handle fallback methods if needed
            return 'https://drive.google.com/file/d/' . $fileId . '/preview';
        }
        
        // Fallback to original URL if conversion fails
        return $url;
    }

    /**
     * Convert OneDrive sharing URL to direct view URL
     *
     * @param   string  $url  OneDrive URL
     *
     * @return  string  Direct view URL
     *
     * @since   3.52.12
     */
    protected function convertOneDriveUrl($url)
    {
        // Extract file ID from OneDrive URL
        $fileId = '';
        
        // Format: https://1drv.ms/b/s!FILE_ID or similar
        if (preg_match('/1drv\.ms\/[a-z]\/s!(.+)/', $url, $matches)) {
            $fileId = $matches[1];
        }
        
        if ($fileId) {
            // Convert to direct view URL for embedding
            // Note: OneDrive embedding is more complex and may require additional setup
            return 'https://onedrive.live.com/embed?resid=' . $fileId . '&authkey=!FILE_ID&em=2';
        }
        
        // Fallback to original URL if conversion fails
        return $url;
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

        // Get clean URL without parameters
        $cleanUrl = $this->quotationFile;
        
        // Remove URL fragments (#toolbar=1&navpanes=1...)
        $cleanUrl = preg_replace('/#[^?]*$/', '', $cleanUrl);

        // For HTTP URLs, extract the local path
        if (strpos($cleanUrl, 'http') === 0) {
            // Parse the URL to get the path
            $parsedUrl = parse_url($cleanUrl);
            if (isset($parsedUrl['path'])) {
                $relativePath = $parsedUrl['path'];
            } else {
                return false;
            }
        } else {
            // For relative URLs, use as is
            $relativePath = $cleanUrl;
            
            // Ensure path starts with /
            if (strpos($relativePath, '/') !== 0) {
                $relativePath = '/' . $relativePath;
            }
        }
        
        // Convert to absolute file system path
        $localPath = JPATH_ROOT . $relativePath;
        
        // Check if file exists
        $fileExists = file_exists($localPath);
        
        // Debug: Log the path check
        error_log('=== FILE ACCESSIBILITY CHECK ===');
        error_log('Original URL: ' . $this->quotationFile);
        error_log('Clean URL: ' . $cleanUrl);
        error_log('Relative path: ' . $relativePath);
        error_log('JPATH_ROOT: ' . JPATH_ROOT);
        error_log('Full local path: ' . $localPath);
        error_log('File exists: ' . ($fileExists ? 'YES' : 'NO'));
        error_log('===============================');
        
        return $fileExists;
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
}
