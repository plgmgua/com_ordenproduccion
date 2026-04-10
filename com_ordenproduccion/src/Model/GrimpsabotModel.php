<?php

/**
 * Frontend Telegram (Grimpsa bot) settings and per-user chat_id.
 *
 * @package     Grimpsa\Component\Ordenproduccion\Site\Model
 * @since       3.105.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\CMS\Table\Extension as TableExtension;
use Joomla\Registry\Registry;
use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramNotificationHelper;

/**
 * Grimpsabot model.
 */
class GrimpsabotModel extends FormModel
{
    /**
     * @param   array  $data  Data for the form
     *
     * @return  Form|bool
     *
     * @throws  \Exception
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_ordenproduccion.grimpsabot', 'grimpsabot', ['control' => 'jform', 'load_data' => $loadData]);

        return $form ?: false;
    }

    /**
     * @return  mixed
     */
    protected function loadFormData()
    {
        $params = ComponentHelper::getParams('com_ordenproduccion');
        $data   = $params->toArray();
        $user   = Factory::getUser();

        if (
            !\array_key_exists('telegram_broadcast_invoice', $data)
            && !\array_key_exists('telegram_broadcast_envio', $data)
            && !empty($data['telegram_broadcast_enabled'])
        ) {
            $data['telegram_broadcast_invoice'] = 1;
            $data['telegram_broadcast_envio']   = 1;
        }

        if (!$user->guest && TelegramNotificationHelper::telegramUsersTableExists($this->getDatabase())) {
            $cid = TelegramNotificationHelper::getChatIdForUser((int) $user->id);
            $data['telegram_chat_id'] = $cid ?? '';
        }

        return $data;
    }

    /**
     * Persist component params (Administración / superuser only — checked in controller).
     *
     * @param   array<string,mixed>  $data  Submitted jform
     *
     * @return  bool
     */
    public function saveComponentParams(array $data): bool
    {
        $db    = $this->getDatabase();
        $table = new TableExtension($db);
        if (!$table->load(['type' => 'component', 'element' => 'com_ordenproduccion'])) {
            $this->setError('Extension not found');

            return false;
        }

        $registry = new Registry($table->params);
        $existing = $registry->toArray();

        foreach (['telegram_enabled', 'telegram_notify_invoice', 'telegram_notify_envio'] as $k) {
            if (isset($data[$k])) {
                $registry->set($k, (int) $data[$k]);
            }
        }

        if (isset($data['telegram_broadcast_invoice'])) {
            $registry->set('telegram_broadcast_invoice', (int) $data['telegram_broadcast_invoice'] ? 1 : 0);
        }
        if (isset($data['telegram_broadcast_envio'])) {
            $registry->set('telegram_broadcast_envio', (int) $data['telegram_broadcast_envio'] ? 1 : 0);
        }

        if (!empty($data['telegram_bot_token'])) {
            $registry->set('telegram_bot_token', trim((string) $data['telegram_bot_token']));
        } elseif (!empty($existing['telegram_bot_token'])) {
            $registry->set('telegram_bot_token', $existing['telegram_bot_token']);
        }

        if (!empty($data['telegram_queue_cron_key'])) {
            $registry->set('telegram_queue_cron_key', trim((string) $data['telegram_queue_cron_key']));
        } elseif (!empty($existing['telegram_queue_cron_key'])) {
            $registry->set('telegram_queue_cron_key', $existing['telegram_queue_cron_key']);
        }

        foreach (['telegram_message_invoice', 'telegram_message_envio'] as $msgKey) {
            if (\array_key_exists($msgKey, $data)) {
                $registry->set($msgKey, trim((string) $data[$msgKey]));
            }
        }

        if (\array_key_exists('telegram_broadcast_chat_id', $data)) {
            $registry->set('telegram_broadcast_chat_id', trim((string) $data['telegram_broadcast_chat_id']));
        }

        $table->params = $registry->toString();

        if (!$table->check() || !$table->store()) {
            $this->setError($table->getError());

            return false;
        }

        return true;
    }

    /**
     * Save current user's Telegram chat_id.
     *
     * @param   string  $chatId  Numeric chat id as string
     *
     * @return  bool
     */
    public function saveUserChatId(string $chatId): bool
    {
        $user = Factory::getUser();
        if ($user->guest) {
            return false;
        }

        $db = $this->getDatabase();
        if (!TelegramNotificationHelper::telegramUsersTableExists($db)) {
            $this->setError('Table missing');

            return false;
        }

        $chatId = trim($chatId);
        $uid    = (int) $user->id;
        $now    = Factory::getDate()->toSql();

        if ($chatId === '') {
            $db->setQuery(
                $db->getQuery(true)
                    ->delete($db->quoteName('#__ordenproduccion_telegram_users'))
                    ->where($db->quoteName('user_id') . ' = ' . $uid)
            );
            $db->execute();

            return true;
        }

        if (!preg_match('/^-?\d{1,32}$/', $chatId)) {
            $this->setError('Invalid chat id');

            return false;
        }

        $db->setQuery(
            $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ordenproduccion_telegram_users'))
                ->where($db->quoteName('user_id') . ' = ' . $uid)
        );
        $exists = (int) $db->loadResult() > 0;

        if ($exists) {
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__ordenproduccion_telegram_users'))
                    ->set($db->quoteName('chat_id') . ' = ' . $db->quote($chatId))
                    ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                    ->where($db->quoteName('user_id') . ' = ' . $uid)
            );
            $db->execute();
        } else {
            $o = (object) [
                'user_id'  => $uid,
                'chat_id'  => $chatId,
                'created'  => $now,
                'modified' => null,
            ];
            $db->insertObject('#__ordenproduccion_telegram_users', $o);
        }

        return true;
    }
}
