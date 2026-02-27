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
     * Generate and download quotation as PDF (or HTML when Dompdf not available).
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

        $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        $hasPreId = isset($itemCols['pre_cotizacion_id']);
        $query = $db->getQuery(true)
            ->select('i.*')
            ->from($db->quoteName('#__ordenproduccion_quotation_items', 'i'))
            ->where($db->quoteName('i.quotation_id') . ' = ' . $quotationId)
            ->order($db->quoteName('i.line_order') . ' ASC, ' . $db->quoteName('i.id') . ' ASC');
        if ($hasPreId) {
            $subq = '(SELECT ' . $db->quoteName('p.number') . ' FROM ' . $db->quoteName('#__ordenproduccion_pre_cotizacion', 'p')
                . ' WHERE ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('i.pre_cotizacion_id') . ' LIMIT 1)';
            $query->select($subq . ' AS ' . $db->quoteName('pre_cotizacion_number'));
        }
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
        $encabezado = CotizacionPdfHelper::replacePlaceholders($pdfSettings['encabezado'] ?? '', $context);
        $terminos   = CotizacionPdfHelper::replacePlaceholders($pdfSettings['terminos_condiciones'] ?? '', $context);
        $pie        = CotizacionPdfHelper::replacePlaceholders($pdfSettings['pie_pagina'] ?? '', $context);

        $currency = $quotation->currency ?? 'Q';
        $totalAmount = isset($quotation->total_amount) ? (float) $quotation->total_amount : 0;

        $bodyRows = '';
        foreach ($items as $item) {
            $qty = isset($item->cantidad) ? (int) $item->cantidad : 1;
            $subtotal = isset($item->subtotal) ? (float) $item->subtotal : 0;
            $unit = $qty > 0 ? ($subtotal / $qty) : 0;
            $desc = htmlspecialchars($item->descripcion ?? '', ENT_QUOTES, 'UTF-8');
            $bodyRows .= '<tr><td>' . $qty . '</td><td>' . $desc . '</td><td class="text-end">' . $currency . ' ' . number_format($unit, 4) . '</td><td class="text-end">' . $currency . ' ' . number_format($subtotal, 2) . '</td></tr>';
        }
        $body = '<div class="cotizacion-pdf-body">';
        $body .= '<table class="table table-bordered" style="width:100%; border-collapse:collapse;"><thead><tr><th>Cantidad</th><th>Descripción</th><th class="text-end">Precio unit.</th><th class="text-end">Subtotal</th></tr></thead><tbody>' . $bodyRows . '</tbody>';
        $body .= '<tfoot><tr class="fw-bold"><td colspan="3" class="text-end">Total:</td><td class="text-end">' . $currency . ' ' . number_format($totalAmount, 2) . '</td></tr></tfoot></table>';
        $body .= '</div>';

        $fullHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . htmlspecialchars($numeroCotizacion, ENT_QUOTES, 'UTF-8') . '</title>';
        $fullHtml .= '<style>';
        $fullHtml .= 'body{font-family:DejaVu Sans,sans-serif;font-size:11px;margin:20px;}';
        $fullHtml .= 'table{width:100%;border-collapse:collapse;} th,td{border:1px solid #333;padding:6px;} .text-end{text-align:right;}';
        $fullHtml .= '.pdf-header,.pdf-footer,.pdf-terminos{margin:15px 0;} .pdf-info{margin:10px 0;}';
        $fullHtml .= '.pdf-header p,.pdf-terminos p,.pdf-footer p{margin:0.5em 0;} .pdf-header ul,.pdf-terminos ul,.pdf-footer ul{margin:0.5em 0;padding-left:1.5em;}';
        $fullHtml .= '.pdf-header ol,.pdf-terminos ol,.pdf-footer ol{margin:0.5em 0;padding-left:1.5em;}';
        $fullHtml .= '.pdf-header img,.pdf-terminos img,.pdf-footer img{max-width:100%;height:auto;}';
        $fullHtml .= '.pdf-header strong,.pdf-terminos strong,.pdf-footer strong{font-weight:bold;}';
        $fullHtml .= '.pdf-header h1,.pdf-header h2,.pdf-header h3,.pdf-header h4,.pdf-terminos h1,.pdf-terminos h2,.pdf-footer h1,.pdf-footer h2{margin:0.4em 0;font-weight:bold;}';
        $fullHtml .= '.pdf-header,.pdf-terminos,.pdf-footer{line-height:1.4;}';
        $fullHtml .= '.pdf-header *,.pdf-terminos *,.pdf-footer *{box-sizing:border-box;}';
        $fullHtml .= '</style></head><body>';
        $fullHtml .= '<div class="pdf-header">' . $encabezado . '</div>';
        $fullHtml .= '<div class="pdf-info"><strong>Cliente:</strong> ' . htmlspecialchars($quotation->client_name ?? '', ENT_QUOTES, 'UTF-8') . ' &nbsp; | &nbsp; <strong>Contacto:</strong> ' . htmlspecialchars($quotation->contact_name ?? '', ENT_QUOTES, 'UTF-8') . ' &nbsp; | &nbsp; <strong>NIT:</strong> ' . htmlspecialchars($quotation->client_nit ?? '', ENT_QUOTES, 'UTF-8') . ' &nbsp; | &nbsp; <strong>Fecha:</strong> ' . htmlspecialchars($fechaFormatted, ENT_QUOTES, 'UTF-8') . ' &nbsp; | &nbsp; <strong>Agente:</strong> ' . htmlspecialchars($quotation->sales_agent ?? '', ENT_QUOTES, 'UTF-8') . '</div>';
        $fullHtml .= $body;
        $fullHtml .= '<div class="pdf-terminos">' . $terminos . '</div>';
        $fullHtml .= '<div class="pdf-footer">' . $pie . '</div>';
        $fullHtml .= '</body></html>';

        if (class_exists(\Dompdf\Dompdf::class)) {
            try {
                $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
                $options = $dompdf->getOptions();
                if (method_exists($options, 'set')) {
                    $basePath = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
                    $options->set('baseUri', $basePath . '/');
                }
                $dompdf->loadHtml($fullHtml, 'UTF-8');
                $dompdf->setPaper('letter', 'portrait');
                $dompdf->render();
                $filename = 'cotizacion-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $numeroCotizacion) . '.pdf';
                $app->setHeader('Content-Type', 'application/pdf', true);
                $forceDownload = (int) $app->input->get('download', 0) === 1;
                $app->setHeader('Content-Disposition', ($forceDownload ? 'attachment' : 'inline') . '; filename="' . $filename . '"', true);
                $app->sendHeaders();
                echo $dompdf->output();
                $app->close();
            } catch (\Throwable $e) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_PDF_GENERATION') . ': ' . $e->getMessage(), 'error');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            }
            return;
        }

        $filename = 'cotizacion-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $numeroCotizacion) . '.html';
        $app->setHeader('Content-Type', 'text/html; charset=UTF-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $app->sendHeaders();
        $note = '<p style="background:#fff3cd;padding:10px;margin-bottom:15px;">' . Text::_('COM_ORDENPRODUCCION_COTIZACION_PDF_PRINT_TO_PDF') . '</p>';
        echo $note . $fullHtml;
        $app->close();
    }
}
