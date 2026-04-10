<?php

/**
 * Low-level Telegram Bot API (sendMessage).
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
     * @return  array{ok:bool,description?:string,error?:string}
     */
    public static function sendMessage(string $botToken, string $chatId, string $text): array
    {
        $botToken = trim($botToken);
        $chatId   = trim($chatId);
        if ($botToken === '' || $chatId === '' || $text === '') {
            return ['ok' => false, 'error' => 'empty'];
        }

        $url = 'https://api.telegram.org/bot' . rawurlencode($botToken) . '/sendMessage';

        try {
            $http = HttpFactory::getHttp();
            $response = $http->post($url, [
                'chat_id' => $chatId,
                'text'    => $text,
            ]);

            $code = $response->code ?? 0;
            $body = $response->body ?? '';
            $data = json_decode($body, true);
            if (\is_array($data) && !empty($data['ok'])) {
                return ['ok' => true];
            }

            $desc = \is_array($data) ? (string) ($data['description'] ?? '') : '';
            if ($desc === '' && $body !== '') {
                $desc = substr($body, 0, 500);
            }

            try {
                Log::add('Telegram sendMessage failed: HTTP ' . $code . ' ' . $desc, Log::WARNING, 'com_ordenproduccion');
            } catch (\Throwable $e) {
            }

            return ['ok' => false, 'description' => $desc];
        } catch (\Throwable $e) {
            try {
                Log::add('Telegram sendMessage exception: ' . $e->getMessage(), Log::ERROR, 'com_ordenproduccion');
            } catch (\Throwable $e2) {
            }

            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
