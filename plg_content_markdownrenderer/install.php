<?php
/**
 * @package     Grimpsa\Plugin\Content\Markdownrenderer
 * @subpackage  Installer
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Installation script for Markdown Renderer plugin
 *
 * @since  1.0.0
 */
class PlgContentMarkdownrendererInstallerScript extends InstallerScript
{
    /**
     * Minimum Joomla version
     *
     * @var    string
     * @since  1.0.0
     */
    protected $minimumJoomla = '5.0';

    /**
     * Minimum PHP version
     *
     * @var    string
     * @since  1.0.0
     */
    protected $minimumPhp = '8.1';

    /**
     * Postflight installation
     *
     * @param   InstallerScriptInterface  $installer  The class calling this method
     * @param   string                    $type       The type of change (install, update, discover_install)
     *
     * @return  boolean  True on success
     *
     * @since   1.0.0
     */
    public function postflight($type, $installer): bool
    {
        // Enable the plugin
        if ($type === 'install') {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('element') . ' = ' . $db->quote('markdownrenderer'))
                ->where($db->quoteName('folder') . ' = ' . $db->quote('content'));
            
            $db->setQuery($query);
            $db->execute();
        }

        return true;
    }
}

