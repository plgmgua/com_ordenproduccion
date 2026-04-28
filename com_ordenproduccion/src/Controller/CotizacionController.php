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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Input\Input;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionHelper;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionFpdfBlocksHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\CotizacionPdfHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\AccessHelper;
use Grimpsa\Component\Ordenproduccion\Site\Service\EbiPayLinkService;
use Grimpsa\Component\Ordenproduccion\Site\Service\FelInvoiceIssuanceService;
use Grimpsa\Component\Ordenproduccion\Site\Service\OrdenFromQuotationService;
use Grimpsa\Component\Ordenproduccion\Site\Service\ApprovalWorkflowService;

/**
 * Cotizacion controller (pliego quote calculation).
 *
 * @since  3.67.0
 */
class CotizacionController extends BaseController
{
    /**
     * Load published quotation or null. If row exists but user may not access it, redirects and closes the app.
     *
     * @return  \stdClass|null
     *
     * @since   3.104.1
     */
    private function loadPublishedQuotationForCurrentUserOrClose(int $quotationId): ?\stdClass
    {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . (int) $quotationId)
                ->where($db->quoteName('state') . ' = 1')
        );
        $row = $db->loadObject();
        if (!$row) {
            return null;
        }
        if (!AccessHelper::userCanAccessQuotationRow($row)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            $app->close();
        }

        return $row;
    }

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

        $quotation = $this->loadPublishedQuotationForCurrentUserOrClose($quotationId);
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
        // Calendar date (same as list/detail): SQL DATE must not be shifted by HTMLHelper UTC→TZ.
        $fechaFormatted = '';
        $dateYmd = CotizacionHelper::formatQuoteDateYmd($quotation->quote_date ?? '');
        if ($dateYmd !== '') {
            $parts = explode('-', $dateYmd);
            if (count($parts) === 3) {
                $y = (int) $parts[0];
                $m = (int) $parts[1];
                $d = (int) $parts[2];
                $meses = [
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo',
                    4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre',
                    10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                ];
                if (isset($meses[$m]) && $y > 0 && $d > 0) {
                    $fechaFormatted = $d . ' de ' . $meses[$m] . ' de ' . $y;
                }
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

        $formatVersion = isset($pdfSettings['format_version']) ? max(1, min(2, (int) $pdfSettings['format_version'])) : 1;
        try {
            if ($formatVersion === 2) {
                $this->generateCotizacionPdfV2(
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
            } else {
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
            }
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
        if (!$this->loadPublishedQuotationForCurrentUserOrClose($quotationId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $nextStep = (int) $app->input->post->get('next_step', 0);
        $file = $app->input->files->get('signed_document', [], 'array');
        if (empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_NO_FILE'), 'error');
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
            $app->redirect(Route::_($url, false));
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
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
            $app->redirect(Route::_($url, false));
            return;
        }
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_INVALID_FILE_TYPE'), 'error');
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
            $app->redirect(Route::_($url, false));
            return;
        }
        $maxSize = 5 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_FILE_TOO_BIG'), 'error');
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
            $app->redirect(Route::_($url, false));
            return;
        }
        $uploadDir = JPATH_ROOT . '/media/com_ordenproduccion/cotizacion_signed';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0755, true)) {
                $app->enqueueMessage('No se pudo crear el directorio de subida.', 'error');
                $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
                $app->redirect(Route::_($url, false));
                return;
            }
        }
        if (!is_writable($uploadDir)) {
            $app->enqueueMessage('El directorio de subida no tiene permisos de escritura.', 'error');
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
            $app->redirect(Route::_($url, false));
            return;
        }
        $uniqueName = 'cotizacion_' . $quotationId . '_' . date('Y-m-d_H-i-s') . '.' . $ext;
        $fullPath = $uploadDir . '/' . $uniqueName;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $app->enqueueMessage('No se pudo guardar el archivo.', 'error');
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep ? '&confirmar_step=1' : '');
            $app->redirect(Route::_($url, false));
            return;
        }
        $relativePath = 'media/com_ordenproduccion/cotizacion_signed/' . $uniqueName;
        $cols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['signed_document_path'])) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
            $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId . ($nextStep === 2 ? '&confirmar_step=2' : '');
            $app->redirect(Route::_($url, false));
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
        $nextStep = (int) $app->input->post->get('next_step', 0);
        if ($nextStep === 2) {
            $app->getSession()->set('com_ordenproduccion.confirmar_step', 2);
        }
        $url = 'index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId;
        $app->redirect(Route::_($url, false));
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
        if (!$this->loadPublishedQuotationForCurrentUserOrClose($quotationId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $nextStep = (int) $app->input->post->get('next_step', 0);
        $cols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        $instruccionesCollected = $this->collectInstruccionesFacturacionFromPost($quotationId);
        if ($instruccionesCollected === null) {
            $isAjaxCol = $app->input->get('format') === 'json' || $app->input->post->get('format') === 'json';
            if ($isAjaxCol && $nextStep === 2) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => true, 'next_step' => 2]);
                $app->close();
            }
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
            if ($nextStep === 2) {
                $app->getSession()->set('com_ordenproduccion.confirmar_step', 2);
            }
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $instrucciones = $instruccionesCollected;
        if (!isset($cols['instrucciones_facturacion'])) {
            $isAjaxCol = $app->input->get('format') === 'json' || $app->input->post->get('format') === 'json';
            if ($isAjaxCol && $nextStep === 2) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode(['success' => true, 'next_step' => 2]);
                $app->close();
            }
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
            if ($nextStep === 2) {
                $app->getSession()->set('com_ordenproduccion.confirmar_step', 2);
            }
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
        $isAjax = $app->input->get('format') === 'json' || $app->input->post->get('format') === 'json';
        if ($isAjax && $nextStep === 2) {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['success' => true, 'next_step' => 2]);
            $app->close();
        }
        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_SAVED'), 'success');
        if ($nextStep === 2) {
            $app->getSession()->set('com_ordenproduccion.confirmar_step', 2);
        }
        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
    }

    /**
     * Finalize quotation confirmation: optional "Cotización aprobada" and "Orden de compra" files, optional instrucciones_facturacion, + flag cotizacion_confirmada.
     *
     * @return  void
     * @since   3.101.21
     */
    public function finalizeConfirmacionCotizacion()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }
        if (!Session::checkToken()) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $quotationId = (int) $app->input->post->getInt('id', 0);
        if ($quotationId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $row = $this->loadPublishedQuotationForCurrentUserOrClose($quotationId);
        if (!$row) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $cols = $db->getTableColumns('#__ordenproduccion_quotations', false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['cotizacion_confirmada'])) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_DB_UPDATE_REQUIRED'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }

        $uploadDir = JPATH_ROOT . '/media/com_ordenproduccion/cotizacion_confirmacion';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        if (!is_writable($uploadDir)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_UPLOAD_DIR_ERROR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }

        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $maxSize = 5 * 1024 * 1024;

        $pathAprobada = $this->getObjectProperty($row, 'cotizacion_aprobada_path');
        $pathOrden    = $this->getObjectProperty($row, 'orden_compra_path');

        $pathAprobada = $this->processOptionalQuotationConfirmUpload(
            $app->input->files->get('cotizacion_aprobada', [], 'array'),
            $quotationId,
            'aprobada',
            $uploadDir,
            $allowed,
            $maxSize
        ) ?? $pathAprobada;

        $pathOrden = $this->processOptionalQuotationConfirmUpload(
            $app->input->files->get('orden_compra', [], 'array'),
            $quotationId,
            'orden_compra',
            $uploadDir,
            $allowed,
            $maxSize
        ) ?? $pathOrden;

        $instruccionesFacturacion = $this->collectInstruccionesFacturacionFromPost($quotationId);
        if ($instruccionesFacturacion !== null && strlen($instruccionesFacturacion) > 65535) {
            $instruccionesFacturacion = substr($instruccionesFacturacion, 0, 65535);
        }

        $hasFacturacionConfig = isset($cols['facturacion_modo']);

        $facturacionModo       = 'con_envio';
        $facturacionFechaSql   = null;
        $facturacionFechaValid = true;
        $facturarCotizacionExactaDb = 1;
        if ($hasFacturacionConfig) {
            $facturacionModo = $app->input->post->getString('facturacion_modo', 'con_envio');
            if (!\in_array($facturacionModo, ['con_envio', 'fecha_especifica'], true)) {
                $facturacionModo = 'con_envio';
            }
            if ($facturacionModo === 'fecha_especifica') {
                $facturacionFechaRaw = trim($app->input->post->getString('facturacion_fecha', ''));
                $d = \DateTime::createFromFormat('Y-m-d', $facturacionFechaRaw);
                if ($facturacionFechaRaw === '' || !$d || $d->format('Y-m-d') !== $facturacionFechaRaw) {
                    $facturacionFechaValid = false;
                } else {
                    $facturacionFechaSql = $facturacionFechaRaw;
                }
            }
            $facturarCotizacionExactaDb = (int) $app->input->post->get('facturar_cotizacion_exacta', 1);
            $facturarCotizacionExactaDb = $facturarCotizacionExactaDb === 0 ? 0 : 1;
        } elseif ($instruccionesFacturacion !== null) {
            $facturacionModo = $app->input->post->getString('facturacion_modo', 'con_envio');
            if (!\in_array($facturacionModo, ['con_envio', 'fecha_especifica'], true)) {
                $facturacionModo = 'con_envio';
            }
            if ($facturacionModo === 'fecha_especifica') {
                $facturacionFechaRaw = trim($app->input->post->getString('facturacion_fecha', ''));
                $d = \DateTime::createFromFormat('Y-m-d', $facturacionFechaRaw);
                if ($facturacionFechaRaw === '' || !$d || $d->format('Y-m-d') !== $facturacionFechaRaw) {
                    $facturacionFechaValid = false;
                } else {
                    $facturacionFechaSql = $facturacionFechaRaw;
                }
            }
            $facturarCotizacionExactaDb = (int) $app->input->post->get('facturar_cotizacion_exacta', 1);
            $facturarCotizacionExactaDb = $facturarCotizacionExactaDb === 0 ? 0 : 1;
        }
        if (($hasFacturacionConfig || $instruccionesFacturacion !== null) && $facturacionModo === 'fecha_especifica' && !$facturacionFechaValid) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_FACTURACION_FECHA_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }

        $wfSvc = new ApprovalWorkflowService($db);
        $includeConfirmInFirstUpdate = !$wfSvc->hasSchema();

        $sets = [
            $db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()),
            $db->quoteName('modified_by') . ' = ' . (int) $user->id,
        ];
        if ($includeConfirmInFirstUpdate) {
            $sets[] = $db->quoteName('cotizacion_confirmada') . ' = 1';
        }
        if (isset($cols['cotizacion_aprobada_path'])) {
            $sets[] = $db->quoteName('cotizacion_aprobada_path') . ' = ' . $db->quote($pathAprobada);
        }
        if (isset($cols['orden_compra_path'])) {
            $sets[] = $db->quoteName('orden_compra_path') . ' = ' . $db->quote($pathOrden);
        }
        if ($instruccionesFacturacion !== null && isset($cols['instrucciones_facturacion'])) {
            $sets[] = $db->quoteName('instrucciones_facturacion') . ' = ' . $db->quote($instruccionesFacturacion);
        }
        if (($hasFacturacionConfig || $instruccionesFacturacion !== null) && isset($cols['facturacion_modo'])) {
            $sets[] = $db->quoteName('facturacion_modo') . ' = ' . $db->quote($facturacionModo);
        }
        if (($hasFacturacionConfig || $instruccionesFacturacion !== null) && isset($cols['facturacion_fecha'])) {
            $sets[] = $db->quoteName('facturacion_fecha') . ' = '
                . ($facturacionFechaSql === null ? 'NULL' : $db->quote($facturacionFechaSql));
        }
        if (($hasFacturacionConfig || $instruccionesFacturacion !== null) && isset($cols['facturar_cotizacion_exacta'])) {
            $sets[] = $db->quoteName('facturar_cotizacion_exacta') . ' = ' . $facturarCotizacionExactaDb;
        }

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_quotations'))
            ->set($sets)
            ->where($db->quoteName('id') . ' = ' . $quotationId);
        $db->setQuery($update);
        $db->execute();

        if ($wfSvc->hasSchema()) {
            $metaJson = json_encode([
                'quotation_id'          => $quotationId,
                'facturacion_modo'      => $facturacionModo,
                'facturacion_fecha_sql' => $facturacionFechaSql,
                'submitter_user_id'     => (int) $user->id,
            ], JSON_UNESCAPED_UNICODE);

            $rid = $wfSvc->createRequest(
                ApprovalWorkflowService::ENTITY_COTIZACION_CONFIRMATION,
                $quotationId,
                (int) $user->id,
                $metaJson
            );

            if ($rid > 0) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_COTIZACION_SUBMITTED'), 'success');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));

                return;
            }

            if ($wfSvc->getOpenPendingRequest(ApprovalWorkflowService::ENTITY_COTIZACION_CONFIRMATION, $quotationId) !== null) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_APPROVAL_COTIZACION_ALREADY_PENDING'), 'notice');
                $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));

                return;
            }

            $qConfirm = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_quotations'))
                ->set($db->quoteName('cotizacion_confirmada') . ' = 1')
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                ->where($db->quoteName('id') . ' = ' . $quotationId);
            $db->setQuery($qConfirm);
            $db->execute();

            if ($facturacionModo === 'fecha_especifica' && $facturacionFechaSql !== null) {
                $felSvc = new FelInvoiceIssuanceService();
                if ($felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn() && $felSvc->hasFelScheduledAtColumn()) {
                    $felSvc->scheduleOrUpdateInvoiceFromQuotation($quotationId, (int) $user->id, $facturacionFechaSql);
                }
            }

            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_FINALIZADA_OK'), 'success');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));

            return;
        }

        // Queue mock FEL for the billing date whenever "fecha específica" + valid date is chosen.
        // Do not tie this to facturar_cotizacion_exacta (that flag is a billing rule, not "whether to enqueue FEL").
        if ($facturacionModo === 'fecha_especifica' && $facturacionFechaSql !== null) {
            $felSvc = new FelInvoiceIssuanceService();
            if ($felSvc->isEngineAvailable() && $felSvc->hasQuotationIdColumn() && $felSvc->hasFelScheduledAtColumn()) {
                $felSvc->scheduleOrUpdateInvoiceFromQuotation($quotationId, (int) $user->id, $facturacionFechaSql);
            }
        }

        $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_CONFIRMAR_FINALIZADA_OK'), 'success');
        $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
    }

    /**
     * Mock ebi pay: create payment link for quotation (JSON). No invoice required.
     *
     * @return  void
     *
     * @since   3.101.55
     */
    public function createEbiPayLink()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);

        if (!Session::checkToken('request')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }

        if (!AccessHelper::isInVentasGroup() && !AccessHelper::isInAdministracionOrAdmonGroup() && !AccessHelper::isSuperUser()) {
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $app->close();
        }

        $quotationId = $app->input->getInt('quotation_id', 0);
        if ($quotationId < 1) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_QUOTATION')]);
            $app->close();
        }

        if (!$this->loadPublishedQuotationForCurrentUserOrClose($quotationId)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_QUOTATION')]);
            $app->close();
        }

        $svc    = new EbiPayLinkService();
        $result = $svc->createMockLinkForQuotation($quotationId);
        if (!empty($result['success'])) {
            $result['message'] = Text::_('COM_ORDENPRODUCCION_EBIPAY_LINK_CREATE_SUCCESS');
        }

        echo json_encode($result);
        $app->close();
    }

    /**
     * @param   array   $file      $_FILES-style
     * @param   int     $quotationId
     * @param   string  $suffix    filename part
     * @param   string  $uploadDir Absolute dir
     * @param   array   $allowed   extensions
     * @param   int     $maxSize   bytes
     * @return  string|null  Relative path from site root, or null if no new file
     */
    private function processOptionalQuotationConfirmUpload($file, $quotationId, $suffix, $uploadDir, $allowed, $maxSize)
    {
        if (empty($file) || !is_array($file) || empty($file['name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ((int) ($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
            return null;
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            return null;
        }
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            return null;
        }
        $uniqueName = 'cot_' . (int) $quotationId . '_' . $suffix . '_' . date('Y-m-d_His') . '.' . $ext;
        $fullPath = $uploadDir . '/' . $uniqueName;
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return null;
        }

        return 'media/com_ordenproduccion/cotizacion_confirmacion/' . $uniqueName;
    }

    /**
     * Save OT wizard step 3 header fields on pre-cotización when posting with a single pre_cotizacion_id.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   int                                   $preCotizacionId
     * @param   Input                                 $input
     *
     * @return  void
     *
     * @since   3.115.2
     */
    private function persistPreCotizacionOtWizardExtras($db, int $preCotizacionId, Input $input): void
    {
        if ($preCotizacionId < 1) {
            return;
        }

        $cols = $db->getTableColumns($db->replacePrefix('#__ordenproduccion_pre_cotizacion'), false);
        if (!is_array($cols)) {
            return;
        }

        $colsLower = array_change_key_case($cols, CASE_LOWER);
        $hasFecha    = isset($colsLower['ot_fecha_entrega']);
        $hasInstr    = isset($colsLower['ot_instrucciones_generales']);

        if (!$hasFecha && !$hasInstr) {
            return;
        }

        $fechaRaw = trim((string) $input->post->get('ot_fecha_entrega', ''));
        $instrRaw = $input->post->get('ot_instrucciones_generales', '', 'raw');
        $instrRaw = is_string($instrRaw) ? trim($instrRaw) : '';

        $fechaSql = null;
        if ($fechaRaw !== '') {
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaRaw, $m)
                && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                $fechaSql = $fechaRaw;
            }
        }

        $instrStored = $instrRaw === '' ? null : $instrRaw;

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ordenproduccion_pre_cotizacion'))
            ->where($db->quoteName('id') . ' = ' . (int) $preCotizacionId);

        if ($hasFecha) {
            if ($fechaSql === null) {
                $query->set($db->quoteName('ot_fecha_entrega') . ' = NULL');
            } else {
                $query->set($db->quoteName('ot_fecha_entrega') . ' = ' . $db->quote($fechaSql));
            }
        }

        if ($hasInstr) {
            if ($instrStored === null) {
                $query->set($db->quoteName('ot_instrucciones_generales') . ' = NULL');
            } else {
                $query->set($db->quoteName('ot_instrucciones_generales') . ' = ' . $db->quote($instrStored));
            }
        }

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (\Throwable $e) {
            // Ignore if migration not applied yet or column mismatch
        }
    }

    /**
     * Save "Detalles" (instructions) per line/concept.
     * POST: quotation_id (required), optional pre_cotizacion_id, detalle[line_id][concepto_key] = value.
     * Optional instrucciones_save_only=1 with format=json: persist detalles only (no notify/webhook); used by the quotation display modal.
     * If pre_cotizacion_id is set: save only that pre-cotizacion's lines and redirect to orden.
     * If only quotation_id: save all lines of all pre-cotizaciones in the quotation and redirect back to quotation display (modal).
     *
     * @return  void
     * @since   3.91.0
     */
    public function saveInstruccionesOrden()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }
        if (!Session::checkToken('post')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $preCotizacionId = (int) $app->input->post->get('pre_cotizacion_id', 0);
        $quotationId = (int) $app->input->post->get('quotation_id', 0);
        if ($quotationId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PRE_COTIZACION_ERROR_INVALID_ID'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $db = Factory::getDbo();
        if (!$this->loadPublishedQuotationForCurrentUserOrClose($quotationId)) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (!$precotModel->lineDetallesTableExists()) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_TABLE_MISSING'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $preCotizacionIds = [];
        if ($preCotizacionId > 0) {
            $preCotizacionIds = [$preCotizacionId];
        } else {
            $db->setQuery($db->getQuery(true)->select($db->quoteName('pre_cotizacion_id'))->from($db->quoteName('#__ordenproduccion_quotation_items'))->where($db->quoteName('quotation_id') . ' = ' . $quotationId));
            $rows = $db->loadColumn() ?: [];
            foreach ($rows as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $preCotizacionIds[] = $id;
                }
            }
        }
        $isAjax = $app->input->get('format') === 'json' || $app->input->post->get('format') === 'json';
        $instruccionesSaveOnly = (int) $app->input->post->get('instrucciones_save_only', 0) === 1;
        $finalizeWizardStep3 = $isAjax && $instruccionesSaveOnly
            && (int) $app->input->post->get('ot_wizard_step3_finalize', 0) === 1;
        if ($finalizeWizardStep3 && $preCotizacionId > 0) {
            $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
            $fechaRaw = trim((string) $app->input->post->get('ot_fecha_entrega', ''));
            $instrGen = $app->input->post->get('ot_instrucciones_generales', '', 'raw');
            $instrGen = \is_string($instrGen) ? trim($instrGen) : '';
            $fechaOk = false;
            if ($fechaRaw !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaRaw, $xm)) {
                $fechaOk = checkdate((int) $xm[2], (int) $xm[3], (int) $xm[1]);
            }
            if (!$fechaOk || $instrGen === '') {
                $app->setHeader('Content-Type', 'application/json', true);
                $msg = !$fechaOk
                    ? Text::_('COM_ORDENPRODUCCION_OT_WIZARD_STEP3_ERR_FECHA')
                    : Text::_('COM_ORDENPRODUCCION_OT_WIZARD_STEP3_ERR_DESCRIPCION');
                echo json_encode(['success' => false, 'message' => $msg]);
                $app->close();
            }
        }
        $detallePost = $app->input->post->get('detalle', [], 'array');
        foreach ($preCotizacionIds as $pid) {
            $lines = $precotModel->getLines($pid);
            foreach ($lines as $line) {
                $lineId = (int) $line->id;
                $concepts = $precotModel->getConceptsForLine($line);
                $keyToDetalle = [];
                $keyToLabel = $concepts;
                $lineData = isset($detallePost[$lineId]) && is_array($detallePost[$lineId]) ? $detallePost[$lineId] : [];
                foreach ($concepts as $key => $label) {
                    $keyToDetalle[$key] = isset($lineData[$key]) ? trim((string) $lineData[$key]) : '';
                }
                $precotModel->saveLineDetalles($lineId, $keyToDetalle, $keyToLabel);
            }
        }
        if ($preCotizacionId > 0) {
            $this->persistPreCotizacionOtWizardExtras($db, $preCotizacionId, $app->input);
        }
        $nextStep = (int) $app->input->post->get('next_step', 0);
        if ($preCotizacionId > 0 && $instruccionesSaveOnly) {
            $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
            if ($isAjax) {
                $app->setHeader('Content-Type', 'application/json', true);
                echo json_encode([
                    'success' => true,
                    'message' => Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVED_FOR_LATER'),
                ]);
                $app->close();
            }
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVED_FOR_LATER'), 'success');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));

            return;
        }
        if ($preCotizacionId > 0) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVED'), 'success');
            // Same follow-up as legacy "Generar Orden de Trabajo": webhook + redirect (notifySolicitudOrden).
            $tok = Session::getFormToken();
            $app->redirect(Route::_(
                'index.php?option=com_ordenproduccion&task=cotizacion.notifySolicitudOrden'
                . '&pre_cotizacion_id=' . $preCotizacionId . '&quotation_id=' . $quotationId . '&' . $tok . '=1',
                false
            ));
        } elseif ($isAjax && $nextStep === 3) {
            $app->setHeader('Content-Type', 'application/json', true);
            echo json_encode(['success' => true, 'next_step' => 3]);
            $app->close();
        } else {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_INSTRUCCIONES_ORDEN_SAVED'), 'success');
            if ($nextStep === 3) {
                $app->getSession()->set('com_ordenproduccion.confirmar_step', 3);
            }
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
        }
    }

    /**
     * Create an internal work order row (Joomla DB) from a confirmed quotation line (pre-cotización).
     * Used by OT wizard Step 3 after persisting instrucciones.
     *
     * POST: quotation_id, pre_cotizacion_id, optional wizard fields:
     *  - tipo_entrega (domicilio|recoger)
     *  - delivery_address
     *  - instrucciones_entrega
     *  - contact_person_name
     *  - contact_person_phone
     *  - ot_fecha_entrega (Y-m-d from step 3 date input when present)
     *
     * Returns JSON: { success, message, order_id?, redirect_url? }
     *
     * Redirect rule:
     * - If there are other pre-cotizaciones on the same quotation without an active OT → redirect back to cotización
     * - Else → redirect to the newly created OT edit screen
     *
     * @return  void
     *
     * @since   3.115.3
     */
    public function createOrdenFromQuotation()
    {
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json', true);
        // JSON task: ensure frontend component strings resolve (fixes raw COM_* when locale has no merged INI)
        $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);

        $user = Factory::getUser();
        if ($user->guest) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED')]);
            $app->close();
        }
        if (!Session::checkToken('post')) {
            echo json_encode(['success' => false, 'message' => Text::_('JINVALID_TOKEN')]);
            $app->close();
        }

        $quotationId = (int) $app->input->post->get('quotation_id', 0);
        $preCotizacionId = (int) $app->input->post->get('pre_cotizacion_id', 0);
        if ($quotationId < 1 || $preCotizacionId < 1) {
            echo json_encode(['success' => false, 'message' => 'Invalid quotation or pre-cotización id']);
            $app->close();
        }

        if (!$this->loadPublishedQuotationForCurrentUserOrClose($quotationId)) {
            echo json_encode(['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND')]);
            $app->close();
        }

        $db = Factory::getDbo();

        $service = new OrdenFromQuotationService($db);

        // If already exists, use it (idempotent UX).
        $existing = $service->findExistingActiveOrderByPreCotizacionId($preCotizacionId);
        if ($existing && isset($existing->id)) {
            $existingId = (int) $existing->id;
            $redirect = $this->buildOrdenWizardRedirectUrl($db, $quotationId, $existingId);
            echo json_encode([
                'success' => true,
                'message' => 'OK',
                'order_id' => $existingId,
                'redirect_url' => $redirect,
            ]);
            $app->close();
        }

        $wizard = [
            'tipo_entrega' => (string) $app->input->post->get('tipo_entrega', '', 'cmd'),
            'delivery_address' => (string) $app->input->post->get('delivery_address', '', 'string'),
            'instrucciones_entrega' => (string) $app->input->post->get('instrucciones_entrega', '', 'raw'),
            'contact_person_name' => (string) $app->input->post->get('contact_person_name', '', 'string'),
            'contact_person_phone' => (string) $app->input->post->get('contact_person_phone', '', 'string'),
            'ot_fecha_entrega' => (string) $app->input->post->get('ot_fecha_entrega', '', 'string'),
            'ot_instrucciones_generales' => (string) $app->input->post->get('ot_instrucciones_generales', '', 'string'),
        ];

        $otFe = trim((string) ($wizard['ot_fecha_entrega'] ?? ''));
        $otDesc = trim((string) ($wizard['ot_instrucciones_generales'] ?? ''));
        $fechaOk = false;
        if ($otFe !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $otFe, $mm)) {
            $fechaOk = checkdate((int) $mm[2], (int) $mm[3], (int) $mm[1]);
        }
        if (!$fechaOk) {
            $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_ORDENPRODUCCION_OT_WIZARD_STEP3_ERR_FECHA'),
            ]);
            $app->close();
        }
        if ($otDesc === '') {
            $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
            echo json_encode([
                'success' => false,
                'message' => Text::_('COM_ORDENPRODUCCION_OT_WIZARD_STEP3_ERR_DESCRIPCION'),
            ]);
            $app->close();
        }

        $built = $service->buildOrdenInsertData($quotationId, $preCotizacionId, $wizard, $user, true);
        if (empty($built['success'])) {
            $wm = (string) ($built['message'] ?? 'Could not build work order');
            $this->logOtWizardCreateFailure(
                'build_orden_insert_data_failed',
                ['quotation_id' => $quotationId, 'pre_cotizacion_id' => $preCotizacionId],
                [],
                $wm
            );
            echo json_encode(['success' => false, 'message' => $wm]);
            $app->close();
        }

        $cols = isset($built['columns']) && is_array($built['columns']) ? $built['columns'] : [];
        if ($cols === []) {
            $this->logOtWizardCreateFailure(
                'no_insertable_columns',
                ['quotation_id' => $quotationId, 'pre_cotizacion_id' => $preCotizacionId],
                [],
                'No insertable columns for work order table'
            );
            echo json_encode(['success' => false, 'message' => 'No insertable columns for work order table']);
            $app->close();
        }

        unset($cols['id']);

        $cols = $this->filterOrdeneInsertRowForMysqlEnums($db, $cols);
        $cols = $this->mirrorOrdenSpanishAliasColumns($cols);

        $wizardCtx = ['quotation_id' => $quotationId, 'pre_cotizacion_id' => $preCotizacionId];
        $persist = $this->persistNewOrdenRow($db, $cols, $wizardCtx);
        if (empty($persist['success'])) {
            $msg = $this->messageOtCreateInternalFailed();
            $out = ['success' => false, 'message' => $msg];
            if (!empty($persist['detail'])) {
                $out['detail'] = (string) $persist['detail'];
            }
            echo json_encode($out);
            $app->close();
        }

        $newId = (int) ($persist['order_id'] ?? 0);
        if ($newId < 1) {
            $this->logOtWizardCreateFailure(
                'missing_order_id_after_persist',
                ['quotation_id' => $quotationId, 'pre_cotizacion_id' => $preCotizacionId],
                $cols,
                'persist returned success:true but missing order_id.'
            );
            $msg = $this->messageOtCreateInternalFailed();
            $out = ['success' => false, 'message' => $msg];
            if (!empty($persist['detail'])) {
                $out['detail'] = (string) $persist['detail'];
            }
            echo json_encode($out);
            $app->close();
        }

        $redirect = $this->buildOrdenWizardRedirectUrl($db, $quotationId, $newId);

        echo json_encode([
            'success' => true,
            'message' => 'OK',
            'order_id' => $newId,
            'redirect_url' => $redirect,
        ]);
        $app->close();
    }

    /**
     * Ensure legacy Spanish columns used by {#__ordenproduccion_ordenes} checks are populated when only
     * newer English aliases were kept after column filtering (and vice versa).
     *
     * @param   array<string,mixed>  $cols
     * @return  array<string,mixed>
     *
     * @since   3.115.6
     */
    private function mirrorOrdenSpanishAliasColumns(array $cols): array
    {
        $trim = static function ($v): string {
            return trim((string) ($v ?? ''));
        };

        $lowerToKey = [];
        foreach ($cols as $k => $_) {
            if (\is_string($k) && $k !== '') {
                $lk = strtolower($k);
                if (!isset($lowerToKey[$lk])) {
                    $lowerToKey[$lk] = $k;
                }
            }
        }

        $mirrorPair = static function (array &$cols, array $lowerToKey, string $spa, string $eng) use ($trim): void {
            $ks = $lowerToKey[strtolower($spa)] ?? null;
            $ke = $lowerToKey[strtolower($eng)] ?? null;

            $vs = $ks !== null ? $trim($cols[$ks] ?? '') : '';
            $ve = $ke !== null ? $trim($cols[$ke] ?? '') : '';

            if ($vs === '' && $ve !== '') {
                if ($ks !== null && $ke !== null) {
                    $cols[$ks] = $cols[$ke];
                } elseif ($ks === null && $ke !== null) {
                    // Row only had the English-ish column key: expose Spanish canonical name too
                    $cols[$spa] = $cols[$ke];
                }
            }

            if ($ks !== null) {
                $vs = $trim($cols[$ks] ?? '');
            } else {
                $vs = $trim($cols[$spa] ?? '');
            }

            if ($ke !== null) {
                $ve = $trim($cols[$ke] ?? '');
            } else {
                $ve = $trim($cols[$eng] ?? '');
            }

            if ($ve === '' && $vs !== '') {
                if ($ks !== null && $ke !== null) {
                    $cols[$ke] = $cols[$ks];
                } elseif ($ke === null && $ks !== null) {
                    $cols[$eng] = $cols[$ks];
                }
            }
        };

        $mirrorPair($cols, $lowerToKey, 'descripcion_de_trabajo', 'work_description');
        $mirrorPair($cols, $lowerToKey, 'nombre_del_cliente', 'client_name');
        $mirrorPair($cols, $lowerToKey, 'orden_de_trabajo', 'order_number');
        $mirrorPair($cols, $lowerToKey, 'fecha_de_entrega', 'delivery_date');
        $mirrorPair($cols, $lowerToKey, 'medidas_en_pulgadas', 'dimensions');

        return $cols;
    }

    /**
     * Safe snippet of orden / client / description columns for troubleshooting ( lengths, empty flags, previews ).
     *
     * @param   array<string,mixed>  $cols  Row after ENUM filter + mirror
     *
     * @return  array<string,array<string,mixed>>
     *
     * @since   3.115.7
     */
    private function snapshotOrdenKeyFieldsForLog(array $cols): array
    {
        $watch = ['orden_de_trabajo', 'order_number', 'nombre_del_cliente', 'client_name', 'descripcion_de_trabajo', 'work_description'];
        $out   = [];

        foreach ($cols as $k => $v) {
            if (!\is_string($k) || $k === '') {
                continue;
            }

            foreach ($watch as $t) {
                if (strcasecmp($k, $t) === 0) {
                    $s = $v !== null ? trim((string) $v) : '';
                    $out[$k] = [
                        'len' => \strlen($s),
                        'empty' => ($s === ''),
                        'preview' => \strlen($s) > 120 ? substr($s, 0, 120) . '…' : $s,
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Recursively replace NAN/INF so json_encode succeeds (PHP rejects them otherwise).
     *
     * @param   mixed  $data
     * @return  mixed
     *
     * @since   3.115.11
     */
    private function sanitizeScalarsForOtWizardLogJson($data)
    {
        if (\is_array($data)) {
            $out = [];
            foreach ($data as $k => $v) {
                $out[$k] = $this->sanitizeScalarsForOtWizardLogJson($v);
            }

            return $out;
        }

        if (\is_float($data) && (!\is_finite($data))) {
            return null;
        }

        return $data;
    }

    /**
     * Encode payload for Joomla log line; never return empty silently when json_encode fails.
     *
     * @since   3.115.11
     */
    private function encodeOtWizardPayloadForLog(array $payload): string
    {
        $clean = $this->sanitizeScalarsForOtWizardLogJson($payload);
        $flags = JSON_UNESCAPED_UNICODE;
        if (\defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= \constant('JSON_INVALID_UTF8_SUBSTITUTE');
        }
        if (\defined('JSON_PARTIAL_OUTPUT_ON_ERROR')) {
            $flags |= \constant('JSON_PARTIAL_OUTPUT_ON_ERROR');
        }

        $json = json_encode($clean, $flags);
        if ($json !== false) {
            return $json;
        }

        $fbFlags = JSON_UNESCAPED_UNICODE;
        if (\defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $fbFlags |= \constant('JSON_INVALID_UTF8_SUBSTITUTE');
        }

        $mini = json_encode([
            '_log_fallback'       => true,
            'json_last_error_msg' => function_exists('json_last_error_msg') ? json_last_error_msg() : (string) json_last_error(),
            'stage'               => isset($payload['stage']) ? (string) $payload['stage'] : '',
            'quotation_id'        => (int) ($payload['quotation_id'] ?? 0),
            'pre_cotizacion_id'   => (int) ($payload['pre_cotizacion_id'] ?? 0),
            'detail'              => isset($payload['detail']) ? substr((string) $payload['detail'], 0, 240) : '',
        ], $fbFlags);

        return $mini !== false ? $mini : '{"_log_fallback":true,"error":"json_encode_failed"}';
    }

    /**
     * Writes Joomla log entry (category com_ordenproduccion, ERROR). Enable global debug or set log priorities in
     * Joomla to capture; does not depend on component enable_debug.
     *
     * @param   string               $stage    build|no_columns|persist|missing_order_id_after_persist|…
     * @param   array<string,int>    $wizard    quotation_id, pre_cotizacion_id
     * @param   array<string,mixed>  $cols      Columns after ENUM filter + mirror (for field snapshot)
     * @param   string               $detail    Message shown or returned to client / raw table error / SQL line
     * @param   string|null          $rawCode   e.g. COM_* before translation; optional
     *
     * @since   3.115.7
     */
    private function logOtWizardCreateFailure(string $stage, array $wizard, array $cols, string $detail, ?string $rawCode = null): void
    {
        $keys = array_keys($cols);
        $payload = [
            'component' => 'com_ordenproduccion',
            'task' => 'createOrdenFromQuotation',
            'stage' => $stage,
            'quotation_id' => (int) ($wizard['quotation_id'] ?? 0),
            'pre_cotizacion_id' => (int) ($wizard['pre_cotizacion_id'] ?? 0),
            'user_id' => (int) Factory::getUser()->id,
            'detail' => \strlen($detail) > 900 ? substr($detail, 0, 900) . '…' : ($detail !== '' ? $detail : '(empty)'),
            'order_field_snapshot' => $this->snapshotOrdenKeyFieldsForLog($cols),
            'column_key_count' => \count($keys),
            'column_keys_sample' => \array_slice($keys, 0, 80),
        ];

        if ($rawCode !== null && $rawCode !== '') {
            $payload['error_code'] = \strlen($rawCode) > 200 ? substr($rawCode, 0, 200) . '…' : $rawCode;
        }

        Log::add(
            'OT wizard create failed: ' . $this->encodeOtWizardPayloadForLog($payload),
            Log::ERROR,
            'com_ordenproduccion'
        );
    }

    /**
     * Persist OT row using Administrator OrdenesTable (preferred; runs check/store). Falls back to insertObject.
     *
     * @param   \Joomla\Database\DatabaseDriver  $db
     * @param   array<string,mixed>               $cols
     * @param   array<string,int>                 $wizardContext  quotation_id, pre_cotizacion_id (for logs)
     * @return  array{success?:bool, order_id?:int, detail?:string}
     *
     * @since   3.115.6
     */
    private function persistNewOrdenRow($db, array $cols, array $wizardContext = []): array
    {
        $row = [];
        foreach ($cols as $k => $v) {
            if (!\is_string($k) || $k === '') {
                continue;
            }

            if ($v !== null && !is_scalar($v)) {
                continue;
            }

            $row[$k] = $v;
        }

        $tableFile = JPATH_ADMINISTRATOR . '/components/com_ordenproduccion/src/Table/OrdenesTable.php';

        if (\is_file($tableFile)) {
            try {
                require_once $tableFile;
                /** @var \Grimpsa\Component\Ordenproduccion\Administrator\Table\OrdenesTable $orderTable */
                $orderTable = new \Grimpsa\Component\Ordenproduccion\Administrator\Table\OrdenesTable($db);

                if (!$orderTable->bind($row)) {
                    $msg = $this->collectOrdenTableErrorMessage($orderTable);
                    $detail = $this->summarizeOrdenPersistenceError($msg);
                    $this->logOtWizardCreateFailure('orden_table_bind_failed', $wizardContext, $cols, $detail, $msg !== '' ? $msg : null);

                    return [
                        'success' => false,
                        'detail' => $detail,
                    ];
                }

                if (!$orderTable->check()) {
                    $raw = $this->collectOrdenTableErrorMessage($orderTable);
                    $detail = $this->summarizeOrdenPersistenceError($this->translateLikelyLangConstant($raw));
                    $this->logOtWizardCreateFailure('orden_table_check_failed', $wizardContext, $cols, $detail, $raw !== '' ? $raw : null);

                    return [
                        'success' => false,
                        'detail' => $detail,
                    ];
                }

                if (!$orderTable->store()) {
                    $msg = $this->collectOrdenTableErrorMessage($orderTable);
                    $detail = $this->summarizeOrdenPersistenceError($msg);
                    $this->logOtWizardCreateFailure('orden_table_store_failed', $wizardContext, $cols, $detail, $msg !== '' ? $msg : null);

                    return [
                        'success' => false,
                        'detail' => $detail,
                    ];
                }

                $newId = (int) $orderTable->id;
                if ($newId > 0) {
                    return ['success' => true, 'order_id' => $newId];
                }

                $msg = $this->collectOrdenTableErrorMessage($orderTable) ?: 'Store finished without primary key.';
                $detail = $this->summarizeOrdenPersistenceError($msg);
                $this->logOtWizardCreateFailure('orden_table_store_missing_id', $wizardContext, $cols, $detail, $msg);

                return [
                    'success' => false,
                    'detail' => $detail,
                ];
            } catch (\Throwable $e) {
                $this->logOtWizardCreateFailure(
                    'orden_table_exception',
                    $wizardContext,
                    $cols,
                    $e->getMessage(),
                    $e->getFile() . ':' . (string) $e->getLine()
                );
                Log::add('persistNewOrdenRow OrdenesTable: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
                // Fallback below
            }
        } else {
            $this->logOtWizardCreateFailure(
                'orden_table_file_missing',
                $wizardContext,
                $cols,
                'Administrator OrdenesTable.php not found at expected path.'
            );
        }

        try {
            $insertRow = (object) $row;
            if (!$db->insertObject('#__ordenproduccion_ordenes', $insertRow, 'id')) {
                $diag = method_exists($db, 'getErrorMsg') ? trim((string) $db->getErrorMsg()) : '';
                $detail = $this->summarizeOrdenPersistenceError($diag !== '' ? $diag : 'insertObject returned false.');
                $this->logOtWizardCreateFailure('insert_object_failed', $wizardContext, $cols, $detail, $diag !== '' ? $diag : null);

                return [
                    'success' => false,
                    'detail' => $detail,
                ];
            }

            $newId = (int) $db->insertid();

            if ($newId < 1 && isset($insertRow->id)) {
                $newId = (int) $insertRow->id;
            }

            if ($newId > 0) {
                return ['success' => true, 'order_id' => $newId];
            }

            $diag = method_exists($db, 'getErrorMsg') ? trim((string) $db->getErrorMsg()) : '';
            $detail = $this->summarizeOrdenPersistenceError($diag !== '' ? $diag : 'Missing insert id after insertObject.');
            $this->logOtWizardCreateFailure('insert_object_missing_pk', $wizardContext, $cols, $detail, $diag !== '' ? $diag : null);

            return [
                'success' => false,
                'detail' => $detail,
            ];
        } catch (\Throwable $e) {
            $detail = $this->summarizeOrdenPersistenceError($e->getMessage());
            $this->logOtWizardCreateFailure('insert_object_exception', $wizardContext, $cols, $detail, $e->getFile() . ':' . (string) $e->getLine());

            return ['success' => false, 'detail' => $detail];
        }
    }

    /**
     * Collect last Table error message.
     *
     * @param   object  $table  Joomla Table
     *
     * @since   3.115.6
     */
    private function collectOrdenTableErrorMessage($table): string
    {
        if (!\is_object($table)) {
            return '';
        }

        if (method_exists($table, 'getError')) {
            $msg = trim((string) call_user_func([$table, 'getError']));
            if ($msg !== '') {
                return $msg;
            }
        }

        if (method_exists($table, 'getErrors')) {
            $errs = call_user_func([$table, 'getErrors']);
            if (\is_array($errs) && $errs !== []) {
                return trim(implode(' ', array_map('strval', $errs)));
            }
        }

        return '';
    }

    /**
     * If error is a language constant, translate for JSON output.
     *
     * @since   3.115.6
     */
    private function translateLikelyLangConstant(string $msg): string
    {
        $msg = trim($msg);
        if ($msg !== '' && strpos($msg, 'COM_ORDENPRODUCCION_') === 0) {
            Factory::getApplication()->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
            $t = Text::_($msg);

            return ($t !== $msg) ? $t : $msg;
        }

        return $msg;
    }

    /**
     * Keep detail helpful but bounded for browser alerts.
     *
     * @since   3.115.6
     */
    private function summarizeOrdenPersistenceError(string $msg): string
    {
        $msg = trim(str_replace(["\r\n", "\r"], "\n", $msg));

        if (\strlen($msg) > 600) {
            return substr($msg, 0, 600) . '…';
        }

        return $msg;
    }

    /**
     * User-visible error when Step 3 internal OT insert fails (JSON). Loads com_ordenproduccion and
     * falls back to ES/EN when the active tag has no translation (e.g. es-GT without override).
     *
     * @return  string
     *
     * @since   3.115.5
     */
    private function messageOtCreateInternalFailed(): string
    {
        $app = Factory::getApplication();
        $app->getLanguage()->load('com_ordenproduccion', JPATH_SITE);
        $msg = Text::_('COM_ORDENPRODUCCION_OT_CREATE_INTERNAL_FAILED');
        if ($msg !== 'COM_ORDENPRODUCCION_OT_CREATE_INTERNAL_FAILED') {
            return $msg;
        }

        $tag = strtolower($app->getLanguage()->getTag());
        if (strpos($tag, 'en') === 0) {
            return 'Could not create the internal work order. If this persists, turn on component debug (Options) for details.';
        }

        return 'No se pudo crear la orden de trabajo interna. Si persiste, active depuración en opciones del componente para más detalle.';
    }

    /**
     * Remove values that would violate MySQL ENUM definitions (e.g. UI stores
     * "Entrega a domicilio" while some schemas use enum('completa','parcial') for shipping_type).
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   array<string,mixed>                  $cols
     * @return  array<string,mixed>
     *
     * @since   3.115.4
     */
    private function filterOrdeneInsertRowForMysqlEnums($db, array $cols): array
    {
        if ($cols === []) {
            return [];
        }

        try {
            $db->setQuery('SELECT DATABASE()');
            $schema = (string) $db->loadResult();
        } catch (\Throwable $e) {
            return $cols;
        }

        if ($schema === '') {
            return $cols;
        }

        $table = $db->replacePrefix('#__ordenproduccion_ordenes');
        $want  = [];
        foreach (array_keys($cols) as $k) {
            if (\is_string($k) && $k !== '') {
                $want[] = $k;
            }
        }

        if ($want === []) {
            return $cols;
        }

        $quoted = [];
        foreach ($want as $w) {
            $quoted[] = $db->quote($w);
        }

        $q = $db->getQuery(true)
            ->select([
                $db->quoteName('COLUMN_NAME'),
                $db->quoteName('COLUMN_TYPE'),
                $db->quoteName('DATA_TYPE'),
            ])
            ->from($db->quoteName('information_schema') . '.' . $db->quoteName('COLUMNS'))
            ->where($db->quoteName('TABLE_SCHEMA') . ' = ' . $db->quote($schema))
            ->where($db->quoteName('TABLE_NAME') . ' = ' . $db->quote($table))
            ->where($db->quoteName('COLUMN_NAME') . ' IN (' . implode(',', $quoted) . ')');

        try {
            $db->setQuery($q);
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return $cols;
        }

        $metaByLower = [];
        foreach ($rows as $r) {
            $name = (string) $this->getObjectProperty($r, 'COLUMN_NAME');
            if ($name === '') {
                continue;
            }
            $metaByLower[strtolower($name)] = [
                'data_type'   => strtolower((string) $this->getObjectProperty($r, 'DATA_TYPE')),
                'column_type' => (string) $this->getObjectProperty($r, 'COLUMN_TYPE'),
            ];
        }

        foreach ($cols as $name => $value) {
            if (!\is_string($name) || $name === '') {
                continue;
            }

            $meta = $metaByLower[strtolower($name)] ?? null;
            if ($meta === null || $meta['data_type'] !== 'enum') {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $allowed = $this->parseMysqlEnumLiteralsFromColumnType($meta['column_type']);
            if ($allowed === []) {
                continue;
            }

            $needle = trim((string) $value);
            $matched = false;
            foreach ($allowed as $lit) {
                if ($needle === trim((string) $lit)) {
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                unset($cols[$name]);
            }
        }

        return $cols;
    }

    /**
     * Extract enum literals from a COLUMN_TYPE definition.
     *
     * @return  string[]
     *
     * @since   3.115.4
     */
    private function parseMysqlEnumLiteralsFromColumnType(string $columnType): array
    {
        $columnType = str_replace(["\r", "\n"], '', trim($columnType));
        if ($columnType === '' || stripos($columnType, 'enum(') !== 0) {
            return [];
        }

        if (!preg_match('/^enum\\((.*)\\)$/i', $columnType, $m)) {
            return [];
        }

        $inner = $m[1];
        if ($inner === '') {
            return [];
        }

        preg_match_all("/'((?:\\\\'|''|[^'])*)'/", $inner, $mm);
        $out = [];
        foreach (($mm[1] ?? []) as $frag) {
            $out[] = str_replace("''", "'", (string) $frag);
        }

        return $out;
    }

    /**
     * Decide whether to redirect back to cotización or to the OT edit page.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   int                                 $quotationId
     * @param   int                                 $orderId
     *
     * @return  string
     *
     * @since   3.115.3
     */
    private function buildOrdenWizardRedirectUrl($db, int $quotationId, int $orderId): string
    {
        $quotationId = (int) $quotationId;
        $orderId = (int) $orderId;

        $cotUrl = Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false);
        $otUrl  = Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&id=' . $orderId, false);

        // If schema doesn't support linkage, just go to the OT.
        $ordenesCols = $db->getTableColumns('#__ordenproduccion_ordenes', false);
        $ordenesCols = is_array($ordenesCols) ? array_change_key_case($ordenesCols, CASE_LOWER) : [];
        if (!isset($ordenesCols['pre_cotizacion_id'])) {
            return $otUrl;
        }

        try {
            $q = $db->getQuery(true)
                ->select($db->quoteName('qi.pre_cotizacion_id'))
                ->from($db->quoteName('#__ordenproduccion_quotation_items', 'qi'))
                ->leftJoin(
                    $db->quoteName('#__ordenproduccion_ordenes', 'o')
                    . ' ON ' . $db->quoteName('o.pre_cotizacion_id') . ' = ' . $db->quoteName('qi.pre_cotizacion_id')
                    . ' AND ' . $db->quoteName('o.state') . ' = 1'
                )
                ->where($db->quoteName('qi.quotation_id') . ' = ' . $quotationId)
                ->where($db->quoteName('qi.pre_cotizacion_id') . ' IS NOT NULL')
                ->where($db->quoteName('o.id') . ' IS NULL')
                ->group($db->quoteName('qi.pre_cotizacion_id'));
            $db->setQuery($q);
            $pending = $db->loadColumn() ?: [];
        } catch (\Throwable $e) {
            return $otUrl;
        }

        // If there are still pending PREs without OT, return to quotation.
        if (!empty($pending)) {
            return $cotUrl;
        }

        return $otUrl;
    }

    /**
     * Notify Solicitud de Orden URL (webhook) with next order number and redirect to orden form.
     * Called when user clicks "Generar Orden de Trabajo" after finishing confirmar steps.
     * POST: pre_cotizacion_id, quotation_id.
     *
     * @return  void
     * @since   3.92.0
     */
    public function notifySolicitudOrden()
    {
        $app = Factory::getApplication();
        $user = Factory::getUser();
        if ($user->guest) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $app->redirect(Route::_('index.php?option=com_users&view=login', false));
            return;
        }
        if (!Session::checkToken('post') && !Session::checkToken('request')) {
            $app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        $preCotizacionId = (int) $app->input->post->get('pre_cotizacion_id', (int) $app->input->get('pre_cotizacion_id', 0));
        $quotationId = (int) $app->input->post->get('quotation_id', (int) $app->input->get('quotation_id', 0));
        if ($preCotizacionId < 1 || $quotationId < 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_QUOTATION_NOT_FOUND'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }
        $db = Factory::getDbo();

        $qCols = ['id', 'created_by', 'client_name', 'client_id', 'client_nit', 'signed_document_path', 'instrucciones_facturacion'];
        $qTableCols = $db->getTableColumns($db->replacePrefix('#__ordenproduccion_quotations'), false);
        $qTableCols = is_array($qTableCols) ? array_change_key_case($qTableCols, CASE_LOWER) : [];
        if (!isset($qTableCols['signed_document_path'])) {
            $qCols = array_values(array_diff($qCols, ['signed_document_path']));
        }
        if (!isset($qTableCols['instrucciones_facturacion'])) {
            $qCols = array_values(array_diff($qCols, ['instrucciones_facturacion']));
        }
        if (isset($qTableCols['cotizacion_confirmada'])) {
            $qCols[] = 'cotizacion_confirmada';
        }
        $selectList = implode(', ', array_map(function ($c) use ($db) {
            return $db->quoteName($c);
        }, $qCols));
        $qQuery = $db->getQuery(true)
            ->select($selectList)
            ->from($db->quoteName('#__ordenproduccion_quotations'))
            ->where($db->quoteName('id') . ' = ' . $quotationId)
            ->where($db->quoteName('state') . ' = 1');
        $db->setQuery($qQuery);
        $quotation = $db->loadObject();
        if (!$quotation) {
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            return;
        }
        if (!AccessHelper::userCanAccessQuotationRow($quotation)) {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizaciones', false));
            $app->close();
        }

        if (isset($qTableCols['cotizacion_confirmada']) && (int) $this->getObjectProperty($quotation, 'cotizacion_confirmada') !== 1) {
            $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_GENERAR_ORDEN_REQUIRES_CONFIRM'), 'warning');
            $app->redirect(Route::_('index.php?option=com_ordenproduccion&view=cotizacion&id=' . $quotationId, false));
            return;
        }

        $clientName = trim((string) ($quotation->client_name ?? ''));
        $clientId = isset($quotation->client_id) ? trim((string) $quotation->client_id) : '';
        $nit = isset($quotation->client_nit) ? trim((string) $quotation->client_nit) : '';
        $signedDocumentPath = $this->getObjectProperty($quotation, 'signed_document_path');
        $instruccionesFacturacion = $this->getObjectProperty($quotation, 'instrucciones_facturacion');

        $lineDetallesJson = $this->getLineDetallesJsonForPreCotizacion($db, $preCotizacionId);

        $confirmationId = $this->savePreCotizacionConfirmation(
            $db,
            $quotationId,
            $preCotizacionId,
            $signedDocumentPath,
            $instruccionesFacturacion,
            $lineDetallesJson,
            $user->id
        );

        $pcCols = ['id', 'number', 'descripcion'];
        $pcTableCols = $db->getTableColumns($db->replacePrefix('#__ordenproduccion_pre_cotizacion'), false);
        $pcTableCols = is_array($pcTableCols) ? array_change_key_case($pcTableCols, CASE_LOWER) : [];
        if (isset($pcTableCols['total_final'])) {
            $pcCols[] = 'total_final';
        }
        if (isset($pcTableCols['total']) && !in_array('total_final', $pcCols)) {
            $pcCols[] = 'total';
        }
        $pcQuery = $db->getQuery(true)
            ->select($db->quoteName($pcCols))
            ->from($db->quoteName('#__ordenproduccion_pre_cotizacion'))
            ->where($db->quoteName('id') . ' = ' . $preCotizacionId);
        $db->setQuery($pcQuery);
        $precotizacion = $db->loadObject();
        $precotizacionNumber = $precotizacion ? (trim((string) ($precotizacion->number ?? '')) ?: 'PRE-' . $preCotizacionId) : 'PRE-' . $preCotizacionId;
        $precotizacionDescription = $precotizacion ? trim((string) ($precotizacion->descripcion ?? '')) : '';
        $precotizacionTotal = '';
        if ($precotizacion) {
            if (isset($precotizacion->total_final) && $precotizacion->total_final !== null && $precotizacion->total_final !== '') {
                $precotizacionTotal = (string) $precotizacion->total_final;
            } elseif (isset($precotizacion->total) && $precotizacion->total !== null && $precotizacion->total !== '') {
                $precotizacionTotal = (string) $precotizacion->total;
            }
        }

        $solicitudUrl = $this->getSolicitudOrdenUrlForNotify();

        if ($solicitudUrl !== '') {
            try {
                $payload = [
                    'pre_cotizacion_confirmation_id' => $confirmationId,
                    'pre_cotizacion_id'       => $preCotizacionId,
                    'quotation_id'            => $quotationId,
                    'client_name'             => $clientName,
                    'client_id'               => $clientId,
                    'nit'                     => $nit,
                    'precotizacion_number'   => $precotizacionNumber,
                    'precotizacion_description' => $precotizacionDescription,
                    'precotizacion_total'     => $precotizacionTotal,
                ];
                $http = \Joomla\CMS\Http\HttpFactory::getHttp();
                $http->post($solicitudUrl, json_encode($payload), ['Content-Type' => 'application/json']);
            } catch (\Exception $e) {
                $app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_SOLICITUD_ORDEN_NOTIFY_ERROR'), 'warning');
            }
            // Redirect the user's browser to the configured Order Request URL (with params). Pass quotation_id (cotización id) only; receiver can build URL if needed.
            $redirectUri = new Uri($solicitudUrl);
            $redirectUri->setVar('pre_cotizacion_confirmation_id', (string) $confirmationId);
            $redirectUri->setVar('precotizacion_number', $precotizacionNumber);
            $redirectUri->setVar('quotation_id', (string) $quotationId);
            $redirectUri->setVar('client_name', $clientName);
            $redirectUri->setVar('client_id', $clientId);
            $redirectUri->setVar('nit', $nit);
            $redirectUri->setVar('precotizacion_description', $precotizacionDescription);
            $redirectUri->setVar('precotizacion_total', $precotizacionTotal);
            $app->redirect((string) $redirectUri);
            return;
        }

        // No Order Request URL configured: fallback to internal order form
        $ordenUrl = Route::_('index.php?option=com_ordenproduccion&view=orden&layout=edit&pre_cotizacion_id=' . $preCotizacionId . '&quotation_id=' . $quotationId, false);
        $app->redirect($ordenUrl);
    }

    /**
     * Build JSON snapshot of line detalles (Step 3: instrucciones para orden de trabajo) for a pre_cotizacion.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   int                                  $preCotizacionId
     * @return  string  JSON array of { pre_cotizacion_line_id, concepto_key, concepto_label, detalle }
     * @since   3.93.1
     */
    private function getLineDetallesJsonForPreCotizacion($db, $preCotizacionId)
    {
        $preCotizacionId = (int) $preCotizacionId;
        if ($preCotizacionId < 1) {
            return '[]';
        }
        try {
            $db->setQuery($db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('pre_cotizacion_id') . ' = ' . $preCotizacionId));
            $lineIds = $db->loadColumn() ?: [];
        } catch (\Throwable $e) {
            return '[]';
        }
        if (empty($lineIds)) {
            return '[]';
        }
        $lineIds = array_map('intval', $lineIds);
        $selectDetalles = implode(', ', array_map(function ($c) use ($db) {
            return $db->quoteName($c);
        }, ['pre_cotizacion_line_id', 'concepto_key', 'concepto_label', 'detalle']));
        try {
            $query = $db->getQuery(true)
                ->select($selectDetalles)
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line_detalles'))
                ->whereIn($db->quoteName('pre_cotizacion_line_id'), $lineIds);
            $db->setQuery($query);
            $rows = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return '[]';
        }
        $out = [];
        foreach ($rows as $r) {
            $lineIdVal = $this->getObjectProperty($r, 'pre_cotizacion_line_id');
            $out[] = [
                'pre_cotizacion_line_id' => $lineIdVal !== '' ? (int) $lineIdVal : 0,
                'concepto_key'           => $this->getObjectProperty($r, 'concepto_key'),
                'concepto_label'         => $this->getObjectProperty($r, 'concepto_label'),
                'detalle'                => $this->getObjectProperty($r, 'detalle'),
            ];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Save a pre-cotización confirmation row (Confirmar cotización steps snapshot) and return its id.
     * Used when "Generar Orden de Trabajo" is clicked; the id is sent as pre_cotizacion_confirmation_id.
     *
     * @param   \Joomla\Database\DatabaseInterface  $db
     * @param   int                                  $quotationId
     * @param   int                                  $preCotizacionId
     * @param   string                               $signedDocumentPath   Step 1
     * @param   string                               $instruccionesFacturacion  Step 2
     * @param   string                               $lineDetallesJson     Step 3: JSON of line detalles
     * @param   int                                  $createdBy
     * @return  int  New confirmation id, or 0 if table missing / insert failed
     * @since   3.93.0
     */
    private function savePreCotizacionConfirmation($db, $quotationId, $preCotizacionId, $signedDocumentPath, $instruccionesFacturacion, $lineDetallesJson, $createdBy)
    {
        $tableName = $db->replacePrefix('#__ordenproduccion_pre_cotizacion_confirmation');
        try {
            $tables = $db->getTableList();
            if (!in_array($tableName, $tables, true)) {
                return 0;
            }
        } catch (\Throwable $e) {
            return 0;
        }
        $cols = $db->getTableColumns($tableName, false);
        $cols = is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        $hasLineDetallesJson = isset($cols['line_detalles_json']);

        $now = Factory::getDate()->toSql();
        $columns = [
            $db->quoteName('quotation_id'),
            $db->quoteName('pre_cotizacion_id'),
            $db->quoteName('signed_document_path'),
            $db->quoteName('instrucciones_facturacion'),
            $db->quoteName('created'),
            $db->quoteName('created_by'),
        ];
        $values = [
            (int) $quotationId,
            (int) $preCotizacionId,
            $db->quote($signedDocumentPath),
            $db->quote($instruccionesFacturacion),
            $db->quote($now),
            (int) $createdBy,
        ];
        if ($hasLineDetallesJson) {
            $columns[] = $db->quoteName('line_detalles_json');
            $values[] = $db->quote(is_string($lineDetallesJson) ? $lineDetallesJson : '[]');
        }
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ordenproduccion_pre_cotizacion_confirmation'))
            ->columns($columns)
            ->values(implode(',', $values));
        $db->setQuery($query);
        try {
            $db->execute();
            return (int) $db->insertid();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get object property value case-insensitively (DB drivers may return different column case).
     *
     * @param   object  $obj
     * @param   string  $key
     * @return  string
     * @since   3.93.1
     */
    private function getObjectProperty($obj, $key)
    {
        if (!is_object($obj)) {
            return '';
        }
        $keyLower = strtolower($key);
        foreach (array_keys((array) $obj) as $k) {
            if (strtolower((string) $k) === $keyLower) {
                $v = $obj->$k;
                return $v !== null && $v !== '' ? trim((string) $v) : '';
            }
        }
        return '';
    }

    /**
     * Get Solicitud de Orden URL from config (model or direct DB) for use when sending the webhook.
     *
     * @return  string
     * @since   3.92.0
     */
    private function getSolicitudOrdenUrlForNotify()
    {
        $app = Factory::getApplication();
        try {
            $component = $app->bootComponent('com_ordenproduccion');
            $model = $component->getMVCFactory()->createModel('Administracion', 'Site', ['ignore_request' => true]);
            if (method_exists($model, 'getSolicitudOrdenUrl')) {
                $url = $model->getSolicitudOrdenUrl();
                if ($url !== '') {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // Fall through to direct DB read
        }
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->quoteName('setting_value'))
            ->from($db->quoteName('#__ordenproduccion_config'))
            ->where($db->quoteName('setting_key') . ' = ' . $db->quote('solicitud_orden_url'));
        $db->setQuery($query);
        $v = $db->loadResult();
        return $v !== null ? trim((string) $v) : '';
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

        // Top brand bar (Cyan 2925C | Yellow 803C | Magenta 213C)
        $cmyBarH = 4;
        $thirdW  = $pdf->GetPageWidth() / 3;
        $pdf->SetY(0);
        $pdf->SetX(0);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($pdf, $thirdW, $cmyBarH, 1);

        // FPDF uses Latin-1 (ISO-8859-1); shared helper matches mock FEL invoice PDF encoding.
        $fixSpanishChars = static function ($text) {
            if ($text === null || $text === '') {
                return $text;
            }
            return CotizacionPdfHelper::encodeTextForFpdf((string) $text);
        };

        $encabezadoBlocks = CotizacionFpdfBlocksHelper::parseHtmlBlocks($encabezadoHtml, $fixSpanishChars);
        $terminosBlocks   = CotizacionFpdfBlocksHelper::parseHtmlBlocks($terminosHtml, $fixSpanishChars);
        $pieBlocks        = CotizacionFpdfBlocksHelper::parseHtmlBlocks($pieHtml, $fixSpanishChars);

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
            $resolvedLogo = CotizacionFpdfBlocksHelper::resolveImagePath($logoPath);
            if ($resolvedLogo) {
                $pdf->Image($resolvedLogo, $logoX, $logoY, $logoWidth);
            }
        }

        // Encabezado: position (X,Y mm) then render block-by-block
        if (!empty($encabezadoBlocks)) {
            $pdf->SetXY($encX, $encY);
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $encabezadoBlocks, 6, 11, $pageW, $marginR, 15, 4, $fixSpanishChars);
            $pdf->SetFont('Arial', '', 10);
        }

        $pdf->Ln(4);

        // Table: Codigo (pre-cotizacion number), Cantidad, Descripción, Precio unit., Subtotal
        if ($tableY > 0 || $tableX > 0) {
            $pdf->SetXY($tableX > 0 ? $tableX : 15, $tableY > 0 ? $tableY : $pdf->GetY());
        }
        $colCodigo = 16;
        $colCant   = 16;
        $colDesc   = 102;
        $colUnit   = 28;
        $colSub    = 28;
        $lineH     = 6;

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($colCodigo, $lineH, 'Codigo', 1, 0, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCant, $lineH, 'Cant.', 1, 0, 'L');
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

            // Draw Codigo (small font) and Cantidad with full row height
            $pdf->SetXY($rowX, $rowY);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell($colCodigo, $rowH, $codigo, 1, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell($colCant, $rowH, (string) $qty, 1, 0, 'C');

            // Skip description column (already drawn), draw price and subtotal
            $pdf->SetXY($rowX + $colCodigo + $colCant + $colDesc, $rowY);
            $pdf->Cell($colUnit, $rowH, $currency . ' ' . number_format($unit, 4), 1, 0, 'R');
            $pdf->Cell($colSub, $rowH, $currency . ' ' . number_format($lineTotal, 2), 1, 1, 'R');

            $rowBottomY = $rowY + $rowH;
            $yAfterIm   = CotizacionPdfHelper::renderQuotationLineItemImagesTableRow(
                $pdf,
                $rowX,
                $rowBottomY,
                $colCodigo,
                $colCant,
                $colDesc,
                $colUnit,
                $colSub,
                isset($item->line_images_json) ? (string) $item->line_images_json : null,
                $lineH,
                false
            );
            $pdf->SetXY($rowX, $yAfterIm);
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
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $terminosBlocks, 5, 9, $pageW, $marginR, 15, 3, $fixSpanishChars, $termW);
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
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $pieBlocks, 5, 9, $pageW, $marginR, 15, 3, $fixSpanishChars);
        }

        // Bottom brand bar
        $pdf->Ln(4);
        $curY = $pdf->GetY();
        $pdf->SetY($curY);
        $pdf->SetX(0);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($pdf, $thirdW, $cmyBarH, 1);

        $filename = 'cotizacion-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $numeroCotizacion) . '.pdf';
        $dest = $forceDownload ? 'D' : 'I';
        $pdf->Output($dest, $filename);
        exit;
    }

    /**
     * Generate cotización PDF format version 2: print-style with CMY top/bottom bars,
     * section headers in a compatible colour, and light table background.
     *
     * @param   object   $quotation       Quotation row
     * @param   array    $items           Quotation items
     * @param   string   $encabezadoHtml  Header content (HTML)
     * @param   string   $terminosHtml    Terms content (HTML)
     * @param   string   $pieHtml         Footer content (HTML)
     * @param   string   $numeroCotizacion Quotation number
     * @param   string   $fechaFormatted  Formatted date
     * @param   string   $currency        Currency symbol
     * @param   float    $totalAmount    Total amount
     * @param   bool     $forceDownload   True to force download (D), false for inline (I)
     * @param   array    $pdfSettings     Optional. Same keys as generateCotizacionPdf.
     * @return  void
     */
    private function generateCotizacionPdfV2($quotation, $items, $encabezadoHtml, $terminosHtml, $pieHtml, $numeroCotizacion, $fechaFormatted, $currency, $totalAmount, $forceDownload = false, array $pdfSettings = [])
    {
        require_once JPATH_ROOT . '/fpdf/fpdf.php';

        $pdf = new \FPDF('P', 'mm', [215.9, 279.4]);
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        $fixSpanishChars = static function ($text) {
            if ($text === null || $text === '') {
                return $text;
            }
            return CotizacionPdfHelper::encodeTextForFpdf((string) $text);
        };

        $encabezadoBlocks = CotizacionFpdfBlocksHelper::parseHtmlBlocks($encabezadoHtml, $fixSpanishChars);
        $terminosBlocks   = CotizacionFpdfBlocksHelper::parseHtmlBlocks($terminosHtml, $fixSpanishChars);
        $pieBlocks        = CotizacionFpdfBlocksHelper::parseHtmlBlocks($pieHtml, $fixSpanishChars);

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

        $pageW   = $pdf->GetPageWidth();
        $marginR = 15;
        $contentW = $pageW - 15 - $marginR;

        // ── Top brand bar (Cyan 2925C | Yellow 803C | Magenta 213C) ──
        $cmyBarH = 4;
        $thirdW  = $pageW / 3;
        $pdf->SetY(0);
        $pdf->SetX(0);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($pdf, $thirdW, $cmyBarH, 1);

        // Start content below top bar and below top margin
        $pdf->SetY($cmyBarH + 15);

        // Section / table accents: brand Magenta 213C (#E6007E)
        $sectionR = 230;
        $sectionG = 0;
        $sectionB = 126;
        $tableHeaderR = 230;
        $tableHeaderG = 0;
        $tableHeaderB = 126;
        $tableLightR = 255;
        $tableLightG = 245;
        $tableLightB = 250;

        // Logo
        if (!empty($logoPath)) {
            $resolvedLogo = CotizacionFpdfBlocksHelper::resolveImagePath($logoPath);
            if ($resolvedLogo) {
                $pdf->Image($resolvedLogo, $logoX, $pdf->GetY(), $logoWidth);
            }
        }

        // ── Section: Client / Quote details (encabezado) ──
        $sectionH = 7;
        $pdf->SetFillColor($sectionR, $sectionG, $sectionB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($contentW, $sectionH, $fixSpanishChars('Datos del cliente / Cotizacion'), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        if (!empty($encabezadoBlocks)) {
            $pdf->SetXY($encX, $pdf->GetY());
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $encabezadoBlocks, 6, 10, $pageW, $marginR, 15, 4, $fixSpanishChars);
        }
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(4);

        // ── Section: Pricing (table) ──
        $pdf->SetFillColor($sectionR, $sectionG, $sectionB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($contentW, $sectionH, $fixSpanishChars('Precios'), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        $colCodigo = 16;
        $colCant   = 16;
        $colDesc   = 102;
        $colUnit   = 28;
        $colSub    = 28;
        $lineH     = 6;

        if ($tableY > 0 || $tableX > 0) {
            $pdf->SetXY($tableX > 0 ? $tableX : 15, $tableY > 0 ? $tableY : $pdf->GetY());
        }

        // Table header row (lighter compatible colour)
        $pdf->SetFillColor($tableHeaderR, $tableHeaderG, $tableHeaderB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell($colCodigo, $lineH, 'Codigo', 1, 0, 'L', true);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCant, $lineH, 'Cant.', 1, 0, 'L', true);
        $pdf->Cell($colDesc, $lineH, 'Descripcion', 1, 0, 'L', true);
        $pdf->Cell($colUnit, $lineH, 'Precio unit.', 1, 0, 'R', true);
        $pdf->Cell($colSub, $lineH, 'Subtotal', 1, 1, 'R', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont('Arial', '', 9);

        $rowIndex = 0;
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
            $fill = ($rowIndex % 2 === 1);
            if ($fill) {
                $pdf->SetFillColor($tableLightR, $tableLightG, $tableLightB);
            }

            $pdf->SetXY($rowX + $colCodigo + $colCant, $rowY);
            $pdf->MultiCell($colDesc, $lineH, $desc, 1, 'L', $fill);
            $newY   = $pdf->GetY();
            $rowH   = max($lineH, $newY - $rowY);

            $pdf->SetXY($rowX, $rowY);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell($colCodigo, $rowH, $codigo, 1, 0, 'L', $fill);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell($colCant, $rowH, (string) $qty, 1, 0, 'C', $fill);
            $pdf->SetXY($rowX + $colCodigo + $colCant + $colDesc, $rowY);
            $pdf->Cell($colUnit, $rowH, $currency . ' ' . number_format($unit, 4), 1, 0, 'R', $fill);
            $pdf->Cell($colSub, $rowH, $currency . ' ' . number_format($lineTotal, 2), 1, 1, 'R', $fill);
            if ($fill) {
                $pdf->SetFillColor($tableLightR, $tableLightG, $tableLightB);
            }
            $rowBottomY = $rowY + $rowH;
            $yAfterIm   = CotizacionPdfHelper::renderQuotationLineItemImagesTableRow(
                $pdf,
                $rowX,
                $rowBottomY,
                $colCodigo,
                $colCant,
                $colDesc,
                $colUnit,
                $colSub,
                isset($item->line_images_json) ? (string) $item->line_images_json : null,
                $lineH,
                $fill
            );
            if ($fill) {
                $pdf->SetFillColor(255, 255, 255);
            }
            $pdf->SetXY($rowX, $yAfterIm);
            $rowIndex++;
        }

        // Total row (no fill)
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($colCodigo + $colCant + $colDesc + $colUnit, $lineH, 'Total:', 1, 0, 'R');
        $pdf->Cell($colSub, $lineH, $currency . ' ' . number_format($totalAmount, 2), 1, 1, 'R');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(6);

        // ── Section: Términos y condiciones + Aceptación ──
        $pdf->SetFillColor($sectionR, $sectionG, $sectionB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($contentW, $sectionH, $fixSpanishChars('Terminos y Condiciones'), 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);

        $termW        = $contentW * 0.6;
        $aceptacionW  = $contentW * 0.4;
        $aceptacionLineH = 9;
        $termStartX   = ($termX > 0 ? $termX : 15);
        $termStartY   = ($termY > 0 ? $termY : $pdf->GetY());
        $pdf->SetXY($termStartX, $termStartY);
        if (!empty($terminosBlocks)) {
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $terminosBlocks, 5, 9, $pageW, $marginR, 15, 3, $fixSpanishChars, $termW);
        }
        $leftEndY = $pdf->GetY();
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
            CotizacionFpdfBlocksHelper::renderPdfBlocks($pdf, $pieBlocks, 5, 9, $pageW, $marginR, 15, 3, $fixSpanishChars);
        }

        // ── Bottom brand bar ──
        $curY = $pdf->GetY();
        $pdf->SetY($curY + 4);
        $pdf->SetX(0);
        CotizacionFpdfBlocksHelper::drawCmyBrandBar($pdf, $thirdW, $cmyBarH, 1);

        $filename = 'cotizacion-' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', $numeroCotizacion) . '.pdf';
        $dest = $forceDownload ? 'D' : 'I';
        $pdf->Output($dest, $filename);
        exit;
    }

    /**
     * Billing instructions from POST for Confirmar flows. Returns null when no linked pre-cot has facturar
     * (caller must not overwrite quotation.instrucciones_facturacion). When facturar_cotizacion_exacta is 1,
     * returns empty string (custom instructions not used).
     *
     * @param   int  $quotationId
     *
     * @return  string|null
     *
     * @since   3.101.44
     */
    private function collectInstruccionesFacturacionFromPost(int $quotationId): ?string
    {
        $app = Factory::getApplication();
        $precotModel = $app->bootComponent('com_ordenproduccion')->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);
        if (!$precotModel) {
            return null;
        }
        $facturarList = $precotModel->getFacturarPreCotizacionesForQuotation($quotationId);
        if ($facturarList === []) {
            return null;
        }
        $facturarCotizacionExacta = (int) $app->input->post->get('facturar_cotizacion_exacta', 1);
        if ($facturarCotizacionExacta !== 0) {
            $facturarCotizacionExacta = 1;
        }
        if ($facturarCotizacionExacta === 1) {
            return '';
        }
        if (\count($facturarList) === 1) {
            return $app->input->post->getString('instrucciones_facturacion', '');
        }
        $arr = $app->input->post->get('instrucciones_facturacion', [], 'array');
        $parts = [];
        foreach ($facturarList as $f) {
            $id = (int) $f['id'];
            $text = '';
            if (isset($arr[$id])) {
                $text = trim((string) $arr[$id]);
            } elseif (isset($arr[(string) $id])) {
                $text = trim((string) $arr[(string) $id]);
            }
            $parts[] = '[' . $f['number'] . ']' . "\n\n" . $text;
        }

        return implode("\n\n---\n\n", $parts);
    }
}
