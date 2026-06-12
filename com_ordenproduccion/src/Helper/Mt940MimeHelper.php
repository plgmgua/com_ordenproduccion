<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

/**
 * Extract MT-940 .txt payloads from email MIME (RFC822) bodies.
 *
 * @since  3.119.150
 */
class Mt940MimeHelper
{
    /**
     * @param   string  $raw  Full RFC822 message
     *
     * @return  array<int, array{filename: string, content: string}>
     *
     * @since   3.119.150
     */
    public static function extractTextAttachments(string $raw): array
    {
        $raw = (string) $raw;
        if ($raw === '') {
            return [];
        }

        $out = [];

        if (self::looksLikeMt940($raw)) {
            $out[] = [
                'filename' => 'inline-mt940-' . \substr(\hash('sha256', $raw), 0, 12) . '.txt',
                'content'  => $raw,
            ];

            return $out;
        }

        if (!\preg_match('/Content-Type:\s*multipart\//im', $raw)) {
            if (self::looksLikeMt940(self::decodeBody($raw))) {
                $out[] = [
                    'filename' => 'inline-mt940-' . \substr(\hash('sha256', $raw), 0, 12) . '.txt',
                    'content'  => self::decodeBody($raw),
                ];
            }

            return $out;
        }

        $boundary = self::extractBoundary($raw);
        if ($boundary === '') {
            return $out;
        }

        $parts = \preg_split('/--' . \preg_quote($boundary, '/') . '(?:--)?\s*\r?\n/', $raw) ?: [];
        foreach ($parts as $part) {
            $part = \trim($part);
            if ($part === '' || $part === '--') {
                continue;
            }

            [$headers, $body] = self::splitHeadersBody($part);
            $filename         = self::extractFilenameFromHeaders($headers);
            $isTxt            = $filename !== '' && (bool) \preg_match('/\.txt$/i', $filename);
            $decoded          = self::decodePartBody($headers, $body);

            if (!$isTxt && !self::looksLikeMt940($decoded)) {
                continue;
            }

            if ($filename === '') {
                $filename = 'attachment-' . \substr(\hash('sha256', $decoded), 0, 12) . '.txt';
            }

            $out[] = [
                'filename' => $filename,
                'content'  => $decoded,
            ];
        }

        return $out;
    }

    /**
     * @param   string  $content
     *
     * @return  bool
     *
     * @since   3.119.150
     */
    public static function looksLikeMt940(string $content): bool
    {
        return \strpos($content, ':25:') !== false
            && (\strpos($content, '{1:F01') !== false || \strpos($content, ':20:') !== false);
    }

    /**
     * @param   string  $raw
     *
     * @return  string
     *
     * @since   3.119.150
     */
    private static function extractBoundary(string $raw): string
    {
        if (\preg_match('/boundary="?([^"\s;]+)"?/i', $raw, $m)) {
            return \trim((string) ($m[1] ?? ''));
        }

        return '';
    }

    /**
     * @param   string  $part
     *
     * @return  array{0: string, 1: string}
     *
     * @since   3.119.150
     */
    private static function splitHeadersBody(string $part): array
    {
        $chunks = \preg_split("/\r?\n\r?\n/", $part, 2);
        if (!\is_array($chunks) || \count($chunks) < 2) {
            return [$part, ''];
        }

        return [(string) $chunks[0], (string) $chunks[1]];
    }

    /**
     * @param   string  $headers
     *
     * @return  string
     *
     * @since   3.119.150
     */
    private static function extractFilenameFromHeaders(string $headers): string
    {
        if (\preg_match('/filename\*?=(?:UTF-8\'\')?"?([^"\r\n;]+)"?/i', $headers, $m)) {
            return \trim((string) ($m[1] ?? ''));
        }

        return '';
    }

    /**
     * @param   string  $headers
     * @param   string  $body
     *
     * @return  string
     *
     * @since   3.119.150
     */
    private static function decodePartBody(string $headers, string $body): string
    {
        $encoding = '';
        if (\preg_match('/Content-Transfer-Encoding:\s*([^\r\n]+)/i', $headers, $m)) {
            $encoding = \strtolower(\trim((string) ($m[1] ?? '')));
        }

        if ($encoding === 'base64') {
            $decoded = \base64_decode(\preg_replace('/\s+/', '', $body) ?? '', true);

            return $decoded === false ? $body : $decoded;
        }

        if ($encoding === 'quoted-printable') {
            return \quoted_printable_decode($body);
        }

        return $body;
    }

    /**
     * @param   string  $raw
     *
     * @return  string
     *
     * @since   3.119.150
     */
    private static function decodeBody(string $raw): string
    {
        [$headers, $body] = self::splitHeadersBody($raw);

        return $headers !== '' ? self::decodePartBody($headers, $body) : $raw;
    }
}
