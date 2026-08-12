<?php
/**
 * Wrapper — forwards to Joomla root troubleshooting hub.
 *
 * Sourcerer (recommended): create a Joomla article and put ONLY this inside Sourcerer php tags:
 *   require JPATH_ROOT . '/troubleshooting.php';
 *
 * Alternative (same result):
 *   require JPATH_ROOT . '/components/com_ordenproduccion/troubleshooting.php';
 *
 * Do NOT paste the full root troubleshooting.php into the article.
 */

defined('_JEXEC') or die;

$rootFile = (defined('JPATH_ROOT') ? JPATH_ROOT : dirname(__DIR__, 2)) . '/troubleshooting.php';

if (!is_file($rootFile)) {
    echo '<p style="color:#c62828;">troubleshooting.php not found at Joomla root. Run deploy (update_build_simple.sh).</p>';
    return;
}

require $rootFile;
