<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Joomla\CMS\Factory;
use Joomla\CMS\Application\AdministratorApplication;
use Grimpsa\Component\Ordenproduccion\Site\Model\WebhookModel;

/**
 * Integration test for webhook functionality
 *
 * @since  1.0.0
 */
class WebhookTest extends TestCase
{
    /**
     * The application instance
     *
     * @var    AdministratorApplication
     * @since  1.0.0
     */
    protected $app;

    /**
     * The database instance
     *
     * @var    \Joomla\Database\DatabaseInterface
     * @since  1.0.0
     */
    protected $db;

    /**
     * Set up before each test
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->app = Factory::getApplication('Administrator');
        $this->db = Factory::getDbo();
        
        // Create test tables if they don't exist
        $this->createTestTables();
    }

    /**
     * Tear down after each test
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();
        
        parent::tearDown();
    }

    /**
     * Test webhook order creation
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testWebhookOrderCreation()
    {
        $webhookModel = new WebhookModel();
        
        $testData = [
            'request_title' => 'Solicitud Ventas a Produccion - TEST',
            'form_data' => [
                'client_id' => '999',
                'cliente' => 'Test Client S.A.',
                'nit' => '123456789',
                'valor_factura' => '1000',
                'descripcion_trabajo' => 'Test work order from webhook - 500 Flyers Full Color',
                'color_impresion' => 'Full Color',
                'tiro_retiro' => 'Tiro/Retiro',
                'medidas' => '8.5 x 11',
                'fecha_entrega' => date('d/m/Y', strtotime('+7 days')),
                'material' => 'Husky 250 grms',
                'cotizacion' => ['/media/com_convertforms/uploads/test_cotizacion.pdf'],
                'arte' => ['/media/com_convertforms/uploads/test_arte.pdf'],
                'corte' => 'SI',
                'detalles_corte' => 'Corte recto en guillotina',
                'blocado' => 'NO',
                'doblado' => 'NO',
                'laminado' => 'NO',
                'lomo' => 'NO',
                'pegado' => 'NO',
                'numerado' => 'NO',
                'sizado' => 'NO',
                'engrapado' => 'NO',
                'troquel' => 'NO',
                'barniz' => 'NO',
                'impresion_blanco' => 'NO',
                'despuntado' => 'NO',
                'ojetes' => 'NO',
                'perforado' => 'NO',
                'instrucciones' => 'Test order - please process normally',
                'agente_de_ventas' => 'Test Agent',
                'fecha_de_solicitud' => date('Y-m-d H:i:s')
            ]
        ];
        
        // Test order creation
        $orderId = $webhookModel->createOrder($testData);
        
        $this->assertIsInt($orderId);
        $this->assertGreaterThan(0, $orderId);
        
        // Verify order was created in database
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $orderId);
        
        $this->db->setQuery($query);
        $order = $this->db->loadObject();
        
        $this->assertNotNull($order);
        $this->assertEquals('Test Client S.A.', $order->nombre_del_cliente);
        $this->assertEquals('externa', $order->type);
        $this->assertEquals('nueva', $order->status);
        
        // Verify EAV data was stored
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_info'))
            ->where($this->db->quoteName('orden_id') . ' = ' . (int) $orderId);
        
        $this->db->setQuery($query);
        $eavData = $this->db->loadObjectList();
        
        $this->assertNotEmpty($eavData);
        
        // Check for specific EAV entries
        $eavValues = [];
        foreach ($eavData as $item) {
            $eavValues[$item->attribute_key] = $item->attribute_value;
        }
        
        $this->assertEquals('999', $eavValues['client_id']);
        $this->assertEquals('123456789', $eavValues['nit']);
        $this->assertEquals('1000', $eavValues['valor_factura']);
    }

    /**
     * Test webhook order update
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testWebhookOrderUpdate()
    {
        $webhookModel = new WebhookModel();
        
        // First create an order
        $testData = [
            'request_title' => 'Solicitud Ventas a Produccion - TEST',
            'form_data' => [
                'client_id' => '999',
                'cliente' => 'Test Client S.A.',
                'nit' => '123456789',
                'valor_factura' => '1000',
                'descripcion_trabajo' => 'Test work order from webhook',
                'fecha_entrega' => date('d/m/Y', strtotime('+7 days')),
                'agente_de_ventas' => 'Test Agent',
                'fecha_de_solicitud' => date('Y-m-d H:i:s')
            ]
        ];
        
        $orderId = $webhookModel->createOrder($testData);
        $this->assertGreaterThan(0, $orderId);
        
        // Now update the order
        $updateData = [
            'request_title' => 'Solicitud Ventas a Produccion - TEST UPDATE',
            'form_data' => [
                'client_id' => '999',
                'cliente' => 'Test Client S.A. Updated',
                'nit' => '987654321',
                'valor_factura' => '2000',
                'descripcion_trabajo' => 'Updated test work order from webhook',
                'fecha_entrega' => date('d/m/Y', strtotime('+10 days')),
                'agente_de_ventas' => 'Updated Test Agent',
                'fecha_de_solicitud' => date('Y-m-d H:i:s')
            ]
        ];
        
        $updatedOrderId = $webhookModel->updateOrder($orderId, $updateData);
        
        $this->assertEquals($orderId, $updatedOrderId);
        
        // Verify order was updated
        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $orderId);
        
        $this->db->setQuery($query);
        $order = $this->db->loadObject();
        
        $this->assertEquals('Test Client S.A. Updated', $order->nombre_del_cliente);
        $this->assertEquals('Updated test work order from webhook', $order->descripcion_de_trabajo);
    }

    /**
     * Test webhook order finding
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testWebhookOrderFinding()
    {
        $webhookModel = new WebhookModel();
        
        $testData = [
            'request_title' => 'Solicitud Ventas a Produccion - TEST',
            'form_data' => [
                'client_id' => '999',
                'cliente' => 'Test Client S.A.',
                'nit' => '123456789',
                'valor_factura' => '1000',
                'descripcion_trabajo' => 'Test work order from webhook',
                'fecha_entrega' => date('d/m/Y', strtotime('+7 days')),
                'agente_de_ventas' => 'Test Agent',
                'fecha_de_solicitud' => date('Y-m-d H:i:s')
            ]
        ];
        
        // First, no order should exist
        $existingOrder = $webhookModel->findExistingOrder($testData);
        $this->assertNull($existingOrder);
        
        // Create the order
        $orderId = $webhookModel->createOrder($testData);
        $this->assertGreaterThan(0, $orderId);
        
        // Now the order should be found
        $existingOrder = $webhookModel->findExistingOrder($testData);
        $this->assertNotNull($existingOrder);
        $this->assertEquals($orderId, $existingOrder->id);
    }

    /**
     * Test date formatting
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testDateFormatting()
    {
        $webhookModel = new WebhookModel();
        
        // Test DD/MM/YYYY format
        $date = '15/10/2025';
        $formatted = $webhookModel->formatDate($date);
        
        $this->assertNotNull($formatted);
        $this->assertStringContainsString('2025-10-15', $formatted);
        
        // Test YYYY-MM-DD format
        $date = '2025-10-15';
        $formatted = $webhookModel->formatDate($date);
        
        $this->assertNotNull($formatted);
        $this->assertStringContainsString('2025-10-15', $formatted);
        
        // Test invalid date
        $date = 'invalid-date';
        $formatted = $webhookModel->formatDate($date);
        
        $this->assertNull($formatted);
    }

    /**
     * Create test tables
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function createTestTables()
    {
        // Create orders table
        $sql = "CREATE TABLE IF NOT EXISTS `#__ordenproduccion_ordenes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `orden_de_trabajo` varchar(100) NOT NULL,
            `nombre_del_cliente` varchar(255) NOT NULL,
            `descripcion_de_trabajo` text,
            `fecha_de_entrega` datetime DEFAULT NULL,
            `type` enum('interna','externa') DEFAULT 'interna',
            `status` enum('nueva','en_proceso','terminada','cerrada') DEFAULT 'nueva',
            `state` tinyint(3) NOT NULL DEFAULT 1,
            `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_by` int(11) NOT NULL,
            `modified` datetime DEFAULT NULL,
            `modified_by` int(11) DEFAULT NULL,
            `version` varchar(20) DEFAULT '1.0.0',
            PRIMARY KEY (`id`),
            UNIQUE KEY `idx_orden_trabajo` (`orden_de_trabajo`),
            KEY `idx_status` (`status`),
            KEY `idx_type` (`type`),
            KEY `idx_created` (`created`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->setQuery($sql);
        $this->db->execute();
        
        // Create info table (EAV)
        $sql = "CREATE TABLE IF NOT EXISTS `#__ordenproduccion_info` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `orden_id` int(11) NOT NULL,
            `attribute_key` varchar(100) NOT NULL,
            `attribute_value` text,
            `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_orden_id` (`orden_id`),
            KEY `idx_attribute_key` (`attribute_key`),
            UNIQUE KEY `idx_orden_attribute` (`orden_id`, `attribute_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->setQuery($sql);
        $this->db->execute();
    }

    /**
     * Clean up test data
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function cleanupTestData()
    {
        // Clean up test orders
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->where($this->db->quoteName('nombre_del_cliente') . ' LIKE ' . $this->db->quote('%Test Client%'));
        
        $this->db->setQuery($query);
        $this->db->execute();
        
        // Clean up test info
        $query = $this->db->getQuery(true)
            ->delete($this->db->quoteName('#__ordenproduccion_info'))
            ->where($this->db->quoteName('attribute_value') . ' LIKE ' . $this->db->quote('%Test%'));
        
        $this->db->setQuery($query);
        $this->db->execute();
    }
}
