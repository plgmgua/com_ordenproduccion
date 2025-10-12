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

        // Set page title
        $this->setDocumentTitle(Text::_('COM_ORDENPRODUCCION_QUOTATION_FORM_TITLE') . ' - ' . $orderNumber);

        // Add CSS
        HTMLHelper::_('bootstrap.framework');
        $wa = $this->document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ordenproduccion.quotation', 'media/com_ordenproduccion/css/quotation.css', [], ['version' => 'auto']);

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
                $app = Factory::getApplication();
                $uri = $app->get('uri');
                $baseUrl = $uri->toString(['scheme', 'host']);
                if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
                    $baseUrl .= ':' . $_SERVER['SERVER_PORT'];
                }
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
}
