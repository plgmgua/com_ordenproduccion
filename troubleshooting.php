<?php
/**
 * Joomla root entry — forwards to the component troubleshooting hub.
 *
 * Deploy copies this file to JPATH_ROOT/troubleshooting.php.
 * Canonical implementation: components/com_ordenproduccion/troubleshooting.php
 *
 * Sourcerer (recommended):
 *   require JPATH_ROOT . '/components/com_ordenproduccion/troubleshooting.php';
 */

declare(strict_types=1);

$componentFile = (defined('JPATH_ROOT') ? JPATH_ROOT : __DIR__)
    . '/components/com_ordenproduccion/troubleshooting.php';

if (!is_file($componentFile)) {
    echo '<p style="color:#c62828;">troubleshooting.php not found at '
        . htmlspecialchars($componentFile)
        . '. Deploy com_ordenproduccion 3.119.346+.</p>';
    return;
}

require $componentFile;
