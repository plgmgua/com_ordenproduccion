<?php
/**
 * @package     Grimpsa.Plugin.System.OpImpersonate
 * @subpackage  Installer
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\Database\DatabaseInterface;

/**
 * @since  3.119.194
 */
class PlgSystemOpImpersonateInstallerScript extends InstallerScript
{
    protected $minimumJoomla = '5.0';

    protected $minimumPhp = '8.1';

    /**
     * @param   string  $type
     *
     * @return  bool
     */
    public function postflight($type, $installer): bool
    {
        if (!\in_array($type, ['install', 'discover_install', 'update'], true)) {
            return true;
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('op_impersonate'))
            )->execute();
        } catch (\Throwable $e) {
            // Non-blocking
        }

        return true;
    }
}
