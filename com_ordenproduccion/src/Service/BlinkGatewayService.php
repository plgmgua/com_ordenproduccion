<?php
/**
 * HTTP client for the Blink payment gateway (server-side only).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Service;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;

/**
 * Blink REST gateway: health, test-login, and POST /api/v1/gateway/payments.
 *
 * @since  3.119.129
 */
class BlinkGatewayService
{
    /**
     * @return  array{success: bool, message?: string, data?: mixed, http_code?: int}
     */
    public function healthCheck(): array
    {
        $baseUrl = BlinkGatewayConfigHelper::getBaseUrl();
        if ($baseUrl === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_BASE_URL_MISSING')];
        }

        try {
            $http     = HttpFactory::getHttp();
            // GET /health — no X-API-Key required (Blink gateway spec).
            $response = $http->get($baseUrl . '/health', [], 15);
            $code = (int) ($response->code ?? 0);
            $body = (string) ($response->body ?? '');
            $json = json_decode($body, true);

            if ($code === 200 && \is_array($json) && ($json['status'] ?? '') === 'ok') {
                return [
                    'success'   => true,
                    'message'   => Text::_('COM_ORDENPRODUCCION_BLINK_HEALTH_OK'),
                    'data'      => $json,
                    'http_code' => $code,
                ];
            }

            return [
                'success'   => false,
                'message'   => Text::sprintf('COM_ORDENPRODUCCION_BLINK_HEALTH_FAILED', $code),
                'http_code' => $code,
                'data'      => $json,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0];
        }
    }

    /**
     * Verify Pay Bi merchant credentials via Blink test-login.
     *
     * @return  array{success: bool, message?: string, data?: mixed, http_code?: int}
     *
     * @since   3.119.133
     */
    public function testLogin(): array
    {
        $cfg = BlinkGatewayConfigHelper::getSnapshot();
        if (empty($cfg['credentials_configured'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        $payload = [
            'credentials' => [
                'usuario' => $cfg['usuario'],
                'clave'   => BlinkGatewayConfigHelper::getPayBiClave(),
            ],
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_REQUEST_ENCODE_FAILED')];
        }

        try {
            $http     = HttpFactory::getHttp();
            $response = $http->post(
                $cfg['base_url'] . '/api/v1/gateway/test-login',
                $jsonBody,
                [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'X-API-Key'    => $cfg['api_key'],
                ],
                30
            );

            $code = (int) ($response->code ?? 0);
            $body = (string) ($response->body ?? '');
            $json = json_decode($body, true);

            return $this->parseTestLoginResponse($code, $json, $body);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0];
        }
    }

    /**
     * @param   int    $httpCode
     * @param   mixed  $json
     * @param   string $rawBody
     *
     * @return  array{success: bool, message?: string, data?: mixed, http_code?: int}
     */
    protected function parseTestLoginResponse(int $httpCode, $json, string $rawBody): array
    {
        $base = ['http_code' => $httpCode];

        if ($httpCode === 200 && \is_array($json) && !empty($json['success'])) {
            $message = Text::_('COM_ORDENPRODUCCION_BLINK_TEST_LOGIN_OK');
            if (!empty($json['message']) && \is_string($json['message'])) {
                $message = $json['message'];
            }

            return $base + [
                'success' => true,
                'message' => $message,
                'data'    => self::redactResponse($json),
            ];
        }

        return $base + [
            'success' => false,
            'message' => $this->extractErrorMessage($httpCode, $json, $rawBody),
            'data'    => self::redactResponse(\is_array($json) ? $json : ['body' => $rawBody]),
        ];
    }

    /**
     * Create a Pay Bi checkout link via Blink.
     *
     * @param   float   $amount
     * @param   string  $installments
     * @param   string  $referenceId
     * @param   string  $title
     * @param   string  $description
     *
     * @return  array{success: bool, message?: string, payment_url?: string, payment_links?: array, reference_id?: string, http_code?: int, raw?: mixed}
     */
    public function createPaymentLink(
        float $amount,
        string $installments,
        string $referenceId,
        string $title = '',
        string $description = ''
    ): array {
        $cfg = BlinkGatewayConfigHelper::getSnapshot();
        if (empty($cfg['configured'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        if ($amount < 0) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_AMOUNT_INVALID')];
        }

        $installments = BlinkGatewayConfigHelper::normalizeInstallments($installments);
        $referenceId  = trim($referenceId);
        if ($referenceId === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_REFERENCE_INVALID')];
        }

        $payload = [
            'credentials' => [
                'usuario' => $cfg['usuario'],
                'clave'   => BlinkGatewayConfigHelper::getPayBiClave(),
            ],
            'amount'       => round($amount, 2),
            'installments' => $installments,
            'referenceId'  => $referenceId,
        ];

        if ($title !== '') {
            $payload['title'] = $title;
        }
        if ($description !== '') {
            $payload['description'] = $description;
        }

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_REQUEST_ENCODE_FAILED')];
        }

        try {
            $http     = HttpFactory::getHttp();
            $response = $http->post(
                $cfg['base_url'] . '/api/v1/gateway/payments',
                $jsonBody,
                [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                    'X-API-Key'    => $cfg['api_key'],
                ],
                60
            );

            $code = (int) ($response->code ?? 0);
            $body = (string) ($response->body ?? '');
            $json = json_decode($body, true);

            return $this->parseCreatePaymentResponse($code, $json, $body, $referenceId);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0];
        }
    }

    /**
     * @param   int          $httpCode
     * @param   mixed        $json
     * @param   string       $rawBody
     * @param   string       $referenceId
     *
     * @return  array{success: bool, message?: string, payment_url?: string, payment_links?: array, reference_id?: string, http_code?: int, raw?: mixed}
     */
    protected function parseCreatePaymentResponse(int $httpCode, $json, string $rawBody, string $referenceId): array
    {
        $base = ['http_code' => $httpCode, 'reference_id' => $referenceId];

        if ($httpCode === 201 && \is_array($json) && !empty($json['success']) && !empty($json['data']) && \is_array($json['data'])) {
            $data = $json['data'];
            $url  = trim((string) ($data['paymentUrl'] ?? ''));
            if ($url === '' && !empty($data['paymentLinks']) && \is_array($data['paymentLinks'])) {
                $first = $data['paymentLinks'][0] ?? null;
                if (\is_array($first)) {
                    $url = trim((string) ($first['url'] ?? ''));
                }
            }

            if ($url === '') {
                return $base + [
                    'success' => false,
                    'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NO_PAYMENT_URL'),
                    'raw'     => self::redactResponse($json),
                ];
            }

            return $base + [
                'success'       => true,
                'message'       => Text::_('COM_ORDENPRODUCCION_BLINK_PAYMENT_CREATED'),
                'payment_url'   => $url,
                'payment_links' => $data['paymentLinks'] ?? [],
                'raw'           => self::redactResponse($json),
            ];
        }

        $message = $this->extractErrorMessage($httpCode, $json, $rawBody);

        return $base + [
            'success' => false,
            'message' => $message,
            'raw'     => self::redactResponse(\is_array($json) ? $json : ['body' => $rawBody]),
        ];
    }

    /**
     * @param   int    $httpCode
     * @param   mixed  $json
     * @param   string $rawBody
     *
     * @return  string
     */
    protected function extractErrorMessage(int $httpCode, $json, string $rawBody): string
    {
        if ($httpCode === 401) {
            return Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_UNAUTHORIZED');
        }
        if ($httpCode === 429) {
            return Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_RATE_LIMIT');
        }

        if (\is_array($json)) {
            if (!empty($json['payBiMessage']) && \is_string($json['payBiMessage'])) {
                return Text::sprintf('COM_ORDENPRODUCCION_BLINK_ERROR_PAYBI', $json['payBiMessage']);
            }
            if (!empty($json['error']) && \is_string($json['error'])) {
                if ($httpCode === 400) {
                    return Text::sprintf('COM_ORDENPRODUCCION_BLINK_ERROR_VALIDATION', $json['error']);
                }
                if ($httpCode === 502) {
                    return Text::sprintf('COM_ORDENPRODUCCION_BLINK_ERROR_GATEWAY', $json['error']);
                }

                return $json['error'];
            }
        }

        if ($httpCode >= 500) {
            return Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_SERVER');
        }
        if ($httpCode >= 400) {
            return Text::sprintf('COM_ORDENPRODUCCION_BLINK_ERROR_HTTP', $httpCode);
        }

        $snippet = trim($rawBody);
        if ($snippet !== '' && \strlen($snippet) > 200) {
            $snippet = \substr($snippet, 0, 197) . '...';
        }

        return $snippet !== '' ? $snippet : Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_UNKNOWN');
    }

    /**
     * Remove credentials from stored gateway JSON.
     *
     * @param   mixed  $data
     *
     * @return  mixed
     */
    public static function redactResponse($data)
    {
        if (!\is_array($data)) {
            return $data;
        }

        $out = $data;
        if (isset($out['credentials']) && \is_array($out['credentials'])) {
            $out['credentials'] = [
                'usuario' => isset($out['credentials']['usuario']) ? '***' : '',
                'clave'   => '***',
                'key'     => '***',
            ];
        }
        if (isset($out['data']) && \is_array($out['data']) && isset($out['data']['tokenPreview'])) {
            $out['data']['tokenPreview'] = '***';
        }

        return $out;
    }
}
