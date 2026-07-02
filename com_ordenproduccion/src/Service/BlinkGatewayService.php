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
        BlinkGatewayConfigHelper::loadLanguage();
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
        BlinkGatewayConfigHelper::loadLanguage();
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

        $fail = $base + [
            'success' => false,
            'message' => $this->extractErrorMessage($httpCode, $json, $rawBody),
            'data'    => self::redactResponse(\is_array($json) ? $json : ['body' => $rawBody]),
        ];

        if ($httpCode === 401) {
            $fail['api_key_hint'] = BlinkGatewayConfigHelper::getApiKeyDiagnostics();
        }

        return $fail;
    }

    /**
     * Create a Pay Bi checkout link via Blink.
     *
     * @param   float   $amount
     * @param   string  $installments
     * @param   string  $referenceId
     * @param   string  $title
     * @param   string  $description
     * @param   bool    $testMode  When true, only credentials are required (Blink may be disabled).
     *
     * @return  array{success: bool, message?: string, payment_url?: string, payment_links?: array, reference_id?: string, http_code?: int, raw?: mixed}
     */
    public function createPaymentLink(
        float $amount,
        string $installments,
        string $referenceId,
        string $title = '',
        string $description = '',
        bool $testMode = false
    ): array {
        BlinkGatewayConfigHelper::loadLanguage();
        $cfg = BlinkGatewayConfigHelper::getSnapshot();
        if ($testMode) {
            if (empty($cfg['credentials_configured'])) {
                return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
            }
        } elseif (empty($cfg['configured'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        if ($amount < 0) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_AMOUNT_INVALID')];
        }

        $installments = BlinkGatewayConfigHelper::normalizeInstallments($installments);
        $referenceId  = trim($referenceId);
        $title        = BlinkGatewayConfigHelper::truncatePaymentTitle($title);
        $description  = BlinkGatewayConfigHelper::truncatePaymentDescription($description);

        $payload = $this->buildPaymentsPayload(
            round($amount, 2),
            $installments,
            $referenceId,
            $title,
            $description,
            $cfg['usuario']
        );

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

            return $this->parseCreatePaymentResponse($code, $json, $body, (string) ($payload['referenceId'] ?? $referenceId), $payload);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0];
        }
    }

    /**
     * Build strict Blink POST /api/v1/gateway/payments JSON body (allowed fields only).
     *
     * @param   float   $amount         JSON number; 0 = open amount on Pay Bi checkout.
     * @param   string  $installments   VC00|VC02|… or comma-separated list.
     * @param   string  $referenceId    Optional; omitted when empty (Blink auto-generates).
     * @param   string  $title          Optional; max 100 chars.
     * @param   string  $description    Optional; max 500 chars.
     * @param   string  $usuario        Pay Bi merchant email.
     *
     * @return  array<string, mixed>
     *
     * @since   3.119.205
     */
    protected function buildPaymentsPayload(
        float $amount,
        string $installments,
        string $referenceId,
        string $title,
        string $description,
        string $usuario
    ): array {
        $credentials = [
            'usuario' => $usuario,
            'clave'   => BlinkGatewayConfigHelper::getPayBiClave(),
        ];
        $payBiKey = BlinkGatewayConfigHelper::getPayBiKey();
        if ($payBiKey !== '') {
            $credentials['key'] = $payBiKey;
        }

        $payload = [
            'credentials'  => $credentials,
            'amount'       => $amount,
            'installments' => $installments,
        ];

        if ($title !== '') {
            $payload['title'] = $title;
        }
        if ($description !== '') {
            $payload['description'] = $description;
        }
        if ($referenceId !== '') {
            $payload['referenceId'] = $referenceId;
        }

        $socialNetworkCode = BlinkGatewayConfigHelper::getSocialNetworkCode();
        $payload['socialNetworkCode'] = $socialNetworkCode !== ''
            ? $socialNetworkCode
            : BlinkGatewayConfigHelper::DEFAULT_SOCIAL_NETWORK_CODE;

        return $payload;
    }

    /**
     * List webhooks registered on the Blink gateway (GET /api/v1/gateway/webhooks).
     *
     * @return  array{success: bool, message?: string, http_code?: int, webhooks?: array, total?: int, raw?: mixed}
     *
     * @since   3.119.209
     */
    public function listWebhooks(): array
    {
        BlinkGatewayConfigHelper::loadLanguage();
        $cfg = BlinkGatewayConfigHelper::getSnapshot();
        if (empty($cfg['credentials_configured'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        try {
            $http     = HttpFactory::getHttp();
            $response = $http->get(
                $cfg['base_url'] . '/api/v1/gateway/webhooks',
                [
                    'Accept'    => 'application/json',
                    'X-API-Key' => $cfg['api_key'],
                ],
                30
            );

            $code = (int) ($response->code ?? 0);
            $body = (string) ($response->body ?? '');
            $json = json_decode($body, true);

            if ($code === 200 && \is_array($json) && !empty($json['success'])) {
                $webhooks = [];
                if (!empty($json['data']['webhooks']) && \is_array($json['data']['webhooks'])) {
                    $webhooks = $json['data']['webhooks'];
                } elseif (!empty($json['data']) && \is_array($json['data']) && array_is_list($json['data'])) {
                    $webhooks = $json['data'];
                } elseif (!empty($json['webhooks']) && \is_array($json['webhooks'])) {
                    $webhooks = $json['webhooks'];
                }

                return [
                    'success'   => true,
                    'message'   => Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOKS_LIST_OK'),
                    'http_code' => $code,
                    'webhooks'  => $webhooks,
                    'total'     => \count($webhooks),
                    'raw'       => self::redactResponse($json),
                ];
            }

            return [
                'success'   => false,
                'message'   => $this->extractErrorMessage($code, $json, $body),
                'http_code' => $code,
                'webhooks'  => [],
                'total'     => 0,
                'raw'       => self::redactResponse(\is_array($json) ? $json : ['body' => $body]),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0, 'webhooks' => [], 'total' => 0];
        }
    }

    /**
     * Subscribe log.created webhook on the Blink gateway (POST /api/v1/gateway/webhooks).
     *
     * @param   string|null  $webhookUrl  Override public URL; defaults to {@see BlinkGatewayConfigHelper::getLogWebhookPublicUrl()}.
     *
     * @return  array{success: bool, message?: string, http_code?: int, data?: mixed, raw?: mixed, webhook_url?: string}
     *
     * @since   3.119.208
     */
    public function subscribeLogWebhook(?string $webhookUrl = null): array
    {
        BlinkGatewayConfigHelper::loadLanguage();
        $cfg = BlinkGatewayConfigHelper::getSnapshot();
        if (empty($cfg['credentials_configured'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        $secret = BlinkGatewayConfigHelper::getWebhookSecret();
        if ($secret === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SECRET_MISSING')];
        }

        $url = trim((string) ($webhookUrl ?? $cfg['webhook_url'] ?? ''));
        if ($url === '') {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_URL_MISSING')];
        }

        $urlIssue = self::explainBlinkWebhookUrlIssue($url);
        if ($urlIssue !== null) {
            return [
                'success'     => false,
                'message'     => $urlIssue,
                'webhook_url' => $url,
            ];
        }

        $payload = [
            'url'    => $url,
            'secret' => $secret,
            'events' => ['log.created'],
            'active' => true,
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($jsonBody === false) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_REQUEST_ENCODE_FAILED')];
        }

        try {
            $http     = HttpFactory::getHttp();
            $response = $http->post(
                $cfg['base_url'] . '/api/v1/gateway/webhooks',
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

            if (($code === 200 || $code === 201) && \is_array($json) && !empty($json['success'])) {
                return [
                    'success'     => true,
                    'message'     => Text::_('COM_ORDENPRODUCCION_BLINK_WEBHOOK_SUBSCRIBE_OK'),
                    'http_code'   => $code,
                    'webhook_url' => $url,
                    'data'        => self::redactResponse($json),
                    'raw'         => self::redactResponse($json),
                ];
            }

            return [
                'success'     => false,
                'message'     => $this->extractErrorMessage($code, $json, $body),
                'http_code'   => $code,
                'webhook_url' => $url,
                'raw'         => self::redactResponse(\is_array($json) ? $json : ['body' => $body]),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0];
        }
    }

    /**
     * Query Blink ↔ Pay Bi HTTP exchange logs (GET /api/v1/gateway/logs).
     *
     * @param   array<string, scalar|null>  $filters  referenceId, requestId, gatewayOperation, from, to, success, limit, offset
     *
     * @return  array{success: bool, message?: string, http_code?: int, entries?: array, total?: int, raw?: mixed}
     *
     * @since   3.119.207
     */
    public function getExchangeLogs(array $filters = []): array
    {
        BlinkGatewayConfigHelper::loadLanguage();
        $cfg = BlinkGatewayConfigHelper::getSnapshot();
        if (empty($cfg['credentials_configured'])) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_NOT_CONFIGURED')];
        }

        $allowed = ['referenceId', 'requestId', 'referenceIdContains', 'gatewayOperation', 'from', 'to', 'success', 'limit', 'offset'];
        $query   = [];
        foreach ($allowed as $key) {
            if (!isset($filters[$key]) || $filters[$key] === '' || $filters[$key] === null) {
                continue;
            }
            $query[$key] = (string) $filters[$key];
        }

        if ($query === []) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_BLINK_LOGS_FILTER_REQUIRED')];
        }

        $url = $cfg['base_url'] . '/api/v1/gateway/logs';
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        try {
            $http     = HttpFactory::getHttp();
            $response = $http->get(
                $url,
                [
                    'Accept'    => 'application/json',
                    'X-API-Key' => $cfg['api_key'],
                ],
                30
            );

            $code = (int) ($response->code ?? 0);
            $body = (string) ($response->body ?? '');
            $json = json_decode($body, true);

            if ($code === 200 && \is_array($json) && !empty($json['success']) && !empty($json['data']) && \is_array($json['data'])) {
                $data = $json['data'];

                return [
                    'success'   => true,
                    'message'   => Text::_('COM_ORDENPRODUCCION_BLINK_LOGS_OK'),
                    'http_code' => $code,
                    'entries'   => $data['entries'] ?? [],
                    'total'     => (int) ($data['total'] ?? 0),
                    'raw'       => self::redactResponse($json),
                ];
            }

            return [
                'success'   => false,
                'message'   => $this->extractErrorMessage($code, $json, $body),
                'http_code' => $code,
                'raw'       => self::redactResponse(\is_array($json) ? $json : ['body' => $body]),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http_code' => 0];
        }
    }

    /**
     * @param   string  $referenceId
     * @param   string  $requestId
     *
     * @return  array{entries: array, total: int, fetch_error?: string}
     *
     * @since   3.119.207
     */
    protected function fetchExchangeLogsForFailure(string $referenceId, string $requestId = ''): array
    {
        if ($requestId === '' && $referenceId === '') {
            return ['entries' => [], 'total' => 0];
        }

        $base = ['limit' => 50];
        if ($requestId !== '') {
            $base['requestId'] = $requestId;
        } else {
            $base['referenceId'] = $referenceId;
        }

        $attempts = [
            $base + ['gatewayOperation' => 'create-payment'],
            $base,
            $base + ['success' => 'false'],
        ];

        $lastError = '';
        foreach ($attempts as $filters) {
            $logs = $this->getExchangeLogs($filters);
            if (!empty($logs['success'])) {
                $entries = \is_array($logs['entries'] ?? null) ? $logs['entries'] : [];
                if ($entries !== []) {
                    return [
                        'entries' => $entries,
                        'total'   => (int) ($logs['total'] ?? \count($entries)),
                    ];
                }
            } else {
                $lastError = (string) ($logs['message'] ?? '');
            }
        }

        return [
            'entries'     => [],
            'total'       => 0,
            'fetch_error' => $lastError !== '' ? $lastError : Text::_('COM_ORDENPRODUCCION_BLINK_LOGS_FETCH_FAILED'),
        ];
    }

    /**
     * @param   int          $httpCode
     * @param   mixed        $json
     * @param   string       $rawBody
     * @param   string       $referenceId
     * @param   array        $requestPayload  Redacted in error responses for debugging.
     *
     * @return  array{success: bool, message?: string, payment_url?: string, payment_links?: array, reference_id?: string, http_code?: int, raw?: mixed, request_preview?: array}
     */
    protected function parseCreatePaymentResponse(int $httpCode, $json, string $rawBody, string $referenceId, array $requestPayload = []): array
    {
        $base = [
            'http_code'       => $httpCode,
            'reference_id'    => $referenceId,
            'request_preview' => self::redactResponse($requestPayload),
        ];

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
                'request_id'    => trim((string) ($data['requestId'] ?? '')),
                'raw'           => self::redactResponse($json),
            ];
        }

        $message   = $this->extractErrorMessage($httpCode, $json, $rawBody);
        $requestId = '';
        if (\is_array($json)) {
            $requestId = trim((string) ($json['data']['requestId'] ?? $json['requestId'] ?? ''));
        }
        $exchangeLogs = $this->fetchExchangeLogsForFailure($referenceId, $requestId);

        return $base + [
            'success'        => false,
            'message'        => $message,
            'request_id'     => $requestId,
            'exchange_logs'  => $exchangeLogs['entries'] ?? [],
            'exchange_total' => (int) ($exchangeLogs['total'] ?? 0),
            'exchange_error' => $exchangeLogs['fetch_error'] ?? null,
            'raw'            => self::redactResponse(\is_array($json) ? $json : ['body' => $rawBody]),
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
            return Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_UNAUTHORIZED_GATEWAY_KEY');
        }
        if ($httpCode === 429) {
            return Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_RATE_LIMIT');
        }

        if (\is_array($json)) {
            if (!empty($json['payBiMessage']) && \is_string($json['payBiMessage'])) {
                $msg = Text::sprintf('COM_ORDENPRODUCCION_BLINK_ERROR_PAYBI', $json['payBiMessage']);
                if (stripos($json['payBiMessage'], 'Datos insuficientes') !== false) {
                    $msg .= ' ' . Text::_('COM_ORDENPRODUCCION_BLINK_ERROR_PAYBI_INSUFFICIENT_HINT');
                }

                return $msg;
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

    /**
     * Blink gateway rejects webhook URLs with query strings, index.php, etc.
     *
     * @param   string  $url
     *
     * @return  string|null  Localized error message, or null when acceptable
     *
     * @since   3.119.215
     */
    private static function explainBlinkWebhookUrlIssue(string $url): ?string
    {
        $lower = strtolower($url);

        if (
            str_contains($lower, 'index.php')
            || str_contains($url, '?')
            || str_contains($url, '&')
            || str_contains($url, '=')
        ) {
            return Text::sprintf(
                'COM_ORDENPRODUCCION_BLINK_WEBHOOK_URL_DISALLOWED',
                BlinkGatewayConfigHelper::getLogWebhookPublicUrl()
            );
        }

        return null;
    }
}
