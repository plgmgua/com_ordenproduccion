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
        } catch (Exception $e) {
            error_log('Error in processQuotationFilePath: ' . $e->getMessage());
            // Return the original value as fallback
            return $quotationFiles;
        }
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
}
