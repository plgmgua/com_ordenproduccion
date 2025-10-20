<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

define('_JEXEC', 1);

// Load Joomla framework
if (file_exists(__DIR__ . '/../../configuration.php')) {
    require_once __DIR__ . '/../../configuration.php';
} elseif (file_exists(__DIR__ . '/../../../configuration.php')) {
    require_once __DIR__ . '/../../../configuration.php';
}

if (!defined('JPATH_BASE')) {
    define('JPATH_BASE', realpath(__DIR__ . '/../..'));
}

require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió ningún archivo o hubo un error en la carga');
    }
    
    $file = $_FILES['file'];
    
    // Validate file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('El archivo es demasiado grande. Tamaño máximo: 10MB');
    }
    
    // Validate file type
    $allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        throw new Exception('Tipo de archivo no permitido. Formatos aceptados: PDF, JPG, PNG, DOC, DOCX');
    }
    
    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Create upload directory if it doesn't exist
    $uploadDir = JPATH_BASE . '/media/com_ordenproduccion/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $timestamp = time();
    $randomString = bin2hex(random_bytes(8));
    $newFilename = $timestamp . '_' . $randomString . '.' . $extension;
    $uploadPath = $uploadDir . '/' . $newFilename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception('Error al guardar el archivo en el servidor');
    }
    
    // Set proper permissions
    chmod($uploadPath, 0644);
    
    // Get file URL (relative to site root)
    $fileUrl = '/media/com_ordenproduccion/uploads/' . $newFilename;
    
    // Return success response
    echo json_encode([
        'success' => true,
        'file_url' => $fileUrl,
        'filename' => $newFilename,
        'original_name' => $file['name'],
        'size' => $file['size'],
        'mime_type' => $mimeType
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

