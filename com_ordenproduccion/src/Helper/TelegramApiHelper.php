<?php

/**
 * Low-level Telegram Bot API (sendMessage).
 *
 * Uses POST application/x-www-form-urlencoded (Telegram requirement). Token must appear
 * in the URL path **without** encoding the colon (rawurlencode breaks BotFather tokens).
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Helper
 * @since       3.105.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Log\Log;

/**
 * Telegram HTTP helper.
 */
class TelegramApiHelper
{
    /**
     * Send a plain-text message via Bot API.
     *
     * @param   string  $botToken  Bot token from @BotFather
     * @param   string  $chatId    Recipient chat_id
     * @param   string  $text      Message body (UTF-8)
     *
     * @return  array{ok:bool,description?:string,error?:string,http_code?:int}
     */
    public static function sendMessage(string $botToken, string $chatId, string $text): array
    {
        $botToken = trim($botToken);
        $chatId   = trim((string) $chatId);
        if ($botToken === '' || $chatId === '' || $text === '') {
            return ['ok' => false, 'error' => 'empty'];
        }

        // Do NOT rawurlencode the token: it contains ":" between id and secret; encoding breaks the API.
        $url = 'https://api.telegram.org/bot' . $botToken . '/sendMessage';

        $postFields = http_build_query(
            [
                'chat_id' => $chatId,
                'text'    => $text,
            ],
            '',
            '&',
            PHP_QUERY_RFC1738
        );

        $httpCode = 0;
        $rawBody  = '';

        if (\function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                \CURLOPT_POST           => true,
                \CURLOPT_POSTFIELDS    => $postFields,
                \CURLOPT_HTTPHEADER    => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'Accept: application/json',
                ],
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_FOLLOWLOCATION => true,
                \CURLOPT_CONNECTTIMEOUT => 12,
                \CURLOPT_TIMEOUT       => 30,
                \CURLOPT_SSL_VERIFYPEER => true,
                \CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $rawBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, \CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($rawBody === false) {
                try {
                    Log::add('Telegram cURL error: ' . $curlErr, Log::WARNING, 'com_ordenproduccion');
                } catch (\Throwable $e) {
                }

                return ['ok' => false, 'error' => $curlErr !== '' ? $curlErr : 'curl_exec failed', 'http_code' => $httpCode];
            }
        } elseif (\ini_get('allow_url_fopen')) {
            $ctx = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\nConnection: close\r\n",
                    'content' => $postFields,
                    'timeout' => 30,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $rawBody = @file_get_contents($url, false, $ctx);
            if (isset($http_response_header[0]) && \preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
                $httpCode = (int) $m[1];
            }
            if ($rawBody === false) {
                return ['ok' => false, 'error' => 'file_get_contents failed (check allow_url_fopen / SSL)'];
            }
        } else {
            return self::sendMessageViaJoomlaHttp($url, $chatId, $text);
        }

        return self::interpretJsonResponse($rawBody, $httpCode);
    }

    /**
     * Fallback when cURL and allow_url_fopen are unavailable.
     *
     * @param   string  $url      Full API URL
     * @param   string  $chatId   Telegram chat id
     * @param   string  $text     Message text
     *
     * @return  array{ok:bool,description?:string,error?:string,http_code?:int}
     */
    protected static function sendMessageViaJoomlaHttp(string $url, string $chatId, string $text): array
    {
        try {
            $http     = HttpFactory::getHttp();
            $response = $http->post($url, [
                'chat_id' => $chatId,
                'text'    => $text,
            ]);
            $code = (int) ($response->code ?? 0);
            $body = (string) ($response->body ?? '');

            return self::interpretJsonResponse($body, $code);
        } catch (\Throwable $e) {
            try {
                Log::add('Telegram HttpFactory: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            } catch (\Throwable $e2) {
            }

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param   string  $rawBody   Response body
     * @param   int     $httpCode  HTTP status
     *
     * @return  array{ok:bool,description?:string,error?:string,http_code?:int}
     */
    protected static function interpretJsonResponse(string $rawBody, int $httpCode): array
    {
        $data = json_decode($rawBody, true);
        if (\is_array($data) && !empty($data['ok'])) {
            $out = ['ok' => true, 'http_code' => $httpCode];
            if (isset($data['result']['message_id'])) {
                $out['message_id'] = (int) $data['result']['message_id'];
            }
            if (isset($data['result']) && \is_array($data['result'])) {
                $out['result'] = $data['result'];
            }

            return $out;
        }

        $desc = \is_array($data) ? (string) ($data['description'] ?? '') : '';
        if ($desc === '' && $rawBody !== '') {
            $desc = substr($rawBody, 0, 500);
        }
        if ($desc === '' && $httpCode > 0) {
            $desc = 'HTTP ' . $httpCode;
        }

        try {
            Log::add('Telegram sendMessage failed: ' . $desc, Log::WARNING, 'com_ordenproduccion');
        } catch (\Throwable $e) {
        }

        return ['ok' => false, 'description' => $desc, 'http_code' => $httpCode];
    }
}
