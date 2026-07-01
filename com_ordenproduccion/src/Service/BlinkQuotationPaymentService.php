<?php
/**
 * Create and persist Blink card-payment links for cotizaciones.
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;

/**
 * Quotation → Blink payment link workflow.
 *
 * @since  3.119.129
 */
class BlinkQuotationPaymentService
{
    /** @var DatabaseInterface */
    protected $db;

    /** @var BlinkGatewayService */
    protected $gateway;

    public function __construct(?DatabaseInterface $db = null, ?BlinkGatewayService $gateway = null)
    {
        $this->db      = $db ?? Factory::getContainer()->get(DatabaseInterface::class);
        $this->gateway = $gateway ?? new BlinkGatewayService();
    }

    public function isTableAvailable(): bool
    {
        try {
            $tables = $this->db->getTableList();
            $needle = $this->db->replacePrefix('#__ordenproduccion_blink_payments');

            return \in_array($needle, $tables, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function isConfigured(): bool
    {
        return BlinkGatewayConfigHelper::getSnapshot()['configured'];
    }

    /**
     * @return  object[]
     */
    public function getPaymentsForQuotation(int $quotationId, int $limit = 10): array
    {
        if ($quotationId < 1 || !$this->isTableAvailable()) {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_blink_payments'))
            ->where($this->db->quoteName('quotation_id') . ' = ' . (int) $quotationId)
            ->order($this->db->quoteName('created') . ' DESC');

        $this->db->setQuery($query, 0, max(1, min(50, $limit)));

        return $this->db->loadObjectList() ?: [];
    }

    /**
     * @return  array{success: bool, message?: string, payment_url?: string, reference_id?: string, payment_id?: int}
     */
    public function createPaymentForQuotation(int $quotationId, int $userId, string $installments = 'VC00'): array
    {
        Factory::getLanguage()->load('com_ordenproduccion', JPATH_SITE);

        if ($quotationId < 1) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_QUOTATION')];
        }

        if (!$this->isTableAvailable()) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_TABLE_MISSING')];
        }

        if (!$this->isConfigured()) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        $q = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_quotations'))
            ->where($this->db->quoteName('id') . ' = ' . (int) $quotationId)
            ->where($this->db->quoteName('state') . ' = 1');
        $this->db->setQuery($q);
        $row = $this->db->loadObject();
        if (!$row) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_QUOTATION')];
        }

        $amount = round((float) ($row->total_amount ?? 0), 2);
        if ($amount <= 0) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_LINK_TOTAL_ZERO')];
        }

        $installments = BlinkGatewayConfigHelper::normalizeInstallments($installments);

        $num    = trim((string) ($row->quotation_number ?? ''));
        $title  = $num !== '' ? $num : ('COT-' . $quotationId);
        $client = trim((string) ($row->client_name ?? ''));
        $titulo = $title . ($client !== '' ? ' — ' . $client : '');
        $titulo = BlinkGatewayConfigHelper::truncatePaymentTitle($titulo);

        $description = BlinkGatewayConfigHelper::truncatePaymentDescription(
            Text::sprintf('COM_ORDENPRODUCCION_BLINK_PAYMENT_DESCRIPTION', $title)
        );
        $referenceId = $this->buildReferenceId($quotationId);
        $now         = Factory::getDate()->toSql();

        $pending = (object) [
            'quotation_id'  => $quotationId,
            'reference_id'  => $referenceId,
            'amount'        => $amount,
            'installments'  => $installments,
            'title'         => $titulo,
            'description'   => $description,
            'payment_url'   => null,
            'status'        => 'pending',
            'blink_response_json' => null,
            'error_message' => null,
            'created'       => $now,
            'created_by'    => max(0, $userId),
            'modified'      => null,
        ];

        if (!$this->db->insertObject('#__ordenproduccion_blink_payments', $pending, 'id')) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_SAVE_FAILED')];
        }

        $paymentId = (int) ($pending->id ?? 0);

        $gw = $this->gateway->createPaymentLink($amount, $installments, $referenceId, $titulo, $description);

        $update = (object) [
            'id'       => $paymentId,
            'modified' => Factory::getDate()->toSql(),
        ];

        if (!empty($gw['raw'])) {
            $update->blink_response_json = json_encode($gw['raw'], JSON_UNESCAPED_UNICODE);
        }

        if (!empty($gw['success']) && !empty($gw['payment_url'])) {
            $update->payment_url = (string) $gw['payment_url'];
            $update->status      = 'created';
            $update->error_message = null;
            $this->db->updateObject('#__ordenproduccion_blink_payments', $update, 'id');

            return [
                'success'      => true,
                'message'      => (string) ($gw['message'] ?? Text::_('COM_ORDENPRODUCCION_BLINK_PAYMENT_CREATED')),
                'payment_url'  => (string) $gw['payment_url'],
                'reference_id' => $referenceId,
                'request_id'   => (string) ($gw['request_id'] ?? ''),
                'payment_id'   => $paymentId,
            ];
        }

        $update->status        = 'failed';
        $update->error_message = (string) ($gw['message'] ?? Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_UNKNOWN'));
        $this->db->updateObject('#__ordenproduccion_blink_payments', $update, 'id');

        return [
            'success'        => false,
            'message'        => $update->error_message,
            'reference_id'   => $referenceId,
            'request_id'     => (string) ($gw['request_id'] ?? ''),
            'exchange_logs'  => $gw['exchange_logs'] ?? [],
            'exchange_total' => (int) ($gw['exchange_total'] ?? 0),
            'payment_id'     => $paymentId,
        ];
    }

    protected function buildReferenceId(int $quotationId): string
    {
        $suffix = bin2hex(random_bytes(4));

        return 'cot-' . $quotationId . '-' . $suffix;
    }
}
