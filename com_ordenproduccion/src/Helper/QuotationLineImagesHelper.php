<?php
/**
 * Store and resolve quotation line images (paths under media/com_ordenproduccion/quotation_line_images).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;

/**
 * @since  3.113.85
 */
final class QuotationLineImagesHelper
{
    public const REL_BASE = 'media/com_ordenproduccion/quotation_line_images';

    private const MAX_BYTES = 5242880;

    /**
     * Translated string, or English/Spanish fallback if the key is not loaded (e.g. early bootstrap).
     */
    private static function lang(string $key, string $en, ?string $es = null): string
    {
        $t = Text::_($key);
        if ($t !== $key && $t !== '') {
            return $t;
        }
        $tag = Factory::getApplication()->getLanguage()->getTag();
        if ($es !== null && (str_starts_with($tag, 'es') || str_contains($tag, 'es-'))) {
            return $es;
        }

        return $en;
    }

    /**
     * FPDF handles JPEG, PNG, GIF natively; other rasters are normalized to PNG.
     */
    private static function convertRasterToPng(string $tmpPath, int $iType): ?string
    {
        if (class_exists(\Imagick::class)) {
            try {
                $im = new \Imagick($tmpPath);
                $im->setImageFormat('png');
                $blob = $im->getImageBlob();
                $im->clear();
                if (is_string($blob) && $blob !== '') {
                    return $blob;
                }
            } catch (\Throwable $e) {
                // Fall through to GD.
            }
        }

        $im = false;
        switch ($iType) {
            case IMAGETYPE_WEBP:
                if (\function_exists('imagecreatefromwebp')) {
                    $im = @imagecreatefromwebp($tmpPath);
                }
                break;
            case IMAGETYPE_BMP:
                if (\function_exists('imagecreatefrombmp')) {
                    $im = @imagecreatefrombmp($tmpPath);
                }
                break;
            default:
                break;
        }

        if (\defined('IMAGETYPE_AVIF') && $iType === \constant('IMAGETYPE_AVIF') && \function_exists('imagecreatefromavif')) {
            $im = @imagecreatefromavif($tmpPath);
        }

        if ($im === false) {
            $raw = @file_get_contents($tmpPath);
            if ($raw !== false && $raw !== '') {
                $im = @imagecreatefromstring($raw);
            }
        }

        if ($im === false) {
            return null;
        }

        ob_start();
        $ok = @imagepng($im);
        imagedestroy($im);
        $png = ob_get_clean();

        return ($ok && is_string($png) && $png !== '') ? $png : null;
    }

    /**
     * Normalize POST value to a JSON array string of relative paths.
     */
    public static function normalizeJsonFromInput(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '[]';
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return '[]';
        }
        $out = [];
        foreach ($decoded as $p) {
            if (!is_string($p)) {
                continue;
            }
            $norm = self::normalizeRelativePath($p);
            if ($norm !== null) {
                $out[] = $norm;
            }
        }
        $out = array_values(array_unique($out));

        return json_encode($out, JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * After quotation save: move staging files into q{quotationId}/ and return cleaned JSON.
     */
    public static function finalizeJsonForQuotation(int $quotationId, int $userId, string $json): string
    {
        if ($quotationId < 1) {
            return '[]';
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return '[]';
        }
        $destDirRel = self::REL_BASE . '/q' . $quotationId;
        $destDirAbs = JPATH_ROOT . '/' . $destDirRel;
        if (!is_dir($destDirAbs)) {
            Folder::create($destDirAbs);
        }
        $stagingPrefix = self::REL_BASE . '/staging/u' . $userId . '/';
        $qPrefix       = self::REL_BASE . '/q' . $quotationId . '/';
        $out           = [];
        foreach ($decoded as $p) {
            if (!is_string($p)) {
                continue;
            }
            $norm = self::normalizeRelativePath($p);
            if ($norm === null) {
                continue;
            }
            if (str_starts_with($norm, $stagingPrefix)) {
                $from = JPATH_ROOT . '/' . $norm;
                if (!is_file($from)) {
                    continue;
                }
                $base = basename($norm);
                $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $base) ?: 'img.bin';
                $targetRel = $destDirRel . '/' . uniqid('img_', true) . '_' . $base;
                $targetAbs = JPATH_ROOT . '/' . $targetRel;
                if (@rename($from, $targetAbs)) {
                    $out[] = $targetRel;
                }
            } elseif (str_starts_with($norm, $qPrefix) && is_file(JPATH_ROOT . '/' . $norm)) {
                $out[] = $norm;
            }
        }

        return json_encode(array_values(array_unique($out)), JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * Upload one image; staging when quotationId &lt; 1, else under q{quotationId}/.
     *
     * @return array{success: bool, path?: string, message?: string}
     */
    public static function processUploadedFile(?array $file, int $userId, int $quotationId): array
    {
        if ($userId < 1) {
            return ['success' => false, 'message' => 'Login required'];
        }
        if (!is_array($file) || empty($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'No file'];
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => 'Invalid upload'];
        }
        $size = (int) ($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) {
            return ['success' => false, 'message' => 'File too large'];
        }

        if ($quotationId > 0) {
            $sub = self::REL_BASE . '/q' . $quotationId;
        } else {
            $sub = self::REL_BASE . '/staging/u' . $userId;
        }
        $absDir = JPATH_ROOT . '/' . $sub;
        if (!is_dir($absDir) && !Folder::create($absDir)) {
            return [
                'success' => false,
                'message' => self::lang(
                    'COM_ORDENPRODUCCION_QUOTATION_LINE_IMAGE_ERR_CREATE_DIR',
                    'Could not create the upload folder. Ask the administrator to allow writes under media/com_ordenproduccion/quotation_line_images (and its parent folders).',
                    'No se pudo crear la carpeta de subidas. Pida al administrador que permita escritura en media/com_ordenproduccion/quotation_line_images (y carpetas superiores).'
                ),
            ];
        }
        if (!is_writable($absDir)) {
            return [
                'success' => false,
                'message' => self::lang(
                    'COM_ORDENPRODUCCION_QUOTATION_LINE_IMAGE_ERR_NOT_WRITABLE',
                    'The upload folder is not writable. Ask the administrator to fix permissions on media/com_ordenproduccion/quotation_line_images.',
                    'La carpeta de subidas no tiene permiso de escritura. Pida al administrador que corrija permisos en media/com_ordenproduccion/quotation_line_images.'
                ),
            ];
        }

        $origStem = pathinfo((string) ($file['name'] ?? 'image'), PATHINFO_FILENAME);
        $name     = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) $origStem) ?: 'image';
        $uniqBase = $absDir . '/' . uniqid('up_', true) . '_' . $name;

        $tmp  = $file['tmp_name'];
        $info = @getimagesize($tmp);
        $iType = (is_array($info) && isset($info[2])) ? (int) $info[2] : 0;

        if (\in_array($iType, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            $ext = match ($iType) {
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_GIF => 'gif',
            };
            $target = $uniqBase . '.' . $ext;
            if (!move_uploaded_file($tmp, $target)) {
                return [
                    'success' => false,
                    'message' => self::lang(
                        'COM_ORDENPRODUCCION_QUOTATION_LINE_IMAGE_ERR_SAVE',
                        'Could not save the uploaded file. Check server write permissions.',
                        'No se pudo guardar el archivo. Compruebe permisos de escritura en el servidor.'
                    ),
                ];
            }

            return ['success' => true, 'path' => $sub . '/' . basename($target)];
        }

        $pngBlob = self::convertRasterToPng($tmp, $iType);
        if ($pngBlob === null) {
            return [
                'success' => false,
                'message' => self::lang(
                    'COM_ORDENPRODUCCION_QUOTATION_LINE_IMAGE_ERR_TYPE',
                    'This file is not a supported image, or the server cannot decode it. Use JPEG, PNG, GIF, BMP, WebP, or TIFF. For TIFF, the Imagick PHP extension may be required.',
                    'El archivo no es una imagen admitida o el servidor no puede decodificarla. Use JPEG, PNG, GIF, BMP, WebP o TIFF. Para TIFF puede hacer falta la extensión PHP Imagick.'
                ),
            ];
        }

        $target = $uniqBase . '.png';
        if (file_put_contents($target, $pngBlob) === false) {
            return [
                'success' => false,
                'message' => self::lang(
                    'COM_ORDENPRODUCCION_QUOTATION_LINE_IMAGE_ERR_SAVE',
                    'Could not save the uploaded file. Check server write permissions.',
                    'No se pudo guardar el archivo. Compruebe permisos de escritura en el servidor.'
                ),
            ];
        }

        return ['success' => true, 'path' => $sub . '/' . basename($target)];
    }

    /**
     * @return list<string> absolute filesystem paths
     */
    public static function absolutePathsFromJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $p) {
            if (!is_string($p)) {
                continue;
            }
            $norm = self::normalizeRelativePath($p);
            if ($norm === null) {
                continue;
            }
            $abs = JPATH_ROOT . '/' . $norm;
            if (is_file($abs)) {
                $out[] = $abs;
            }
        }

        return $out;
    }

    private static function normalizeRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        $path = ltrim($path, '/');
        $base = self::REL_BASE;
        if (!str_starts_with($path, $base . '/') && $path !== $base) {
            return null;
        }

        return $path;
    }
}
