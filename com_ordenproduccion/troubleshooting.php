<?php
/**
 * Wrapper — forwards to Joomla root Verificar pago diagnostic.
 *
 * Sourcerer: use ONLY one require line (do not paste the full root troubleshooting.php).
 *   require JPATH_ROOT . '/troubleshooting.php';
 * or:
 *   require JPATH_ROOT . '/components/com_ordenproduccion/troubleshooting.php';
 */

defined('_JEXEC') or die;

$rootFile = (defined('JPATH_ROOT') ? JPATH_ROOT : dirname(__DIR__, 2)) . '/troubleshooting.php';

if (!is_file($rootFile)) {
    echo '<p style="color:#c62828;">troubleshooting.php not found at Joomla root. Run deploy (update_build_simple.sh).</p>';
    return;
}

require $rootFile;
