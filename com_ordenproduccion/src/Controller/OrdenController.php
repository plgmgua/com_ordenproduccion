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
use Grimpsa\Component\Ordenproduccion\Site\Helper\HistorialHelper;

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
                $tipoMensajeria = $this->input->getString('tipo_mensajeria', 'propio');
                $descripcionEnvio = $this->input->getString('descripcion_envio', '');
        
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

            // Only save historial entries on POST requests (initial generation)
            // Skip saving on GET requests (when just opening/viewing the PDF)
            $requestMethod = $app->input->server->get('REQUEST_METHOD', 'GET');
            if ($requestMethod === 'POST') {
                // Save historial entries for shipping slip generation
                if ($tipoEnvio === 'completo') {
                    // For "completo", save print event with fixed description
                    HistorialHelper::saveEntry(
                        $orderId,
                        'shipping_print',
                        'Impresion de Envio',
                        'Envio completo impreso',
                        $user->id,
                        ['tipo_envio' => $tipoEnvio, 'tipo_mensajeria' => $tipoMensajeria]
                    );
                } else {
                    // For "parcial", save shipping description if provided
                    if (!empty($descripcionEnvio)) {
                        HistorialHelper::saveEntry(
                            $orderId,
                            'shipping_description',
                            'Descripcion de Envio',
                            $descripcionEnvio,
                            $user->id,
                            ['tipo_envio' => $tipoEnvio, 'tipo_mensajeria' => $tipoMensajeria]
                        );
                    }
                    
                    // Also save print event for parcial
                    HistorialHelper::saveEntry(
                        $orderId,
                        'shipping_print',
                        'Impresion de Envio',
                        'Envio parcial impreso',
                        $user->id,
                        ['tipo_envio' => $tipoEnvio, 'tipo_mensajeria' => $tipoMensajeria]
                    );
                }
            }

                    // Generate shipping slip PDF using FPDF
                    $this->generateShippingSlipPDF($orderId, $workOrderData, $tipoEnvio, $tipoMensajeria, $descripcionEnvio);
            
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
        
                // Create PDF instance with UTF-8 support for 8.5" x 11" paper
                $pdf = new \FPDF('P', 'mm', array(215.9, 279.4)); // 8.5" x 11" in mm
                $pdf->AddPage();
                
                // Set margins for letter size
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
        
        // Use MultiCell approach like INSTRUCCIONES GENERALES for text wrapping
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(49, 8, 'TRABAJO:', 1, 0, 'L'); // Same width as CLIENTE label (49mm)
        $pdf->SetFont('Arial', '', 9);
        // Use MultiCell to allow text wrapping like INSTRUCCIONES GENERALES
        $pdf->MultiCell(0, 6, $jobDesc, 1, 'L'); // Full remaining width like CLIENTE
        
        $pdf->Ln(5);
        
        // Production specifications table - 2 columns × 4 rows layout
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'COLOR:', 1, 0, 'L');
        // Get color from main table (print_color)
        $color = $workOrderData->print_color ?? 'N/A';
        $color = $fixSpanishChars($color);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $color, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'TIRO / RETIRO:', 1, 0, 'L');
        // Get tiro/retiro from main table
        $tiroRetiro = $workOrderData->tiro_retiro ?? 'N/A';
        $tiroRetiro = $fixSpanishChars($tiroRetiro);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $tiroRetiro, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'MATERIAL:', 1, 0, 'L');
        $material = $workOrderData->material ?? 'N/A';
        $material = $fixSpanishChars($material);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $material, 1, 1, 'L');
        
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(80, 8, 'MEDIDAS:', 1, 0, 'L');
        // Get medidas from main table (dimensions)
        $medidas = $workOrderData->dimensions ?? 'N/A';
        $medidas = $fixSpanishChars($medidas);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 8, $medidas, 1, 1, 'L');
        
        $pdf->Ln(5);
        
        // Finishing options table with real data from main table
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(60, 8, 'ACABADOS', 1, 0, 'C');
        $pdf->Cell(30, 8, 'SELECCION', 1, 0, 'C');
        $pdf->Cell(0, 8, 'DETALLES', 1, 1, 'C');
        
        // Map display names to database field names (main table)
        $finishingFieldMap = [
            'BLOCADO' => ['field' => 'blocking', 'details' => 'blocking_details'],
            'CORTE' => ['field' => 'cutting', 'details' => 'cutting_details'],
            'DOBLADO' => ['field' => 'folding', 'details' => 'folding_details'],
            'LAMINADO' => ['field' => 'laminating', 'details' => 'laminating_details'],
            'LOMO' => ['field' => 'spine', 'details' => 'spine_details'],
            'NUMERADO' => ['field' => 'numbering', 'details' => 'numbering_details'],
            'PEGADO' => ['field' => 'gluing', 'details' => 'gluing_details'],
            'SIZADO' => ['field' => 'sizing', 'details' => 'sizing_details'],
            'ENGRAPADO' => ['field' => 'stapling', 'details' => 'stapling_details'],
            'TROQUEL' => ['field' => 'die_cutting', 'details' => 'die_cutting_details'],
            'BARNIZ' => ['field' => 'varnish', 'details' => 'varnish_details'],
            'IMP. EN BLANCO' => ['field' => 'white_print', 'details' => 'white_print_details'],
            'DESPUNTADO' => ['field' => 'trimming', 'details' => 'trimming_details'],
            'OJETES' => ['field' => 'eyelets', 'details' => 'eyelets_details'],
            'PERFORADO' => ['field' => 'perforation', 'details' => 'perforation_details']
        ];
        
        $pdf->SetFont('Arial', '', 9);
        $hasSelectedAcabados = false; // Track if any acabados are selected
        
        foreach ($finishingFieldMap as $displayName => $fields) {
            // Get selection value (SI/NO) from main table
            $fieldName = $fields['field'];
            $detailsFieldName = $fields['details'];
            
            $isSelected = $workOrderData->$fieldName ?? 'NO';
            $isSelected = strtoupper($isSelected); // Ensure uppercase
            if (empty($isSelected) || $isSelected === 'NULL') {
                $isSelected = 'NO';
            }
            
            // Only display rows where selection is "SI"
            if ($isSelected === 'SI') {
                $hasSelectedAcabados = true;
                
                // Get details from main table
                $details = $workOrderData->$detailsFieldName ?? '';
                $details = $fixSpanishChars($details);
                
                // No truncation - let MultiCell handle text wrapping
                $pdf->Cell(60, 6, $displayName, 1, 0, 'L');
                $pdf->Cell(30, 6, $isSelected, 1, 0, 'C');
                // Use MultiCell like INSTRUCCIONES GENERALES to allow text wrapping
                $pdf->MultiCell(0, 6, $details, 1, 'L');
            }
        }
        
        // If no acabados are selected, show a message
        if (!$hasSelectedAcabados) {
            $pdf->Cell(60, 6, 'NINGUNO SELECCIONADO', 1, 0, 'L');
            $pdf->Cell(30, 6, '', 1, 0, 'C');
            $pdf->Cell(0, 6, '', 1, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // INSTRUCCIONES GENERALES section with 3-row height
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(220, 220, 220); // Light gray background
        $pdf->Cell(0, 8, 'INSTRUCCIONES GENERALES', 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        $instructions = $workOrderData->instructions ?? 'N/A';
        $instructions = $fixSpanishChars($instructions); // Fix Spanish characters
        // Use MultiCell with proper line height for 3-row display
        $pdf->MultiCell(0, 6, $instructions, 1, 'L');
        
        $pdf->Ln(5);
        
        // INFORMACION DE ENVIO section
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetFillColor(220, 220, 220); // Light gray background
        $pdf->Cell(0, 8, 'INFORMACION DE ENVIO', 1, 1, 'L', true);
        
        // Tipo de Entrega
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(50, 7, 'TIPO DE ENTREGA:', 1, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $shippingType = $workOrderData->shipping_type ?? 'Entrega a domicilio';
        $shippingType = $fixSpanishChars($shippingType);
        $pdf->Cell(0, 7, $shippingType, 1, 1, 'L');
        
        // Check if "Recoge en oficina"
        if ($shippingType === 'Recoge en oficina') {
            // Only show "Recoge en oficina" message
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->SetTextColor(0, 102, 153); // Blue color
            $pdf->Cell(0, 7, 'El cliente recogera el pedido en la oficina de GRIMPSA', 1, 1, 'L');
            $pdf->SetTextColor(0, 0, 0); // Reset to black
        } else {
            // Show full shipping information
            // Direccion de Entrega - using MultiCell like INSTRUCCIONES GENERALES
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 7, 'DIRECCION DE ENTREGA:', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $shippingAddress = $workOrderData->shipping_address ?? 'N/A';
            $shippingAddress = $fixSpanishChars($shippingAddress);
            // Use MultiCell to allow text wrapping like INSTRUCCIONES GENERALES
            $pdf->MultiCell(0, 6, $shippingAddress, 1, 'L');
            
            // Nombre de Contacto
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 7, 'NOMBRE DE CONTACTO:', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $shippingContact = $workOrderData->shipping_contact ?? 'N/A';
            $shippingContact = $fixSpanishChars($shippingContact);
            $pdf->Cell(0, 7, $shippingContact, 1, 1, 'L');
            
            // Telefono de Contacto
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(50, 7, 'TELEFONO DE CONTACTO:', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $shippingPhone = $workOrderData->shipping_phone ?? 'N/A';
            $shippingPhone = $fixSpanishChars($shippingPhone);
            $pdf->Cell(0, 7, $shippingPhone, 1, 1, 'L');
        }
        
        // Instrucciones de Entrega - Label row (spans both columns)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 7, 'INSTRUCCIONES DE ENTREGA:', 1, 1, 'L');
        
        // Instrucciones de Entrega - Value row with 3-row height
        $pdf->SetFont('Arial', '', 9);
        $shippingInstructions = $workOrderData->instrucciones_entrega ?? 'N/A';
        $shippingInstructions = $fixSpanishChars($shippingInstructions);
        // Use MultiCell with proper line height for 3-row display
        $pdf->MultiCell(0, 6, $shippingInstructions, 1, 'L');
        
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
             * @param   string  $tipoMensajeria Tipo de mensajería (propio/terceros)
             * @param   string  $descripcionEnvio Shipping description text
             *
             * @return  void
             *
             * @since   1.0.0
             */
            private function generateShippingSlipPDF($orderId, $workOrderData, $tipoEnvio = 'completo', $tipoMensajeria = 'propio', $descripcionEnvio = '')
    {
        // Include FPDF library (same path as working PDF generation)
        require_once JPATH_ROOT . '/fpdf/fpdf.php';

        // Create PDF for 8.5" x 11" paper
        $pdf = new \FPDF('P', 'mm', array(215.9, 279.4)); // 8.5" x 11" in mm
        $pdf->AddPage();
        
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
        
        // Get shipping number (convert ORD-000000 to ENV-000000)
        $ordenNumber = $workOrderData->orden_de_trabajo ?? $workOrderData->numero_de_orden ?? 'ORD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);
        $envioNumber = str_replace('ORD-', 'ENV-', $ordenNumber);
        
        // Get current date
        $currentDate = date('d/m/Y');
        
        // Get client data from main table
        $clientName = $fixSpanishChars($workOrderData->client_name ?? 'N/A');
        $salesAgent = $fixSpanishChars($workOrderData->sales_agent ?? 'N/A');
        $workDescription = $fixSpanishChars($workOrderData->work_description ?? 'N/A');
        
        // Get shipping type and information from main table
        $shippingType = $workOrderData->shipping_type ?? 'Entrega a domicilio';
        
        // Handle "Recoge en oficina" vs "Entrega a domicilio"
        if ($shippingType === 'Recoge en oficina') {
            $shippingContact = 'N/A';
            $shippingPhone = 'N/A';
            $shippingAddress = 'Recoge en oficina';
            $shippingInstructions = 'El cliente recogera el pedido en la oficina de GRIMPSA';
        } else {
            $shippingContact = $fixSpanishChars($workOrderData->shipping_contact ?? 'N/A');
            $shippingPhone = $workOrderData->shipping_phone ?? 'N/A';
            $shippingAddress = $fixSpanishChars($workOrderData->shipping_address ?? 'N/A');
            $shippingInstructions = $fixSpanishChars($workOrderData->instrucciones_entrega ?? '');
        }
        
        // Generate two identical shipping slips on one page
        // Reduce spacing to fit both on one page (279.4mm total height)
        // Calculate optimal spacing: (279.4 - footer space) / 2 = ~125mm per slip max
        // Use 110mm spacing to leave room for footer
        $slipSpacing = 110; // Reduced spacing to ensure both fit on one page
        
        for ($slip = 0; $slip < 2; $slip++) {
            // Calculate Y position for each slip
            $startY = $slip * $slipSpacing;
            
            // Header with logo and title - clean layout
            // Logo (top left) - only show for "Propio" mensajería
            if ($tipoMensajeria === 'propio') {
                $logoPath = 'https://grimpsa_webserver.grantsolutions.cc/images/grimpsa_logo.gif';
                $pdf->Image($logoPath, 10, $startY + 20, 55, 0); // 55mm width, auto height
            }
            
            // Envio number only (center) - no "Envio #" label
            $pdf->SetFont('Arial', 'B', 26);
            $pdf->SetXY(0, $startY + 30);
            $pdf->Cell(0, 10, $envioNumber, 0, 1, 'C');
            
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetXY(0, $startY + 40);
            $pdf->Cell(0, 6, 'GUATEMALA, ' . $currentDate, 0, 1, 'C');
            
            // QR Code (top right) - no square border, no label
            $pdf->SetXY(159, $startY + 20);
            $pdf->Cell(40, 40, '', 0, 0, 'C'); // No border
            
            // Client and delivery information table - using exact dimensions from working code
            $cellHeight = 5;
            
            // Start table below header area
            $pdf->SetY($startY + 50);
            
            // Row 1: Cliente
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(37, $cellHeight, 'Cliente', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(153, $cellHeight, $clientName, 1, 0, 'L');
            $pdf->Ln();
            
            // Row 2: Agente de Ventas (only show if mensajeria is NOT "terceros")
            if ($tipoMensajeria !== 'terceros') {
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(37, $cellHeight, 'Agente de Ventas', 1, 0, 'L');
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(153, $cellHeight, $salesAgent, 1, 0, 'L');
                $pdf->Ln();
            }
            
            // Row 3: Contacto and Telefono (split row) - matching original layout
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(37, $cellHeight, 'Contacto', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(81, $cellHeight, $shippingContact, 1, 0, 'L');
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(32, $cellHeight, 'Telefono', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(40, $cellHeight, $shippingPhone, 1, 0, 'L');
            $pdf->Ln();
            
            // Row 4: Direccion de entrega - using MultiCell for text wrapping
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(37, $cellHeight, 'Direccion de entrega', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            // Use MultiCell to allow text wrapping like TRABAJO row
            $pdf->MultiCell(153, $cellHeight, $shippingAddress, 1, 'L');
            
            // Row 5: Instrucciones de entrega (full width)
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(190, $cellHeight, 'Instrucciones de entrega', 1, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(190, $cellHeight, $shippingInstructions, 1, 'L');
            $pdf->Ln();
            
            // Row 6: Tipo de Entrega
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(37, $cellHeight, 'Tipo de Entrega', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(153, $cellHeight, $tipoEnvio, 1, 0, 'L');
            $pdf->Ln();
            
            // Row 7: Trabajo - using MultiCell for text wrapping
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(37, $cellHeight, 'Trabajo', 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            // Use MultiCell to allow text wrapping like work order PDF
            $pdf->MultiCell(153, $cellHeight, $workDescription, 1, 'L');
            
            // Row 8: Descripcion de Envio - only show if provided
            if (!empty($descripcionEnvio)) {
                $descripcionEnvioFixed = $fixSpanishChars($descripcionEnvio);
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(37, $cellHeight, 'Descripcion de Envio', 1, 0, 'L');
                $pdf->SetFont('Arial', '', 9);
                // Use MultiCell to allow text wrapping
                $pdf->MultiCell(153, $cellHeight, $descripcionEnvioFixed, 1, 'L');
            }
            
            // Row 9: Large empty cell for additional work details (only if no shipping description)
            // Further reduce this height to save space
            if (empty($descripcionEnvio)) {
                $pdf->Cell(190, $cellHeight * 1.5, '', 1, 0, 'L'); // Reduced to 1.5 rows
                $pdf->Ln();
            }
            
            // Light gray separator - minimal spacing
            $pdf->SetFillColor(211, 211, 211);
            $pdf->Cell(190, 2, '', 0, 0, '', true);
            // Don't add extra line break here - footer will be positioned after loop
        }
        
        // Footer with single signature box and labels - only once at the bottom of the page
        // (after both slips are generated)
        // Use GetY() to position footer right after the last slip's content
        // But ensure it doesn't go beyond page boundary (279.4mm)
        $currentY = $pdf->GetY();
        $maxY = 250; // Maximum Y position to ensure footer fits on page
        
        // If content extends beyond maxY, adjust footer position
        $footerX = 10; // Starting X position (matches content start)
        $footerWidth = 190; // Width matching content cells above
        $signatureBoxHeight = 16; // Further reduced height
        $labelHeight = 5; // Height for labels
        
        // Calculate footer Y position
        $footerY = min($currentY + 3, $maxY); // Add small spacing after last slip, but cap at maxY
        
        // Draw single signature box spanning full width
        $pdf->Rect($footerX, $footerY, $footerWidth, $signatureBoxHeight);
        
        // Labels below box (centered in three equal sections)
        $labelY = $footerY + $signatureBoxHeight + 1; // Small spacing between box and labels
        $pdf->SetY($labelY);
        $pdf->SetFont('Arial', 'B', 8); // Smaller font to save space
        $labelWidth = $footerWidth / 3; // Divide width equally among three labels
        $pdf->Cell($labelWidth, $labelHeight, 'FECHA', 0, 0, 'C');
        $pdf->Cell($labelWidth, $labelHeight, 'NOMBRE Y FIRMA', 0, 0, 'C');
        $pdf->Cell($labelWidth, $labelHeight, 'Sello', 0, 0, 'C');
        $pdf->Ln();
        
        // Output PDF
        $pdf->Output('I', 'envio_' . $envioNumber . '.pdf');
        exit;
    }

}