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
use Grimpsa\Component\Ordenproduccion\Administrator\Helper\SecurityHelper;

/**
 * Test class for SecurityHelper
 *
 * @since  1.0.0
 */
class SecurityHelperTest extends TestCase
{
    /**
     * Test sanitizeInput method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testSanitizeInput()
    {
        // Test string sanitization
        $result = SecurityHelper::sanitizeInput('<script>alert("test")</script>', 'string');
        $this->assertStringNotContainsString('<script>', $result);
        
        // Test integer sanitization
        $result = SecurityHelper::sanitizeInput('123abc', 'int');
        $this->assertEquals(123, $result);
        
        // Test email sanitization
        $result = SecurityHelper::sanitizeInput('test@example.com<script>', 'email');
        $this->assertEquals('test@example.com', $result);
        
        // Test array sanitization
        $input = ['<script>alert("test")</script>', 'normal text'];
        $result = SecurityHelper::sanitizeInput($input, 'string');
        $this->assertIsArray($result);
        $this->assertStringNotContainsString('<script>', $result[0]);
    }

    /**
     * Test validateInput method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testValidateInput()
    {
        // Test email validation
        $this->assertTrue(SecurityHelper::validateInput('test@example.com', 'email'));
        $this->assertFalse(SecurityHelper::validateInput('invalid-email', 'email'));
        
        // Test integer validation
        $this->assertTrue(SecurityHelper::validateInput('123', 'int'));
        $this->assertFalse(SecurityHelper::validateInput('123.45', 'int'));
        
        // Test URL validation
        $this->assertTrue(SecurityHelper::validateInput('https://example.com', 'url'));
        $this->assertFalse(SecurityHelper::validateInput('not-a-url', 'url'));
        
        // Test length validation
        $this->assertTrue(SecurityHelper::validateInput('test', 'length', ['min' => 2, 'max' => 10]));
        $this->assertFalse(SecurityHelper::validateInput('a', 'length', ['min' => 2, 'max' => 10]));
        
        // Test in_array validation
        $this->assertTrue(SecurityHelper::validateInput('value1', 'in_array', ['allowed' => ['value1', 'value2']]));
        $this->assertFalse(SecurityHelper::validateInput('value3', 'in_array', ['allowed' => ['value1', 'value2']]));
    }

    /**
     * Test escapeOutput method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testEscapeOutput()
    {
        $input = '<script>alert("test")</script>';
        
        // Test HTML escaping
        $result = SecurityHelper::escapeOutput($input, 'html');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        
        // Test attribute escaping
        $result = SecurityHelper::escapeOutput($input, 'attr');
        $this->assertStringNotContainsString('<script>', $result);
        
        // Test JavaScript escaping
        $result = SecurityHelper::escapeOutput($input, 'js');
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * Test generateSecureToken method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testGenerateSecureToken()
    {
        $token1 = SecurityHelper::generateSecureToken(32);
        $token2 = SecurityHelper::generateSecureToken(32);
        
        $this->assertEquals(32, strlen($token1));
        $this->assertEquals(32, strlen($token2));
        $this->assertNotEquals($token1, $token2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token1);
    }

    /**
     * Test validateFileUpload method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testValidateFileUpload()
    {
        // Test with valid file data
        $file = [
            'name' => 'test.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => '/tmp/test.jpg',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];
        
        $result = SecurityHelper::validateFileUpload($file, [
            'allowed_types' => ['jpg', 'jpeg', 'png'],
            'max_size' => 2048
        ]);
        
        $this->assertTrue($result['valid']);
        
        // Test with file too large
        $file['size'] = 4096;
        $result = SecurityHelper::validateFileUpload($file, [
            'allowed_types' => ['jpg', 'jpeg', 'png'],
            'max_size' => 2048
        ]);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['error']);
        
        // Test with invalid file type
        $file['name'] = 'test.exe';
        $file['size'] = 1024;
        $result = SecurityHelper::validateFileUpload($file, [
            'allowed_types' => ['jpg', 'jpeg', 'png'],
            'max_size' => 2048
        ]);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['error']);
    }

    /**
     * Test checkRateLimit method
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function testCheckRateLimit()
    {
        $key = 'test_key_' . time();
        
        // First request should be allowed
        $result = SecurityHelper::checkRateLimit($key, 5, 60);
        $this->assertTrue($result['allowed']);
        $this->assertEquals(1, $result['count']);
        
        // Multiple requests should be allowed up to limit
        for ($i = 2; $i <= 5; $i++) {
            $result = SecurityHelper::checkRateLimit($key, 5, 60);
            $this->assertTrue($result['allowed']);
            $this->assertEquals($i, $result['count']);
        }
        
        // Request beyond limit should be denied
        $result = SecurityHelper::checkRateLimit($key, 5, 60);
        $this->assertFalse($result['allowed']);
        $this->assertEquals(6, $result['count']);
    }
}
