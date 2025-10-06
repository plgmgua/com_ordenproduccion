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

        // Get additional EAV data (tecnico, detalles, etc.) - using correct table structure
        try {
            $eavQuery = $db->getQuery(true)
                ->select('attribute_name, attribute_value, created, created_by')
                ->from($db->quoteName('#__ordenproduccion_info'))
                ->where($db->quoteName('order_id') . ' = ' . (int)$orderId)
                ->where($db->quoteName('state') . ' = 1');

            $db->setQuery($eavQuery);
            $eavData = $db->loadObjectList();

            // Organize EAV data by attribute name
            $workOrder->eav_data = [];
            foreach ($eavData as $item) {
                $workOrder->eav_data[$item->attribute_name] = $item;
            }
        } catch (Exception $e) {
            // EAV table doesn't exist or other error - continue without EAV data
            $workOrder->eav_data = [];
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
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        
        // Header with logo and title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(60, 15, 'GRIMPSA', 0, 0, 'L');
        $pdf->Cell(60, 15, 'Impresion Digital', 0, 0, 'L');
        
        // Right side - Work order number
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 8, 'ORDEN DE TRABAJO #:', 0, 1, 'R');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 6, $workOrderData->numero_de_orden ?? 'N/A', 0, 1, 'R');
        
        $pdf->Ln(5);
        
        // Date information
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 6, 'FECHA SOLICITUD:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(40, 6, $workOrderData->request_date ?? 'N/A', 0, 0, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 6, 'FECHA ENTREGA:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $workOrderData->delivery_date ?? 'N/A', 0, 1, 'L');
        
        // Sales agent
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(30, 6, 'AGENTE DE VENTAS:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 6, $workOrderData->agente_de_ventas ?? 'N/A', 0, 1, 'L');
        
        $pdf->Ln(5);
        
        // Client and job information table with proper cell sizing (40% larger labels)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'CLIENTE:', 1, 0, 'L'); // 35 * 1.4 = 49
        $pdf->SetFont('Arial', '', 9);
        $clientName = $workOrderData->client_name ?? 'N/A';
        if (strlen($clientName) > 50) {
            $clientName = substr($clientName, 0, 47) . '...';
        }
        $pdf->Cell(0, 8, $clientName, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'TRABAJO:', 1, 0, 'L'); // 35 * 1.4 = 49
        $pdf->SetFont('Arial', '', 9);
        $jobDesc = $workOrderData->description ?? 'N/A';
        if (strlen($jobDesc) > 50) {
            $jobDesc = substr($jobDesc, 0, 47) . '...';
        }
        $pdf->Cell(0, 8, $jobDesc, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'DIRECCION DE ENTREGA', 1, 0, 'L'); // 35 * 1.4 = 49
        $pdf->SetFont('Arial', '', 9);
        $deliveryAddr = $workOrderData->delivery_address ?? 'N/A';
        if (strlen($deliveryAddr) > 50) {
            $deliveryAddr = substr($deliveryAddr, 0, 47) . '...';
        }
        $pdf->Cell(0, 8, $deliveryAddr, 1, 1, 'L');
        
        $pdf->Ln(5);
        
        // Production specifications table with proper cell sizing (40% larger labels)
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(49, 8, 'COLOR', 1, 0, 'C'); // 35 * 1.4 = 49
        $pdf->Cell(49, 8, 'TIRO / RETIRO:', 1, 0, 'C'); // 35 * 1.4 = 49
        $pdf->Cell(49, 8, 'MATERIAL:', 1, 0, 'C'); // 35 * 1.4 = 49
        $pdf->Cell(0, 8, 'MEDIDAS:', 1, 1, 'C');
        
        $pdf->SetFont('Arial', '', 8);
        $color = $workOrderData->color ?? 'N/A';
        if (strlen($color) > 15) {
            $color = substr($color, 0, 12) . '...';
        }
        $pdf->Cell(49, 8, $color, 1, 0, 'C'); // 35 * 1.4 = 49
        
        $tiroRetiro = $workOrderData->tiro_retiro ?? 'N/A';
        if (strlen($tiroRetiro) > 15) {
            $tiroRetiro = substr($tiroRetiro, 0, 12) . '...';
        }
        $pdf->Cell(49, 8, $tiroRetiro, 1, 0, 'C'); // 35 * 1.4 = 49
        
        $material = $workOrderData->material ?? 'N/A';
        if (strlen($material) > 15) {
            $material = substr($material, 0, 12) . '...';
        }
        $pdf->Cell(49, 8, $material, 1, 0, 'C'); // 35 * 1.4 = 49
        
        $medidas = $workOrderData->medidas ?? 'N/A';
        if (strlen($medidas) > 20) {
            $medidas = substr($medidas, 0, 17) . '...';
        }
        $pdf->Cell(0, 8, $medidas, 1, 1, 'C');
        
        $pdf->Ln(5);
        
        // Finishing options table with real data from EAV
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 8, 'ACABADOS', 1, 0, 'C');
        $pdf->Cell(30, 8, 'SELECCION', 1, 0, 'C');
        $pdf->Cell(0, 8, 'DETALLES', 1, 1, 'C');
        
        // List of finishing options with actual data
        $finishingOptions = [
            'BLOCADO', 'CORTE', 'DOBLADO', 'LAMINADO', 'LOMO', 'NUMERADO',
            'PEGADO', 'SIZADO', 'ENGRAPADO', 'TROQUEL', 'TROQUEL CAMEO',
            'BARNIZ', 'IMP. EN BLANCO', 'DESPUNTADO', 'OJETES', 'PERFORADO'
        ];
        
        $pdf->SetFont('Arial', '', 9);
        foreach ($finishingOptions as $option) {
            // Check if this finishing option is selected
            $isSelected = 'NO';
            $details = '';
            
            // Map option names to EAV attribute names
            $eavMapping = [
                'BLOCADO' => 'blocado',
                'CORTE' => 'corte', 
                'DOBLADO' => 'doblado',
                'LAMINADO' => 'laminado',
                'LOMO' => 'lomo',
                'NUMERADO' => 'numerado',
                'PEGADO' => 'pegado',
                'SIZADO' => 'sizado',
                'ENGRAPADO' => 'engrapado',
                'TROQUEL' => 'troquel',
                'TROQUEL CAMEO' => 'troquel_cameo',
                'BARNIZ' => 'barniz',
                'IMP. EN BLANCO' => 'imp_en_blanco',
                'DESPUNTADO' => 'despuntado',
                'OJETES' => 'ojetes',
                'PERFORADO' => 'perforado'
            ];
            
            $eavKey = $eavMapping[$option] ?? strtolower($option);
            
            // Check if this finishing option is selected (SI/NO)
            if (isset($workOrderData->eav_data[$eavKey])) {
                $isSelected = $workOrderData->eav_data[$eavKey]->attribute_value;
            }
            
            // Get details for this finishing option
            $detailsKey = 'detalles_' . $eavKey;
            if (isset($workOrderData->eav_data[$detailsKey])) {
                $details = $workOrderData->eav_data[$detailsKey]->attribute_value;
            }
            
            // Truncate details if too long
            if (strlen($details) > 30) {
                $details = substr($details, 0, 27) . '...';
            }
            
            $pdf->Cell(60, 6, $option, 1, 0, 'L');
            $pdf->Cell(30, 6, $isSelected, 1, 0, 'C');
            $pdf->Cell(0, 6, $details, 1, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // Instructions/Observations with proper text wrapping
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 8, 'Instrucciones / Observaciones', 1, 1, 'L');
        $pdf->SetFont('Arial', '', 9);
        $instructions = $workOrderData->instructions ?? 'N/A';
        // Ensure text fits within cell boundaries
        $pdf->MultiCell(0, 6, $instructions, 1, 'L');
        
        // Additional information (non-acabados data only, excluding specified fields)
        if (isset($workOrderData->eav_data) && !empty($workOrderData->eav_data)) {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 8, 'INFORMACION ADICIONAL', 1, 1, 'L');
            
            $pdf->SetFont('Arial', '', 9);
            
            // Filter out acabados-related data and excluded fields
            $acabadosKeys = ['blocado', 'corte', 'doblado', 'laminado', 'lomo', 'numerado', 
                           'pegado', 'sizado', 'engrapado', 'troquel', 'troquel_cameo', 
                           'barniz', 'imp_en_blanco', 'despuntado', 'ojetes', 'perforado'];
            
            $detailsKeys = array_map(function($key) { return 'detalles_' . $key; }, $acabadosKeys);
            $excludeKeys = array_merge($acabadosKeys, $detailsKeys);
            
            // Additional fields to exclude
            $additionalExcludeKeys = ['arte', 'valor_factura', 'client_id'];
            $excludeKeys = array_merge($excludeKeys, $additionalExcludeKeys);
            
            foreach ($workOrderData->eav_data as $attributeName => $data) {
                // Skip acabados data and excluded fields
                if (!in_array($attributeName, $excludeKeys)) {
                    $pdf->Cell(40, 6, ucfirst($attributeName) . ':', 1, 0, 'L');
                    $pdf->Cell(0, 6, $data->attribute_value ?? 'N/A', 1, 1, 'L');
                }
            }
        }
        
        // Set headers for inline PDF viewing in new tab
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="orden_trabajo_' . $orderId . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output PDF inline (opens in new tab)
        $pdf->Output('I', 'orden_trabajo_' . $orderId . '.pdf');
        exit;
    }
}