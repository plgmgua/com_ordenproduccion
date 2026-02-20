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

        // Procesos Adicionales: price by pliego range (1–1000 vs 1001+), total per process not per sheet
        $processesTotal = 0.0;
        if (!empty($processIds)) {
            $processes = $productosModel->getProcesses();
            $useRange = $quantity <= 1000;
            foreach ($processes as $p) {
                if (in_array((int) $p->id, $processIds, true)) {
                    $processesTotal += $useRange
                        ? (float) ($p->price_1_to_1000 ?? 0)
                        : (float) ($p->price_1001_plus ?? 0);
                }
            }
        }

        $pricePerSheet = $printPrice + $laminationPrice;
        $total = $pricePerSheet * $quantity + $processesTotal;

        echo json_encode([
            'success' => true,
            'price_per_sheet' => round($pricePerSheet, 4),
            'total' => round($total, 2),
            'print_price' => $printPrice,
            'lamination_price' => $laminationPrice,
            'processes_total' => $processesTotal,
        ]);
        $app->close();
    }
}
