<?php
/**
 * Eligibility rules for Blink payment links on cotizaciones (pre-cotización tarjeta de crédito).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Model\PrecotizacionModel;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * @since  3.119.216
 */
final class BlinkQuotationPaymentLinkHelper
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }

    /**
     * Distinct pre-cotización ids linked to quotation lines.
     *
     * @return  int[]
     */
    public static function getLinkedPreCotizacionIds(int $quotationId, ?DatabaseInterface $db = null): array
    {
        if ($quotationId < 1) {
            return [];
        }

        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $cols = $db->getTableColumns('#__ordenproduccion_quotation_items', false);
        $cols = \is_array($cols) ? array_change_key_case($cols, CASE_LOWER) : [];
        if (!isset($cols['pre_cotizacion_id'])) {
            return [];
        }

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('pre_cotizacion_id'))
            ->from($db->quoteName('#__ordenproduccion_quotation_items'))
            ->where($db->quoteName('quotation_id') . ' = ' . (int) $quotationId)
            ->where($db->quoteName('pre_cotizacion_id') . ' > 0');

        $db->setQuery($query);
        $ids = $db->loadColumn() ?: [];

        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * @param   object  $quotation  Row from #__ordenproduccion_quotations
     *
     * @return  string  e.g. COT-000042
     */
    public static function formatQuotationReference(object $quotation): string
    {
        $num = trim((string) ($quotation->quotation_number ?? ''));
        if ($num !== '') {
            return $num;
        }

        return 'COT-' . str_pad((string) (int) ($quotation->id ?? 0), 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param   object  $quotation
     *
     * @return  string
     */
    public static function formatPaymentTitle(object $quotation): string
    {
        return 'Pago de cotización ' . self::formatQuotationReference($quotation);
    }

    /**
     * Analyze whether "Crear Link de Pago" should appear.
     *
     * @return  array{
     *   show_button: bool,
     *   show_cuotas_mismatch: bool,
     *   cuotas_meses: int|null,
     *   installments_vc: string,
     *   cuotas_label: string,
     *   pre_count: int,
     *   reference: string,
     *   title: string,
     *   monto: float
     * }
     */
    public static function analyze(int $quotationId, ?object $quotation = null, ?DatabaseInterface $db = null): array
    {
        $db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        if ($quotation === null && $quotationId > 0) {
            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ordenproduccion_quotations'))
                ->where($db->quoteName('id') . ' = ' . (int) $quotationId)
                ->where($db->quoteName('state') . ' = 1');
            $db->setQuery($query);
            $quotation = $db->loadObject();
        }

        $monto = $quotation ? round((float) ($quotation->total_amount ?? 0), 2) : 0.0;
        $reference = $quotation ? self::formatQuotationReference($quotation) : '';
        $title     = $quotation ? BlinkGatewayConfigHelper::truncatePaymentTitle(self::formatPaymentTitle($quotation)) : '';

        $empty = [
            'show_button'            => false,
            'show_cuotas_mismatch'   => false,
            'cuotas_meses'           => null,
            'installments_vc'        => 'VC00',
            'cuotas_label'           => '',
            'pre_count'              => 0,
            'reference'              => $reference,
            'title'                  => $title,
            'monto'                  => $monto,
        ];

        if ($quotationId < 1 || !$quotation || $monto <= 0) {
            return $empty;
        }

        $preIds = self::getLinkedPreCotizacionIds($quotationId, $db);
        $empty['pre_count'] = \count($preIds);

        if ($preIds === []) {
            return $empty;
        }

        $precotModel = Factory::getApplication()->bootComponent('com_ordenproduccion')
            ->getMVCFactory()
            ->createModel('Precotizacion', 'Site', ['ignore_request' => true]);

        if (!$precotModel instanceof PrecotizacionModel) {
            return $empty;
        }

        $cuotasByPre = [];
        foreach ($preIds as $preId) {
            $item = $precotModel->getItem($preId);
            if (!$item) {
                return $empty;
            }

            $cuotas = null;
            if (isset($item->tarjeta_credito_cuotas) && $item->tarjeta_credito_cuotas !== null && $item->tarjeta_credito_cuotas !== '') {
                $cuotas = (int) $item->tarjeta_credito_cuotas;
            }

            $cuotasByPre[$preId] = ($cuotas !== null && $cuotas > 0) ? $cuotas : null;
        }

        foreach ($cuotasByPre as $cuotas) {
            if ($cuotas === null) {
                return $empty;
            }
        }

        $unique = array_values(array_unique($cuotasByPre));
        if (\count($unique) > 1) {
            return $empty + [
                'show_cuotas_mismatch' => true,
                'pre_count'            => \count($preIds),
            ];
        }

        $meses = (int) $unique[0];
        $vc    = BlinkGatewayConfigHelper::cuotasToInstallmentCode($meses);

        Factory::getLanguage()->load('com_ordenproduccion', JPATH_SITE);
        $cuotasLabel = $meses <= 1
            ? \Joomla\CMS\Language\Text::_('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_SINGLE') . ' (' . $vc . ')'
            : \Joomla\CMS\Language\Text::sprintf('COM_ORDENPRODUCCION_BLINK_INSTALLMENTS_N', $meses) . ' (' . $vc . ')';

        return [
            'show_button'            => true,
            'show_cuotas_mismatch'   => false,
            'cuotas_meses'           => $meses,
            'installments_vc'        => $vc,
            'cuotas_label'           => $cuotasLabel,
            'pre_count'              => \count($preIds),
            'reference'              => $reference,
            'title'                  => $title,
            'monto'                  => $monto,
        ];
    }
}
