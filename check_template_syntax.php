<?php
/**
 * Check if template file has syntax errors
 */

echo '<h1>Template Syntax Check</h1>';

$templatePath = '/var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/orden/default.php';

echo '<p><strong>File:</strong> ' . htmlspecialchars($templatePath) . '</p>';

if (!file_exists($templatePath)) {
    echo '<p style="color: red;">❌ File does NOT exist!</p>';
    exit;
}

echo '<p style="color: green;">✅ File exists</p>';

// Check file modification time
$modTime = filemtime($templatePath);
echo '<p><strong>Last Modified:</strong> ' . date('Y-m-d H:i:s', $modTime) . '</p>';

// Run PHP syntax check
$output = [];
$returnVar = 0;
exec('php -l ' . escapeshellarg($templatePath) . ' 2>&1', $output, $returnVar);

echo '<p><strong>Syntax Check Result:</strong></p>';
echo '<pre>' . htmlspecialchars(implode("\n", $output)) . '</pre>';

if ($returnVar === 0) {
    echo '<p style="color: green;">✅ No syntax errors!</p>';
} else {
    echo '<p style="color: red;">❌ Syntax errors found!</p>';
}

// Show first 30 lines of the file
echo '<h2>First 30 Lines of Template:</h2>';
$lines = file($templatePath);
echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto;">';
for ($i = 0; $i < min(30, count($lines)); $i++) {
    printf("%3d| %s", $i + 1, htmlspecialchars($lines[$i]));
}
echo '</pre>';

// Check for 'use' statements inside try-catch
echo '<h2>Checking for "use" Statements:</h2>';
$content = file_get_contents($templatePath);
$usePosition = strpos($content, 'use Joomla');
$tryPosition = strpos($content, 'try {');

if ($usePosition !== false && $tryPosition !== false) {
    if ($usePosition < $tryPosition) {
        echo '<p style="color: green;">✅ "use" statements come BEFORE try-catch (correct!)</p>';
    } else {
        echo '<p style="color: red;">❌ "use" statements come AFTER try-catch (syntax error!)</p>';
    }
}

