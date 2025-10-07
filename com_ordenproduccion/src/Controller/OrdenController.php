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
     * Method to generate shipping slip PDF.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function generateShippingSlip()
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
            $app->enqueueMessage('Acceso denegado. Solo usuarios del grupo Producción pueden generar envios.', 'error');
            $app->redirect('index.php?option=com_ordenproduccion&view=ordenes');
            return;
        }

        $orderId = $this->input->getInt('id', 0);
        $tipoEnvio = $this->input->getString('tipo_envio', 'completo');
        
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

            // Generate shipping slip PDF using FPDF
            $this->generateShippingSlipPDF($orderId, $workOrderData, $tipoEnvio);
            
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
        
                // Create PDF instance with UTF-8 support
                $pdf = new \FPDF('P', 'mm', 'A4');
                $pdf->AddPage();
                
                // Set margins
                $pdf->SetMargins(15, 15, 15);
                
        // Set UTF-8 encoding for proper Spanish character support
        $pdf->SetAutoPageBreak(true, 15);
        
        // Function to fix Spanish characters for FPDF
        $fixSpanishChars = function($text) {
            if (empty($text)) return $text;
            
            // Convert common Spanish characters that FPDF doesn't handle well
            $replacements = [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
                'Ü' => 'U', 'ü' => 'u', 'Ç' => 'C', 'ç' => 'c'
            ];
            
            return strtr($text, $replacements);
        };
        
                // Header with logo and work order info - 3 rows layout with borders
                // Store current Y position for alignment
                $startY = $pdf->GetY();

                // ROW 1: Logo + ORDEN DE TRABAJO # and number
                // Add GRIMPSA logo (left side) - correct path, maintain aspect ratio, 100% larger
                $logoPath = JPATH_ROOT . '/images/grimpsa_logo.gif';
                if (file_exists($logoPath)) {
                    // Add logo image (50mm width, auto height to maintain aspect ratio - 100% larger)
                    $pdf->Image($logoPath, 15, $startY, 50, 0, 'GIF');
                } else {
                    // Fallback if logo not found
                    $pdf->SetXY(15, $startY);
                    $pdf->SetFont('Arial', 'B', 12);
                    $pdf->Cell(50, 15, 'GRIMPSA', 1, 0, 'C');
                }

                // Right side: ORDEN DE TRABAJO and number on same line (no borders)
                $pdf->SetXY(100, $startY);
                $pdf->SetFont('Arial', 'B', 14);
                
                // Get orden_de_trabajo from main table (as shown in screenshot)
                $numeroOrden = 'N/A';
                
                // Check main table field first (orden_de_trabajo from joomla_ordenproduccion_ordenes)
                if (isset($workOrderData->orden_de_trabajo)) {
                    $numeroOrden = $workOrderData->orden_de_trabajo;
                } elseif (isset($workOrderData->numero_de_orden)) {
                    $numeroOrden = $workOrderData->numero_de_orden;
                }
                
                // Try EAV data as fallback
                if ($numeroOrden === 'N/A') {
                    $possibleFields = ['orden_de_trabajo', 'numero_de_orden', 'orden_trabajo', 'numero_orden'];
                    foreach ($possibleFields as $field) {
                        if (isset($workOrderData->eav_data[$field])) {
                            $numeroOrden = $workOrderData->eav_data[$field]->attribute_value;
                            break;
                        }
                    }
                }
                
                // Format as ORD-000000 if numeric
                if (is_numeric($numeroOrden)) {
                    $numeroOrden = 'ORD-' . str_pad($numeroOrden, 6, '0', STR_PAD_LEFT);
                }
                
                // Display label and number on same line, right-aligned
                $fullText = 'ORDEN DE TRABAJO ' . $numeroOrden;
                $pdf->Cell(0, 8, $fullText, 0, 1, 'R');

                // ROW 2: FECHA SOLICITUD + FECHA ENTREGA with proper spacing
                $pdf->SetXY(15, $startY + 20); // Position below logo
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 6, 'FECHA SOLICITUD:', 1, 0, 'L');
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(50, 6, $workOrderData->request_date ?? 'N/A', 1, 0, 'L');
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(40, 6, 'FECHA ENTREGA:', 1, 0, 'L');
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell(0, 6, $workOrderData->delivery_date ?? 'N/A', 1, 1, 'L');

                // ROW 3: AGENTE DE VENTAS (single cell with label and value)
                $pdf->SetXY(15, $startY + 26); // Position below dates
                $pdf->SetFont('Arial', 'B', 10);
                
                // Get sales agent from correct field name
                $salesAgent = 'N/A';
                if (isset($workOrderData->sales_agent)) {
                    $salesAgent = $workOrderData->sales_agent;
                } elseif (isset($workOrderData->agente_de_ventas)) {
                    $salesAgent = $workOrderData->agente_de_ventas;
                }
                
                // Try EAV data as fallback
                if ($salesAgent === 'N/A' && isset($workOrderData->eav_data['sales_agent'])) {
                    $salesAgent = $workOrderData->eav_data['sales_agent']->attribute_value;
                }
                
                $agentText = 'AGENTE DE VENTAS: ' . $salesAgent;
                $pdf->Cell(0, 6, $agentText, 1, 1, 'L');

                $pdf->Ln(5);
        
        
        // Client and job information table with proper cell sizing (40% larger labels)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'CLIENTE:', 1, 0, 'L'); // 35 * 1.4 = 49
        $pdf->SetFont('Arial', '', 9);
        $clientName = $workOrderData->client_name ?? 'N/A';
        $clientName = $fixSpanishChars($clientName); // Fix Spanish characters
        if (strlen($clientName) > 50) {
            $clientName = substr($clientName, 0, 47) . '...';
        }
        $pdf->Cell(0, 8, $clientName, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'TRABAJO:', 1, 0, 'L'); // 35 * 1.4 = 49
        $pdf->SetFont('Arial', '', 9);
        // Get job description from correct field name
        $jobDesc = 'N/A';
        
        // Debug: Check what fields are available
        $debugInfo = [];
        if (isset($workOrderData->work_description)) {
            $jobDesc = $workOrderData->work_description;
            $debugInfo[] = 'work_description: ' . $workOrderData->work_description;
        } elseif (isset($workOrderData->description)) {
            $jobDesc = $workOrderData->description;
            $debugInfo[] = 'description: ' . $workOrderData->description;
        }
        
        // Try EAV data as fallback
        if ($jobDesc === 'N/A') {
            if (isset($workOrderData->eav_data['work_description'])) {
                $jobDesc = $workOrderData->eav_data['work_description']->attribute_value;
                $debugInfo[] = 'EAV work_description: ' . $jobDesc;
            } elseif (isset($workOrderData->eav_data['descripcion_de_trabajo'])) {
                $jobDesc = $workOrderData->eav_data['descripcion_de_trabajo']->attribute_value;
                $debugInfo[] = 'EAV descripcion_de_trabajo: ' . $jobDesc;
            }
        }
        
        // If still N/A, try to get from EAV data directly
        if ($jobDesc === 'N/A' && isset($workOrderData->eav_data)) {
            foreach ($workOrderData->eav_data as $key => $data) {
                if (strpos($key, 'work') !== false || strpos($key, 'description') !== false || strpos($key, 'trabajo') !== false) {
                    $jobDesc = $data->attribute_value;
                    $debugInfo[] = 'EAV ' . $key . ': ' . $jobDesc;
                    break;
                }
            }
        }
        
        $jobDesc = $fixSpanishChars($jobDesc); // Fix Spanish characters
        if (strlen($jobDesc) > 50) {
            $jobDesc = substr($jobDesc, 0, 47) . '...';
        }
        
        // Remove debug info for production
        $pdf->Cell(0, 8, $jobDesc, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'DIRECCION DE ENTREGA', 1, 0, 'L'); // 35 * 1.4 = 49
        $pdf->SetFont('Arial', '', 9);
        $deliveryAddr = $workOrderData->delivery_address ?? 'N/A';
        $deliveryAddr = $fixSpanishChars($deliveryAddr); // Fix Spanish characters
        if (strlen($deliveryAddr) > 50) {
            $deliveryAddr = substr($deliveryAddr, 0, 47) . '...';
        }
        $pdf->Cell(0, 8, $deliveryAddr, 1, 1, 'L');
        
        $pdf->Ln(5);
        
        // Production specifications table - 2 columns × 4 rows layout
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'COLOR:', 1, 0, 'L');
        // Get color from EAV data (Color de Impresion)
        $color = 'N/A';
        if (isset($workOrderData->eav_data['color_impresion'])) {
            $color = $workOrderData->eav_data['color_impresion']->attribute_value;
        }
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $color, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'TIRO / RETIRO:', 1, 0, 'L');
        // Get tiro/retiro from EAV data
        $tiroRetiro = 'N/A';
        if (isset($workOrderData->eav_data['tiro_retiro'])) {
            $tiroRetiro = $workOrderData->eav_data['tiro_retiro']->attribute_value;
        }
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $tiroRetiro, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'MATERIAL:', 1, 0, 'L');
        $material = $workOrderData->material ?? 'N/A';
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $material, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'MEDIDAS:', 1, 0, 'L');
        // Get medidas from EAV data
        $medidas = 'N/A';
        if (isset($workOrderData->eav_data['medidas'])) {
            $medidas = $workOrderData->eav_data['medidas']->attribute_value;
        }
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $medidas, 1, 1, 'L');
        
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
                'IMP. EN BLANCO' => 'impresion_blanco',
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
        $instructions = $fixSpanishChars($instructions); // Fix Spanish characters
        // Ensure text fits within cell boundaries
        $pdf->MultiCell(0, 6, $instructions, 1, 'L');
        
        
        // Set headers for inline PDF viewing in new tab
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="orden_trabajo_' . $orderId . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Output PDF inline (opens in new tab)
        $pdf->Output('I', 'orden_trabajo_' . $orderId . '.pdf');
        exit;
    }

    /**
     * Method to generate shipping slip PDF using FPDF.
     *
     * @param   int     $orderId        Work order ID
     * @param   object  $workOrderData  Work order data
     * @param   string  $tipoEnvio      Tipo de envio (completo/parcial)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function generateShippingSlipPDF($orderId, $workOrderData, $tipoEnvio = 'completo')
    {
        // Include FPDF library (same path as working PDF generation)
        require_once JPATH_ROOT . '/fpdf/fpdf.php';

        // Create PDF
        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        
        // Get shipping number (convert ORD-000000 to ENV-000000)
        $ordenNumber = $workOrderData->numero_de_orden ?? 'ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $envioNumber = str_replace('ORD-', 'ENV-', $ordenNumber);
        
        // Get current date
        $currentDate = date('d/m/Y');
        
        // Get client data
        $clientName = $workOrderData->client_name ?? 'N/A';
        $salesAgent = $workOrderData->agente_de_ventas ?? $workOrderData->eav_data['agente_de_ventas']->attribute_value ?? 'N/A';
        $workDescription = $workOrderData->work_description ?? $workOrderData->eav_data['work_description']->attribute_value ?? $workOrderData->eav_data['descripcion_de_trabajo']->attribute_value ?? 'N/A';
        
        // Generate two identical shipping slips
        for ($slip = 0; $slip < 2; $slip++) {
            if ($slip > 0) {
                $pdf->AddPage();
            }
            
            // Header with logo and title
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->SetXY(20, 20);
            $pdf->Cell(40, 10, 'GRIMPSA', 0, 0, 'L');
            
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetXY(20, 30);
            $pdf->Cell(40, 5, 'Impresion Digital', 0, 0, 'L');
            
            // Envio number and date
            $pdf->SetFont('Arial', 'B', 20);
            $pdf->SetXY(80, 20);
            $pdf->Cell(60, 10, 'Envio # ' . $envioNumber, 0, 0, 'C');
            
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetXY(80, 30);
            $pdf->Cell(60, 5, 'GUATEMALA, ' . $currentDate, 0, 0, 'C');
            
            // QR Code placeholder (simple rectangle for now)
            $pdf->SetXY(150, 20);
            $pdf->Cell(30, 30, '', 1, 0, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(150, 35);
            $pdf->Cell(30, 5, 'QR: ' . $envioNumber, 0, 0, 'C');
            
            // Client and delivery information table
            $pdf->SetXY(20, 50);
            $pdf->Cell(160, 8, '', 1, 0, 'L'); // Border
            
            // Table headers and data
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetXY(20, 50);
            $pdf->Cell(40, 8, 'Cliente', 1, 0, 'L');
            $pdf->Cell(120, 8, $clientName, 1, 0, 'L');
            
            $pdf->SetXY(20, 58);
            $pdf->Cell(40, 8, 'Agente de Ventas', 1, 0, 'L');
            $pdf->Cell(120, 8, $salesAgent, 1, 0, 'L');
            
            $pdf->SetXY(20, 66);
            $pdf->Cell(40, 8, 'Contacto', 1, 0, 'L');
            $pdf->Cell(120, 8, '', 1, 0, 'L');
            
            $pdf->SetXY(20, 74);
            $pdf->Cell(40, 8, 'Direccion de entrega', 1, 0, 'L');
            $pdf->Cell(120, 8, '', 1, 0, 'L');
            
            $pdf->SetXY(20, 82);
            $pdf->Cell(40, 8, 'Telefono', 1, 0, 'L');
            $pdf->Cell(120, 8, '', 1, 0, 'L');
            
            $pdf->SetXY(20, 90);
            $pdf->Cell(160, 8, 'Instrucciones de entrega', 1, 0, 'L');
            $pdf->SetXY(20, 98);
            $pdf->Cell(160, 20, '', 1, 0, 'L');
            
            // Delivery and work details table
            $pdf->SetXY(20, 130);
            $pdf->Cell(160, 8, '', 1, 0, 'L'); // Border
            
            $pdf->SetXY(20, 130);
            $pdf->Cell(40, 8, 'Tipo de Entrega', 1, 0, 'L');
            $pdf->Cell(120, 8, $tipoEnvio, 1, 0, 'L');
            
            $pdf->SetXY(20, 138);
            $pdf->Cell(40, 8, 'Trabajo', 1, 0, 'L');
            $pdf->Cell(120, 8, $workDescription, 1, 0, 'L');
            
            $pdf->SetXY(20, 146);
            $pdf->Cell(160, 20, '', 1, 0, 'L');
            
            // Footer with signature fields
            $pdf->SetXY(20, 180);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 8, 'FECHA', 0, 0, 'L');
            $pdf->Cell(50, 8, 'NOMBRE Y FIRMA', 0, 0, 'L');
            $pdf->Cell(50, 8, 'Sello', 0, 0, 'L');
            
            $pdf->SetXY(20, 188);
            $pdf->Cell(50, 15, '', 1, 0, 'L');
            $pdf->Cell(50, 15, '', 1, 0, 'L');
            $pdf->Cell(50, 15, '', 1, 0, 'L');
        }
        
        // Output PDF
        $pdf->Output('I', 'envio_' . $envioNumber . '.pdf');
        exit;
    }

}