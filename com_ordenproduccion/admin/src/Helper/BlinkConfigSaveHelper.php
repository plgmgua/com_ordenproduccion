<?php
/**
 * Preserve Blink password fields when saving Global Configuration (com_config).
 *
 * @package     com_ordenproduccion
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Extension\BeforeSaveEvent;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Mirrors Blink secrets into #__ordenproduccion_config and keeps blank password inputs on save.
 *
 * @since  3.119.135
 */
class BlinkConfigSaveHelper
{
    private const SECRET_KEYS = [
        'blink_api_key',
        'blink_paybi_clave',
    ];

    /**
     * @param   BeforeSaveEvent  $event
     *
     * @return  void
     *
     * @since   3.119.135
     */
    public static function onExtensionBeforeSave(BeforeSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_config.component') {
            return;
        }

        $table = $event->getItem();
        if (!\is_object($table) || ($table->element ?? '') !== 'com_ordenproduccion') {
            return;
        }

        $existing = self::loadExistingParams();
        $incoming = new Registry($table->params ?? '');

        foreach (self::SECRET_KEYS as $key) {
            $newVal = trim((string) $incoming->get($key, ''));
            if ($newVal === '') {
                $oldVal = trim((string) $existing->get($key, ''));
                if ($oldVal !== '') {
                    $incoming->set($key, $oldVal);
                }
            }
        }

        $table->params = $incoming->toString();

        foreach (self::SECRET_KEYS as $key) {
            $val = trim((string) $incoming->get($key, ''));
            if ($val !== '') {
                self::upsertConfigValue($key, $val);
            }
        }
    }

    /**
     * @return  Registry
     */
    private static function loadExistingParams(): Registry
    {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('component'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('com_ordenproduccion'))
            );

            return new Registry((string) $db->loadResult());
        } catch (\Throwable $e) {
            return new Registry();
        }
    }

    /**
     * @param   string  $settingKey
     * @param   string  $value
     *
     * @return  void
     */
    private static function upsertConfigValue(string $settingKey, string $value): void
    {
        try {
            $db   = Factory::getContainer()->get(DatabaseInterface::class);
            $user = Factory::getUser();
            $now  = Factory::getDate()->toSql();

            $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__ordenproduccion_config'))
                    ->where($db->quoteName('setting_key') . ' = ' . $db->quote($settingKey))
            );
            $id = $db->loadResult();

            if ($id) {
                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__ordenproduccion_config'))
                        ->set($db->quoteName('setting_value') . ' = ' . $db->quote($value))
                        ->set($db->quoteName('modified') . ' = ' . $db->quote($now))
                        ->set($db->quoteName('modified_by') . ' = ' . (int) $user->id)
                        ->where($db->quoteName('id') . ' = ' . (int) $id)
                );
            } else {
                $db->setQuery(
                    $db->getQuery(true)
                        ->insert($db->quoteName('#__ordenproduccion_config'))
                        ->columns([
                            $db->quoteName('setting_key'),
                            $db->quoteName('setting_value'),
                            $db->quoteName('state'),
                            $db->quoteName('created_by'),
                            $db->quoteName('modified'),
                            $db->quoteName('modified_by'),
                        ])
                        ->values(
                            $db->quote($settingKey) . ',' .
                            $db->quote($value) . ',1,' .
                            (int) $user->id . ',' .
                            $db->quote($now) . ',' .
                            (int) $user->id
                        )
                );
            }

            $db->execute();
        } catch (\Throwable $e) {
        }
    }
}
