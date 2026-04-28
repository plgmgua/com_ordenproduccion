<?php
/**
 * Reads Joomla log files for entries emitted by CotizacionController::logOtWizardCreateFailure
 * (OT wizard / createOrdenFromQuotation failures).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * @since  3.115.9
 */
class OtWizardCreationLogHelper
{
    /**
     * Must match the prefix in CotizacionController::logOtWizardCreateFailure (Log::add).
     */
    public const LOG_MESSAGE_PREFIX = 'OT wizard create failed: ';

    /**
     * @param   int  $maxEntries  Max matching lines to return (newest first after sort).
     *
     * @return  array<int, array{raw:string, file:string, sort_key:int, payload:?array}>
     *
     * @since   3.115.9
     */
    public static function collectEntriesFromJoomlaLogs(int $maxEntries = 150): array
    {
        $maxEntries = max(10, min(500, $maxEntries));
        $dirs       = [];

        if (\defined('JPATH_ADMINISTRATOR')) {
            $dirs[] = JPATH_ADMINISTRATOR . '/logs';
        }

        if (\defined('JPATH_ROOT')) {
            $dirs[] = JPATH_ROOT . '/logs';
        }

        $dirs = array_unique(array_filter($dirs, 'is_dir'));
        $hits = [];

        foreach ($dirs as $dir) {
            foreach (array_merge(\glob($dir . '/*.php') ?: [], \glob($dir . '/*.log') ?: []) as $path) {
                if (!\is_string($path) || $path === '') {
                    continue;
                }
                if (!is_file($path) || !is_readable($path)) {
                    continue;
                }

                $base = basename($path);
                if ($base === 'index.html' || $base === '.htaccess') {
                    continue;
                }

                self::gatherFromFile($path, $hits);
            }
        }

        usort(
            $hits,
            static function (array $a, array $b): int {
                return ($b['sort_key'] <=> $a['sort_key']);
            }
        );

        if (\count($hits) > $maxEntries) {
            $hits = \array_slice($hits, 0, $maxEntries);
        }

        return $hits;
    }

    /**
     * Human-readable dirs scanned (for hints in UI).
     *
     * @return  string[]
     *
     * @since   3.115.9
     */
    public static function getScannedDirectoryLabels(): array
    {
        $out = [];

        foreach ([JPATH_ADMINISTRATOR . '/logs', JPATH_ROOT . '/logs'] as $d) {
            if (\is_dir($d)) {
                $out[] = $d;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @param   array<int, array<string,mixed>>  $hits
     *
     * @since   3.115.9
     */
    protected static function gatherFromFile(string $path, array &$hits): void
    {
        $size = @filesize($path);
        if ($size === false || $size < 1) {
            return;
        }

        // Read tail of large logs (formatted PHP logs can grow).
        $maxRead = (int) min($size, 2097152);
        $chunk   = self::readFileTail($path, $maxRead);
        if ($chunk === '') {
            return;
        }

        $fileLabel = basename($path);
        $lines     = preg_split("/\R/", $chunk) ?: [];

        foreach ($lines as $line) {
            if (!is_string($line) || strpos($line, self::LOG_MESSAGE_PREFIX) === false) {
                continue;
            }

            $pos = strpos($line, self::LOG_MESSAGE_PREFIX);
            $jsonStr = trim(substr($line, $pos + \strlen(self::LOG_MESSAGE_PREFIX)));
            $decoded = json_decode($jsonStr, true);
            $payload = \is_array($decoded) ? $decoded : null;

            $hits[] = [
                'raw'      => $line,
                'file'     => $fileLabel,
                'sort_key' => self::inferSortKeyFromLine($line),
                'payload'  => $payload,
            ];
        }
    }

    /**
     * @since   3.115.9
     */
    protected static function readFileTail(string $path, int $maxBytes): string
    {
        $size = filesize($path);
        if ($size === false) {
            return '';
        }

        $len = (int) min($size, $maxBytes);
        $start = (int) max(0, $size - $len);

        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return '';
        }

        if ($start > 0) {
            fseek($fh, $start);
            // Drop possible partial first line
            fgets($fh);
        }

        $data = stream_get_contents($fh);
        fclose($fh);

        return is_string($data) ? $data : '';
    }

    /**
     * Joomla log lines often start with ISO date or legacy date.
     *
     * @since   3.115.9
     */
    protected static function inferSortKeyFromLine(string $line): int
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2}:\d{2})/', $line, $m)) {
            $t = strtotime($m[1] . ' ' . $m[2]);

            return $t !== false ? $t : 0;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})/', $line, $m)) {
            $t = strtotime($m[1] . ' ' . $m[2]);

            return $t !== false ? $t : 0;
        }

        return 0;
    }
}
