<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Orden controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class OrdenController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'orden';

    /**
     * Method to display a view.
     *
     * @param   boolean  $cachable   If true, the view output will be cached
     * @param   array    $urlparams  An array of safe URL parameters and their variable types, for valid values see {@link \JFilterInput::clean()}.
     *
     * @return  \Joomla\CMS\MVC\Controller\BaseController|boolean  This object to support chaining.
     *
     * @since   1.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $view = $this->input->get('view', $this->default_view);
        $this->input->set('view', $view);

        return parent::display($cachable, $urlparams);
    }

    /**
     * Method to generate PDF for a work order.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function generatePdf()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        
        // Check if user is in produccion group
        $userGroups = $user->getAuthorisedGroups();
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));

        $db->setQuery($query);
        $produccionGroupId = $db->loadResult();

        $hasProductionAccess = false;
        if ($produccionGroupId && in_array($produccionGroupId, $userGroups)) {
            $hasProductionAccess = true;
        }

        if (!$hasProductionAccess) {
            $app->enqueueMessage('Acceso denegado. Solo usuarios del grupo Producción pueden generar PDFs.', 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=ordenes');
            return;
        }

        $orderId = $this->input->getInt('id', 0);
        
        if (!$orderId) {
            $app->enqueueMessage('ID de orden no válido.', 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=ordenes');
            return;
        }

        try {
            // Get work order data
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_ordenes'))
                ->where($db->quoteName('id') . ' = ' . (int)$orderId)
                ->where($db->quoteName('state') . ' = 1');

            $db->setQuery($query);
            $workOrderData = $db->loadObject();

            if (!$workOrderData) {
                $app->enqueueMessage('Orden de trabajo no encontrada.', 'error');
                $app->redirect('index.php?option=com_ordenproduccion&view=ordenes');
                return;
            }

            // Generate PDF
            $pdfPath = $this->generateWorkOrderPDF($orderId, $workOrderData);
            
            if ($pdfPath && file_exists($pdfPath)) {
                // Set headers for PDF download
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="orden_trabajo_' . $orderId . '.pdf"');
                header('Content-Length: ' . filesize($pdfPath));
                
                // Output PDF file
                readfile($pdfPath);
                
                // Clean up temporary file
                unlink($pdfPath);
                exit;
            } else {
                $app->enqueueMessage('Error al generar el PDF.', 'error');
                $app->redirect('index.php?option=com_ordenproduccion&view=orden&id=' . $orderId);
            }
            
        } catch (Exception $e) {
            $app->enqueueMessage('Error: ' . $e->getMessage(), 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=orden&id=' . $orderId);
        }
    }

    /**
     * Method to generate PDF file for work order.
     *
     * @param   int     $orderId        Work order ID
     * @param   object  $workOrderData Work order data
     *
     * @return  string|false  PDF file path on success, false on failure
     *
     * @since   1.0.0
     */
    private function generateWorkOrderPDF($orderId, $workOrderData)
    {
        // Create PDF directory if it doesn't exist
        $pdfDir = JPATH_ROOT . '/media/com_ordenproduccion/pdf';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $pdfFile = $pdfDir . '/orden_trabajo_' . $orderId . '_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Simple HTML to PDF conversion (you can enhance this with proper PDF library)
        $html = '<html><body>';
        $html .= '<h1>Orden de Trabajo #' . $orderId . '</h1>';
        $html .= '<p><strong>Cliente:</strong> ' . htmlspecialchars($workOrderData->client_name ?? 'N/A') . '</p>';
        $html .= '<p><strong>Fecha de Solicitud:</strong> ' . htmlspecialchars($workOrderData->request_date ?? 'N/A') . '</p>';
        $html .= '<p><strong>Fecha de Entrega:</strong> ' . htmlspecialchars($workOrderData->delivery_date ?? 'N/A') . '</p>';
        $html .= '<p><strong>Estado:</strong> ' . htmlspecialchars($workOrderData->status ?? 'N/A') . '</p>';
        $html .= '<p><strong>Valor Factura:</strong> $' . number_format($workOrderData->invoice_value ?? 0, 2) . '</p>';
        $html .= '<p><strong>Descripción:</strong> ' . htmlspecialchars($workOrderData->description ?? 'N/A') . '</p>';
        $html .= '</body></html>';
        
        // For now, create a simple text file (you can enhance this with proper PDF generation)
        if (file_put_contents($pdfFile, $html)) {
            return $pdfFile;
        }
        
        return false;
    }
}
