<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Tests\Unit\Helper;

use PHPUnit\Framework\TestCase;
use Grimpsa\Component\Ordenproduccion\Administrator\Helper\DebugHelper;

/**
 * Test class for DebugHelper
 *
 * @since  1.0.0
 */
class DebugHelperTest extends TestCase
{
    /**
     * Test getComponentVersion method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testGetComponentVersion()
    {
        // Create a temporary version file
        $versionFile = sys_get_temp_dir() . '/VERSION';
        file_put_contents($versionFile, '1.0.0-TEST');
        
        // Mock the version file path
        $reflection = new \ReflectionClass(DebugHelper::class);
        $property = $reflection->getProperty('version');
        $property->setAccessible(true);
        $property->setValue(null, null);
        
        // Test version reading
        $version = DebugHelper::getVersion();
        $this->assertNotEmpty($version);
        
        // Clean up
        unlink($versionFile);
    }

    /**
     * Test log method with different levels
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testLog()
    {
        // Test that logging works without errors
        $this->assertNull(DebugHelper::log('Test message', 'DEBUG'));
        $this->assertNull(DebugHelper::log('Test info', 'INFO'));
        $this->assertNull(DebugHelper::log('Test warning', 'WARNING'));
        $this->assertNull(DebugHelper::log('Test error', 'ERROR'));
    }

    /**
     * Test debug method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testDebug()
    {
        $this->assertNull(DebugHelper::debug('Debug message'));
        $this->assertNull(DebugHelper::debug('Debug with context', ['key' => 'value']));
    }

    /**
     * Test info method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testInfo()
    {
        $this->assertNull(DebugHelper::info('Info message'));
        $this->assertNull(DebugHelper::info('Info with context', ['key' => 'value']));
    }

    /**
     * Test warning method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testWarning()
    {
        $this->assertNull(DebugHelper::warning('Warning message'));
        $this->assertNull(DebugHelper::warning('Warning with context', ['key' => 'value']));
    }

    /**
     * Test error method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testError()
    {
        $this->assertNull(DebugHelper::error('Error message'));
        $this->assertNull(DebugHelper::error('Error with context', ['key' => 'value']));
    }

    /**
     * Test getLogs method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testGetLogs()
    {
        $logs = DebugHelper::getLogs(10);
        $this->assertIsArray($logs);
    }

    /**
     * Test clearLogs method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testClearLogs()
    {
        $result = DebugHelper::clearLogs();
        $this->assertTrue($result);
    }

    /**
     * Test cleanupLogs method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testCleanupLogs()
    {
        $removedCount = DebugHelper::cleanupLogs();
        $this->assertIsInt($removedCount);
        $this->assertGreaterThanOrEqual(0, $removedCount);
    }

    /**
     * Test getLogStats method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testGetLogStats()
    {
        $stats = DebugHelper::getLogStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('file_exists', $stats);
        $this->assertArrayHasKey('file_size', $stats);
        $this->assertArrayHasKey('file_size_formatted', $stats);
        $this->assertArrayHasKey('line_count', $stats);
        $this->assertArrayHasKey('last_modified', $stats);
        $this->assertArrayHasKey('last_modified_formatted', $stats);
        
        $this->assertIsBool($stats['file_exists']);
        $this->assertIsInt($stats['file_size']);
        $this->assertIsString($stats['file_size_formatted']);
        $this->assertIsInt($stats['line_count']);
    }

    /**
     * Test dump method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testDump()
    {
        $var = ['test' => 'value', 'number' => 123];
        
        // Test with return = true
        $result = DebugHelper::dump($var, 'Test Variable', true);
        $this->assertIsString($result);
        $this->assertStringContainsString('Test Variable', $result);
        $this->assertStringContainsString('test', $result);
        $this->assertStringContainsString('value', $result);
        
        // Test with return = false (should output and return null)
        ob_start();
        $result = DebugHelper::dump($var, 'Test Variable', false);
        $output = ob_get_clean();
        
        $this->assertNull($result);
        $this->assertStringContainsString('Test Variable', $output);
    }

    /**
     * Test logQuery method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testLogQuery()
    {
        $query = 'SELECT * FROM #__test_table WHERE id = ?';
        $params = [1];
        $time = 0.05;
        
        $this->assertNull(DebugHelper::logQuery($query, $params, $time));
    }

    /**
     * Test logPerformance method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testLogPerformance()
    {
        $this->assertNull(DebugHelper::logPerformance('test_operation', 0.05));
        $this->assertNull(DebugHelper::logPerformance('slow_operation', 1.5, ['details' => 'test']));
    }

    /**
     * Test getConfig method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testGetConfig()
    {
        $config = DebugHelper::getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('log_level', $config);
        $this->assertArrayHasKey('retention_days', $config);
        $this->assertArrayHasKey('log_file', $config);
        $this->assertArrayHasKey('version', $config);
        
        $this->assertIsBool($config['enabled']);
        $this->assertIsString($config['log_level']);
        $this->assertIsInt($config['retention_days']);
        $this->assertIsString($config['log_file']);
        $this->assertIsString($config['version']);
    }
}
