<?php
/**
 * Install FPDF library for PDF generation
 */

// Set proper headers
header('Content-Type: text/plain');

try {
    echo "=== FPDF LIBRARY INSTALLATION ===\n";
    echo "Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    $joomlaRoot = '/var/www/grimpsa_webserver';
    $fpdfPath = $joomlaRoot . '/libraries/fpdf';
    
    echo "1. Checking FPDF library...\n";
    
    if (is_dir($fpdfPath)) {
        echo "✅ FPDF directory exists: $fpdfPath\n";
        
        if (file_exists($fpdfPath . '/fpdf.php')) {
            echo "✅ FPDF library already installed\n";
            echo "File: " . $fpdfPath . "/fpdf.php\n";
            echo "Size: " . filesize($fpdfPath . '/fpdf.php') . " bytes\n";
        } else {
            echo "❌ FPDF library file not found\n";
        }
    } else {
        echo "❌ FPDF directory not found: $fpdfPath\n";
        echo "Creating directory...\n";
        
        if (mkdir($fpdfPath, 0755, true)) {
            echo "✅ Directory created successfully\n";
        } else {
            echo "❌ Failed to create directory\n";
            exit;
        }
    }
    
    echo "\n2. Downloading FPDF library...\n";
    
    // Download FPDF from GitHub
    $fpdfUrl = 'https://github.com/Setasign/FPDF/archive/refs/heads/master.zip';
    $zipFile = '/tmp/fpdf.zip';
    
    echo "Downloading from: $fpdfUrl\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fpdfUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; FPDF Installer)');
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $data) {
        file_put_contents($zipFile, $data);
        echo "✅ FPDF downloaded successfully\n";
        echo "File size: " . filesize($zipFile) . " bytes\n";
    } else {
        echo "❌ Failed to download FPDF (HTTP Code: $httpCode)\n";
        exit;
    }
    
    echo "\n3. Extracting FPDF library...\n";
    
    $zip = new ZipArchive();
    if ($zip->open($zipFile) === TRUE) {
        // Extract to temporary directory
        $tempDir = '/tmp/fpdf_extract';
        if (is_dir($tempDir)) {
            exec("rm -rf $tempDir");
        }
        mkdir($tempDir, 0755, true);
        
        $zip->extractTo($tempDir);
        $zip->close();
        
        echo "✅ FPDF extracted successfully\n";
        
        // Copy fpdf.php to libraries directory
        $sourceFile = $tempDir . '/FPDF-master/fpdf.php';
        $targetFile = $fpdfPath . '/fpdf.php';
        
        if (file_exists($sourceFile)) {
            if (copy($sourceFile, $targetFile)) {
                echo "✅ FPDF library installed successfully\n";
                echo "Target: $targetFile\n";
                echo "Size: " . filesize($targetFile) . " bytes\n";
            } else {
                echo "❌ Failed to copy FPDF library\n";
            }
        } else {
            echo "❌ FPDF source file not found: $sourceFile\n";
        }
        
        // Clean up
        exec("rm -rf $tempDir");
        unlink($zipFile);
        
    } else {
        echo "❌ Failed to extract FPDF library\n";
    }
    
    echo "\n4. Testing FPDF library...\n";
    
    if (file_exists($fpdfPath . '/fpdf.php')) {
        require_once $fpdfPath . '/fpdf.php';
        echo "✅ FPDF library loaded successfully\n";
        
        // Test creating a simple PDF
        try {
            $pdf = new FPDF();
            echo "✅ FPDF class instantiated successfully\n";
        } catch (Exception $e) {
            echo "❌ FPDF class error: " . $e->getMessage() . "\n";
        }
    } else {
        echo "❌ FPDF library file not found after installation\n";
    }
    
    echo "\n=== FPDF INSTALLATION COMPLETE ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . basename($e->getFile()) . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>
