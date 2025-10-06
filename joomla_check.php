<!DOCTYPE html>
<html>
<head>
    <title>Joomla Installation Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { background-color: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 800px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 2px solid #5B9BD5; padding-bottom: 10px; }
        h2 { color: #5B9BD5; margin-top: 30px; }
        .success { color: green; }
        .warning { color: orange; }
        .error { color: red; }
        pre { background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Joomla Installation Diagnostics</h1>
        <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>

        <h2>1. File System Check</h2>
        <?php
        $joomlaRoot = '/var/www/grimpsa_webserver';
        $criticalFiles = [
            'configuration.php' => 'Joomla configuration file',
            'index.php' => 'Main entry point',
            'libraries/src/Factory.php' => 'Joomla Factory class',
            'libraries/vendor/autoload.php' => 'Composer autoloader',
        ];

        foreach ($criticalFiles as $file => $description) {
            $fullPath = $joomlaRoot . '/' . $file;
            if (file_exists($fullPath)) {
                echo "<p class='success'>✅ <strong>$description</strong>: $fullPath</p>\n";
            } else {
                echo "<p class='error'>❌ <strong>$description missing</strong>: $fullPath</p>\n";
            }
        }
        ?>

        <h2>2. PHP Configuration</h2>
        <?php
        echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>\n";
        echo "<p><strong>PHP SAPI:</strong> " . php_sapi_name() . "</p>\n";
        echo "<p><strong>Server Software:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "</p>\n";
        ?>

        <h2>3. Joomla Configuration Check</h2>
        <?php
        $configFile = $joomlaRoot . '/configuration.php';
        if (file_exists($configFile)) {
            try {
                require_once $configFile;
                
                if (class_exists('JConfig')) {
                    $config = new JConfig();
                    echo "<p class='success'>✅ <strong>Joomla configuration loaded</strong></p>\n";
                    echo "<p><strong>Site Name:</strong> " . ($config->sitename ?? 'Unknown') . "</p>\n";
                    echo "<p><strong>DB Host:</strong> " . ($config->host ?? 'Unknown') . "</p>\n";
                    echo "<p><strong>DB Name:</strong> " . ($config->db ?? 'Unknown') . "</p>\n";
                    echo "<p><strong>DB User:</strong> " . ($config->user ?? 'Unknown') . "</p>\n";
                    echo "<p><strong>DB Prefix:</strong> " . ($config->dbprefix ?? 'Unknown') . "</p>\n";
                    echo "<p><strong>Debug Enabled:</strong> " . ($config->debug ?? 0) . "</p>\n";
                    echo "<p><strong>Error Reporting:</strong> " . ($config->error_reporting ?? 'Unknown') . "</p>\n";
                } else {
                    echo "<p class='error'>❌ <strong>JConfig class not found</strong></p>\n";
                }
            } catch (Exception $e) {
                echo "<p class='error'>❌ <strong>Error loading configuration:</strong> " . $e->getMessage() . "</p>\n";
            }
        } else {
            echo "<p class='error'>❌ <strong>Configuration file not found</strong></p>\n";
        }
        ?>

        <h2>4. Database Connection Test</h2>
        <?php
        if (isset($config)) {
            try {
                $dsn = "mysql:host={$config->host};dbname={$config->db};charset=utf8mb4";
                $pdo = new PDO($dsn, $config->user, $config->password);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                echo "<p class='success'>✅ <strong>Database connection successful</strong></p>\n";
                
                // Test query
                $stmt = $pdo->query("SELECT VERSION() as version");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<p><strong>MySQL Version:</strong> " . $result['version'] . "</p>\n";
                
            } catch (PDOException $e) {
                echo "<p class='error'>❌ <strong>Database connection failed:</strong> " . $e->getMessage() . "</p>\n";
            }
        }
        ?>

        <h2>5. Joomla Framework Test</h2>
        <?php
        define('_JEXEC', 1);
        define('JPATH_BASE', $joomlaRoot);
        
        $autoloaderPath = $joomlaRoot . '/libraries/vendor/autoload.php';
        if (file_exists($autoloaderPath)) {
            require_once $autoloaderPath;
            echo "<p class='success'>✅ <strong>Composer autoloader loaded</strong></p>\n";
            
            // Try to include Joomla's Factory
            if (class_exists('Joomla\\CMS\\Factory')) {
                echo "<p class='success'>✅ <strong>Joomla\\CMS\\Factory class available</strong></p>\n";
                
                // Try to get application
                try {
                    $app = Joomla\CMS\Factory::getApplication('site');
                    echo "<p class='success'>✅ <strong>Application created successfully</strong></p>\n";
                    echo "<p><strong>Application Name:</strong> " . $app->getName() . "</p>\n";
                } catch (Exception $e) {
                    echo "<p class='error'>❌ <strong>Failed to create application:</strong> " . $e->getMessage() . "</p>\n";
                    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
                }
            } else {
                echo "<p class='error'>❌ <strong>Joomla\\CMS\\Factory class not available</strong></p>\n";
            }
        } else {
            echo "<p class='error'>❌ <strong>Composer autoloader not found</strong></p>\n";
        }
        ?>

        <h2>6. Error Log Check</h2>
        <?php
        $errorLog = $joomlaRoot . '/logs/error.php';
        if (file_exists($errorLog)) {
            echo "<p class='success'>✅ <strong>Error log found:</strong> $errorLog</p>\n";
            $lines = file($errorLog);
            $lastLines = array_slice($lines, -20);
            echo "<p><strong>Last 20 lines:</strong></p>\n";
            echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>\n";
        } else {
            echo "<p class='warning'>⚠️ <strong>No error log found</strong></p>\n";
        }
        ?>

        <h2>Recommendations</h2>
        <ul>
            <li>If Joomla Factory is available but application creation fails, check Joomla's error logs</li>
            <li>If database connection fails, verify credentials in configuration.php</li>
            <li>If autoloader is missing, run: <code>composer install</code> in Joomla root</li>
            <li>If "getPath() on string" error persists, it's likely a Joomla core issue, not component issue</li>
        </ul>
    </div>
</body>
</html>

