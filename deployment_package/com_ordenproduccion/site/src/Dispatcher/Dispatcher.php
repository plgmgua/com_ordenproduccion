<?php

namespace Grimpsa\Component\Ordenproduccion\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;

/**
 * Component dispatcher class for com_ordenproduccion
 *
 * @since  1.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Dispatch a controller task. Redirecting the user if appropriate.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function dispatch()
    {
        // Debug logging
        $debugLog = function($message) {
            $logFile = '/var/www/grimpsa_webserver/dispatcher_debug.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] $message\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        };

        $debugLog("=== DISPATCHER START ===");
        $debugLog("Input data: " . print_r($this->input->getArray(), true));
        $debugLog("Task: " . $this->input->getCmd('task', 'display'));
        $debugLog("View: " . $this->input->getCmd('view', ''));
        $debugLog("Controller: " . $this->input->getCmd('controller', ''));
        
        try {
            $debugLog("Calling parent::dispatch()");
            parent::dispatch();
            $debugLog("parent::dispatch() completed successfully");
        } catch (\Exception $e) {
            $debugLog("ERROR in parent::dispatch(): " . $e->getMessage());
            $debugLog("ERROR TRACE: " . $e->getTraceAsString());
            throw $e;
        }
        
        $debugLog("=== DISPATCHER END ===");
    }
}