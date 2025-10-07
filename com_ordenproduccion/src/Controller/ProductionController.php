<?php
/**
 * Production Actions Controller
 * 
 * Handles production-related actions like PDF generation, Excel export,
 * and production management.
 * 
 * @package     Grimpsa\Component\Ordenproduccion\Site\Controller
 * @subpackage  Production
 * @author      Grimpsa Development Team
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 * @since       1.0.0
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Grimpsa\Component\Ordenproduccion\Site\Helper\ProductionActionsHelper;

defined('_JEXEC') or die;

/**
 * Production Actions Controller
 */
class ProductionController extends BaseController
{
    /**
     * Generate PDF for work order
     *
     * @return  void
     * @since   1.0.0
     */
    public function generatePDF()
    {
        // Check user access - only produccion group
        if (!$this->checkProductionAccess()) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Check CSRF token
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $orderId = $this->input->getInt('id', 0);
        
        if (!$orderId) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $helper = new ProductionActionsHelper();
            $pdfPath = $helper->generateWorkOrderPDF($orderId);
            
            if ($pdfPath) {
                // For now, redirect to the PDF file
                // In a full implementation, you would serve the file for download
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_PDF_GENERATED_SUCCESS'), 'success');
                $this->setRedirect($pdfPath);
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_PDF_GENERATION'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            }
            
        } catch (\Exception $e) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_PDF_GENERATION') . ': ' . $e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }
    }

    /**
     * Export orders to Excel
     *
     * @return  void
     * @since   1.0.0
     */
    public function exportExcel()
    {
        // Check user access - only produccion group
        if (!$this->checkProductionAccess()) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Check CSRF token
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $helper = new ProductionActionsHelper();
            
            // Get filter parameters
            $filters = [
                'start_date' => $this->input->getString('start_date', ''),
                'end_date' => $this->input->getString('end_date', ''),
                'status' => $this->input->getString('status', '')
            ];
            
            $excelPath = $helper->exportOrdersToExcel($filters);
            
            if ($excelPath) {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_EXCEL_EXPORT_SUCCESS'), 'success');
                $this->setRedirect($excelPath);
            } else {
                $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_EXCEL_EXPORT'), 'error');
                $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            }
            
        } catch (\Exception $e) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_EXCEL_EXPORT') . ': ' . $e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }
    }

    /**
     * Get production statistics
     *
     * @return  void
     * @since   1.0.0
     */
    public function getStatistics()
    {
        // Check user access - only produccion group
        if (!$this->checkProductionAccess()) {
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode(['error' => 'Access denied']);
            $this->app->close();
            return;
        }

        $startDate = $this->input->getString('start_date', date('Y-m-01'));
        $endDate = $this->input->getString('end_date', date('Y-m-d'));

        try {
            $helper = new ProductionActionsHelper();
            $stats = $helper->getProductionStatistics($startDate, $endDate);
            
            // Return JSON response
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode($stats);
            $this->app->close();
            
        } catch (\Exception $e) {
            $this->app->setHeader('Content-Type', 'application/json');
            echo json_encode(['error' => $e->getMessage()]);
            $this->app->close();
        }
    }

    /**
     * Mark order as completed
     *
     * @return  void
     * @since   1.0.0
     */
    public function markCompleted()
    {
        // Check user access - only produccion group
        if (!$this->checkProductionAccess()) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_ACCESS_DENIED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        // Check CSRF token
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        $orderId = $this->input->getInt('id', 0);
        
        if (!$orderId) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ORDER'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            return;
        }

        try {
            $db = Factory::getDbo();
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__ordenproduccion_ordenes'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('Terminada'))
                ->set($db->quoteName('modified') . ' = ' . $db->quote(Factory::getDate()->toSql()))
                ->where($db->quoteName('id') . ' = ' . (int) $orderId);

            $db->setQuery($query);
            $db->execute();

            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ORDER_MARKED_COMPLETED'), 'success');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
            
        } catch (\Exception $e) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_UPDATE_ORDER') . ': ' . $e->getMessage(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=ordenes'));
        }
    }

    /**
     * Check if user has production access (produccion group)
     *
     * @return  bool  True if user has access, false otherwise
     * @since   1.0.0
     */
    protected function checkProductionAccess()
    {
        $user = Factory::getUser();
        
        // Check if user is in produccion group
        $userGroups = $user->getAuthorisedGroups();
        
        // Get produccion group ID
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select('id')
            ->from($db->quoteName('#__usergroups'))
            ->where($db->quoteName('title') . ' = ' . $db->quote('produccion'));
        
        $db->setQuery($query);
        $produccionGroupId = $db->loadResult();
        
        if (!$produccionGroupId) {
            return false;
        }
        
        return in_array($produccionGroupId, $userGroups);
    }
}
