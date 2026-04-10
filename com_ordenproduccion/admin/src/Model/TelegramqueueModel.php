<?php

/**
 * Admin: Telegram outbound queue (pending) and sent log.
 *
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Model;

defined('_JEXEC') or die;

use Grimpsa\Component\Ordenproduccion\Site\Helper\TelegramQueueHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Telegram queue / sent log model.
 *
 * @since  3.109.0
 */
class TelegramqueueModel extends BaseDatabaseModel
{
    /**
     * @var    int
     * @since  3.109.0
     */
    public const LIST_LIMIT = 200;

    /**
     * @return  bool
     *
     * @since 3.109.0
     */
    public function isQueueTablePresent(): bool
    {
        try {
            $db = $this->getDatabase();

            return TelegramQueueHelper::telegramQueueTableExists($db);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return  bool
     *
     * @since   3.109.0
     */
    public function isSentLogTablePresent(): bool
    {
        try {
            $db = $this->getDatabase();

            return TelegramQueueHelper::telegramSentLogTableExists($db);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Pending rows (FIFO order).
     *
     * @return  array<int, object>
     *
     * @since   3.109.0
     */
    public function getPendingItems(): array
    {
        if (!$this->isQueueTablePresent()) {
            return [];
        }

        try {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_telegram_queue'))
                    ->order($db->quoteName('id') . ' ASC')
                    ->setLimit(self::LIST_LIMIT)
            );

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Recently sent rows (newest first).
     *
     * @return  array<int, object>
     *
     * @since   3.109.0
     */
    public function getSentItems(): array
    {
        if (!$this->isSentLogTablePresent()) {
            return [];
        }

        try {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ordenproduccion_telegram_sent_log'))
                    ->order($db->quoteName('sent_at') . ' DESC')
                    ->setLimit(self::LIST_LIMIT)
            );

            return $db->loadObjectList() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @return  int
     *
     * @since   3.109.0
     */
    public function getPendingCount(): int
    {
        if (!$this->isQueueTablePresent()) {
            return 0;
        }

        try {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_telegram_queue'))
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return  int
     *
     * @since   3.109.0
     */
    public function getSentCount(): int
    {
        if (!$this->isSentLogTablePresent()) {
            return 0;
        }

        try {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__ordenproduccion_telegram_sent_log'))
            );

            return (int) $db->loadResult();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
