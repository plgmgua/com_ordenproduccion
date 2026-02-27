<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;

/**
 * Cotizacion controller (pliego quote calculation).
 *
 * @since  3.67.0
 */
class CotizacionController extends BaseController
{
    /**
     * Calculate pliego price (AJAX). Returns JSON: success, price_per_sheet, total, message.
     *
     * @return  void
     * @since   3.67.0
     */
    public function calculatePliegoPrice()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json', true);

        $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => 'Invalid token']);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => 'Login required']);
            $app->close();
        }

        $quantity = (int) $app->input->get('quantity', 0);
        $paperTypeId = (int) $app->input->get('paper_type_id', 0);
        $sizeId = (int) $app->input->get('size_id', 0);
        $tiroRetiro = $app->input->get('tiro_retiro', 'tiro', 'cmd') === 'retiro' ? 'retiro' : 'tiro';
        $laminationTypeId = (int) $app->input->get('lamination_type_id', 0);
        $laminationTiroRetiro = $app->input->get('lamination_tiro_retiro', '', 'cmd');
        if ($laminationTiroRetiro !== 'retiro' && $laminationTiroRetiro !== 'tiro') {
            $laminationTiroRetiro = $tiroRetiro;
        }
        $processIds = $app->input->get('process_ids', [], 'array');
        $processIds = array_map('intval', array_filter($processIds));

        if ($quantity < 1 || $paperTypeId < 1 || $sizeId < 1) {
            echo json_encode(['success' => false, 'message' => 'Quantity, paper type and size required']);
            $app->close();
        }

        $productosModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Productos', 'Site');

        $printPrice = $productosModel->getPrintPricePerSheet($paperTypeId, $sizeId, $tiroRetiro, $quantity);
        if ($printPrice === null) {
            echo json_encode(['success' => false, 'message' => 'No print price for this combination']);
            $app->close();
        }

        $laminationPrice = 0.0;
        if ($laminationTypeId > 0) {
            $laminationPrice = $productosModel->getLaminationPricePerSheet($laminationTypeId, $sizeId, $laminationTiroRetiro, $quantity);
            if ($laminationPrice === null) {
                $laminationPrice = 0.0;
            }
        }

        // Procesos Adicionales: custom range per process (range_1_ceiling = upper bound of first range)
        $processesTotal = 0.0;
        if (!empty($processIds)) {
            $processes = $productosModel->getProcesses();
            foreach ($processes as $p) {
                if (!in_array((int) $p->id, $processIds, true)) {
                    continue;
                }
                $ceiling = (int) ($p->range_1_ceiling ?? 1000);
                if ($ceiling < 1) {
                    $ceiling = 1000;
                }
                $useRange1 = $quantity <= $ceiling;
                $processesTotal += $useRange1
                    ? (float) ($p->price_1_to_1000 ?? 0)
                    : (float) ($p->price_1001_plus ?? 0);
            }
        }

        $pricePerSheet = $printPrice + $laminationPrice;
        $total = $pricePerSheet * $quantity + $processesTotal;

        $getLabel = function ($key, $fallback) {
            $t = Text::_($key);
            return (is_string($t) && (strpos($t, 'COM_ORDENPRODUCCION_') === 0 || $t === $key)) ? $fallback : $t;
        };

        $printLabel = $tiroRetiro === 'retiro'
            ? $getLabel('COM_ORDENPRODUCCION_CALC_PRINT_RETIRO', 'Impresión (Tiro/Retiro)')
            : $getLabel('COM_ORDENPRODUCCION_CALC_PRINT_TIRO', 'Impresión (Tiro)');
        $laminationLabel = $getLabel('COM_ORDENPRODUCCION_CALC_LAMINATION', 'Laminación');

        $rows = [];
        $rows[] = [
            'label' => $printLabel,
            'detail' => 'Q ' . number_format((float) $printPrice, 2),
            'subtotal' => round($printPrice * $quantity, 2),
        ];
        if ($laminationPrice > 0) {
            $rows[] = [
                'label' => $laminationLabel,
                'detail' => 'Q ' . number_format((float) $laminationPrice, 2),
                'subtotal' => round($laminationPrice * $quantity, 2),
            ];
        }
        if (!empty($processIds)) {
            $processes = $productosModel->getProcesses();
            foreach ($processes as $p) {
                if (!in_array((int) $p->id, $processIds, true)) {
                    continue;
                }
                $ceiling = (int) ($p->range_1_ceiling ?? 1000);
                if ($ceiling < 1) {
                    $ceiling = 1000;
                }
                $useRange1 = $quantity <= $ceiling;
                $price = $useRange1 ? (float) ($p->price_1_to_1000 ?? 0) : (float) ($p->price_1001_plus ?? 0);
                $rangeLabel = $useRange1 ? ('1–' . $ceiling) : (($ceiling + 1) . '+');
                $rows[] = [
                    'label' => $p->name ?? '',
                    'detail' => $rangeLabel . ': Q ' . number_format($price, 2),
                    'subtotal' => $price,
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'quantity' => $quantity,
            'price_per_sheet' => round($pricePerSheet, 4),
            'total' => round($total, 2),
            'print_price' => $printPrice,
            'lamination_price' => $laminationPrice,
            'processes_total' => $processesTotal,
            'rows' => $rows,
        ]);
        $app->close();
    }

    /**
     * Generate and output quotation as PDF using FPDF (same method as orden de trabajo / envio).
     *
     * @return  void
     * @since   3.78.0
     */
    public function downloadPdf()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();

        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }

        $quotationId = (int) $app->input->get('id', 0);
        if ($quotationId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }

        // Ventas group check (same as Cotizacion view)
        $userGroups = $user->getAuthorisedGroups();
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote('ventas'));
        $db->setQuery($query);
        $ventasGroupId = $db->loadResult();
        if (!$ventasGroupId || !in_array($ventasGroupId, $userGroups, true)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_NO_PERMISSION'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ordenproduccion_quotations'))
            ->where($db->quoteName('id') . ' = ' . $quotationId)
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        $quotation = $db->loadObject();
        if (!$quotation) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }

        $query = $db->getQuery(true)
            ->select('i.*')
            ->from($db->quoteName('#__ordenproduccion_quotation_items', 'i'))
            ->where($db->quoteName('i.quotation_id') . ' = ' . $quotationId)
            ->order($db->quoteName('i.line_order') . ' ASC, ' . $db->quoteName('i.id') . ' ASC');
        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];

        $admModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Administracion', 'Site', ['ignore_request' => true]);
        $pdfSettings = $admModel->getCotizacionPdfSettings();

        $numeroCotizacion = $quotation->quotation_number ?? ('COT-' . $quotationId);
        $salesAgentName = trim((string) ($quotation->sales_agent ?? ''));
        $quoteDate = isset($quotation->quote_date) && $quotation->quote_date ? $quotation->quote_date : null;
        $fechaFormatted = $quoteDate ? \Joomla\CMS\HTML\HTMLHelper::_('date', $quoteDate, 'Y-m-d') : '';
        if ($fechaFormatted === '' && $quoteDate) {
            $fechaFormatted = date('Y-m-d', strtotime($quoteDate));
        }
        $context = [
            'numero_cotizacion' => $numeroCotizacion,
            'fecha'              => $fechaFormatted,
            'cliente'            => trim((string) ($quotation->client_name ?? '')),
            'contacto'           => trim((string) ($quotation->contact_name ?? '')),
            'sales_agent_name'   => $salesAgentName !== '' ? $salesAgentName : null,
            'user'               => $user,
        ];
        $encabezadoHtml = CotizacionPdfHelper::replacePlaceholders($pdfSettings['encabezado'] ?? '', $context);
        $terminosHtml   = CotizacionPdfHelper::replacePlaceholders($pdfSettings['terminos_condiciones'] ?? '', $context);
        $pieHtml        = CotizacionPdfHelper::replacePlaceholders($pdfSettings['pie_pagina'] ?? '', $context);

        $currency = $quotation->currency ?? 'Q';
        $totalAmount = isset($quotation->total_amount) ? (float) $quotation->total_amount : 0;

        try {
            $this->generateCotizacionPdf(
                $quotation,
                $items,
                $encabezadoHtml,
                $terminosHtml,
                $pieHtml,
                $numeroCotizacion,
                $fechaFormatted,
                $currency,
                $totalAmount,
                (int) $app->input->get('download', 0) === 1,
                $pdfSettings
            );
        } catch (\Throwable $e) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_PDF_GENERATION') . ': ' . $e->getMessage(), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
        }
    }

    /**
     * Parse HTML into blocks with alignment (preserve WYSIWYG: left/right/center and line breaks).
     * Used for encabezado, términos and pie. Block text preserves \n for MultiCell.
     *
     * @param   string    $html             HTML content (placeholders already replaced)
     * @param   callable  $fixSpanishChars  Function to normalize characters for FPDF
     * @return  array  List of [ 'text' => string, 'align' => 'L'|'R'|'C' ]
     */
    private function parseHtmlBlocks($html, callable $fixSpanishChars)
    {
        $blocks = [];
        $html = trim((string) $html);
        if ($html === '') {
            return $blocks;
        }

        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Convert <li> items to lines prefixed with a bullet "* " before any other processing.
        // Handles <ul> and <ol> (ordered lists get numbered).
        $html = preg_replace_callback(
            '/<\s*ol[^>]*>(.*?)<\s*\/\s*ol\s*>/is',
            function ($m) {
                $idx = 1;
                return preg_replace_callback(
                    '/<\s*li[^>]*>(.*?)<\s*\/\s*li\s*>/is',
                    function ($li) use (&$idx) {
                        return '<__li_num__>' . ($idx++) . '. ' . strip_tags($li[1]) . '</__li_num__>';
                    },
                    $m[1]
                );
            },
            $html
        );
        $html = preg_replace_callback(
            '/<\s*ul[^>]*>(.*?)<\s*\/\s*ul\s*>/is',
            function ($m) {
                return preg_replace_callback(
                    '/<\s*li[^>]*>(.*?)<\s*\/\s*li\s*>/is',
                    function ($li) {
                        return '<__li__>' . strip_tags($li[1]) . '</__li__>';
                    },
                    $m[1]
                );
            },
            $html
        );
        // Replace our temporary markers with "* item\n"
        // Collect list items into a single block with a __LIST__ marker so they stay together
        $html = preg_replace_callback('/((?:<__li__>.*?<\/__li__>)+)/s', function ($m) {
            $lines = [];
            preg_match_all('/<__li__>(.*?)<\/__li__>/s', $m[1], $ms);
            foreach ($ms[1] as $item) {
                $lines[] = '* ' . trim($item);
            }
            return '<__LISTBLOCK__>' . implode("\n", $lines) . '</__LISTBLOCK__>';
        }, $html);
        $html = preg_replace_callback('/((?:<__li_num__>.*?<\/__li_num__>)+)/s', function ($m) {
            $lines = [];
            preg_match_all('/<__li_num__>(.*?)<\/__li_num__>/s', $m[1], $ms);
            foreach ($ms[1] as $item) {
                $lines[] = trim($item);
            }
            return '<__LISTBLOCK__>' . implode("\n", $lines) . '</__LISTBLOCK__>';
        }, $html);

        // Replace <br> with newline
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // Split by block-level closing tags: </p>, </div>, </ul>, </ol>
        $chunks = preg_split('/<\s*\/\s*(?:p|div|ul|ol|li)\s*>/i', $html);
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            $align = 'L';
            if (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*right/i', $chunk)
                || preg_match('/class\s*=\s*["\'][^"\']*text-right/i', $chunk)) {
                $align = 'R';
            } elseif (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*center/i', $chunk)
                || preg_match('/class\s*=\s*["\'][^"\']*text-center/i', $chunk)) {
                $align = 'C';
            }
            // Detect pre-built list blocks (all bullets merged into one)
            $isList = (strpos($chunk, '<__LISTBLOCK__>') !== false);
            if ($isList) {
                preg_match('/<__LISTBLOCK__>(.*?)<\/__LISTBLOCK__>/s', $chunk, $lm);
                $text = isset($lm[1]) ? trim($lm[1]) : '';
            } else {
                $text = strip_tags($chunk);
            }
            // Collapse horizontal whitespace only (preserve \n)
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $lines = array_map('trim', explode("\n", $text));
            $text = trim(implode("\n", $lines));
            if ($text !== '') {
                $blocks[] = ['text' => $fixSpanishChars($text), 'align' => $align, 'list' => $isList];
            }
        }

        // Fallback: whole content as one left-aligned block
        if (empty($blocks)) {
            $text = preg_replace('/<__LISTBLOCK__>(.*?)<\/__LISTBLOCK__>/s', '$1', $html);
            $text = strip_tags($text);
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = trim($text);
            if ($text !== '') {
                $blocks[] = ['text' => $fixSpanishChars($text), 'align' => 'L', 'list' => false];
            }
        }

        return $blocks;
    }

    /**
     * Generate cotización PDF with FPDF (same pattern as OrdenController work order / envio).
     *
     * @param   object   $quotation       Quotation row
     * @param   array    $items           Quotation items
     * @param   string   $encabezadoHtml   Header content (HTML, will be stripped for FPDF)
     * @param   string   $terminosHtml     Terms content (HTML)
     * @param   string   $pieHtml          Footer content (HTML)
     * @param   string   $numeroCotizacion Quotation number
     * @param   string   $fechaFormatted   Formatted date
     * @param   string   $currency         Currency symbol
     * @param   float    $totalAmount      Total amount
     * @param   bool     $forceDownload    True to force download (D), false for inline (I)
     * @param   array    $pdfSettings      Optional. Keys encabezado_x, encabezado_y, terminos_x, terminos_y, pie_x, pie_y (mm). 0 = flow after content.
     * @return  void
     */
    private function generateCotizacionPdf($quotation, $items, $encabezadoHtml, $terminosHtml, $pieHtml, $numeroCotizacion, $fechaFormatted, $currency, $totalAmount, $forceDownload = false, array $pdfSettings = [])
    {
        require_once JPATH_ROOT . '/fpdf/fpdf.php';

        $pdf = new \FPDF('P', 'mm', [215.9, 279.4]); // 8.5" x 11" Letter
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        $fixSpanishChars = function ($text) {
            if (empty($text)) {
                return $text;
            }
            $replacements = [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
                'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
                'Ü' => 'U', 'ü' => 'u', 'Ç' => 'C', 'ç' => 'c',
                'Å' => 'A', 'å' => 'a', 'Æ' => 'AE', 'æ' => 'ae', 'Ø' => 'O', 'ø' => 'o',
                'º' => '', 'ª' => '', '°' => '', '´' => "'", '`' => "'",
            ];
            return strtr($text, $replacements);
        };

        $encabezadoBlocks = $this->parseHtmlBlocks($encabezadoHtml, $fixSpanishChars);
        $terminosBlocks   = $this->parseHtmlBlocks($terminosHtml, $fixSpanishChars);
        $pieBlocks        = $this->parseHtmlBlocks($pieHtml, $fixSpanishChars);

        $encX = isset($pdfSettings['encabezado_x']) ? (float) $pdfSettings['encabezado_x'] : 15;
        $encY = isset($pdfSettings['encabezado_y']) ? (float) $pdfSettings['encabezado_y'] : 15;
        $tableX = isset($pdfSettings['table_x']) ? (float) $pdfSettings['table_x'] : 0;
        $tableY = isset($pdfSettings['table_y']) ? (float) $pdfSettings['table_y'] : 0;
        $termX = isset($pdfSettings['terminos_x']) ? (float) $pdfSettings['terminos_x'] : 0;
        $termY = isset($pdfSettings['terminos_y']) ? (float) $pdfSettings['terminos_y'] : 0;
        $pieX = isset($pdfSettings['pie_x']) ? (float) $pdfSettings['pie_x'] : 0;
        $pieY = isset($pdfSettings['pie_y']) ? (float) $pdfSettings['pie_y'] : 0;

        $pdf->SetFont('Arial', '', 10);

        // Encabezado: position (X,Y mm) then render block-by-block
        if (!empty($encabezadoBlocks)) {
            $pdf->SetXY($encX, $encY);
            $lineH = 6;
            $marginR = 15;
            $pageW = $pdf->GetPageWidth();

            foreach ($encabezadoBlocks as $i => $block) {
                if (trim($block['text']) === '') {
                    $pdf->Ln(4);
                    continue;
                }
                $align = $block['align'];
                $text = $block['text'];
                $isList = !empty($block['list']);
                $pdf->SetFont('Arial', 'B', 11);

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
                } else {
                    $pdf->MultiCell(0, $lineH, $text, 0, $align);
                }
                $pdf->Ln($isList ? 1 : 4);
            }
            $pdf->SetFont('Arial', '', 10);
        }

        // Info line: Cliente, Contacto, NIT, Fecha, Agente (no vertical bar)
        $clientName = $fixSpanishChars($quotation->client_name ?? '');
        $contactName = $fixSpanishChars($quotation->contact_name ?? '');
        $clientNit = $fixSpanishChars($quotation->client_nit ?? '');
        $salesAgent = $fixSpanishChars($quotation->sales_agent ?? '');
        $infoLine = "Cliente: $clientName   Contacto: $contactName   NIT: $clientNit   Fecha: $fechaFormatted   Agente: $salesAgent";
        $pdf->MultiCell(0, 6, $infoLine, 0, 'L');
        $pdf->Ln(6);

        // Table: position (X,Y mm) then Cantidad, Descripción, Precio unit., Subtotal
        if ($tableY > 0 || $tableX > 0) {
            $pdf->SetXY($tableX > 0 ? $tableX : 15, $tableY > 0 ? $tableY : $pdf->GetY());
        }
        $colCant = 18;
        $colDesc = 95;
        $colUnit = 35;
        $colSub  = 35;
        $lineH  = 6;

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCant, $lineH, 'Cantidad', 1, 0, 'L');
        $pdf->Cell($colDesc, $lineH, 'Descripcion', 1, 0, 'L');
        $pdf->Cell($colUnit, $lineH, 'Precio unit.', 1, 0, 'R');
        $pdf->Cell($colSub, $lineH, 'Subtotal', 1, 1, 'R');
        $pdf->SetFont('Arial', '', 9);

        foreach ($items as $item) {
            $qty = isset($item->cantidad) ? (int) $item->cantidad : 1;
            $subtotal = isset($item->subtotal) ? (float) $item->subtotal : 0;
            $unit = $qty > 0 ? ($subtotal / $qty) : 0;
            $desc = $fixSpanishChars($item->descripcion ?? '');
            if (strlen($desc) > 70) {
                $desc = substr($desc, 0, 67) . '...';
            }
            $pdf->Cell($colCant, $lineH, (string) $qty, 1, 0, 'L');
            $pdf->Cell($colDesc, $lineH, $desc, 1, 0, 'L');
            $pdf->Cell($colUnit, $lineH, $currency . ' ' . number_format($unit, 4), 1, 0, 'R');
            $pdf->Cell($colSub, $lineH, $currency . ' ' . number_format($subtotal, 2), 1, 1, 'R');
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCant + $colDesc + $colUnit, $lineH, 'Total:', 1, 0, 'R');
        $pdf->Cell($colSub, $lineH, $currency . ' ' . number_format($totalAmount, 2), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(6);

        // Términos y condiciones: block-by-block with WYSIWYG alignment and line breaks
        if (!empty($terminosBlocks)) {
            if ($termY > 0 || $termX > 0) {
                $pdf->SetXY($termX > 0 ? $termX : 15, $termY > 0 ? $termY : $pdf->GetY());
            }
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, $lineH, 'Terminos y Condiciones', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pageW = $pdf->GetPageWidth();
            $marginR = 15;
            foreach ($terminosBlocks as $block) {
                if (trim($block['text']) === '') {
                    $pdf->Ln(3);
                    continue;
                }
                $align = $block['align'];
                $text = $block['text'];
                $isList = !empty($block['list']);
                if ($align === 'R') {
                    foreach (explode("\n", $text) as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        $w = $pdf->GetStringWidth($line);
                        $pdf->SetX($pageW - $marginR - $w);
                        $pdf->Cell($w, 5, $line, 0, 1, 'L');
                    }
                } else {
                    $pdf->MultiCell(0, 5, $text, 0, $align);
                }
                $pdf->Ln($isList ? 1 : 3);
            }
            $pdf->Ln(4);
        }

        // Pie de página: block-by-block with WYSIWYG alignment and line breaks
        if (!empty($pieBlocks)) {
            if ($pieY > 0 || $pieX > 0) {
                $pdf->SetXY($pieX > 0 ? $pieX : 15, $pieY > 0 ? $pieY : $pdf->GetY());
            }
            $pdf->SetFont('Arial', '', 9);
            $pageW = $pdf->GetPageWidth();
            $marginR = 15;
            foreach ($pieBlocks as $block) {
                if (trim($block['text']) === '') {
                    $pdf->Ln(3);
                    continue;
                }
                $align = $block['align'];
                $text = $block['text'];
                $isList = !empty($block['list']);
                if ($align === 'R') {
                    foreach (explode("\n", $text) as $line) {
                        $line = trim($line);
                        if ($line === '') {
                            continue;
                        }
                        $w = $pdf->GetStringWidth($line);
                        $pdf->SetX($pageW - $marginR - $w);
                        $pdf->Cell($w, 5, $line, 0, 1, 'L');
                    }
                } else {
                    $pdf->MultiCell(0, 5, $text, 0, $align);
                }
                $pdf->Ln($isList ? 1 : 3);
            }
        }

        $filename = 'cotizacion-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $numeroCotizacion) . '.pdf';
        $dest = $forceDownload ? 'D' : 'I';
        $pdf->Output($dest, $filename);
        exit;
    }
}
