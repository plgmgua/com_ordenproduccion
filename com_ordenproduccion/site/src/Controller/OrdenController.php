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
            $workOrderData = $this->getWorkOrderData($orderId);
            
            if (!$workOrderData) {
                $app->enqueueMessage('Orden de trabajo no encontrada.', 'error');
                $app->redirect('index.php?option=com_ordenproduccion&view=ordenes');
                return;
            }

            // Generate PDF using FPDF
            $this->generateWorkOrderPDF($orderId, $workOrderData);
            
        } catch (Exception $e) {
            $app->enqueueMessage('Error: ' . $e->getMessage(), 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=orden&id=' . $orderId);
        }
    }

    /**
     * Method to get work order data from database.
     *
     * @param   int  $orderId  Work order ID
     *
     * @return  object|null  Work order data or null if not found
     *
     * @since   1.0.0
     */
    private function getWorkOrderData($orderId)
    {
        $db = Factory::getDbo();
        
        // Get main work order data
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int)$orderId)
            ->where($db->quoteName('state') . ' = 1');

        $db->setQuery($query);
        $workOrder = $db->loadObject();

        if (!$workOrder) {
            return null;
        }

        // Get additional EAV data (tecnico, detalles, etc.)
        $eavQuery = $db->getQuery(true)
            ->select('tipo_de_campo, valor, timestamp, usuario')
            ->from($db->quoteName('#__ordenproduccion_ordenes_info'))
            ->where($db->quoteName('numero_de_orden') . ' = ' . $db->quote($workOrder->numero_de_orden));

        $db->setQuery($eavQuery);
        $eavData = $db->loadObjectList();

        // Organize EAV data by type
        $workOrder->eav_data = [];
        foreach ($eavData as $item) {
            $workOrder->eav_data[$item->tipo_de_campo] = $item;
        }

        return $workOrder;
    }

    /**
     * Method to generate PDF file for work order using FPDF.
     *
     * @param   int     $orderId        Work order ID
     * @param   object  $workOrderData Work order data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function generateWorkOrderPDF($orderId, $workOrderData)
    {
        // Include FPDF library
        require_once JPATH_ROOT . '/fpdf/fpdf.php';
        
        // Create PDF instance
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('Arial', 'B', 16);
        
        // Header
        $pdf->Cell(0, 10, 'ORDEN DE TRABAJO', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Work order number
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'Numero: ' . ($workOrderData->numero_de_orden ?? 'N/A'), 0, 1, 'L');
        $pdf->Ln(3);
        
        // Client information
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 8, 'Cliente:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $workOrderData->client_name ?? 'N/A', 0, 1, 'L');
        
        // Request date
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 8, 'Fecha Solicitud:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $workOrderData->request_date ?? 'N/A', 0, 1, 'L');
        
        // Delivery date
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 8, 'Fecha Entrega:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $workOrderData->delivery_date ?? 'N/A', 0, 1, 'L');
        
        // Status
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 8, 'Estado:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, $workOrderData->status ?? 'N/A', 0, 1, 'L');
        
        // Invoice value
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(40, 8, 'Valor Factura:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 8, '$' . number_format($workOrderData->invoice_value ?? 0, 2), 0, 1, 'L');
        
        $pdf->Ln(5);
        
        // Description
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'Descripcion:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->MultiCell(0, 6, $workOrderData->description ?? 'N/A', 0, 'L');
        
        $pdf->Ln(5);
        
        // EAV Data (tecnico, detalles, etc.)
        if (isset($workOrderData->eav_data)) {
            foreach ($workOrderData->eav_data as $type => $data) {
                $pdf->SetFont('Arial', 'B', 12);
                $pdf->Cell(40, 8, ucfirst($type) . ':', 0, 0, 'L');
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(0, 8, $data->valor ?? 'N/A', 0, 1, 'L');
            }
        }
        
        // Output PDF
        $pdf->Output('I', 'orden_trabajo_' . $orderId . '.pdf');
        exit;
    }
}