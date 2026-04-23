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

/**
 * @since  3.113.85
 */
final class QuotationLineImagesHelper
{
    public const REL_BASE = 'media/com_ordenproduccion/quotation_line_images';

    private const MAX_BYTES = 5242880;

    /** @var list<string> */
    private static function allowedMimeToExt(): array
    {
        // FPDF Image(): jpeg/png/gif only (no webp).
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
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
            @mkdir($destDirAbs, 0755, true);
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
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']) ?: '';
        $map   = self::allowedMimeToExt();
        if (!isset($map[$mime])) {
            return ['success' => false, 'message' => 'Invalid image type'];
        }
        $ext = $map[$mime];

        if ($quotationId > 0) {
            $sub = self::REL_BASE . '/q' . $quotationId;
        } else {
            $sub = self::REL_BASE . '/staging/u' . $userId;
        }
        $absDir = JPATH_ROOT . '/' . $sub;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0755, true);
        }
        $name     = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) ($file['name'] ?? 'image')) ?: 'image';
        $target   = $absDir . '/' . uniqid('up_', true) . '_' . $name . '.' . $ext;
        if (!@move_uploaded_file($file['tmp_name'], $target)) {
            return ['success' => false, 'message' => 'Save failed'];
        }
        $rel = $sub . '/' . basename($target);

        return ['success' => true, 'path' => $rel];
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
