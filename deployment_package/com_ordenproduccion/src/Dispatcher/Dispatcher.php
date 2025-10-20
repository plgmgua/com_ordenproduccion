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
        // Fix malformed view parameter before dispatch
        $view = $this->input->getCmd('view', '');
        $originalView = $view;
        
        // Handle malformed URLs like "view=cotizacion?client_id=7" 
        // which causes Joomla to interpret "cotizacionclient_id7" as view name
        if (!empty($view)) {
            // Handle case where view has question mark: "cotizacion?client_id=7" -> "cotizacion"
            if (strpos($view, '?') !== false) {
                $view = substr($view, 0, strpos($view, '?'));
            }
            
            // Handle case where parameters got concatenated to view name: "cotizacionclient_id7" -> "cotizacion"
            // IMPORTANT: Put longer matches first to avoid truncating "ordenes" to "orden"
            if (preg_match('/^(cotizaciones|ordenes|administracion|production|quotation|cotizacion|orden)(.+)/', $view, $matches)) {
                $view = $matches[1]; // Extract just the view name part
            }
            
            // If view was modified, update the input
            if ($view !== $originalView && !empty($view)) {
                $this->input->set('view', $view);
            }
        }

        // Debug logging
        $debugLog = function($message) {
            $logFile = '/var/www/grimpsa_webserver/dispatcher_debug.log';
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[$timestamp] $message\n";
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        };

        $debugLog("=== DISPATCHER START ===");
        $debugLog("Original view: " . $view);
        $debugLog("Cleaned view: " . $this->input->getCmd('view', ''));
        $debugLog("Input data: " . print_r($this->input->getArray(), true));
        $debugLog("Task: " . $this->input->getCmd('task', 'display'));
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