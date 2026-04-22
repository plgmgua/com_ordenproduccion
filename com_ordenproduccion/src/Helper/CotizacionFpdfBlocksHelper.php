<?php
/**
 * Parse WYSIWYG HTML into FPDF blocks and render them (shared by cotización and vendor-quote PDFs).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Uri\Uri;

/**
 * @since  3.113.8
 */
class CotizacionFpdfBlocksHelper
{
    /**
     * Parse HTML into blocks with alignment (preserve WYSIWYG: left/right/center and line breaks).
     * Used for encabezado, términos and pie. Block text preserves \n for MultiCell.
     *
     * @param   string    $html             HTML content (placeholders already replaced)
     * @param   callable  $fixSpanishChars  Reserved for API compatibility (encoding is done in render)
     *
     * @return  array<int, array<string, mixed>>
     */
    public static function parseHtmlBlocks($html, callable $fixSpanishChars)
    {
        $blocks = [];
        $html = trim((string) $html);
        if ($html === '') {
            return $blocks;
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $html = str_replace("\xc2\xa0", ' ', $html);

        $html = preg_replace_callback(
            '/<\s*ol[^>]*>(.*?)<\s*\/\s*ol\s*>/is',
            static function ($m) {
                $idx   = 1;
                $lines = [];
                preg_match_all('/<\s*li[^>]*>(.*?)<\s*\/\s*li\s*>/is', $m[1], $ms);
                foreach ($ms[1] as $item) {
                    $lines[] = ($idx++) . '. ' . trim(strip_tags($item));
                }

                return '<__LISTBLOCK__>' . implode("\n", $lines) . '</__LISTBLOCK__>';
            },
            $html
        );

        $html = preg_replace_callback(
            '/<\s*ul[^>]*>(.*?)<\s*\/\s*ul\s*>/is',
            static function ($m) {
                $lines = [];
                preg_match_all('/<\s*li[^>]*>(.*?)<\s*\/\s*li\s*>/is', $m[1], $ms);
                foreach ($ms[1] as $item) {
                    $lines[] = '* ' . trim(strip_tags($item));
                }

                return '<__LISTBLOCK__>' . implode("\n", $lines) . '</__LISTBLOCK__>';
            },
            $html
        );

        $html = preg_replace_callback(
            '/<\s*table[^>]*>(.*?)<\s*\/\s*table\s*>/is',
            static function ($m) {
                $rows = [];
                preg_match_all('/<\s*tr[^>]*>(.*?)<\s*\/\s*tr\s*>/is', $m[1], $trMatches);
                foreach ($trMatches[1] as $trContent) {
                    $cells = [];
                    preg_match_all('/<\s*t[dh][^>]*>(.*?)<\s*\/\s*t[dh]\s*>/is', $trContent, $tdMatches, PREG_SET_ORDER);
                    foreach ($tdMatches as $tdMatch) {
                        $cellTag     = $tdMatch[0];
                        $cellContent = $tdMatch[1];

                        $cellAlign = 'L';
                        if (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*right/i', $cellTag)) {
                            $cellAlign = 'R';
                        } elseif (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*center/i', $cellTag)) {
                            $cellAlign = 'C';
                        }

                        $colspan = 1;
                        if (preg_match('/colspan\s*=\s*["\']?(\d+)["\']?/i', $cellTag, $csm)) {
                            $colspan = max(1, (int) $csm[1]);
                        }

                        $cellImages = [];
                        if (preg_match_all('/<img[^>]+>/i', $cellContent, $imgMatches)) {
                            foreach ($imgMatches[0] as $imgTag) {
                                if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $imgTag, $srcM)) {
                                    $iw = 0;
                                    $ih = 0;
                                    if (preg_match('/width\s*=\s*["\']?(\d+)["\']?/i', $imgTag, $wm)) {
                                        $iw = (int) $wm[1];
                                    }
                                    if (preg_match('/height\s*=\s*["\']?(\d+)["\']?/i', $imgTag, $hm)) {
                                        $ih = (int) $hm[1];
                                    }
                                    $cellImages[] = ['src' => $srcM[1], 'width' => $iw, 'height' => $ih];
                                }
                            }
                        }

                        $cellStyle = '';
                        if (preg_match('/<(b|strong)\b/i', $cellContent)) {
                            $cellStyle .= 'B';
                        }
                        if (preg_match('/<(i|em)\b/i', $cellContent)) {
                            $cellStyle .= 'I';
                        }

                        $cellContent = self::expandAnchorHrefsForPdf($cellContent);
                        $cellText = preg_replace('/<br\s*\/?>/i', "\n", $cellContent);
                        $cellText = strip_tags($cellText);
                        $cellText = trim(preg_replace('/[ \t]+/', ' ', $cellText));

                        $cells[] = [
                            'text'    => $cellText,
                            'align'   => $cellAlign,
                            'style'   => $cellStyle,
                            'images'  => $cellImages,
                            'colspan' => $colspan,
                        ];
                    }
                    if (!empty($cells)) {
                        $rows[] = $cells;
                    }
                }
                if (!empty($rows)) {
                    return '<__TABLEBLOCK__>' . base64_encode(json_encode($rows)) . '</__TABLEBLOCK__>';
                }

                return '';
            },
            $html
        );

        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $chunks = preg_split('/<\s*\/\s*(?:p|div)\s*>/i', $html);

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $chunk = self::expandAnchorHrefsForPdf($chunk);

            if (strpos($chunk, '<__TABLEBLOCK__>') !== false) {
                preg_match_all('/<__TABLEBLOCK__>(.*?)<\/__TABLEBLOCK__>/s', $chunk, $tbMatches);
                foreach ($tbMatches[1] as $encoded) {
                    $rows = json_decode(base64_decode($encoded), true);
                    if (!empty($rows)) {
                        $blocks[] = ['type' => 'table', 'rows' => $rows, 'text' => '', 'align' => 'L', 'list' => false, 'style' => ''];
                    }
                }
                $chunk = trim(preg_replace('/<__TABLEBLOCK__>.*?<\/__TABLEBLOCK__>/s', '', $chunk));
                if ($chunk === '') {
                    continue;
                }
            }

            $align = 'L';
            if (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*right/i', $chunk)
                || preg_match('/class\s*=\s*["\'][^"\']*text-right/i', $chunk)) {
                $align = 'R';
            } elseif (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*center/i', $chunk)
                || preg_match('/class\s*=\s*["\'][^"\']*text-center/i', $chunk)) {
                $align = 'C';
            }

            $fontStyle = '';
            if (preg_match('/<(b|strong)\b/i', $chunk)) {
                $fontStyle .= 'B';
            }
            if (preg_match('/<(i|em)\b/i', $chunk)) {
                $fontStyle .= 'I';
            }

            if (preg_match_all('/<img[^>]+>/i', $chunk, $imgMatches)) {
                foreach ($imgMatches[0] as $imgTag) {
                    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $imgTag, $srcM)) {
                        $iw = 0;
                        $ih = 0;
                        if (preg_match('/width\s*=\s*["\']?(\d+)["\']?/i', $imgTag, $wm)) {
                            $iw = (int) $wm[1];
                        }
                        if (preg_match('/height\s*=\s*["\']?(\d+)["\']?/i', $imgTag, $hm)) {
                            $ih = (int) $hm[1];
                        }
                        $blocks[] = ['type' => 'image', 'src' => $srcM[1], 'width' => $iw, 'height' => $ih, 'text' => '', 'align' => $align, 'list' => false, 'style' => ''];
                    }
                }
            }

            $isList = (strpos($chunk, '<__LISTBLOCK__>') !== false);

            if ($isList) {
                $textParts = [];
                $remaining = preg_replace_callback(
                    '/<__LISTBLOCK__>(.*?)<\/__LISTBLOCK__>/s',
                    static function ($lm) use (&$textParts) {
                        $textParts[] = trim($lm[1]);

                        return '';
                    },
                    $chunk
                );
                $extra = trim(strip_tags(self::expandAnchorHrefsForPdf($remaining)));
                if ($extra !== '') {
                    array_unshift($textParts, $extra);
                }
                $text = implode("\n", $textParts);
            } else {
                $text = strip_tags($chunk);
            }

            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = trim(implode("\n", array_map('trim', explode("\n", $text))));

            if ($text !== '') {
                $blocks[] = ['type' => 'text', 'text' => $text, 'align' => $align, 'list' => $isList, 'style' => $fontStyle];
            }
        }

        if (empty($blocks)) {
            $text = preg_replace('/<__LISTBLOCK__>(.*?)<\/__LISTBLOCK__>/s', '$1', $html);
            $text = strip_tags(self::expandAnchorHrefsForPdf($text));
            $text = trim(preg_replace('/[ \t]+/', ' ', $text));
            if ($text !== '') {
                $blocks[] = ['type' => 'text', 'text' => $text, 'align' => 'L', 'list' => false, 'style' => ''];
            }
        }

        return $blocks;
    }

    /**
     * Resolve an image src (relative URL or root-relative) to an absolute filesystem path.
     *
     * @param   string  $src  Image src attribute value
     *
     * @return  string|null  Absolute path or null if not resolvable
     */
    public static function resolveImagePath($src)
    {
        if (empty($src)) {
            return null;
        }
        if (strpos($src, 'data:') === 0) {
            return null;
        }
        if (preg_match('/^https?:\/\//i', $src) || strpos($src, '//') === 0) {
            $siteRoot   = rtrim(Uri::root(), '/');
            $normalised = preg_replace('/^\/\//', 'https://', $src);
            if (stripos($normalised, $siteRoot) === 0) {
                $src = ltrim(substr($normalised, strlen($siteRoot)), '/');
            } else {
                return null;
            }
        }
        $src  = ltrim($src, '/');
        $src  = preg_replace('/\?.*$/', '', $src);
        $path = JPATH_ROOT . '/' . $src;

        return file_exists($path) ? $path : null;
    }

    /**
     * Render a list of parsed HTML blocks to an FPDF instance.
     *
     * @param   \FPDF    $pdf       FPDF instance
     * @param   array    $blocks    Block list from parseHtmlBlocks()
     * @param   float    $lineH     Line height in mm
     * @param   int      $fontSize  Font size in pt
     * @param   float    $pageW     Page width in mm
     * @param   float    $marginR   Right margin in mm
     * @param   float    $marginL   Left margin in mm
     * @param   float    $gap       Spacing added after each non-list text block (mm)
     * @param   callable|null $fixSpanishChars  Encoder for FPDF
     * @param   float    $maxWidth  Optional max width in mm; 0 = full width
     */
    public static function renderPdfBlocks($pdf, $blocks, $lineH, $fontSize, $pageW, $marginR, $marginL = 15, $gap = 4, ?callable $fixSpanishChars = null, $maxWidth = 0)
    {
        $encode = $fixSpanishChars ?? static function ($t) {
            return $t;
        };

        $contentStarted = false;

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';

            if ($type === 'image') {
                $imgPath = self::resolveImagePath($block['src'] ?? '');
                if ($imgPath) {
                    $contentStarted = true;
                    $imgWpx = (int) ($block['width'] ?? 0);
                    $imgWmm = $imgWpx > 0 ? min($imgWpx * 0.2646, $pageW - $marginL - $marginR) : 50;
                    $imgHpx = (int) ($block['height'] ?? 0);
                    $imgHmm = $imgHpx > 0 ? $imgHpx * 0.2646 : ($imgWmm * 0.5);
                    $pdf->Image($imgPath, $pdf->GetX(), $pdf->GetY(), $imgWmm);
                    $pdf->SetY($pdf->GetY() + $imgHmm + 2);
                }

                continue;
            }

            if ($type === 'table') {
                $contentStarted = true;
                $tableW = ($maxWidth > 0 ? $maxWidth : ($pageW - $marginL - $marginR));
                foreach ($block['rows'] as $row) {
                    $numCols = count($row);
                    if ($numCols === 0) {
                        continue;
                    }
                    $colW = $tableW / $numCols;
                    $rowY = $pdf->GetY();
                    $maxH = $lineH;
                    $curX = $marginL;

                    foreach ($row as $cell) {
                        $cw    = $colW * max(1, (int) ($cell['colspan'] ?? 1));
                        $cellY = $rowY;

                        foreach ($cell['images'] ?? [] as $img) {
                            $imgPath = self::resolveImagePath($img['src'] ?? '');
                            if ($imgPath) {
                                $imgWpx = (int) ($img['width'] ?? 0);
                                $imgWmm = $imgWpx > 0 ? min($imgWpx * 0.2646, $cw - 2) : min(50, $cw - 2);
                                $imgHpx = (int) ($img['height'] ?? 0);
                                $imgHmm = $imgHpx > 0 ? $imgHpx * 0.2646 : ($imgWmm * 0.5);
                                $pdf->Image($imgPath, $curX + 1, $cellY + 1, $imgWmm);
                                $maxH = max($maxH, $imgHmm + 3);
                            }
                        }

                        $cellText = $encode($cell['text'] ?? '');
                        if ($cellText !== '') {
                            $pdf->SetFont('Arial', $cell['style'] ?? '', $fontSize);
                            $pdf->SetXY($curX, $cellY);
                            $pdf->MultiCell($cw, $lineH, $cellText, 0, $cell['align'] ?? 'L');
                            $textH = $pdf->GetY() - $cellY;
                            $maxH  = max($maxH, $textH);
                        }

                        $curX += $cw;
                    }

                    $pdf->SetXY($marginL, $rowY + $maxH);
                }
                $pdf->Ln(2);

                continue;
            }

            if (trim($block['text'] ?? '') === '') {
                if ($contentStarted) {
                    $pdf->Ln($gap);
                }

                continue;
            }

            $contentStarted = true;

            $align  = $block['align'];
            $text   = $encode($block['text']);
            $isList = !empty($block['list']);
            $pdf->SetFont('Arial', $block['style'] ?? '', $fontSize);

            $textW = ($maxWidth > 0 ? $maxWidth : 0);
            if ($align === 'R') {
                foreach (explode("\n", $text) as $line) {
                    $line = trim($line);
                    if ($line === '') {
                        continue;
                    }
                    $w = $pdf->GetStringWidth($line);
                    $pdf->SetX($pageW - $marginR - $w);
                    $pdf->Cell($w, $lineH, $line, 0, 1, 'L');
                }
            } elseif ($align === 'L' && preg_match('#https?://#i', $text)) {
                self::renderLatin1TextLinesWithHttpLinks($pdf, $text, $lineH, $textW);
            } else {
                $pdf->MultiCell($textW, $lineH, $text, 0, $align);
            }
            $pdf->Ln($isList ? 1 : $gap);
        }
    }

    /**
     * FPDF strips &lt;a&gt; to inner text only; editors often keep a shortened label while href is correct.
     * Replace each anchor with href text when it is a WhatsApp URL, when inner is empty, or when inner is
     * a strict prefix of href (truncated URL in the label).
     *
     * @param   string  $html  HTML fragment
     *
     * @return  string
     *
     * @since   3.113.40
     */
    public static function expandAnchorHrefsForPdf(string $html): string
    {
        if ($html === '' || stripos($html, '<a') === false) {
            return $html;
        }
        $out = preg_replace_callback(
            '/<\s*a\b[^>]*\bhref\s*=\s*(["\'])([^"\']*)\1[^>]*>(.*?)<\s*\/\s*a\s*>/is',
            static function (array $m): string {
                $href = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $href = trim($href);
                $inner = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($href === '') {
                    return $inner;
                }
                if (preg_match('#^https?://(wa\.me/|api\.whatsapp\.com/)#i', $href)) {
                    return $href;
                }
                if (preg_match('#^https?://#i', $href)) {
                    if ($inner === '') {
                        return $href;
                    }
                    $innerNorm = rtrim($inner, '/');
                    $hrefNorm  = rtrim($href, '/');
                    if ($innerNorm !== '' && stripos($hrefNorm, $innerNorm) === 0 && strlen($hrefNorm) > strlen($innerNorm)) {
                        return $href;
                    }
                }

                return $inner !== '' ? $inner : $href;
            },
            $html
        );

        return $out !== null ? $out : $html;
    }

    /**
     * Left-aligned Latin-1 text with clickable http(s) segments (FPDF Write).
     *
     * @param   \FPDF   $pdf
     * @param   string  $latin1Text  Already passed through encodeTextForFpdf
     *
     * @since   3.113.40
     */
    private static function renderLatin1TextLinesWithHttpLinks(\FPDF $pdf, string $latin1Text, float $lineH, float $textW): void
    {
        $lines = explode("\n", $latin1Text);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                $pdf->Ln($lineH);

                continue;
            }
            if (!preg_match('#https?://#i', $line)) {
                $pdf->MultiCell($textW, $lineH, $line, 0, 'L');

                continue;
            }
            $parts = preg_split('#(https?://[^\s]+)#i', $line, -1, PREG_SPLIT_DELIM_CAPTURE);
            if ($parts === false) {
                $pdf->MultiCell($textW, $lineH, $line, 0, 'L');

                continue;
            }
            foreach ($parts as $j => $part) {
                if ($part === '') {
                    continue;
                }
                $isUrl = ($j % 2 === 1);
                if ($isUrl) {
                    $pdf->SetTextColor(0, 0, 255);
                    $pdf->Write($lineH, $part, $part);
                    $pdf->SetTextColor(0, 0, 0);
                } else {
                    $pdf->Write($lineH, $part, '');
                }
            }
            $pdf->Ln($lineH);
        }
    }
}
