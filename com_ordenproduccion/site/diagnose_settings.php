<?php
/**
 * Settings Diagnostic Script
 * Run this to diagnose Settings view issues
 */

// Include Joomla framework
define('_JEXEC', 1);
define('JPATH_BASE', '/var/www/grimpsa_webserver');
require_once JPATH_BASE . '/includes/defines.php';
require_once JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;

echo "<h1>Settings Diagnostic Script</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .error{color:red;} .success{color:green;} .info{color:blue;}</style>";

try {
    echo "<h2>1. Testing Joomla Bootstrap</h2>";
    $app = Factory::getApplication('administrator');
    echo "<div class='success'>✓ Joomla application loaded successfully</div>";
    
    echo "<h2>2. Testing Settings Model</h2>";
    $modelPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/src/Model/SettingsModel.php';
    if (file_exists($modelPath)) {
        echo "<div class='success'>✓ Settings model file exists</div>";
        
        // Try to load the model
        require_once $modelPath;
        $model = new \Grimpsa\Component\Ordenproduccion\Administrator\Model\SettingsModel();
        echo "<div class='success'>✓ Settings model instantiated successfully</div>";
        
        // Test getItem method
        $item = $model->getItem();
        if ($item) {
            echo "<div class='success'>✓ getItem() method works</div>";
            echo "<div class='info'>Item data: " . json_encode($item) . "</div>";
        } else {
            echo "<div class='error'>✗ getItem() method failed</div>";
        }
        
    } else {
        echo "<div class='error'>✗ Settings model file not found at: $modelPath</div>";
    }
    
    echo "<h2>3. Testing Form XML</h2>";
    $formPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/forms/settings.xml';
    if (file_exists($formPath)) {
        echo "<div class='success'>✓ Form XML file exists</div>";
        
        // Test form loading
        try {
            $form = Form::getInstance('test', $formPath, ['control' => 'jform']);
            if ($form) {
                echo "<div class='success'>✓ Form loaded successfully</div>";
            } else {
                echo "<div class='error'>✗ Form loading failed</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Form loading error: " . $e->getMessage() . "</div>";
        }
        
    } else {
        echo "<div class='error'>✗ Form XML file not found at: $formPath</div>";
    }
    
    echo "<h2>4. Testing Settings View</h2>";
    $viewPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/src/View/Settings/HtmlView.php';
    if (file_exists($viewPath)) {
        echo "<div class='success'>✓ Settings view file exists</div>";
        
        // Try to load the view
        require_once $viewPath;
        $view = new \Grimpsa\Component\Ordenproduccion\Administrator\View\Settings\HtmlView();
        echo "<div class='success'>✓ Settings view instantiated successfully</div>";
        
    } else {
        echo "<div class='error'>✗ Settings view file not found at: $viewPath</div>";
    }
    
    echo "<h2>5. Testing Settings Controller</h2>";
    $controllerPath = JPATH_BASE . '/administrator/components/com_ordenproduccion/src/Controller/SettingsController.php';
    if (file_exists($controllerPath)) {
        echo "<div class='success'>✓ Settings controller file exists</div>";
        
        // Try to load the controller
        require_once $controllerPath;
        $controller = new \Grimpsa\Component\Ordenproduccion\Administrator\Controller\SettingsController();
        echo "<div class='success'>✓ Settings controller instantiated successfully</div>";
        
    } else {
        echo "<div class='error'>✗ Settings controller file not found at: $controllerPath</div>";
    }
    
    echo "<h2>6. Testing Language Files</h2>";
    $langPath = JPATH_BASE . '/administrator/language/en-GB/com_ordenproduccion.ini';
    if (file_exists($langPath)) {
        echo "<div class='success'>✓ English language file exists</div>";
    } else {
        echo "<div class='error'>✗ English language file not found</div>";
    }
    
    echo "<h2>7. Testing Component Registration</h2>";
    $db = Factory::getDbo();
    $query = $db->getQuery(true)
        ->select('*')
        ->from('#__extensions')
        ->where('element = ' . $db->quote('com_ordenproduccion'))
        ->where('type = ' . $db->quote('component'));
    
    $db->setQuery($query);
    $component = $db->loadObject();
    
    if ($component) {
        echo "<div class='success'>✓ Component registered in database</div>";
        echo "<div class='info'>Component ID: " . $component->extension_id . "</div>";
        echo "<div class='info'>Enabled: " . ($component->enabled ? 'Yes' : 'No') . "</div>";
    } else {
        echo "<div class='error'>✗ Component not found in database</div>";
    }
    
    echo "<h2>8. Testing Autoloading</h2>";
    $autoloadFile = JPATH_BASE . '/administrator/cache/autoload_psr4.php';
    if (file_exists($autoloadFile)) {
        echo "<div class='success'>✓ Autoload file exists</div>";
        
        // Check if our component is in autoload
        $autoloadContent = file_get_contents($autoloadFile);
        if (strpos($autoloadContent, 'Grimpsa\\\\Component\\\\Ordenproduccion') !== false) {
            echo "<div class='success'>✓ Component found in autoload file</div>";
        } else {
            echo "<div class='error'>✗ Component not found in autoload file</div>";
        }
    } else {
        echo "<div class='error'>✗ Autoload file not found</div>";
    }
    
    echo "<h2>9. Testing MVC Factory</h2>";
    try {
        $mvcFactory = $app->bootComponent('com_ordenproduccion');
        if ($mvcFactory) {
            echo "<div class='success'>✓ Component booted successfully</div>";
        } else {
            echo "<div class='error'>✗ Component boot failed</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>✗ Component boot error: " . $e->getMessage() . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Fatal error: " . $e->getMessage() . "</div>";
    echo "<div class='error'>Stack trace: " . $e->getTraceAsString() . "</div>";
}

echo "<h2>Diagnostic Complete</h2>";
echo "<p>If you see any errors above, those are likely causing the 500 error.</p>";
?>
