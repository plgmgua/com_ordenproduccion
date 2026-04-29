<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Builds orden de trabajo view sections from pre-cotización lines (solo pliego + otros elementos; líneas tipo envío se omiten — ver Información de Envío).
 *
 * @since  3.115.18
 */
class OrdenPrecotSectionsHelper
{
    /**
     * One structured card per línea PRE (metadata + instrucciones por concepto; sin precios).
     *
     * @param   int  $preCotizacionId  Pre-cotización id
     *
     * @return  array<int, array<string, mixed>>
     *
     * @since   3.115.18
     */
    public static function buildSections(int $preCotizacionId): array
    {
        if ($preCotizacionId < 1) {
            return [];
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        try {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line'))
                ->where($db->quoteName('pre_cotizacion_id') . ' = ' . (int) $preCotizacionId)
                ->order($db->quoteName('ordering') . ' ASC, id ASC');

            $db->setQuery($query);
            $lines = $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }

        if ($lines === []) {
            return [];
        }

        $lineIds = [];
        foreach ($lines as $line) {
            $lineIds[] = (int) ($line->id ?? 0);
        }
        $lineIds = array_values(array_filter(array_map('intval', $lineIds), static fn($id) => $id > 0));

        $productosModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
            ->getMVCFactory()->createModel('Productos', 'Site', ['ignore_request' => true]);

        $papersById = [];
        $sizesById  = [];
        $procById   = [];

        if ($productosModel && $productosModel->tablesExist()) {
            foreach ($productosModel->getPaperTypes() as $p) {
                $papersById[(int) $p->id] = $p;
            }
            foreach ($productosModel->getSizes() as $s) {
                $sizesById[(int) $s->id] = $s;
            }
            foreach ($productosModel->getProcesses() as $pr) {
                $procById[(int) $pr->id] = $pr;
            }
        }

        $lamsById = [];
        if ($productosModel && $productosModel->tablesExist()) {
            foreach ($productosModel->getLaminationTypes() as $lam) {
                $lamsById[(int) $lam->id] = $lam;
            }
        }

        $detallesByLine = [];
        if ($lineIds !== []) {
            try {
                $tq = $db->getQuery(true)
                    ->select([$db->quoteName('pre_cotizacion_line_id'), $db->quoteName('concepto_key'), $db->quoteName('concepto_label'), $db->quoteName('detalle')])
                    ->from($db->quoteName('#__ordenproduccion_pre_cotizacion_line_detalles'))
                    ->whereIn($db->quoteName('pre_cotizacion_line_id'), $lineIds)
                    ->order($db->quoteName('id') . ' ASC');
                $db->setQuery($tq);
                $drows = $db->loadObjectList() ?: [];
                foreach ($drows as $dr) {
                    $lid = (int) ($dr->pre_cotizacion_line_id ?? 0);
                    if ($lid < 1) {
                        continue;
                    }
                    if (!isset($detallesByLine[$lid])) {
                        $detallesByLine[$lid] = [];
                    }
                    $detalle = isset($dr->detalle) ? trim((string) $dr->detalle) : '';
                    $detallesByLine[$lid][] = [
                        'concepto_label' => trim((string) ($dr->concepto_label ?? '')),
                        'concepto_key'   => (string) ($dr->concepto_key ?? ''),
                        'detalle'        => $detalle,
                    ];
                }
            } catch (\Throwable $e) {
                $detallesByLine = [];
            }
        }

        $sections = [];

        foreach ($lines as $line) {
            $lineTypeRaw = isset($line->line_type) ? strtolower(trim((string) $line->line_type)) : '';
            $lineType    = ($lineTypeRaw === '') ? 'pliego' : $lineTypeRaw;
            if ($lineType === 'proveedor_externo') {
                continue;
            }

            /** Envío se muestra solo en «Información de Envío» del OT, no como tarjeta duplicada. */
            if ($lineType === 'envio') {
                continue;
            }

            $tipoElementoEl = isset($line->tipo_elemento) ? trim((string) $line->tipo_elemento) : '';
            $tipoLabel      = $tipoElementoEl !== '' ? $tipoElementoEl : '';

            $lineId = (int) ($line->id ?? 0);
            $metaRows = [];

            if ($lineType === 'pliego') {
                if ($tipoLabel === '') {
                    $tipoLabel = Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_TIPO_FALLBACK_PLIEGO');
                }

                $paperId = isset($line->paper_type_id) ? (int) $line->paper_type_id : 0;
                $sizeId  = isset($line->size_id) ? (int) $line->size_id : 0;

                $paperName = isset($papersById[$paperId]->name)
                    ? (string) $papersById[$paperId]->name
                    : ($paperId > 0 ? (string) $paperId : '');
                $sizeObj   = $sizeId > 0 && isset($sizesById[$sizeId])
                    ? $sizesById[$sizeId]
                    : null;

                $sizeLabel = '';
                if ($sizeObj !== null) {
                    $sizeLabel = trim((string) ($sizeObj->name ?? ''));
                    if ($sizeLabel === '' && isset($sizeObj->width_in, $sizeObj->height_in)) {
                        $sizeLabel = trim((string) $sizeObj->width_in) . '×' . trim((string) $sizeObj->height_in);
                    }
                }

                if ($paperName !== '') {
                    $metaRows[] = ['label_key' => 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_PAPER', 'value' => $paperName];
                }
                if ($sizeLabel !== '') {
                    $metaRows[] = ['label_key' => 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_PLIEGO_SIZE', 'value' => $sizeLabel];
                }

                $tiroRetiro = strtolower((string) ($line->tiro_retiro ?? 'tiro'));
                $trDisp     = ($tiroRetiro === 'retiro')
                    ? Text::_('COM_ORDENPRODUCCION_ORDEN_DISP_IMPRESION_TIRO_RETIRO')
                    : Text::_('COM_ORDENPRODUCCION_ORDEN_DISP_IMPRESION_TIRO_ONLY');
                $metaRows[] = ['label_key' => 'COM_ORDENPRODUCCION_ORDEN_TIRO_RETIRO', 'value' => $trDisp];

                $lamId = isset($line->lamination_type_id) ? (int) $line->lamination_type_id : 0;
                if ($lamId > 0 && isset($lamsById[$lamId])) {
                    $lamName       = trim((string) ($lamsById[$lamId]->name ?? ''));
                    $lamTiroRaw    = strtolower((string) ($line->lamination_tiro_retiro ?? 'tiro'));
                    $lamTiroPhrase = ($lamTiroRaw === 'retiro')
                        ? Text::_('COM_ORDENPRODUCCION_ORDEN_DISP_IMPRESION_TIRO_RETIRO')
                        : Text::_('COM_ORDENPRODUCCION_ORDEN_DISP_IMPRESION_TIRO_ONLY');
                    $metaRows[] = [
                        'label_key' => 'COM_ORDENPRODUCCION_ORDEN_LAMINADO',
                        'value'     => trim($lamName . ' — ' . $lamTiroPhrase),
                    ];
                }

                $procNames = [];
                if (!empty($line->process_ids)) {
                    $pids = json_decode((string) $line->process_ids, true);
                    if (\is_array($pids)) {
                        foreach ($pids as $pid) {
                            $pid = (int) $pid;
                            if ($pid > 0 && isset($procById[$pid]->name)) {
                                $procNames[] = (string) $procById[$pid]->name;
                            }
                        }
                    }
                }
                if ($procNames !== []) {
                    $metaRows[] = [
                        'label_key' => 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_EXTRA_PROCESSES',
                        'value'     => implode(', ', $procNames),
                    ];
                }

                $subtitle = trim($tipoLabel . ($paperName !== '' ? ' · ' . $paperName : ''));
                $heading = $tipoLabel !== '' ? $tipoLabel : Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_TIPO_FALLBACK_PLIEGO');
            } elseif ($lineType === 'elementos') {
                if ($tipoLabel === '') {
                    $tipoLabel = Text::_('COM_ORDENPRODUCCION_ORDEN_PRECOT_TIPO_FALLBACK_ELEMENTOS');
                }
                $elId     = isset($line->elemento_id) ? (int) $line->elemento_id : 0;
                $elName   = '';
                if ($elId > 0 && $productosModel && $productosModel->elementosTableExists()) {
                    $el = $productosModel->getElemento($elId);
                    if ($el !== null) {
                        $elName = trim((string) ($el->name ?? ''));
                    }
                }
                if ($elName !== '') {
                    $metaRows[] = ['label_key' => 'COM_ORDENPRODUCCION_ORDEN_PRECOT_META_ELEMENTO', 'value' => $elName];
                }
                $subtitle = trim($tipoLabel . ($elName !== '' ? ' · ' . $elName : ''));
                $heading    = $tipoLabel;
            } else {
                continue;
            }

            $qty = isset($line->quantity) ? (int) $line->quantity : 0;
            if ($qty > 0) {
                $qtyKey = ($lineType === 'pliego')
                    ? 'COM_ORDENPRODUCCION_ORDEN_PRECOT_CANTIDAD_PLIEGOS_IMPR'
                    : 'COM_ORDENPRODUCCION_ORDEN_PRECOT_CANTIDAD_OTROS';
                $metaRows[] = ['label_key' => $qtyKey, 'value' => (string) $qty];
            }

            $instructions = [];

            foreach ($detallesByLine[$lineId] ?? [] as $d) {
                $detTxt = isset($d['detalle']) ? trim((string) $d['detalle']) : '';
                if ($detTxt === '') {
                    continue;
                }
                $lab = isset($d['concepto_label']) ? trim((string) $d['concepto_label']) : '';
                if ($lab === '') {
                    $lab = Text::_('COM_ORDENPRODUCCION_ORDEN_INSTRUCCIONES');
                }
                $instructions[] = ['label' => $lab, 'text' => $detTxt];
            }

            $sections[] = [
                'line_id'       => $lineId,
                'line_type'     => $lineType,
                'heading'       => $heading ?? '',
                'subtitle'      => $subtitle ?? '',
                'meta_rows'     => $metaRows,
                'instructions'  => $instructions,
            ];
        }

        return $sections;
    }
}
