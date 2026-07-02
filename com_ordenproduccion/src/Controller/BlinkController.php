<?php
/**
 * Blink gateway inbound webhooks (no Joomla session).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkGatewayConfigHelper;
use Grimpsa\Component\Ordenproduccion\Site\Helper\BlinkWebhookLogHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

/**
 * Blink gateway webhook controller.
 *
 * @since  3.119.208
 */
class BlinkController extends BaseController
{
    /**
     * Blink log.created webhook: verify X-Blink-Signature, store payload.data, return JSON success.
     *
     * POST URL (public, no session):
     * index.php?option=com_ordenproduccion&controller=blink&task=logWebhook&format=raw
     *
     * @return  void
     */
    public function logWebhook(): void
    {
        BlinkGatewayConfigHelper::loadLanguage();

        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
        if ($method !== 'POST') {
            $this->emitJsonResponse(405, ['success' => false, 'message' => 'Method Not Allowed']);

            return;
        }

        $rawBody = (string) file_get_contents('php://input');
        $secret  = BlinkGatewayConfigHelper::getWebhookSecret();
        $header  = self::readBlinkSignatureHeader();

        if ($secret === '') {
            $this->emitJsonResponse(403, ['success' => false, 'message' => 'Webhook secret not configured']);

            return;
        }

        if ($header === '') {
            $this->emitJsonResponse(403, ['success' => false, 'message' => 'Missing X-Blink-Signature']);

            return;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, self::normalizeSignature($header))) {
            $this->emitJsonResponse(403, ['success' => false, 'message' => 'Invalid signature']);

            return;
        }

        if ($rawBody === '') {
            $this->emitJsonResponse(400, ['success' => false, 'message' => 'Empty body']);

            return;
        }

        $payload = json_decode($rawBody, true);
        if (!\is_array($payload)) {
            $this->emitJsonResponse(400, ['success' => false, 'message' => 'Invalid JSON']);

            return;
        }

        $event = trim((string) ($payload['event'] ?? ''));
        if ($event === 'log.created') {
            $logData = $payload['data'] ?? $payload['payload']['data'] ?? null;
            if (\is_array($logData)) {
                BlinkWebhookLogHelper::storeLogEntry($logData, $event);
            }
        }

        $this->emitJsonResponse(200, ['success' => true]);
    }

    /**
     * @return  string
     */
    private static function readBlinkSignatureHeader(): string
    {
        foreach (
            [
                'HTTP_X_BLINK_SIGNATURE',
                'REDIRECT_HTTP_X_BLINK_SIGNATURE',
            ] as $serverKey
        ) {
            if (!empty($_SERVER[$serverKey])) {
                return trim((string) $_SERVER[$serverKey]);
            }
        }

        if (\function_exists('getallheaders')) {
            $headers = getallheaders();
            if (\is_array($headers)) {
                foreach ($headers as $name => $value) {
                    if (\is_string($name) && strcasecmp($name, 'X-Blink-Signature') === 0) {
                        return trim((string) $value);
                    }
                }
            }
        }

        return '';
    }

    /**
     * Accept raw hex or sha256= prefix from Blink.
     *
     * @param   string  $header
     *
     * @return  string
     */
    private static function normalizeSignature(string $header): string
    {
        $header = strtolower(trim($header));
        if (str_starts_with($header, 'sha256=')) {
            $header = substr($header, 7);
        }

        return $header;
    }

    /**
     * @param   int                  $status
     * @param   array<string,mixed>  $body
     *
     * @return  void
     */
    private function emitJsonResponse(int $status, array $body): void
    {
        $app = Factory::getApplication();
        if (!\headers_sent()) {
            \http_response_code($status);
        }
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
