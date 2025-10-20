<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Log\Log;

/**
 * Security helper for com_ordenproduccion
 *
 * @since  1.0.0
 */
class SecurityHelper
{
    /**
     * Validate CSRF token
     *
     * @param   string  $method  HTTP method (post, get, request)
     *
     * @return  boolean  True if token is valid
     *
     * @since   1.0.0
     */
    public static function validateCSRF($method = 'post')
    {
        try {
            $app = Factory::getApplication();
            $input = $app->input;
            
            switch (strtolower($method)) {
                case 'get':
                    $token = $input->get('_token', '', 'alnum');
                    break;
                case 'post':
                    $token = $input->post->get('_token', '', 'alnum');
                    break;
                case 'request':
                default:
                    $token = $input->get('_token', '', 'alnum');
                    break;
            }
            
            return Session::checkToken($token);
            
        } catch (\Exception $e) {
            self::logSecurityEvent('csrf_validation_failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Sanitize input data
     *
     * @param   mixed   $data    Data to sanitize
     * @param   string  $type    Type of sanitization (string, int, email, url, etc.)
     * @param   array   $options Additional options
     *
     * @return  mixed  Sanitized data
     *
     * @since   1.0.0
     */
    public static function sanitizeInput($data, $type = 'string', $options = [])
    {
        if (is_array($data)) {
            return array_map(function($item) use ($type, $options) {
                return self::sanitizeInput($item, $type, $options);
            }, $data);
        }

        $filter = new InputFilter();
        
        switch (strtolower($type)) {
            case 'int':
            case 'integer':
                return (int) $data;
                
            case 'float':
            case 'double':
                return (float) $data;
                
            case 'bool':
            case 'boolean':
                return (bool) $data;
                
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
                
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
                
            case 'html':
                return $filter->clean($data, 'HTML');
                
            case 'raw':
                return $data;
                
            case 'string':
            default:
                return $filter->clean($data, 'STRING');
        }
    }

    /**
     * Validate input data
     *
     * @param   mixed   $data     Data to validate
     * @param   string  $type     Type of validation
     * @param   array   $options  Validation options
     *
     * @return  boolean  True if valid
     *
     * @since   1.0.0
     */
    public static function validateInput($data, $type = 'string', $options = [])
    {
        if (empty($data) && !isset($options['allow_empty'])) {
            return false;
        }

        switch (strtolower($type)) {
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
                
            case 'url':
                return filter_var($data, FILTER_VALIDATE_URL) !== false;
                
            case 'int':
            case 'integer':
                return is_numeric($data) && (int) $data == $data;
                
            case 'float':
                return is_numeric($data);
                
            case 'date':
                $format = $options['format'] ?? 'Y-m-d';
                $date = \DateTime::createFromFormat($format, $data);
                return $date && $date->format($format) === $data;
                
            case 'datetime':
                $format = $options['format'] ?? 'Y-m-d H:i:s';
                $date = \DateTime::createFromFormat($format, $data);
                return $date && $date->format($format) === $data;
                
            case 'length':
                $min = $options['min'] ?? 0;
                $max = $options['max'] ?? PHP_INT_MAX;
                $length = strlen($data);
                return $length >= $min && $length <= $max;
                
            case 'regex':
                $pattern = $options['pattern'] ?? '';
                return preg_match($pattern, $data);
                
            case 'in_array':
                $allowed = $options['allowed'] ?? [];
                return in_array($data, $allowed);
                
            case 'string':
            default:
                return is_string($data);
        }
    }

    /**
     * Escape output for display
     *
     * @param   string  $data     Data to escape
     * @param   string  $context  Context (html, attr, js, css, url)
     *
     * @return  string  Escaped data
     *
     * @since   1.0.0
     */
    public static function escapeOutput($data, $context = 'html')
    {
        if (!is_string($data)) {
            return $data;
        }

        switch (strtolower($context)) {
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                
            case 'attr':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                
            case 'js':
                return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                
            case 'css':
                return preg_replace('/[^a-zA-Z0-9\-_]/', '', $data);
                
            case 'url':
                return urlencode($data);
                
            case 'raw':
            default:
                return $data;
        }
    }

    /**
     * Check user permissions
     *
     * @param   string  $action    Action to check
     * @param   string  $asset     Asset to check against
     * @param   int     $userId    User ID (optional, defaults to current user)
     *
     * @return  boolean  True if user has permission
     *
     * @since   1.0.0
     */
    public static function checkPermission($action, $asset = 'com_ordenproduccion', $userId = null)
    {
        try {
            if ($userId === null) {
                $user = Factory::getUser();
            } else {
                $user = Factory::getUser($userId);
            }
            
            return $user->authorise($action, $asset);
            
        } catch (\Exception $e) {
            self::logSecurityEvent('permission_check_failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Validate file upload
     *
     * @param   array   $file      File data from $_FILES
     * @param   array   $options   Validation options
     *
     * @return  array   Validation result
     *
     * @since   1.0.0
     */
    public static function validateFileUpload($file, $options = [])
    {
        $result = [
            'valid' => false,
            'error' => '',
            'file' => null
        ];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['error'] = self::getUploadErrorMessage($file['error']);
            return $result;
        }

        // Check file size
        $maxSize = $options['max_size'] ?? 5242880; // 5MB default
        if ($file['size'] > $maxSize) {
            $result['error'] = Text::sprintf('COM_ORDENPRODUCCION_FILE_TOO_LARGE', $maxSize);
            return $result;
        }

        // Check file type
        $allowedTypes = $options['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedTypes)) {
            $result['error'] = Text::sprintf('COM_ORDENPRODUCCION_FILE_TYPE_NOT_ALLOWED', implode(', ', $allowedTypes));
            return $result;
        }

        // Check MIME type
        $allowedMimes = $options['allowed_mimes'] ?? [];
        if (!empty($allowedMimes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedMimes)) {
                $result['error'] = Text::_('COM_ORDENPRODUCCION_FILE_MIME_TYPE_NOT_ALLOWED');
                return $result;
            }
        }

        $result['valid'] = true;
        $result['file'] = $file;
        return $result;
    }

    /**
     * Get upload error message
     *
     * @param   int  $errorCode  Upload error code
     *
     * @return  string  Error message
     *
     * @since   1.0.0
     */
    protected static function getUploadErrorMessage($errorCode)
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_INI_SIZE');
            case UPLOAD_ERR_FORM_SIZE:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_FORM_SIZE');
            case UPLOAD_ERR_PARTIAL:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_PARTIAL');
            case UPLOAD_ERR_NO_FILE:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_NO_FILE');
            case UPLOAD_ERR_NO_TMP_DIR:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_NO_TMP_DIR');
            case UPLOAD_ERR_CANT_WRITE:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_CANT_WRITE');
            case UPLOAD_ERR_EXTENSION:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_EXTENSION');
            default:
                return Text::_('COM_ORDENPRODUCCION_UPLOAD_ERR_UNKNOWN');
        }
    }

    /**
     * Log security events
     *
     * @param   string  $event     Event type
     * @param   string  $message   Event message
     * @param   array   $context   Additional context
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public static function logSecurityEvent($event, $message, $context = [])
    {
        try {
            $user = Factory::getUser();
            $app = Factory::getApplication();
            
            $logData = [
                'event' => $event,
                'message' => $message,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'ip_address' => $app->input->server->get('REMOTE_ADDR', 'unknown'),
                'user_agent' => $app->input->server->get('HTTP_USER_AGENT', 'unknown'),
                'timestamp' => date('Y-m-d H:i:s'),
                'context' => $context
            ];
            
            // Log to Joomla's log system
            Log::add(
                'Security Event: ' . $event . ' - ' . $message,
                Log::WARNING,
                'com_ordenproduccion'
            );
            
            // Log to custom security log file
            $logFile = JPATH_ROOT . '/logs/com_ordenproduccion_security.log';
            $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . json_encode($logData) . PHP_EOL;
            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
        } catch (\Exception $e) {
            // Silent fail for logging
        }
    }

    /**
     * Generate secure random token
     *
     * @param   int  $length  Token length
     *
     * @return  string  Random token
     *
     * @since   1.0.0
     */
    public static function generateSecureToken($length = 32)
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        } else {
            // Fallback to less secure method
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $token = '';
            for ($i = 0; $i < $length; $i++) {
                $token .= $characters[mt_rand(0, strlen($characters) - 1)];
            }
            return $token;
        }
    }

    /**
     * Rate limiting check
     *
     * @param   string  $key       Rate limit key (IP, user ID, etc.)
     * @param   int     $limit     Request limit
     * @param   int     $window    Time window in seconds
     *
     * @return  array   Rate limit result
     *
     * @since   1.0.0
     */
    public static function checkRateLimit($key, $limit = 100, $window = 3600)
    {
        try {
            $cache = Factory::getCache('com_ordenproduccion_rate_limit', 'output');
            $cacheKey = 'rate_limit_' . md5($key);
            
            $data = $cache->get($cacheKey);
            
            if ($data === false) {
                $data = [
                    'count' => 1,
                    'reset_time' => time() + $window
                ];
            } else {
                if (time() > $data['reset_time']) {
                    $data = [
                        'count' => 1,
                        'reset_time' => time() + $window
                    ];
                } else {
                    $data['count']++;
                }
            }
            
            $cache->store($data, $cacheKey, $window);
            
            return [
                'allowed' => $data['count'] <= $limit,
                'count' => $data['count'],
                'limit' => $limit,
                'reset_time' => $data['reset_time']
            ];
            
        } catch (\Exception $e) {
            return [
                'allowed' => true,
                'count' => 0,
                'limit' => $limit,
                'reset_time' => time() + $window
            ];
        }
    }
}
