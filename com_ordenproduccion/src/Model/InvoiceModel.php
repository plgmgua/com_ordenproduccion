<?php
/**
 * Invoice Model (Single) for Com Orden Produccion
 * 
 * Handles single invoice operations, including PDF extraction
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Model
 * @subpackage  Invoice
 * @since       3.2.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseInterface;

class InvoiceModel extends BaseDatabaseModel
{
    /**
     * Get invoice by ID
     */
    public function getItem($id = null)
    {
        if (empty($id)) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('i.*')
            ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
            ->where($db->quoteName('i.id') . ' = ' . (int) $id)
            ->where($db->quoteName('i.state') . ' = 1');

        $db->setQuery($query);
        $invoice = $db->loadObject();

        if ($invoice && !empty($invoice->line_items)) {
            $invoice->line_items = json_decode($invoice->line_items, true);
        }

        return $invoice;
    }

    /**
     * Get invoice by invoice number
     */
    public function getItemByInvoiceNumber($invoiceNumber)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('i.*')
            ->from($db->quoteName('#__ordenproduccion_invoices', 'i'))
            ->where($db->quoteName('i.invoice_number') . ' = ' . $db->quote($invoiceNumber))
            ->where($db->quoteName('i.state') . ' = 1');

        $db->setQuery($query);
        return $db->loadObject();
    }

    /**
     * Get work order associated with invoice
     */
    public function getWorkOrder($ordenId)
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_ordenes'))
            ->where($db->quoteName('id') . ' = ' . (int) $ordenId);

        $db->setQuery($query);
        return $db->loadObject();
    }

    /**
     * Extract data from quotation PDF
     * 
     * @param string $pdfPath Path to PDF file
     * @return array Extracted data or empty array on failure
     */
    public function extractFromPDF($pdfPath)
    {
        // Check if PDF exists
        if (!file_exists($pdfPath)) {
            return [
                'success' => false,
                'error' => 'PDF file not found: ' . $pdfPath
            ];
        }

        // Check if smalot/pdfparser is available
        $parserClass = '\\Smalot\\PdfParser\\Parser';
        if (!class_exists($parserClass)) {
            return [
                'success' => false,
                'error' => 'PDF Parser library not installed. Run: composer require smalot/pdfparser'
            ];
        }

        try {
            // Parse PDF
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdfPath);
            $text = $pdf->getText();

            // Extract line items from table
            $lineItems = $this->parseTableFromText($text);

            // Extract client information
            $clientInfo = $this->parseClientInfo($text);

            return [
                'success' => true,
                'line_items' => $lineItems,
                'client_info' => $clientInfo,
                'raw_text' => $text
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'PDF parsing error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse table data from PDF text
     */
    private function parseTableFromText($text)
    {
        $lineItems = [];

        // Pattern to match: Cantidad | Descripción | Precio
        // Example: "2 Rótulos en PVC de 3mm... Q. 450.00"
        
        // Split text into lines
        $lines = explode("\n", $text);
        
        $inTable = false;
        $currentItem = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Detect table start
            if (stripos($line, 'Cantidad') !== false && stripos($line, 'Descripción') !== false) {
                $inTable = true;
                continue;
            }

            // Detect table end
            if ($inTable && (stripos($line, 'Precios incluyen') !== false || empty($line))) {
                $inTable = false;
            }

            // Extract row data
            if ($inTable && !empty($line)) {
                // Try to match: [number] [description] [Q. amount]
                if (preg_match('/^(\d+)\s+(.+?)\s+(Q\.\s*[\d,\.]+)$/i', $line, $matches)) {
                    $quantity = (int) $matches[1];
                    $description = trim($matches[2]);
                    $priceStr = $matches[3];
                    
                    // Clean price
                    $price = $this->cleanPrice($priceStr);
                    
                    $lineItems[] = [
                        'quantity' => $quantity,
                        'description' => $description,
                        'unit_price' => $price / $quantity,
                        'total' => $price
                    ];
                }
            }
        }

        return $lineItems;
    }

    /**
     * Parse client information from PDF text
     */
    private function parseClientInfo($text)
    {
        $info = [];

        // Extract client name (Señores: ...)
        if (preg_match('/Señores:\s*([^\n]+)/i', $text, $matches)) {
            $info['client_name'] = trim($matches[1]);
        }

        // Extract attention (Atención: ...)
        if (preg_match('/Atención:\s*([^\n]+)/i', $text, $matches)) {
            $info['attention'] = trim($matches[1]);
        }

        // Extract date
        if (preg_match('/Guatemala\s+(\d+\s+de\s+\w+\s+\d{4})/i', $text, $matches)) {
            $info['date'] = trim($matches[1]);
        }

        return $info;
    }

    /**
     * Clean price string to decimal
     */
    private function cleanPrice($priceStr)
    {
        // Remove Q., $, spaces
        $cleaned = preg_replace('/^Q\.?\s*/', '', $priceStr);
        $cleaned = preg_replace('/^\$\s*/', '', $cleaned);
        $cleaned = str_replace(',', '', $cleaned);
        $cleaned = preg_replace('/[^0-9.]/', '', $cleaned);
        
        return floatval($cleaned);
    }

    /**
     * Save invoice
     */
    public function save($data)
    {
        error_log('=== InvoiceModel::save() called ===');
        error_log('Input data: ' . print_r($data, true));
        
        $db = $this->getDatabase();
        $user = Factory::getUser();
        $date = Factory::getDate()->toSql();
        
        error_log('User ID: ' . $user->id);
        error_log('Current date: ' . $date);

        try {
            // Prepare data
            $invoiceData = [
                'invoice_number' => $data['invoice_number'],
                'orden_id' => (int) $data['orden_id'],
                'orden_de_trabajo' => $data['orden_de_trabajo'],
                'client_name' => $data['client_name'],
                'client_nit' => $data['client_nit'] ?? null,
                'sales_agent' => $data['sales_agent'] ?? null,
                'request_date' => $data['request_date'] ?? null,
                'delivery_date' => $data['delivery_date'] ?? null,
                'invoice_date' => $data['invoice_date'] ?? $date,
                'invoice_amount' => floatval($data['invoice_amount']),
                'currency' => $data['currency'] ?? 'Q',
                'work_description' => $data['work_description'] ?? null,
                'material' => $data['material'] ?? null,
                'dimensions' => $data['dimensions'] ?? null,
                'print_color' => $data['print_color'] ?? null,
                'line_items' => json_encode($data['line_items'] ?? []),
                'quotation_file' => $data['quotation_file'] ?? null,
                'extraction_status' => $data['extraction_status'] ?? 'manual',
                'extraction_date' => !empty($data['extraction_date']) ? $data['extraction_date'] : null,
                'status' => $data['status'] ?? 'draft',
                'notes' => $data['notes'] ?? null,
                'state' => 1,
                'version' => '3.2.0'
            ];

            // Check if updating or creating
            if (!empty($data['id'])) {
                // Update
                $invoiceData['id'] = (int) $data['id'];
                $invoiceData['modified'] = $date;
                $invoiceData['modified_by'] = $user->id;

                $result = $db->updateObject('#__ordenproduccion_invoices', (object) $invoiceData, 'id');
            } else {
                // Create
                $invoiceData['created'] = $date;
                $invoiceData['created_by'] = $user->id;

                $result = $db->insertObject('#__ordenproduccion_invoices', (object) $invoiceData, 'id');
            }

            return $result;

        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage('Error saving invoice: ' . $e->getMessage(), 'error');
            return false;
        }
    }
}

