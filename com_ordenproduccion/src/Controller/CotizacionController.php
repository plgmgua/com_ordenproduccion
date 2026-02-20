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
use Joomla\CMS\Session\Session;

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

        $printLabel = $tiroRetiro === 'retiro'
            ? Text::_('COM_ORDENPRODUCCION_CALC_PRINT_RETIRO')
            : Text::_('COM_ORDENPRODUCCION_CALC_PRINT_TIRO');
        $perPliego = [
            ['label' => $printLabel, 'unit_price' => (float) $printPrice, 'subtotal' => round($printPrice * $quantity, 2)],
        ];
        if ($laminationPrice > 0) {
            $perPliego[] = ['label' => Text::_('COM_ORDENPRODUCCION_CALC_LAMINATION'), 'unit_price' => (float) $laminationPrice, 'subtotal' => round($laminationPrice * $quantity, 2)];
        }

        $processBreakdown = [];
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
                $processBreakdown[] = [
                    'name' => $p->name ?? '',
                    'range_label' => $rangeLabel,
                    'price' => $price,
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
            'per_pliego' => $perPliego,
            'processes' => $processBreakdown,
        ]);
        $app->close();
    }
}
