<?php
/**
 * Test language loading for com_ordenproduccion
 */

// Define _JEXEC to allow execution
define('_JEXEC', 1);

// Load Joomla framework
require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

// Initialize Joomla application
$app = \Joomla\CMS\Factory::getApplication('site');

echo "<h2>🔍 Language Loading Test</h2>";
echo "<p>Timestamp: " . date('Y-m-d H:i:s') . "</p>";

// Test 1: Check current language
echo "<h3>1. Current Language</h3>";
$lang = $app->getLanguage();
echo "Current language: " . $lang->getTag() . "<br>";
echo "Language name: " . $lang->getName() . "<br>";

// Test 2: Check if component language is loaded
echo "<h3>2. Component Language Loading</h3>";
try {
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/site/language');
    echo "✅ Component language loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Component language loading failed: " . $e->getMessage() . "<br>";
}

// Test 3: Test specific language strings
echo "<h3>3. Language String Tests</h3>";
$testStrings = [
    'COM_ORDENPRODUCCION_ORDEN_TITLE',
    'COM_ORDENPRODUCCION_ORDEN_INFO_CLIENTE',
    'COM_ORDENPRODUCCION_ORDEN_INFO_TRABAJO',
    'COM_ORDENPRODUCCION_ORDEN_INFO_PRODUCCION',
    'COM_ORDENPRODUCCION_ORDEN_CORTE',
    'COM_ORDENPRODUCCION_ORDEN_BLOQUEADO',
    'COM_ORDENPRODUCCION_ORDEN_DOBLADO',
    'COM_ORDENPRODUCCION_ORDEN_LAMINADO',
    'COM_ORDENPRODUCCION_ORDEN_NUMERADO',
    'COM_ORDENPRODUCCION_ORDEN_TROQUEL',
    'COM_ORDENPRODUCCION_ORDEN_BARNIZ'
];

foreach ($testStrings as $string) {
    $translated = \Joomla\CMS\Language\Text::_($string);
    echo "String: $string<br>";
    echo "Translated: $translated<br>";
    echo "Is translated: " . ($translated !== $string ? 'Yes' : 'No') . "<br><br>";
}

// Test 4: Check language file existence
echo "<h3>4. Language File Existence</h3>";
$languageFiles = [
    JPATH_ROOT . '/components/com_ordenproduccion/site/language/es-ES/com_ordenproduccion.ini',
    JPATH_ROOT . '/components/com_ordenproduccion/site/language/en-GB/com_ordenproduccion.ini',
    JPATH_ROOT . '/language/es-ES/com_ordenproduccion.ini',
    JPATH_ROOT . '/language/en-GB/com_ordenproduccion.ini'
];

foreach ($languageFiles as $file) {
    if (file_exists($file)) {
        echo "✅ Found: " . $file . "<br>";
        echo "File size: " . filesize($file) . " bytes<br>";
    } else {
        echo "❌ Not found: " . $file . "<br>";
    }
}

// Test 5: Check if we need to load from admin language
echo "<h3>5. Admin Language Loading</h3>";
try {
    $lang->load('com_ordenproduccion', JPATH_ROOT . '/components/com_ordenproduccion/admin/language');
    echo "✅ Admin component language loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Admin component language loading failed: " . $e->getMessage() . "<br>";
}

// Test 6: Test strings after admin language load
echo "<h3>6. Language String Tests After Admin Load</h3>";
foreach ($testStrings as $string) {
    $translated = \Joomla\CMS\Language\Text::_($string);
    echo "String: $string<br>";
    echo "Translated: $translated<br>";
    echo "Is translated: " . ($translated !== $string ? 'Yes' : 'No') . "<br><br>";
}

echo "<h3>Recommendations</h3>";
echo "<ul>";
echo "<li>Check if language files are in the correct location</li>";
echo "<li>Verify language loading in the view</li>";
echo "<li>Check if we need to load both site and admin language files</li>";
echo "<li>Verify the language file content and encoding</li>";
echo "</ul>";
?>
