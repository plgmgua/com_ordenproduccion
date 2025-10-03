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

/**
 * Integration test for component installation
 *
 * @since  1.0.0
 */
class InstallationTest extends TestCase
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
        // Clean up test tables
        $this->cleanupTestTables();
        
        parent::tearDown();
    }

    /**
     * Test database table creation
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testDatabaseTableCreation()
    {
        // Read the installation SQL file
        $sqlFile = dirname(__DIR__, 2) . '/admin/sql/install.mysql.utf8.sql';
        $this->assertFileExists($sqlFile);
        
        $sql = file_get_contents($sqlFile);
        $this->assertNotEmpty($sql);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        // Execute each statement
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                $this->db->setQuery($statement);
                $this->db->execute();
            }
        }
        
        // Verify tables were created
        $tables = [
            '#__ordenproduccion_ordenes',
            '#__ordenproduccion_info',
            '#__ordenproduccion_technicians',
            '#__ordenproduccion_assignments',
            '#__ordenproduccion_attendance',
            '#__ordenproduccion_production_notes',
            '#__ordenproduccion_shipping',
            '#__ordenproduccion_config',
            '#__ordenproduccion_webhook_logs'
        ];
        
        foreach ($tables as $table) {
            $this->assertTrue($this->tableExists($table), "Table {$table} should exist");
        }
    }

    /**
     * Test orders table structure
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testOrdersTableStructure()
    {
        $this->createTestTables();
        
        $columns = $this->getTableColumns('#__ordenproduccion_ordenes');
        
        $expectedColumns = [
            'id',
            'orden_de_trabajo',
            'nombre_del_cliente',
            'descripcion_de_trabajo',
            'fecha_de_entrega',
            'type',
            'status',
            'state',
            'created',
            'created_by',
            'modified',
            'modified_by',
            'version'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $columns, "Column {$column} should exist in orders table");
        }
        
        // Test specific column types
        $this->assertEquals('int', $columns['id']['type']);
        $this->assertEquals('varchar', $columns['orden_de_trabajo']['type']);
        $this->assertEquals('varchar', $columns['nombre_del_cliente']['type']);
        $this->assertEquals('text', $columns['descripcion_de_trabajo']['type']);
        $this->assertEquals('datetime', $columns['fecha_de_entrega']['type']);
        $this->assertEquals('enum', $columns['type']['type']);
        $this->assertEquals('enum', $columns['status']['type']);
    }

    /**
     * Test info table structure (EAV)
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testInfoTableStructure()
    {
        $this->createTestTables();
        
        $columns = $this->getTableColumns('#__ordenproduccion_info');
        
        $expectedColumns = [
            'id',
            'orden_id',
            'attribute_key',
            'attribute_value',
            'created'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $columns, "Column {$column} should exist in info table");
        }
        
        // Test specific column types
        $this->assertEquals('int', $columns['id']['type']);
        $this->assertEquals('int', $columns['orden_id']['type']);
        $this->assertEquals('varchar', $columns['attribute_key']['type']);
        $this->assertEquals('text', $columns['attribute_value']['type']);
        $this->assertEquals('datetime', $columns['created']['type']);
    }

    /**
     * Test technicians table structure
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testTechniciansTableStructure()
    {
        $this->createTestTables();
        
        $columns = $this->getTableColumns('#__ordenproduccion_technicians');
        
        $expectedColumns = [
            'id',
            'name',
            'email',
            'phone',
            'status',
            'state',
            'created',
            'created_by',
            'modified',
            'modified_by'
        ];
        
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $columns, "Column {$column} should exist in technicians table");
        }
    }

    /**
     * Test table indexes
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testTableIndexes()
    {
        $this->createTestTables();
        
        // Test orders table indexes
        $indexes = $this->getTableIndexes('#__ordenproduccion_ordenes');
        
        $expectedIndexes = [
            'PRIMARY',
            'idx_orden_trabajo',
            'idx_status',
            'idx_type',
            'idx_created'
        ];
        
        foreach ($expectedIndexes as $index) {
            $this->assertArrayHasKey($index, $indexes, "Index {$index} should exist in orders table");
        }
        
        // Test info table indexes
        $indexes = $this->getTableIndexes('#__ordenproduccion_info');
        
        $expectedIndexes = [
            'PRIMARY',
            'idx_orden_id',
            'idx_attribute_key',
            'idx_orden_attribute'
        ];
        
        foreach ($expectedIndexes as $index) {
            $this->assertArrayHasKey($index, $indexes, "Index {$index} should exist in info table");
        }
    }

    /**
     * Test ACL rules creation
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testACLRulesCreation()
    {
        $this->createTestTables();
        
        // Check if ACL rules table exists
        $this->assertTrue($this->tableExists('#__assets'));
        
        // Test ACL rules insertion
        $aclRules = [
            'com_ordenproduccion',
            'com_ordenproduccion.orden',
            'com_ordenproduccion.technician',
            'com_ordenproduccion.webhook',
            'com_ordenproduccion.debug'
        ];
        
        foreach ($aclRules as $rule) {
            $query = $this->db->getQuery(true)
                ->select('id')
                ->from($this->db->quoteName('#__assets'))
                ->where($this->db->quoteName('name') . ' = ' . $this->db->quote($rule));
            
            $this->db->setQuery($query);
            $assetId = $this->db->loadResult();
            
            // For this test, we'll just verify the query works
            $this->assertIsNumeric($assetId);
        }
    }

    /**
     * Test sample data insertion
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testSampleDataInsertion()
    {
        $this->createTestTables();
        
        // Insert sample order
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__ordenproduccion_ordenes'))
            ->columns([
                $this->db->quoteName('orden_de_trabajo'),
                $this->db->quoteName('nombre_del_cliente'),
                $this->db->quoteName('descripcion_de_trabajo'),
                $this->db->quoteName('fecha_de_entrega'),
                $this->db->quoteName('type'),
                $this->db->quoteName('status'),
                $this->db->quoteName('state'),
                $this->db->quoteName('created'),
                $this->db->quoteName('created_by'),
                $this->db->quoteName('version')
            ])
            ->values([
                $this->db->quote('TEST-001'),
                $this->db->quote('Test Client'),
                $this->db->quote('Test work order'),
                $this->db->quote('2025-12-31 00:00:00'),
                $this->db->quote('interna'),
                $this->db->quote('nueva'),
                1,
                $this->db->quote(date('Y-m-d H:i:s')),
                1,
                $this->db->quote('1.0.0')
            ]);
        
        $this->db->setQuery($query);
        $result = $this->db->execute();
        
        $this->assertTrue($result);
        
        $orderId = $this->db->insertid();
        $this->assertGreaterThan(0, $orderId);
        
        // Insert sample EAV data
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__ordenproduccion_info'))
            ->columns([
                $this->db->quoteName('orden_id'),
                $this->db->quoteName('attribute_key'),
                $this->db->quoteName('attribute_value'),
                $this->db->quoteName('created')
            ])
            ->values([
                (int) $orderId,
                $this->db->quote('test_attribute'),
                $this->db->quote('test_value'),
                $this->db->quote(date('Y-m-d H:i:s'))
            ]);
        
        $this->db->setQuery($query);
        $result = $this->db->execute();
        
        $this->assertTrue($result);
    }

    /**
     * Check if table exists
     *
     * @param   string  $table  Table name
     *
     * @return  boolean  True if table exists
     *
     * @since   1.0.0
     */
    protected function tableExists($table)
    {
        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from('INFORMATION_SCHEMA.TABLES')
            ->where('TABLE_SCHEMA = ' . $this->db->quote($this->db->getDatabase()))
            ->where('TABLE_NAME = ' . $this->db->quote($table));
        
        $this->db->setQuery($query);
        $count = $this->db->loadResult();
        
        return $count > 0;
    }

    /**
     * Get table columns
     *
     * @param   string  $table  Table name
     *
     * @return  array  Table columns
     *
     * @since   1.0.0
     */
    protected function getTableColumns($table)
    {
        $query = $this->db->getQuery(true)
            ->select('COLUMN_NAME, DATA_TYPE')
            ->from('INFORMATION_SCHEMA.COLUMNS')
            ->where('TABLE_SCHEMA = ' . $this->db->quote($this->db->getDatabase()))
            ->where('TABLE_NAME = ' . $this->db->quote($table));
        
        $this->db->setQuery($query);
        $columns = $this->db->loadObjectList();
        
        $result = [];
        foreach ($columns as $column) {
            $result[$column->COLUMN_NAME] = [
                'type' => $column->DATA_TYPE
            ];
        }
        
        return $result;
    }

    /**
     * Get table indexes
     *
     * @param   string  $table  Table name
     *
     * @return  array  Table indexes
     *
     * @since   1.0.0
     */
    protected function getTableIndexes($table)
    {
        $query = $this->db->getQuery(true)
            ->select('INDEX_NAME')
            ->from('INFORMATION_SCHEMA.STATISTICS')
            ->where('TABLE_SCHEMA = ' . $this->db->quote($this->db->getDatabase()))
            ->where('TABLE_NAME = ' . $this->db->quote($table))
            ->group('INDEX_NAME');
        
        $this->db->setQuery($query);
        $indexes = $this->db->loadObjectList();
        
        $result = [];
        foreach ($indexes as $index) {
            $result[$index->INDEX_NAME] = true;
        }
        
        return $result;
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
        $sqlFile = dirname(__DIR__, 2) . '/admin/sql/install.mysql.utf8.sql';
        
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->db->setQuery($statement);
                    $this->db->execute();
                }
            }
        }
    }

    /**
     * Clean up test tables
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function cleanupTestTables()
    {
        $tables = [
            '#__ordenproduccion_ordenes',
            '#__ordenproduccion_info',
            '#__ordenproduccion_technicians',
            '#__ordenproduccion_assignments',
            '#__ordenproduccion_attendance',
            '#__ordenproduccion_production_notes',
            '#__ordenproduccion_shipping',
            '#__ordenproduccion_config',
            '#__ordenproduccion_webhook_logs'
        ];
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $query = $this->db->getQuery(true)
                    ->delete($this->db->quoteName($table))
                    ->where('1=1');
                
                $this->db->setQuery($query);
                $this->db->execute();
            }
        }
    }
}
