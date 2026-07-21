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
use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkQuotationPaymentLinkHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;
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
    public function createPaymentForQuotation(
        int $quotationId,
        int $userId,
        string $installments = 'VC00',
        ?string $referenceId = null,
        ?string $title = null,
        ?string $description = null
    ): array {
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

        $eligibility = BlinkQuotationPaymentLinkHelper::analyze($quotationId, $row, $this->db);
        if (empty($eligibility['show_button'])) {
            if (!empty($eligibility['show_cuotas_mismatch'])) {
                return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_PAY_LINK_CUOTAS_MISMATCH')];
            }

            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_PAY_LINK_NOT_ELIGIBLE')];
        }

        $installments = BlinkGatewayConfigHelper::normalizeInstallments((string) $eligibility['installments_vc']);

        $titulo = BlinkGatewayConfigHelper::truncatePaymentTitle(
            trim((string) ($title ?? '')) !== ''
                ? trim((string) $title)
                : (string) $eligibility['title']
        );

        // Blink checkout shows the description as-is; empty must not fall back to a language key.
        $descInput   = trim((string) ($description ?? ''));
        $description = BlinkGatewayConfigHelper::truncatePaymentDescription($descInput !== '' ? $descInput : '.');

        $referenceId = trim((string) ($referenceId ?? ''));
        if ($referenceId === '') {
            $referenceId = (string) $eligibility['reference'];
        }
        $referenceId = substr($referenceId, 0, 100);
        $now         = Factory::getDate()->toSql();

        // Reuse an existing successful link (UI only shows Create when no created URL is found).
        $existingCreated = $this->findCreatedPaymentWithUrl($quotationId, $referenceId);
        if ($existingCreated !== null) {
            return [
                'success'      => true,
                'message'      => Text::_('COM_ORDENPRODUCCION_BLINK_PAYMENT_ALREADY_EXISTS'),
                'payment_url'  => (string) $existingCreated->payment_url,
                'reference_id' => (string) ($existingCreated->reference_id ?? $referenceId),
                'payment_id'   => (int) ($existingCreated->id ?? 0),
            ];
        }

        // A prior failed/pending row keeps reference_id (unique). Reuse it instead of inserting again.
        $existingRow = $this->findPaymentByReference($referenceId);
        $paymentId   = 0;

        if ($existingRow === null) {
            $pending = (object) [
                'quotation_id'        => $quotationId,
                'reference_id'        => $referenceId,
                'amount'              => $amount,
                'installments'        => $installments,
                'title'               => $titulo,
                'description'         => $description,
                'payment_url'         => null,
                'status'              => 'pending',
                'blink_response_json' => null,
                'error_message'       => null,
                'created'             => $now,
                'created_by'          => max(0, $userId),
                'modified'            => null,
            ];

            $inserted = false;

            try {
                $inserted = (bool) $this->db->insertObject('#__ordenproduccion_blink_payments', $pending, 'id');
            } catch (\Throwable $e) {
                $inserted = false;
            }

            if ($inserted) {
                $paymentId = (int) ($pending->id ?? 0);
            } else {
                // Duplicate key / race: load the row that won and reuse it below.
                $existingRow = $this->findPaymentByReference($referenceId);
                if ($existingRow === null) {
                    return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_SAVE_FAILED')];
                }

                $createdUrl = trim((string) ($existingRow->payment_url ?? ''));
                if ((string) ($existingRow->status ?? '') === 'created' && $createdUrl !== '') {
                    return [
                        'success'      => true,
                        'message'      => Text::_('COM_ORDENPRODUCCION_BLINK_PAYMENT_ALREADY_EXISTS'),
                        'payment_url'  => $createdUrl,
                        'reference_id' => $referenceId,
                        'payment_id'   => (int) ($existingRow->id ?? 0),
                    ];
                }
            }
        }

        if ($paymentId < 1 && $existingRow !== null) {
            $paymentId = (int) ($existingRow->id ?? 0);
            $reuse     = (object) [
                'id'                  => $paymentId,
                'quotation_id'        => $quotationId,
                'amount'              => $amount,
                'installments'        => $installments,
                'title'               => $titulo,
                'description'         => $description,
                'payment_url'         => null,
                'status'              => 'pending',
                'blink_response_json' => null,
                'error_message'       => null,
                'modified'            => $now,
            ];

            try {
                if (!$this->db->updateObject('#__ordenproduccion_blink_payments', $reuse, 'id')) {
                    return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_SAVE_FAILED')];
                }
            } catch (\Throwable $e) {
                return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_SAVE_FAILED')];
            }
        }

        if ($paymentId < 1) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_SAVE_FAILED')];
        }

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

            TelegramNotificationHelper::notifyBlinkPaymentLinkCreated(
                $userId,
                $referenceId,
                $amount,
                BlinkQuotationPaymentLinkHelper::formatCuotasLabelHuman(
                    BlinkQuotationPaymentLinkHelper::installmentCodeToMeses($installments)
                ),
                (string) $gw['payment_url']
            );

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

    /**
     * @return  object|null
     */
    protected function findPaymentByReference(string $referenceId): ?object
    {
        $referenceId = trim($referenceId);
        if ($referenceId === '' || !$this->isTableAvailable()) {
            return null;
        }

        $query = $this->db->getQuery(true)
            ->select('*')
            ->from($this->db->quoteName('#__ordenproduccion_blink_payments'))
            ->where($this->db->quoteName('reference_id') . ' = ' . $this->db->quote($referenceId));
        $this->db->setQuery($query, 0, 1);
        $row = $this->db->loadObject();

        return $row ?: null;
    }

    /**
     * Prefer an active created link for this quotation (or exact reference).
     *
     * @return  object|null
     */
    protected function findCreatedPaymentWithUrl(int $quotationId, string $referenceId): ?object
    {
        if (!$this->isTableAvailable()) {
            return null;
        }

        $referenceId = trim($referenceId);

        if ($quotationId > 0) {
            $query = $this->db->getQuery(true)
                ->select('*')
                ->from($this->db->quoteName('#__ordenproduccion_blink_payments'))
                ->where($this->db->quoteName('quotation_id') . ' = ' . (int) $quotationId)
                ->where($this->db->quoteName('status') . ' = ' . $this->db->quote('created'))
                ->where($this->db->quoteName('payment_url') . ' != ' . $this->db->quote(''))
                ->where($this->db->quoteName('payment_url') . ' IS NOT NULL')
                ->order($this->db->quoteName('created') . ' DESC');
            $this->db->setQuery($query, 0, 1);
            $row = $this->db->loadObject();
            if ($row) {
                return $row;
            }
        }

        if ($referenceId === '') {
            return null;
        }

        $byRef = $this->findPaymentByReference($referenceId);
        if ($byRef === null) {
            return null;
        }

        $url = trim((string) ($byRef->payment_url ?? ''));
        if ((string) ($byRef->status ?? '') === 'created' && $url !== '') {
            return $byRef;
        }

        return null;
    }
}
