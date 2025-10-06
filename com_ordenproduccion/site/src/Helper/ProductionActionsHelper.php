<?php
/**
 * Production Actions Helper
 * 
 * Provides functionality for production-related actions like PDF generation,
 * Excel export, and production management.
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @subpackage  Production
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * Production Actions Helper Class
 */
class ProductionActionsHelper
{
    protected $db;
    protected $app;

    public function __construct()
    {
        $this->db = Factory::getDbo();
        $this->app = Factory::getApplication();
    }

    /**
     * Generate PDF for work order
     *
     * @param   int     $orderId  The order ID
     * @return  string  PDF file path or false on error
     * @since   1.0.0
     */
    public function generateWorkOrderPDF($orderId)
    {
        try {
            // Get order data
            $order = $this->getOrderData($orderId);
            if (!$order) {
                throw new \Exception('Order not found');
            }

            // Create PDF content
            $pdfContent = $this->createPDFContent($order);
            
            // Generate PDF file
            $fileName = 'orden_' . $order->orden_de_trabajo . '_' . date('Y-m-d') . '.pdf';
            $filePath = JPATH_ROOT . '/tmp/production_pdfs/' . $fileName;
            
            // Ensure directory exists
            $this->ensureDirectoryExists(dirname($filePath));
            
            // For now, create a simple HTML file that can be converted to PDF
            // In a full implementation, you would use a PDF library like TCPDF or mPDF
            file_put_contents($filePath, $pdfContent);
            
            Log::add('PDF generated for order: ' . $order->orden_de_trabajo, Log::INFO, 'com_ordenproduccion');
            
            return $filePath;
            
        } catch (\Exception $e) {
            Log::add('Error generating PDF: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return false;
        }
    }

    /**
     * Export orders to Excel
     *
     * @param   array   $filters  Filter criteria
     * @return  string  Excel file path or false on error
     * @since   1.0.0
     */
    public function exportOrdersToExcel($filters = [])
    {
        try {
            // Get orders data
            $orders = $this->getOrdersForExport($filters);
            
            // Create Excel content
            $excelContent = $this->createExcelContent($orders);
            
            // Generate Excel file
            $fileName = 'ordenes_export_' . date('Y-m-d_H-i-s') . '.xlsx';
            $filePath = JPATH_ROOT . '/tmp/production_exports/' . $fileName;
            
            // Ensure directory exists
            $this->ensureDirectoryExists(dirname($filePath));
            
            // For now, create a CSV file that can be opened in Excel
            // In a full implementation, you would use PhpSpreadsheet
            file_put_contents($filePath, $excelContent);
            
            Log::add('Excel export generated: ' . $fileName, Log::INFO, 'com_ordenproduccion');
            
            return $filePath;
            
        } catch (\Exception $e) {
            Log::add('Error generating Excel export: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return false;
        }
    }

    /**
     * Get production statistics
     *
     * @param   string  $startDate  Start date
     * @param   string  $endDate    End date
     * @return  array   Production statistics
     * @since   1.0.0
     */
    public function getProductionStatistics($startDate, $endDate)
    {
        try {
            $query = $this->db->getQuery(true)
                ->select([
                    'COUNT(*) as total_orders',
                    'SUM(invoice_value) as total_value',
                    'AVG(invoice_value) as average_value',
                    'COUNT(CASE WHEN status = "Terminada" THEN 1 END) as completed_orders',
                    'COUNT(CASE WHEN status = "En Proceso" THEN 1 END) as in_progress_orders'
                ])
                ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
                ->where($this->db->quoteName('request_date') . ' >= ' . $this->db->quote($startDate))
                ->where($this->db->quoteName('request_date') . ' <= ' . $this->db->quote($endDate));

            $this->db->setQuery($query);
            $stats = $this->db->loadObject();

            return [
                'total_orders' => (int) $stats->total_orders,
                'total_value' => (float) $stats->total_value,
                'average_value' => (float) $stats->average_value,
                'completed_orders' => (int) $stats->completed_orders,
                'in_progress_orders' => (int) $stats->in_progress_orders,
                'completion_rate' => $stats->total_orders > 0 ? round(($stats->completed_orders / $stats->total_orders) * 100, 2) : 0
            ];
            
        } catch (\Exception $e) {
            Log::add('Error getting production statistics: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return [];
        }
    }

    /**
     * Get order data for PDF generation
     *
     * @param   int  $orderId  The order ID
     * @return  object|false  Order data or false on error
     * @since   1.0.0
     */
    protected function getOrderData($orderId)
    {
        try {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
                ->where($this->db->quoteName('id') . ' = ' . (int) $orderId);

            $this->db->setQuery($query);
            return $this->db->loadObject();
            
        } catch (\Exception $e) {
            Log::add('Error getting order data: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return false;
        }
    }

    /**
     * Create PDF content
     *
     * @param   object  $order  Order data
     * @return  string  HTML content for PDF
     * @since   1.0.0
     */
    protected function createPDFContent($order)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Orden de Trabajo - ' . htmlspecialchars($order->orden_de_trabajo) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .order-info { margin-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .section h3 { background-color: #f0f0f0; padding: 10px; margin: 0; }
        .section-content { padding: 10px; border: 1px solid #ddd; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <div class="header">
        <h1>ORDEN DE TRABAJO</h1>
        <h2>' . htmlspecialchars($order->orden_de_trabajo) . '</h2>
    </div>

    <div class="order-info">
        <table>
            <tr>
                <th>Cliente:</th>
                <td>' . htmlspecialchars($order->client_name) . '</td>
                <th>Fecha de Solicitud:</th>
                <td>' . htmlspecialchars($order->request_date) . '</td>
            </tr>
            <tr>
                <th>NIT:</th>
                <td>' . htmlspecialchars($order->nit) . '</td>
                <th>Fecha de Entrega:</th>
                <td>' . htmlspecialchars($order->delivery_date) . '</td>
            </tr>
            <tr>
                <th>Agente de Ventas:</th>
                <td>' . htmlspecialchars($order->sales_agent) . '</td>
                <th>Estado:</th>
                <td>' . htmlspecialchars($order->status) . '</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Descripción del Trabajo</h3>
        <div class="section-content">
            <p><strong>Descripción:</strong> ' . htmlspecialchars($order->work_description) . '</p>
            <p><strong>Color de Impresión:</strong> ' . htmlspecialchars($order->print_color) . '</p>
            <p><strong>Medidas:</strong> ' . htmlspecialchars($order->dimensions) . '</p>
            <p><strong>Material:</strong> ' . htmlspecialchars($order->material) . '</p>
        </div>
    </div>

    <div class="section">
        <h3>Información de Producción</h3>
        <div class="section-content">
            <table>
                <tr>
                    <th>Corte:</th>
                    <td>' . ($order->cutting === 'SI' ? 'Sí' : 'No') . '</td>
                    <th>Blocado:</th>
                    <td>' . ($order->blocking === 'SI' ? 'Sí' : 'No') . '</td>
                </tr>
                <tr>
                    <th>Doblado:</th>
                    <td>' . ($order->folding === 'SI' ? 'Sí' : 'No') . '</td>
                    <th>Laminado:</th>
                    <td>' . ($order->laminating === 'SI' ? 'Sí' : 'No') . '</td>
                </tr>
                <tr>
                    <th>Numerado:</th>
                    <td>' . ($order->numbering === 'SI' ? 'Sí' : 'No') . '</td>
                    <th>Troquel:</th>
                    <td>' . ($order->die_cutting === 'SI' ? 'Sí' : 'No') . '</td>
                </tr>
                <tr>
                    <th>Barniz:</th>
                    <td>' . ($order->varnish === 'SI' ? 'Sí' : 'No') . '</td>
                    <th>Valor a Facturar:</th>
                    <td>Q. ' . number_format($order->invoice_value, 2) . '</td>
                </tr>
            </table>
        </div>
    </div>

    <div class="section">
        <h3>Instrucciones Especiales</h3>
        <div class="section-content">
            <p>' . htmlspecialchars($order->instructions ?? 'Sin instrucciones especiales') . '</p>
        </div>
    </div>

    <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
        <p>Generado el: ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Get orders for export
     *
     * @param   array  $filters  Filter criteria
     * @return  array  Orders data
     * @since   1.0.0
     */
    protected function getOrdersForExport($filters)
    {
        try {
            $query = $this->db->getQuery(true)
                ->select([
                    'orden_de_trabajo',
                    'client_name',
                    'nit',
                    'sales_agent',
                    'request_date',
                    'delivery_date',
                    'status',
                    'work_description',
                    'print_color',
                    'dimensions',
                    'material',
                    'invoice_value'
                ])
                ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
                ->where($this->db->quoteName('state') . ' = 1');

            // Apply filters
            if (!empty($filters['start_date'])) {
                $query->where($this->db->quoteName('request_date') . ' >= ' . $this->db->quote($filters['start_date']));
            }
            if (!empty($filters['end_date'])) {
                $query->where($this->db->quoteName('request_date') . ' <= ' . $this->db->quote($filters['end_date']));
            }
            if (!empty($filters['status'])) {
                $query->where($this->db->quoteName('status') . ' = ' . $this->db->quote($filters['status']));
            }

            $query->order($this->db->quoteName('request_date') . ' DESC');

            $this->db->setQuery($query);
            return $this->db->loadObjectList();
            
        } catch (\Exception $e) {
            Log::add('Error getting orders for export: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            return [];
        }
    }

    /**
     * Create Excel content
     *
     * @param   array  $orders  Orders data
     * @return  string  CSV content
     * @since   1.0.0
     */
    protected function createExcelContent($orders)
    {
        $csv = "Orden de Trabajo,Cliente,NIT,Agente de Ventas,Fecha Solicitud,Fecha Entrega,Estado,Descripción,Color,Medidas,Material,Valor\n";
        
        foreach ($orders as $order) {
            $csv .= '"' . $order->orden_de_trabajo . '",';
            $csv .= '"' . $order->client_name . '",';
            $csv .= '"' . $order->nit . '",';
            $csv .= '"' . $order->sales_agent . '",';
            $csv .= '"' . $order->request_date . '",';
            $csv .= '"' . $order->delivery_date . '",';
            $csv .= '"' . $order->status . '",';
            $csv .= '"' . $order->work_description . '",';
            $csv .= '"' . $order->print_color . '",';
            $csv .= '"' . $order->dimensions . '",';
            $csv .= '"' . $order->material . '",';
            $csv .= '"' . $order->invoice_value . '"' . "\n";
        }
        
        return $csv;
    }

    /**
     * Ensure directory exists
     *
     * @param   string  $dir  Directory path
     * @return  void
     * @since   1.0.0
     */
    protected function ensureDirectoryExists($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
