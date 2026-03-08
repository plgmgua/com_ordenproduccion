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
use Joomla\CMS\Filesystem\File;
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
        $itemCols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $itemCols = is_array($itemCols) ? array_change_key_case($itemCols, CASE_LOWER) : [];
        if (isset($itemCols['pre_cotizacion_id'])) {
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
        $fechaFormatted = '';
        if ($quoteDate) {
            $ts = strtotime($quoteDate);
            if ($ts !== false) {
                $meses = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
                    4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
                    10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                ];
                $fechaFormatted = (int) date('d', $ts) . ' de ' . $meses[(int) date('n', $ts)] . ' de ' . date('Y', $ts);
            }
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
     * Save Confirmar Cotización step 1: signed document (PDF or image) upload.
     *
     * @return  void
     * @since   3.80.0
     */
    public function saveConfirmarStep1()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        if (!Session::checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $quotationId = (int) $app->input->getInt('id', 0);
        if ($quotationId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_quotations'))
            ->where($db->quoteName('id') . ' = ' . $quotationId)
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        if (!$db->loadResult()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $file = $app->input->files->get('signed_document', [], 'array');
        if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_NO_FILE'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $phpError = (int) ($file['error'] ?? 0);
        if ($phpError !== UPLOAD_ERR_OK) {
            $messages = [
                UPLOAD_ERR_INI_SIZE   => 'El archivo supera el límite de tamaño del servidor.',
                UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el límite del formulario.',
                UPLOAD_ERR_PARTIAL    => 'El archivo fue subido parcialmente.',
                UPLOAD_ERR_NO_FILE     => 'No se seleccionó ningún archivo.',
                UPLOAD_ERR_NO_TMP_DIR  => 'Falta la carpeta temporal.',
                UPLOAD_ERR_CANT_WRITE  => 'No se pudo escribir el archivo.',
                UPLOAD_ERR_EXTENSION   => 'Una extensión bloqueó la subida.',
            ];
            $app->enqueueMessage($messages[$phpError] ?? 'Error al subir el archivo.', 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_INVALID_FILE_TYPE'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $maxSize = 5 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_FILE_TOO_BIG'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $uploadDir = JPATH_ROOT . '/media/com_ordenproduccion/cotizacion_signed';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                $app->enqueueMessage('No se pudo crear el directorio de subida.', 'error');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
                return;
            }
        }
        if (!is_writable($uploadDir)) {
            $app->enqueueMessage('El directorio de subida no tiene permisos de escritura.', 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $uniqueName = 'cotizacion_' . $quotationId . '_' . date('Y-m-d_H-i-s') . '.' . $ext;
        $fullPath = $uploadDir . '/' . $uniqueName;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $app->enqueueMessage('No se pudo guardar el archivo.', 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $relativePath = 'media/com_ordenproduccion/cotizacion_signed/' . $uniqueName;
        $cols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['signed_document_path'])) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $update = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_quotations'))
            ->set($db->quoteName('signed_document_path') . ' = ' . $db->quote($relativePath))
            ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
            ->where($db->quoteName('id') . ' = ' . $quotationId);
        $db->setQuery($update);
        $db->execute();
        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
    }

    /**
     * Save Confirmar Cotización step 2: billing instructions (Instrucciones de Facturación).
     *
     * @return  void
     * @since   3.80.0
     */
    public function saveConfirmarStep2()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        if (!Session::checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $quotationId = (int) $app->input->getInt('id', 0);
        if ($quotationId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__ordenproduccion_quotations'))
            ->where($db->quoteName('id') . ' = ' . $quotationId)
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($query);
        if (!$db->loadResult()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $instrucciones = $app->input->post->getString('instrucciones_facturacion', '');
        $cols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['instrucciones_facturacion'])) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $update = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_quotations'))
            ->set($db->quoteName('instrucciones_facturacion') . ' = ' . $db->quote($instrucciones))
            ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
            ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
            ->where($db->quoteName('id') . ' = ' . $quotationId);
        $db->setQuery($update);
        $db->execute();
        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
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
        // Strip UTF-8 non-breaking space (0xC2 0xA0) which FPDF renders as "Â "
        $html = str_replace("\xc2\xa0", ' ', $html);

        // Step 1: Replace each complete <ol>...</ol> with a single <__LISTBLOCK__> placeholder.
        // We do this BEFORE any splitting so all items stay together as one block.
        $html = preg_replace_callback(
            '/<\s*ol[^>]*>(.*?)<\s*\/\s*ol\s*>/is',
            function ($m) {
                $idx = 1;
                $lines = [];
                preg_match_all('/<\s*li[^>]*>(.*?)<\s*\/\s*li\s*>/is', $m[1], $ms);
                foreach ($ms[1] as $item) {
                    $lines[] = ($idx++) . '. ' . trim(strip_tags($item));
                }
                return '<__LISTBLOCK__>' . implode("\n", $lines) . '</__LISTBLOCK__>';
            },
            $html
        );

        // Step 2: Same for <ul>...</ul>
        $html = preg_replace_callback(
            '/<\s*ul[^>]*>(.*?)<\s*\/\s*ul\s*>/is',
            function ($m) {
                $lines = [];
                preg_match_all('/<\s*li[^>]*>(.*?)<\s*\/\s*li\s*>/is', $m[1], $ms);
                foreach ($ms[1] as $item) {
                    $lines[] = '* ' . trim(strip_tags($item));
                }
                return '<__LISTBLOCK__>' . implode("\n", $lines) . '</__LISTBLOCK__>';
            },
            $html
        );

        // Step 3: Extract <table> blocks into placeholders so cells survive strip_tags.
        // Each placeholder encodes rows→cells (images, text, alignment, colspan, style) as base64 JSON.
        // IMPORTANT: cell text is kept as UTF-8 here so json_encode works correctly.
        // The Latin-1 conversion for FPDF is applied later at render time in renderPdfBlocks().
        $html = preg_replace_callback(
            '/<\s*table[^>]*>(.*?)<\s*\/\s*table\s*>/is',
            function ($m) {
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
                                    $iw = 0; $ih = 0;
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

        // Step 4: Replace <br> with newline
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);

        // Step 5: Split by paragraph/div closing tags only (ul/ol/li/table already handled)
        $chunks = preg_split('/<\s*\/\s*(?:p|div)\s*>/i', $html);

        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            // Handle table blocks inside this chunk
            if (strpos($chunk, '<__TABLEBLOCK__>') !== false) {
                preg_match_all('/<__TABLEBLOCK__>(.*?)<\/__TABLEBLOCK__>/s', $chunk, $tbMatches);
                foreach ($tbMatches[1] as $encoded) {
                    $rows = json_decode(base64_decode($encoded), true);
                    if (!empty($rows)) {
                        $blocks[] = ['type' => 'table', 'rows' => $rows, 'text' => '', 'align' => 'L', 'list' => false, 'style' => ''];
                    }
                }
                // Process any remaining text in the same chunk (outside the table placeholder)
                $chunk = trim(preg_replace('/<__TABLEBLOCK__>.*?<\/__TABLEBLOCK__>/s', '', $chunk));
                if ($chunk === '') {
                    continue;
                }
            }

            // Detect alignment from inline style or class
            $align = 'L';
            if (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*right/i', $chunk)
                || preg_match('/class\s*=\s*["\'][^"\']*text-right/i', $chunk)) {
                $align = 'R';
            } elseif (preg_match('/style\s*=\s*["\'][^"\']*text-align\s*:\s*center/i', $chunk)
                || preg_match('/class\s*=\s*["\'][^"\']*text-center/i', $chunk)) {
                $align = 'C';
            }

            // Detect font style (bold / italic) from HTML tags in this chunk
            $fontStyle = '';
            if (preg_match('/<(b|strong)\b/i', $chunk)) {
                $fontStyle .= 'B';
            }
            if (preg_match('/<(i|em)\b/i', $chunk)) {
                $fontStyle .= 'I';
            }

            // Extract standalone <img> tags before strip_tags removes them
            if (preg_match_all('/<img[^>]+>/i', $chunk, $imgMatches)) {
                foreach ($imgMatches[0] as $imgTag) {
                    if (preg_match('/src\s*=\s*["\']([^"\']+)["\']/', $imgTag, $srcM)) {
                        $iw = 0; $ih = 0;
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
                // Extract all list blocks in this chunk; there should only be one but handle multiples
                $textParts = [];
                $remaining = preg_replace_callback(
                    '/<__LISTBLOCK__>(.*?)<\/__LISTBLOCK__>/s',
                    function ($lm) use (&$textParts) {
                        $textParts[] = trim($lm[1]);
                        return '';
                    },
                    $chunk
                );
                // Include any non-list text in the same chunk (e.g. a label before the list)
                $extra = trim(strip_tags($remaining));
                if ($extra !== '') {
                    array_unshift($textParts, $extra);
                }
                $text = implode("\n", $textParts);
            } else {
                $text = strip_tags($chunk);
            }

            // Collapse horizontal whitespace only; preserve newlines
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = trim(implode("\n", array_map('trim', explode("\n", $text))));

            if ($text !== '') {
                // Keep UTF-8 here — Latin-1 conversion for FPDF happens in renderPdfBlocks()
                $blocks[] = ['type' => 'text', 'text' => $text, 'align' => $align, 'list' => $isList, 'style' => $fontStyle];
            }
        }

        // Fallback: whole content as one left-aligned block
        if (empty($blocks)) {
            $text = preg_replace('/<__LISTBLOCK__>(.*?)<\/__LISTBLOCK__>/s', '$1', $html);
            $text = strip_tags($text);
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
     * @return  string|null   Absolute path or null if not resolvable
     */
    private function resolveImagePath($src)
    {
        if (empty($src)) {
            return null;
        }
        // Skip data URIs
        if (strpos($src, 'data:') === 0) {
            return null;
        }
        // Full HTTP/HTTPS URL: try to strip the site base URL and resolve locally
        if (preg_match('/^https?:\/\//i', $src) || strpos($src, '//') === 0) {
            $siteRoot = rtrim(\Joomla\CMS\Uri\Uri::root(), '/');
            // Normalise protocol-relative URLs
            $normalised = preg_replace('/^\/\//', 'https://', $src);
            if (stripos($normalised, $siteRoot) === 0) {
                // Convert to relative path
                $src = ltrim(substr($normalised, strlen($siteRoot)), '/');
            } else {
                return null; // External domain — cannot resolve to filesystem
            }
        }
        $src  = ltrim($src, '/');
        $src  = preg_replace('/\?.*$/', '', $src); // strip query string
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
     * @param   callable $fixSpanishChars Optional encoder for FPDF
     * @param   float    $maxWidth  Optional max width in mm (e.g. for 50% column); 0 = full width
     * @return  void
     */
    private function renderPdfBlocks($pdf, $blocks, $lineH, $fontSize, $pageW, $marginR, $marginL = 15, $gap = 4, callable $fixSpanishChars = null, $maxWidth = 0)
    {
        // Apply Latin-1 conversion for FPDF; fall back to identity if not provided
        $encode = $fixSpanishChars ?? static function ($t) { return $t; };

        // Track whether real content has been rendered yet so we never add
        // vertical spacing (Ln) for empty paragraphs that appear BEFORE the
        // first actual image/table/text block. TinyMCE often emits leading
        // <p>&nbsp;</p> elements that would otherwise push the header down.
        $contentStarted = false;

        foreach ($blocks as $block) {
            $type = $block['type'] ?? 'text';

            // ── Image block ──────────────────────────────────────────────────
            if ($type === 'image') {
                $imgPath = $this->resolveImagePath($block['src'] ?? '');
                if ($imgPath) {
                    $contentStarted = true;
                    $imgWpx  = (int) ($block['width'] ?? 0);
                    $imgWmm  = $imgWpx > 0 ? min($imgWpx * 0.2646, $pageW - $marginL - $marginR) : 50;
                    $imgHpx  = (int) ($block['height'] ?? 0);
                    $imgHmm  = $imgHpx > 0 ? $imgHpx * 0.2646 : ($imgWmm * 0.5);
                    $pdf->Image($imgPath, $pdf->GetX(), $pdf->GetY(), $imgWmm);
                    $pdf->SetY($pdf->GetY() + $imgHmm + 2);
                }
                continue;
            }

            // ── Table block ───────────────────────────────────────────────────
            if ($type === 'table') {
                $contentStarted = true;
                $tableW = ($maxWidth > 0 ? $maxWidth : ($pageW - $marginL - $marginR));
                foreach ($block['rows'] as $row) {
                    $numCols = count($row);
                    if ($numCols === 0) {
                        continue;
                    }
                    $colW  = $tableW / $numCols;
                    $rowY  = $pdf->GetY();
                    $maxH  = $lineH;
                    $curX  = $marginL;

                    foreach ($row as $cell) {
                        $cw       = $colW * max(1, (int) ($cell['colspan'] ?? 1));
                        $cellY    = $rowY;

                        // Render images in this cell
                        foreach ($cell['images'] ?? [] as $img) {
                            $imgPath = $this->resolveImagePath($img['src'] ?? '');
                            if ($imgPath) {
                                $imgWpx = (int) ($img['width'] ?? 0);
                                $imgWmm = $imgWpx > 0 ? min($imgWpx * 0.2646, $cw - 2) : min(50, $cw - 2);
                                $imgHpx = (int) ($img['height'] ?? 0);
                                $imgHmm = $imgHpx > 0 ? $imgHpx * 0.2646 : ($imgWmm * 0.5);
                                $pdf->Image($imgPath, $curX + 1, $cellY + 1, $imgWmm);
                                $maxH = max($maxH, $imgHmm + 3);
                            }
                        }

                        // Render text in this cell (encode to Latin-1 for FPDF)
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

            // ── Text block ────────────────────────────────────────────────────
            if (trim($block['text'] ?? '') === '') {
                // Skip spacing for leading empty blocks (before any content).
                // Only add a blank line gap once real content has started.
                if ($contentStarted) {
                    $pdf->Ln($gap);
                }
                continue;
            }

            $contentStarted = true;

            $align  = $block['align'];
            $text   = $encode($block['text']); // convert UTF-8 → Latin-1 for FPDF
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
            } else {
                $pdf->MultiCell($textW, $lineH, $text, 0, $align);
            }
            $pdf->Ln($isList ? 1 : $gap);
        }
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

        // FPDF uses Latin-1 (ISO-8859-1) internally. Spanish characters (á, é, í, ó, ú, ñ, ü…)
        // exist in Latin-1, so we simply re-encode from UTF-8 instead of stripping them.
        // iconv with //TRANSLIT preserves Latin-1 characters exactly and transliterates any
        // characters that fall outside Latin-1 (e.g. emoji) to their nearest ASCII equivalent.
        $fixSpanishChars = function ($text) {
            if (empty($text)) {
                return $text;
            }
            // Replace UTF-8 non-breaking space before encoding (avoids "Â " artefact)
            $text = str_replace("\xc2\xa0", ' ', $text);
            // Convert UTF-8 → ISO-8859-1 so FPDF renders ñ, á, é, í, ó, ú, ü correctly
            if (function_exists('iconv')) {
                $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
                return ($converted !== false) ? $converted : $text;
            }
            return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
        };

        $encabezadoBlocks = $this->parseHtmlBlocks($encabezadoHtml, $fixSpanishChars);
        $terminosBlocks   = $this->parseHtmlBlocks($terminosHtml, $fixSpanishChars);
        $pieBlocks        = $this->parseHtmlBlocks($pieHtml, $fixSpanishChars);

        $logoPath  = isset($pdfSettings['logo_path'])  ? trim($pdfSettings['logo_path'])  : '';
        $logoX     = isset($pdfSettings['logo_x'])     ? (float) $pdfSettings['logo_x']     : 15;
        $logoY     = isset($pdfSettings['logo_y'])     ? (float) $pdfSettings['logo_y']     : 15;
        $logoWidth = isset($pdfSettings['logo_width']) ? (float) $pdfSettings['logo_width'] : 50;
        $encX = isset($pdfSettings['encabezado_x']) ? (float) $pdfSettings['encabezado_x'] : 15;
        $encY = isset($pdfSettings['encabezado_y']) ? (float) $pdfSettings['encabezado_y'] : 15;
        $tableX = isset($pdfSettings['table_x']) ? (float) $pdfSettings['table_x'] : 0;
        $tableY = isset($pdfSettings['table_y']) ? (float) $pdfSettings['table_y'] : 0;
        $termX = isset($pdfSettings['terminos_x']) ? (float) $pdfSettings['terminos_x'] : 0;
        $termY = isset($pdfSettings['terminos_y']) ? (float) $pdfSettings['terminos_y'] : 0;
        $pieX = isset($pdfSettings['pie_x']) ? (float) $pdfSettings['pie_x'] : 0;
        $pieY = isset($pdfSettings['pie_y']) ? (float) $pdfSettings['pie_y'] : 0;

        $pdf->SetFont('Arial', '', 10);
        $pageW   = $pdf->GetPageWidth();
        $marginR = 15;

        // ── Logo (rendered at its own absolute coordinates, independent of encabezado) ──
        if (!empty($logoPath)) {
            $resolvedLogo = $this->resolveImagePath($logoPath);
            if ($resolvedLogo) {
                $pdf->Image($resolvedLogo, $logoX, $logoY, $logoWidth);
            }
        }

        // Encabezado: position (X,Y mm) then render block-by-block
        if (!empty($encabezadoBlocks)) {
            $pdf->SetXY($encX, $encY);
            $this->renderPdfBlocks($pdf, $encabezadoBlocks, 6, 11, $pageW, $marginR, 15, 4, $fixSpanishChars);
            $pdf->SetFont('Arial', '', 10);
        }

        $pdf->Ln(4);

        // Table: Codigo (pre-cotizacion number), Cantidad, Descripción, Precio unit., Subtotal
        if ($tableY > 0 || $tableX > 0) {
            $pdf->SetXY($tableX > 0 ? $tableX : 15, $tableY > 0 ? $tableY : $pdf->GetY());
        }
        $colCodigo = 22;
        $colCant   = 16;
        $colDesc   = 82;
        $colUnit   = 35;
        $colSub    = 35;
        $lineH     = 6;

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCodigo, $lineH, 'Codigo', 1, 0, 'L');
        $pdf->Cell($colCant, $lineH, 'Cantidad', 1, 0, 'L');
        $pdf->Cell($colDesc, $lineH, 'Descripcion', 1, 0, 'L');
        $pdf->Cell($colUnit, $lineH, 'Precio unit.', 1, 0, 'R');
        $pdf->Cell($colSub, $lineH, 'Subtotal', 1, 1, 'R');
        $pdf->SetFont('Arial', '', 9);

        foreach ($items as $item) {
            $qty       = isset($item->cantidad) ? (int) $item->cantidad : 1;
            $lineTotal = (isset($item->valor_final) && $item->valor_final !== null && $item->valor_final !== '') ? (float) $item->valor_final : (isset($item->subtotal) ? (float) $item->subtotal : 0);
            $unit      = $qty > 0 ? ($lineTotal / $qty) : 0;
            $desc      = $fixSpanishChars($item->descripcion ?? '');
            $codigo    = $fixSpanishChars(isset($item->pre_cotizacion_number) && trim((string) $item->pre_cotizacion_number) !== ''
                ? trim((string) $item->pre_cotizacion_number)
                : (isset($item->pre_cotizacion_id) && (int) $item->pre_cotizacion_id > 0 ? 'PRE-' . (int) $item->pre_cotizacion_id : '-'));

            $rowX = $pdf->GetX();
            $rowY = $pdf->GetY();

            // Draw description first (MultiCell wraps and advances Y)
            $pdf->SetXY($rowX + $colCodigo + $colCant, $rowY);
            $pdf->MultiCell($colDesc, $lineH, $desc, 1, 'L');
            $newY   = $pdf->GetY();
            $rowH   = max($lineH, $newY - $rowY);

            // Draw Codigo and Cantidad with full row height
            $pdf->SetXY($rowX, $rowY);
            $pdf->Cell($colCodigo, $rowH, $codigo, 1, 0, 'L');
            $pdf->Cell($colCant, $rowH, (string) $qty, 1, 0, 'C');

            // Skip description column (already drawn), draw price and subtotal
            $pdf->SetXY($rowX + $colCodigo + $colCant + $colDesc, $rowY);
            $pdf->Cell($colUnit, $rowH, $currency . ' ' . number_format($unit, 4), 1, 0, 'R');
            $pdf->Cell($colSub, $rowH, $currency . ' ' . number_format($lineTotal, 2), 1, 1, 'R');

            $pdf->SetXY($rowX, $newY);
        }

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCodigo + $colCant + $colDesc + $colUnit, $lineH, 'Total:', 1, 0, 'R');
        $pdf->Cell($colSub, $lineH, $currency . ' ' . number_format($totalAmount, 2), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(6);

        // Términos y condiciones (60% width) + Aceptación de cotización (40% width)
        $contentW     = $pageW - 15 - $marginR;
        $termW        = $contentW * 0.6;
        $aceptacionW  = $contentW * 0.4;
        $aceptacionLineH = 9;
        $termStartX   = ($termX > 0 ? $termX : 15);
        $termStartY   = ($termY > 0 ? $termY : $pdf->GetY());
        $pdf->SetXY($termStartX, $termStartY);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($termW, $lineH, 'Terminos y Condiciones', 0, 1, 'L');
        if (!empty($terminosBlocks)) {
            $this->renderPdfBlocks($pdf, $terminosBlocks, 5, 9, $pageW, $marginR, 15, 3, $fixSpanishChars, $termW);
        }
        $leftEndY   = $pdf->GetY();
        $aceptacionX = $termStartX + $termW;
        $pdf->SetXY($aceptacionX, $termStartY);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($aceptacionW, $aceptacionLineH, $fixSpanishChars('Aceptacion de cotizacion'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetX($aceptacionX);
        $pdf->Cell($aceptacionW, $aceptacionLineH, $fixSpanishChars('Nombre'), 0, 1, 'L');
        $pdf->SetX($aceptacionX);
        $pdf->Cell($aceptacionW, $aceptacionLineH, $fixSpanishChars('Fecha'), 0, 1, 'L');
        $pdf->SetX($aceptacionX);
        $pdf->Cell($aceptacionW, $aceptacionLineH, $fixSpanishChars('Firma'), 0, 1, 'L');
        $pdf->SetY(max($leftEndY, $pdf->GetY()));
        $pdf->Ln(4);

        // Pie de página
        if (!empty($pieBlocks)) {
            if ($pieY > 0 || $pieX > 0) {
                $pdf->SetXY($pieX > 0 ? $pieX : 15, $pieY > 0 ? $pieY : $pdf->GetY());
            }
            $this->renderPdfBlocks($pdf, $pieBlocks, 5, 9, $pageW, $marginR, 15, 3, $fixSpanishChars);
        }

        $filename = 'cotizacion-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $numeroCotizacion) . '.pdf';
        $dest = $forceDownload ? 'D' : 'I';
        $pdf->Output($dest, $filename);
        exit;
    }
}
