<?php
/**
 * Template Syntax Checker and Component Diagnostics
 * 
 * URL: https://grimpsa_webserver.grantsolutions.cc/fix_production_component.php
 */

echo '<html><head><title>Template Syntax Check</title></head><body>';
echo '<div style="font-family: Arial; margin: 20px; max-width: 1200px;">';
echo '<h1>🔍 Template Syntax Check & Component Diagnostics</h1>';
echo '<hr>';

// Test 1: Check template file exists
echo '<h2>Test 1: Template File Existence</h2>';
$templatePath = '/var/www/grimpsa_webserver/components/com_ordenproduccion/tmpl/orden/default.php';

echo '<p><strong>File:</strong> ' . htmlspecialchars($templatePath) . '</p>';

if (!file_exists($templatePath)) {
    echo '<p style="color: red;">❌ File does NOT exist!</p>';
    echo '<p>This means the template was not deployed. Run: ./update_build_simple.sh</p>';
} else {
    echo '<p style="color: green;">✅ File exists</p>';
    
    // Test 2: File modification time
    echo '<h2>Test 2: File Modification Time</h2>';
    $modTime = filemtime($templatePath);
    $timeAgo = time() - $modTime;
    echo '<p><strong>Last Modified:</strong> ' . date('Y-m-d H:i:s', $modTime) . '</p>';
    echo '<p><strong>Time Ago:</strong> ';
    if ($timeAgo < 60) {
        echo '<span style="color: green;">' . $timeAgo . ' seconds ago (RECENT!)</span>';
    } elseif ($timeAgo < 3600) {
        echo '<span style="color: orange;">' . round($timeAgo / 60) . ' minutes ago</span>';
    } else {
        echo '<span style="color: red;">' . round($timeAgo / 3600) . ' hours ago (OLD!)</span>';
    }
    echo '</p>';
    
    // Test 3: PHP syntax check
    echo '<h2>Test 3: PHP Syntax Check</h2>';
    $output = [];
    $returnVar = 0;
    exec('php -l ' . escapeshellarg($templatePath) . ' 2>&1', $output, $returnVar);
    
    echo '<p><strong>Command:</strong> <code>php -l ' . htmlspecialchars($templatePath) . '</code></p>';
    echo '<p><strong>Result:</strong></p>';
    echo '<pre style="background: #f5f5f5; padding: 10px;">' . htmlspecialchars(implode("\n", $output)) . '</pre>';
    
    if ($returnVar === 0) {
        echo '<p style="color: green; font-size: 18px; font-weight: bold;">✅ NO SYNTAX ERRORS!</p>';
    } else {
        echo '<p style="color: red; font-size: 18px; font-weight: bold;">❌ SYNTAX ERRORS FOUND!</p>';
        echo '<p>The template file has PHP syntax errors. This is why you get 500 error.</p>';
    }
    
    // Test 4: Show first 35 lines of the file
    echo '<h2>Test 4: Template File Content (First 35 Lines)</h2>';
    $lines = file($templatePath);
    echo '<pre style="background: #f5f5f5; padding: 10px; overflow: auto; border: 1px solid #ccc;">';
    for ($i = 0; $i < min(35, count($lines)); $i++) {
        $lineNum = $i + 1;
        $highlight = '';
        
        // Highlight important lines
        if (strpos($lines[$i], 'use Joomla') !== false) {
            $highlight = ' style="background: yellow;"';
        } elseif (strpos($lines[$i], 'try {') !== false) {
            $highlight = ' style="background: lightblue;"';
        }
        
        printf("<span%s>%3d| %s</span>", $highlight, $lineNum, htmlspecialchars($lines[$i]));
    }
    echo '</pre>';
    echo '<p><em>Yellow = "use" statements, Light Blue = try-catch blocks</em></p>';
    
    // Test 5: Check for 'use' statements inside try-catch
    echo '<h2>Test 5: "use" Statement Position Check</h2>';
    $content = file_get_contents($templatePath);
    
    // Find all 'use' statements
    preg_match_all('/use\s+Joomla.*?;/m', $content, $useMatches, PREG_OFFSET_CAPTURE);
    
    // Find all try blocks
    preg_match_all('/try\s*\{/m', $content, $tryMatches, PREG_OFFSET_CAPTURE);
    
    if (!empty($useMatches[0])) {
        echo '<p>Found ' . count($useMatches[0]) . ' "use" statement(s)</p>';
        
        // Get position of first use statement
        $firstUsePos = $useMatches[0][0][1];
        
        // Get position of first try block
        $firstTryPos = !empty($tryMatches[0]) ? $tryMatches[0][0][1] : PHP_INT_MAX;
        
        if ($firstUsePos < $firstTryPos) {
            echo '<p style="color: green; font-size: 18px; font-weight: bold;">✅ "use" statements come BEFORE try-catch (CORRECT!)</p>';
            echo '<p>The template structure is correct.</p>';
        } else {
            echo '<p style="color: red; font-size: 18px; font-weight: bold;">❌ "use" statements come AFTER try-catch (SYNTAX ERROR!)</p>';
            echo '<p><strong>This is the bug!</strong> In PHP, "use" statements must be at the top of the file, BEFORE any try-catch blocks.</p>';
            echo '<p>The fix is to move all "use" statements to the top, right after "defined(\'_JEXEC\') or die;"</p>';
        }
    } else {
        echo '<p style="color: orange;">⚠️ No "use" statements found</p>';
    }
    
    // Test 6: File permissions
    echo '<h2>Test 6: File Permissions</h2>';
    $perms = fileperms($templatePath);
    $permsString = substr(sprintf('%o', $perms), -4);
    echo '<p><strong>Permissions:</strong> ' . $permsString . '</p>';
    
    if (is_readable($templatePath)) {
        echo '<p style="color: green;">✅ File is readable</p>';
    } else {
        echo '<p style="color: red;">❌ File is NOT readable</p>';
    }
    
    // Test 7: File size
    echo '<h2>Test 7: File Size</h2>';
    $fileSize = filesize($templatePath);
    echo '<p><strong>File Size:</strong> ' . number_format($fileSize) . ' bytes (' . round($fileSize / 1024, 2) . ' KB)</p>';
    
    if ($fileSize < 100) {
        echo '<p style="color: red;">❌ File is very small - might be corrupted or empty</p>';
    } else {
        echo '<p style="color: green;">✅ File size looks normal</p>';
    }
}

// Test 8: Check OrdenModel file
echo '<h2>Test 8: OrdenModel File Check</h2>';
$modelPath = '/var/www/grimpsa_webserver/components/com_ordenproduccion/src/Model/OrdenModel.php';

if (file_exists($modelPath)) {
    echo '<p style="color: green;">✅ OrdenModel.php exists</p>';
    
    // Check for getEAVData method
    $modelContent = file_get_contents($modelPath);
    if (strpos($modelContent, 'function getEAVData') !== false) {
        echo '<p style="color: green;">✅ getEAVData() method found</p>';
        
        // Check if it uses English column names
        if (strpos($modelContent, "'attribute_name'") !== false || strpos($modelContent, '"attribute_name"') !== false) {
            echo '<p style="color: green;">✅ Uses ENGLISH column names (correct!)</p>';
        } elseif (strpos($modelContent, "'tipo_de_campo'") !== false || strpos($modelContent, '"tipo_de_campo"') !== false) {
            echo '<p style="color: red;">❌ Uses SPANISH column names (wrong!)</p>';
            echo '<p>The model needs to be updated to use English column names.</p>';
        }
    } else {
        echo '<p style="color: orange;">⚠️ getEAVData() method not found</p>';
    }
} else {
    echo '<p style="color: red;">❌ OrdenModel.php NOT found</p>';
}

// Test 9: Component version check
echo '<h2>Test 9: Component Version Check</h2>';
$manifestPath = '/var/www/grimpsa_webserver/administrator/components/com_ordenproduccion/com_ordenproduccion.xml';

if (file_exists($manifestPath)) {
    $xmlContent = file_get_contents($manifestPath);
    if (preg_match('/<version>(.*?)<\/version>/', $xmlContent, $versionMatch)) {
        echo '<p><strong>Installed Version:</strong> ' . htmlspecialchars($versionMatch[1]) . '</p>';
        echo '<p><strong>Expected Version:</strong> 2.0.21-STABLE or higher</p>';
        
        if (version_compare($versionMatch[1], '2.0.21', '>=')) {
            echo '<p style="color: green;">✅ Version is up to date</p>';
        } else {
            echo '<p style="color: orange;">⚠️ Version might be outdated</p>';
        }
    }
}

echo '<hr>';
echo '<h2 style="color: #1976d2;">📋 SUMMARY & RECOMMENDATIONS</h2>';
echo '<div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #1976d2;">';
echo '<p><strong>Next Steps:</strong></p>';
echo '<ol>';
echo '<li>If Test 3 shows syntax errors → Deploy the latest code with <code>./update_build_simple.sh</code></li>';
echo '<li>If Test 5 shows "use" statements AFTER try-catch → Deploy the fix</li>';
echo '<li>If Test 8 shows Spanish column names → Deploy the latest model fix</li>';
echo '<li>After deployment, access the order view: <a href="/index.php/component/ordenproduccion/?view=orden&id=1401">/index.php/component/ordenproduccion/?view=orden&id=1401</a></li>';
echo '</ol>';
echo '</div>';

echo '</div></body></html>';
