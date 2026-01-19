<?php
/**
 * Comprehensive Troubleshooting & Validation Script for com_ordenproduccion
 * Validates component functionality, especially bank dropdown population
 */

// Define _JEXEC if not already defined (may be defined by Joomla framework)
if (!defined('_JEXEC')) {
    define('_JEXEC', 1);
}

// Detect JPATH_ROOT automatically
if (!defined('JPATH_ROOT')) {
    // Try multiple common locations
    $possibleRoots = [
        __DIR__,                      // Same directory
        dirname(__DIR__),             // Parent directory
        dirname(dirname(__DIR__)),    // Grandparent directory
        '/var/www/grimpsa_webserver', // Common production path
    ];
    
    foreach ($possibleRoots as $path) {
        if (file_exists($path . '/includes/defines.php')) {
            define('JPATH_ROOT', $path);
            break;
        }
    }
    
    if (!defined('JPATH_ROOT')) {
        die("ERROR: Cannot find Joomla root directory. Please set JPATH_ROOT manually.");
    }
}

require_once JPATH_ROOT . '/includes/defines.php';
require_once JPATH_ROOT . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

$app = Factory::getApplication('site');
$db = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);

// CSS styling
echo "<!DOCTYPE html>
<html><head>
<meta charset='UTF-8'>
<title>Bank Dropdown Validation - com_ordenproduccion</title>
<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    margin: 20px; 
    background: #f5f5f5; 
    font-size: 13px; 
    line-height: 1.6;
}
.container { 
    max-width: 1200px; 
    margin: 0 auto; 
    background: white; 
    padding: 20px; 
    border-radius: 8px; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
h2 { color: #0073aa; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 5px; }
h3 { color: #555; margin-top: 20px; }
table { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 15px 0; 
    background: white;
    border: 1px solid #ddd;
}
th { 
    background: #0073aa; 
    color: white; 
    padding: 10px; 
    text-align: left; 
    font-size: 12px; 
    border: 1px solid #005177;
}
td { 
    padding: 8px 10px; 
    border: 1px solid #ddd; 
    font-size: 12px; 
    vertical-align: top;
}
.ok { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; font-weight: bold; }
.warning { background: #fff3cd; color: #856404; }
.info { background: #d1ecf1; color: #0c5460; }
.nodata { background: #e2e3e5; color: #666; }
code { 
    background: #f4f4f4; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace;
    font-size: 11px;
}
pre { 
    background: #f4f4f4; 
    padding: 10px; 
    border-radius: 4px; 
    overflow-x: auto;
    border: 1px solid #ddd;
    font-size: 11px;
}
.summary { 
    background: #f0f0f0; 
    padding: 15px; 
    margin: 15px 0; 
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}
.test-section {
    margin: 20px 0;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fafafa;
}
.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 10px;
}
.badge-ok { background: #28a745; color: white; }
.badge-error { background: #dc3545; color: white; }
.badge-warning { background: #ffc107; color: #000; }
</style>
</head><body>
<div class='container'>";

echo "<h1>🔍 Bank Dropdown Validation & Troubleshooting</h1>";
echo "<div class='summary'>";
echo "<strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "<strong>Joomla Root:</strong> " . JPATH_ROOT . "<br>";
echo "<strong>Component:</strong> com_ordenproduccion<br>";
echo "</div>";

$testResults = [];
$totalTests = 0;
$passedTests = 0;

// ============================================
// TEST 1: Database Table Exists
// ============================================
echo "<div class='test-section'>";
echo "<h2>Test 1: Database Table Validation</h2>";

$totalTests++;
$tableName = '#__ordenproduccion_banks';
$tableExists = false;

try {
    // Try to query the table directly - this will fail if table doesn't exist
    $query = $db->getQuery(true)
        ->select('COUNT(*)')
        ->from($db->quoteName($tableName));
    
    $db->setQuery($query);
    $count = $db->loadResult();
    
    // If we got here without exception, table exists
    $tableExists = true;
    echo "<p class='ok'>✅ Table <code>{$tableName}</code> exists in database</p>";
    echo "<p class='info'>Table contains <strong>{$count}</strong> total record(s)</p>";
    $passedTests++;
    $testResults['table_exists'] = true;
    
    // Get table structure by fetching a sample record
    try {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName($tableName));
        $query->setLimit(1, 0);
        $db->setQuery($query);
        $sample = $db->loadObject();
        
        if ($sample) {
            echo "<p class='info'>✅ Table structure validated - sample record retrieved successfully</p>";
            
            // Show column names
            $columns = array_keys((array) $sample);
            echo "<p class='info'>Table columns: <code>" . implode('</code>, <code>', $columns) . "</code></p>";
        } else {
            echo "<p class='warning'>⚠️ Table exists but is empty</p>";
        }
    } catch (\Exception $e2) {
        echo "<p class='warning'>⚠️ Could not retrieve sample record: " . htmlspecialchars($e2->getMessage()) . "</p>";
    }
    
} catch (\Exception $e) {
    // Table doesn't exist or other error
    $errorMsg = $e->getMessage();
    if (stripos($errorMsg, "doesn't exist") !== false || 
        stripos($errorMsg, "unknown table") !== false ||
        stripos($errorMsg, "table") !== false && stripos($errorMsg, "exist") !== false) {
        echo "<p class='error'>❌ Table <code>{$tableName}</code> does NOT exist in database</p>";
        echo "<p class='warning'>Action required: Run SQL migration script to create table</p>";
        echo "<p class='info'>Expected table name: <code>" . str_replace('#__', $db->getPrefix(), $tableName) . "</code></p>";
    } else {
        echo "<p class='error'>❌ Error checking table: " . htmlspecialchars($errorMsg) . "</p>";
    }
    $testResults['table_exists'] = false;
}

echo "</div>";

// ============================================
// TEST 2: Bank Data in Database
// ============================================
if ($tableExists) {
    echo "<div class='test-section'>";
    echo "<h2>Test 2: Bank Data Validation</h2>";
    
    $totalTests++;
    try {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($tableName))
            ->where($db->quoteName('state') . ' = 1');
        
        $db->setQuery($query);
        $activeBanksCount = (int) $db->loadResult();
        
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($tableName));
        
        $db->setQuery($query);
        $totalBanksCount = (int) $db->loadResult();
        
        echo "<table>";
        echo "<tr><th>Metric</th><th>Value</th><th>Status</th></tr>";
        echo "<tr class='" . ($totalBanksCount > 0 ? 'ok' : 'error') . "'>";
        echo "<td>Total Banks in Database</td>";
        echo "<td><strong>{$totalBanksCount}</strong></td>";
        echo "<td>" . ($totalBanksCount > 0 ? "✅ OK" : "❌ No banks found") . "</td>";
        echo "</tr>";
        echo "<tr class='" . ($activeBanksCount > 0 ? 'ok' : 'error') . "'>";
        echo "<td>Active Banks (state=1)</td>";
        echo "<td><strong>{$activeBanksCount}</strong></td>";
        echo "<td>" . ($activeBanksCount > 0 ? "✅ OK" : "❌ No active banks") . "</td>";
        echo "</tr>";
        echo "</table>";
        
        // Check for default bank
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($tableName))
            ->where($db->quoteName('is_default') . ' = 1')
            ->where($db->quoteName('state') . ' = 1');
        
        $db->setQuery($query);
        $defaultBankCount = (int) $db->loadResult();
        
        echo "<p class='" . ($defaultBankCount == 1 ? 'ok' : 'warning') . "'>";
        echo ($defaultBankCount == 1 ? "✅" : "⚠️") . " Default bank: <strong>{$defaultBankCount}</strong> ";
        if ($defaultBankCount == 0) {
            echo "(No default bank set)";
        } elseif ($defaultBankCount > 1) {
            echo "(Multiple default banks - should be only 1)";
        } else {
            // Get default bank code
            $query = $db->getQuery(true)
                ->select('code, name, name_es, name_en')
                ->from($db->quoteName($tableName))
                ->where($db->quoteName('is_default') . ' = 1')
                ->where($db->quoteName('state') . ' = 1');
            $query->setLimit(1, 0);
            $db->setQuery($query);
            $defaultBank = $db->loadObject();
            if ($defaultBank) {
                echo "(Code: <code>{$defaultBank->code}</code>, Name: " . 
                     htmlspecialchars($defaultBank->name_es ?: $defaultBank->name ?: $defaultBank->code) . ")";
            }
        }
        echo "</p>";
        
        if ($activeBanksCount > 0) {
            $passedTests++;
            $testResults['bank_data'] = true;
            
            // Show sample banks
            $query = $db->getQuery(true)
                ->select('id, code, name, name_es, name_en, ordering, is_default, state')
                ->from($db->quoteName($tableName))
                ->where($db->quoteName('state') . ' = 1')
                ->order($db->quoteName('ordering') . ' ASC, ' . $db->quoteName('id') . ' ASC');
            $query->setLimit(10, 0);
            $db->setQuery($query);
            $banks = $db->loadObjectList();
            
            if (!empty($banks)) {
                echo "<h3>Sample Banks (first 10 by ordering):</h3>";
                echo "<table>";
                echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Name (ES)</th><th>Ordering</th><th>Default</th><th>State</th></tr>";
                foreach ($banks as $bank) {
                    $rowClass = $bank->is_default ? 'warning' : 'ok';
                    echo "<tr class='{$rowClass}'>";
                    echo "<td>{$bank->id}</td>";
                    echo "<td><code>{$bank->code}</code></td>";
                    echo "<td>" . htmlspecialchars($bank->name ?: '-') . "</td>";
                    echo "<td>" . htmlspecialchars($bank->name_es ?: '-') . "</td>";
                    echo "<td>{$bank->ordering}</td>";
                    echo "<td>" . ($bank->is_default ? '✅ Yes' : 'No') . "</td>";
                    echo "<td>{$bank->state}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } else {
            $testResults['bank_data'] = false;
        }
    } catch (\Exception $e) {
        echo "<p class='error'>❌ Error querying bank data: " . htmlspecialchars($e->getMessage()) . "</p>";
        $testResults['bank_data'] = false;
    }
    
    echo "</div>";
}

// ============================================
// TEST 3: BankModel Class Loading
// ============================================
echo "<div class='test-section'>";
echo "<h2>Test 3: BankModel Class Validation</h2>";

$totalTests++;
$bankModelLoaded = false;

try {
    $component = $app->bootComponent('com_ordenproduccion');
    echo "<p class='ok'>✅ Component booted successfully</p>";
    
    $mvcFactory = $component->getMVCFactory();
    echo "<p class='ok'>✅ MVC Factory available</p>";
    
    $bankModel = $mvcFactory->createModel('Bank', 'Site', ['ignore_request' => true]);
    
    if ($bankModel) {
        echo "<p class='ok'>✅ BankModel created successfully</p>";
        $bankModelLoaded = true;
        $passedTests++;
        $testResults['bank_model_loaded'] = true;
        
        // Check methods
        $requiredMethods = ['getBanks', 'getBankOptions', 'getDefaultBankCode'];
        echo "<h3>Required Methods Check:</h3>";
        echo "<table>";
        echo "<tr><th>Method</th><th>Status</th></tr>";
        foreach ($requiredMethods as $method) {
            $exists = method_exists($bankModel, $method);
            $rowClass = $exists ? 'ok' : 'error';
            echo "<tr class='{$rowClass}'>";
            echo "<td><code>BankModel::{$method}()</code></td>";
            echo "<td>" . ($exists ? "✅ EXISTS" : "❌ MISSING") . "</td>";
            echo "</tr>";
            if (!$exists) {
                $bankModelLoaded = false;
            }
        }
        echo "</table>";
        
    } else {
        echo "<p class='error'>❌ BankModel could not be created</p>";
        $testResults['bank_model_loaded'] = false;
    }
} catch (\Exception $e) {
    echo "<p class='error'>❌ Error loading BankModel: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    $testResults['bank_model_loaded'] = false;
}

echo "</div>";

// ============================================
// TEST 4: BankModel::getBankOptions() Functionality
// ============================================
if ($bankModelLoaded && isset($bankModel)) {
    echo "<div class='test-section'>";
    echo "<h2>Test 4: BankModel::getBankOptions() Validation</h2>";
    
    $totalTests++;
    try {
        $bankOptions = $bankModel->getBankOptions();
        
        if (is_array($bankOptions)) {
            $optionsCount = count($bankOptions);
            echo "<p class='" . ($optionsCount > 0 ? 'ok' : 'warning') . "'>";
            echo ($optionsCount > 0 ? "✅" : "⚠️") . " getBankOptions() returned <strong>{$optionsCount}</strong> options";
            echo "</p>";
            
            if ($optionsCount > 0) {
                $passedTests++;
                $testResults['bank_options'] = true;
                
                echo "<h3>Bank Options Array:</h3>";
                echo "<table>";
                echo "<tr><th>Code (Key)</th><th>Name (Value)</th></tr>";
                $count = 0;
                foreach ($bankOptions as $code => $name) {
                    $count++;
                    if ($count <= 20) { // Show first 20
                        echo "<tr class='ok'>";
                        echo "<td><code>" . htmlspecialchars($code) . "</code></td>";
                        echo "<td>" . htmlspecialchars($name) . "</td>";
                        echo "</tr>";
                    }
                }
                if ($count > 20) {
                    echo "<tr><td colspan='2' class='info'>... and " . ($count - 20) . " more banks</td></tr>";
                }
                echo "</table>";
                
                // Check for empty codes or names
                $invalidOptions = [];
                foreach ($bankOptions as $code => $name) {
                    if (empty($code) || empty($name)) {
                        $invalidOptions[] = ['code' => $code, 'name' => $name];
                    }
                }
                
                if (!empty($invalidOptions)) {
                    echo "<p class='warning'>⚠️ Found " . count($invalidOptions) . " options with empty code or name:</p>";
                    echo "<pre>" . print_r($invalidOptions, true) . "</pre>";
                }
            } else {
                echo "<p class='error'>❌ getBankOptions() returned empty array - dropdown will be empty!</p>";
                $testResults['bank_options'] = false;
            }
        } else {
            echo "<p class='error'>❌ getBankOptions() did not return an array. Got: " . gettype($bankOptions) . "</p>";
            $testResults['bank_options'] = false;
        }
    } catch (\Exception $e) {
        echo "<p class='error'>❌ Error calling getBankOptions(): " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        $testResults['bank_options'] = false;
    }
    
    echo "</div>";
}

// ============================================
// TEST 5: BankModel::getDefaultBankCode() Functionality
// ============================================
if ($bankModelLoaded && isset($bankModel) && method_exists($bankModel, 'getDefaultBankCode')) {
    echo "<div class='test-section'>";
    echo "<h2>Test 5: Default Bank Code Validation</h2>";
    
    $totalTests++;
    try {
        $defaultBankCode = $bankModel->getDefaultBankCode();
        
        if (!empty($defaultBankCode)) {
            echo "<p class='ok'>✅ Default bank code: <code>{$defaultBankCode}</code></p>";
            $passedTests++;
            $testResults['default_bank_code'] = true;
            
            // Verify it exists in options
            if (isset($bankOptions) && isset($bankOptions[$defaultBankCode])) {
                echo "<p class='ok'>✅ Default bank code exists in options: " . 
                     htmlspecialchars($bankOptions[$defaultBankCode]) . "</p>";
            } else {
                echo "<p class='warning'>⚠️ Default bank code not found in options array</p>";
            }
        } else {
            echo "<p class='warning'>⚠️ No default bank code set (getDefaultBankCode() returned null/empty)</p>";
            echo "<p class='info'>This is OK - dropdown will show 'Select Bank' option first</p>";
            $testResults['default_bank_code'] = null; // Not an error, just informational
        }
    } catch (\Exception $e) {
        echo "<p class='error'>❌ Error calling getDefaultBankCode(): " . htmlspecialchars($e->getMessage()) . "</p>";
        $testResults['default_bank_code'] = false;
    }
    
    echo "</div>";
}

// ============================================
// TEST 6: PaymentProofModel Integration
// ============================================
echo "<div class='test-section'>";
echo "<h2>Test 6: PaymentProofModel Integration</h2>";

$totalTests++;
$paymentProofModelLoaded = false;

try {
    $paymentProofModel = $mvcFactory->createModel('PaymentProof', 'Site', ['ignore_request' => true]);
    
    if ($paymentProofModel) {
        echo "<p class='ok'>✅ PaymentProofModel created successfully</p>";
        $paymentProofModelLoaded = true;
        
        if (method_exists($paymentProofModel, 'getBankOptions')) {
            echo "<p class='ok'>✅ PaymentProofModel::getBankOptions() method exists</p>";
            
            try {
                $paymentProofOptions = $paymentProofModel->getBankOptions();
                $paymentOptionsCount = is_array($paymentProofOptions) ? count($paymentProofOptions) : 0;
                
                echo "<p class='" . ($paymentOptionsCount > 0 ? 'ok' : 'warning') . "'>";
                echo ($paymentOptionsCount > 0 ? "✅" : "⚠️") . 
                     " PaymentProofModel::getBankOptions() returned <strong>{$paymentOptionsCount}</strong> options";
                echo "</p>";
                
                if ($paymentOptionsCount > 0) {
                    $passedTests++;
                    $testResults['payment_proof_model'] = true;
                    
                    // Compare with BankModel results
                    if (isset($bankOptions)) {
                        $diff1 = array_diff_key($bankOptions, $paymentProofOptions);
                        $diff2 = array_diff_key($paymentProofOptions, $bankOptions);
                        
                        if (empty($diff1) && empty($diff2)) {
                            echo "<p class='ok'>✅ PaymentProofModel options match BankModel options perfectly</p>";
                        } else {
                            echo "<p class='warning'>⚠️ Options mismatch detected:</p>";
                            if (!empty($diff1)) {
                                echo "<p>In BankModel but not in PaymentProofModel: " . implode(', ', array_keys($diff1)) . "</p>";
                            }
                            if (!empty($diff2)) {
                                echo "<p>In PaymentProofModel but not in BankModel: " . implode(', ', array_keys($diff2)) . "</p>";
                            }
                        }
                    }
                } else {
                    $testResults['payment_proof_model'] = false;
                }
            } catch (\Exception $e) {
                echo "<p class='error'>❌ Error calling PaymentProofModel::getBankOptions(): " . 
                     htmlspecialchars($e->getMessage()) . "</p>";
                $testResults['payment_proof_model'] = false;
            }
        } else {
            echo "<p class='error'>❌ PaymentProofModel::getBankOptions() method does not exist</p>";
            $testResults['payment_proof_model'] = false;
        }
    } else {
        echo "<p class='error'>❌ PaymentProofModel could not be created</p>";
        $testResults['payment_proof_model'] = false;
    }
} catch (\Exception $e) {
    echo "<p class='error'>❌ Error loading PaymentProofModel: " . htmlspecialchars($e->getMessage()) . "</p>";
    $testResults['payment_proof_model'] = false;
}

echo "</div>";

// ============================================
// TEST 7: View Integration
// ============================================
echo "<div class='test-section'>";
echo "<h2>Test 7: PaymentProof View Integration</h2>";

$totalTests++;
$viewLoaded = false;

try {
    // Try both PaymentProof and Paymentproof (case sensitivity matters in some systems)
    try {
        $view = $mvcFactory->createView('PaymentProof', 'Site');
        echo "<p class='ok'>✅ PaymentProof View created successfully (with capital P)</p>";
    } catch (\Exception $e1) {
        try {
            $view = $mvcFactory->createView('Paymentproof', 'Site');
            echo "<p class='ok'>✅ Paymentproof View created successfully (with lowercase p)</p>";
        } catch (\Exception $e2) {
            throw new \Exception("Failed with both cases. PaymentProof: " . $e1->getMessage() . "; Paymentproof: " . $e2->getMessage());
        }
    }
    
    if ($view) {
        echo "<p class='ok'>✅ PaymentProof View created successfully</p>";
        $viewLoaded = true;
        
        $viewMethods = ['getBankOptions', 'getDefaultBankCode'];
        echo "<h3>View Methods Check:</h3>";
        echo "<table>";
        echo "<tr><th>Method</th><th>Status</th></tr>";
        $allMethodsExist = true;
        foreach ($viewMethods as $method) {
            $exists = method_exists($view, $method);
            $rowClass = $exists ? 'ok' : 'warning';
            echo "<tr class='{$rowClass}'>";
            echo "<td><code>HtmlView::{$method}()</code></td>";
            echo "<td>" . ($exists ? "✅ EXISTS" : "⚠️ MISSING (may use model directly)") . "</td>";
            echo "</tr>";
            if (!$exists && $method == 'getDefaultBankCode') {
                $allMethodsExist = false; // This is more critical
            }
        }
        echo "</table>";
        
        if ($allMethodsExist) {
            $passedTests++;
            $testResults['view_integration'] = true;
            
            // Test getBankOptions if available
            if (method_exists($view, 'getBankOptions')) {
                try {
                    $viewOptions = $view->getBankOptions();
                    $viewOptionsCount = is_array($viewOptions) ? count($viewOptions) : 0;
                    echo "<p class='" . ($viewOptionsCount > 0 ? 'ok' : 'warning') . "'>";
                    echo "View::getBankOptions() returned <strong>{$viewOptionsCount}</strong> options";
                    echo "</p>";
                } catch (\Exception $e) {
                    echo "<p class='error'>❌ Error calling View::getBankOptions(): " . 
                         htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        } else {
            $testResults['view_integration'] = false;
        }
    } else {
        echo "<p class='error'>❌ PaymentProof View could not be created</p>";
        $testResults['view_integration'] = false;
    }
} catch (\Exception $e) {
    echo "<p class='error'>❌ Error loading PaymentProof View: " . htmlspecialchars($e->getMessage()) . "</p>";
    $testResults['view_integration'] = false;
}

echo "</div>";

// ============================================
// TEST 8: Template File Check
// ============================================
echo "<div class='test-section'>";
echo "<h2>Test 8: Template File Validation</h2>";

$totalTests++;
$templatePath = JPATH_ROOT . '/components/com_ordenproduccion/tmpl/paymentproof/default.php';
$templateExists = file_exists($templatePath);

echo "<table>";
echo "<tr><th>Check</th><th>Result</th></tr>";
echo "<tr class='" . ($templateExists ? 'ok' : 'error') . "'>";
echo "<td>Template file exists</td>";
echo "<td>" . ($templateExists ? "✅ <code>default.php</code> found" : "❌ Template file not found") . "</td>";
echo "</tr>";

if ($templateExists) {
    $passedTests++;
    $testResults['template_file'] = true;
    
    // Check for key code in template
    $templateContent = file_get_contents($templatePath);
    $hasGetBankOptions = (strpos($templateContent, 'getBankOptions') !== false);
    $hasGetDefaultBankCode = (strpos($templateContent, 'getDefaultBankCode') !== false);
    $hasBankSelect = (strpos($templateContent, '<select') !== false && strpos($templateContent, 'bank') !== false);
    
    echo "<tr class='" . ($hasGetBankOptions ? 'ok' : 'warning') . "'>";
    echo "<td>Uses getBankOptions()</td>";
    echo "<td>" . ($hasGetBankOptions ? "✅ Found" : "⚠️ Not found") . "</td>";
    echo "</tr>";
    
    echo "<tr class='" . ($hasGetDefaultBankCode ? 'ok' : 'warning') . "'>";
    echo "<td>Uses getDefaultBankCode()</td>";
    echo "<td>" . ($hasGetDefaultBankCode ? "✅ Found" : "⚠️ Not found (may use method_exists check)") . "</td>";
    echo "</tr>";
    
    echo "<tr class='" . ($hasBankSelect ? 'ok' : 'warning') . "'>";
    echo "<td>Contains bank dropdown</td>";
    echo "<td>" . ($hasBankSelect ? "✅ Found" : "⚠️ Not found") . "</td>";
    echo "</tr>";
} else {
    $testResults['template_file'] = false;
}

echo "</table>";
echo "</div>";

// ============================================
// FINAL SUMMARY
// ============================================
echo "<div class='test-section'>";
echo "<h2>📊 Final Summary</h2>";

$criticalTests = [
    'table_exists' => 'Database table exists',
    'bank_data' => 'Bank data in database',
    'bank_model_loaded' => 'BankModel loads successfully',
    'bank_options' => 'Bank options populated',
    'payment_proof_model' => 'PaymentProofModel integration',
];

$criticalPassed = 0;
foreach ($criticalTests as $test => $label) {
    if (isset($testResults[$test]) && $testResults[$test]) {
        $criticalPassed++;
    }
}

echo "<table>";
echo "<tr><th>Test</th><th>Status</th></tr>";

foreach ($criticalTests as $test => $label) {
    $status = isset($testResults[$test]) ? $testResults[$test] : false;
    $rowClass = $status ? 'ok' : 'error';
    $icon = $status ? '✅' : '❌';
    echo "<tr class='{$rowClass}'>";
    echo "<td>{$label}</td>";
    echo "<td>{$icon} " . ($status ? 'PASS' : 'FAIL') . "</td>";
    echo "</tr>";
}

// Additional tests
$additionalTests = [
    'default_bank_code' => 'Default bank code available',
    'view_integration' => 'View integration working',
    'template_file' => 'Template file exists',
];

foreach ($additionalTests as $test => $label) {
    $status = isset($testResults[$test]);
    if ($status) {
        $value = $testResults[$test];
        if ($value === null) {
            $rowClass = 'info';
            $icon = 'ℹ️';
            $text = 'INFO (optional)';
        } else {
            $rowClass = $value ? 'ok' : 'warning';
            $icon = $value ? '✅' : '⚠️';
            $text = $value ? 'PASS' : 'WARNING';
        }
        echo "<tr class='{$rowClass}'>";
        echo "<td>{$label}</td>";
        echo "<td>{$icon} {$text}</td>";
        echo "</tr>";
    }
}

echo "</table>";

echo "<div class='summary' style='margin-top: 20px;'>";
echo "<h3>Test Results: {$criticalPassed} / " . count($criticalTests) . " Critical Tests Passed</h3>";
echo "<p><strong>Total Tests:</strong> {$passedTests} / {$totalTests} passed</p>";

if ($criticalPassed == count($criticalTests)) {
    echo "<p class='ok' style='padding: 10px; border-radius: 4px;'><strong>✅ SUCCESS:</strong> Bank dropdown should be working correctly!</p>";
    echo "<p class='info'>All critical tests passed. The dropdown should populate with banks from the database.</p>";
} else {
    echo "<p class='error' style='padding: 10px; border-radius: 4px;'><strong>❌ ISSUES DETECTED:</strong> Some critical tests failed.</p>";
    echo "<p class='warning'>Please review the failed tests above and take corrective action:</p>";
    echo "<ul>";
    if (!$testResults['table_exists'] ?? false) {
        echo "<li>Run the SQL migration script to create the banks table</li>";
    }
    if (!$testResults['bank_data'] ?? false) {
        echo "<li>Add banks to the database via Component > Administracion > Herramientas > Bancos</li>";
    }
    if (!$testResults['bank_model_loaded'] ?? false) {
        echo "<li>Check that BankModel.php file exists and is properly deployed</li>";
    }
    if (!$testResults['bank_options'] ?? false) {
        echo "<li>Verify BankModel::getBankOptions() is returning data correctly</li>";
    }
    echo "</ul>";
}
echo "</div>";

echo "</div>"; // End summary section

echo "</div></body></html>";
