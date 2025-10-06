<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

/**
 * Field Visibility controller for com_ordenproduccion
 *
 * @since  1.0.0
 */
class FieldVisibilityController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_FIELD_VISIBILITY';

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key (sometimes required to avoid router collisions).
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   1.0.0
     */
    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries.
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=fieldvisibility'));
            return false;
        }

        $model = $this->getModel('FieldVisibility');
        $data = $this->input->post->get('jform', [], 'array');

        // Validate the data
        if (!$this->validateFieldVisibilityData($data)) {
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=fieldvisibility'));
            return false;
        }

        // Attempt to save the data.
        $return = $model->save($data);

        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $this->app->setUserState('com_ordenproduccion.edit.fieldvisibility.data', $data);

            // Redirect back to the edit screen.
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=fieldvisibility'));
            return false;
        }

        // Clear the profile id from the session.
        $this->app->setUserState('com_ordenproduccion.edit.fieldvisibility.id', null);

        // Redirect to the list screen.
        $this->setMessage(Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_SAVED_SUCCESSFULLY'));
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=fieldvisibility'));

        return true;
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   1.0.0
     */
    public function cancel($key = null)
    {
        // Clear the profile id from the session.
        $this->app->setUserState('com_ordenproduccion.edit.fieldvisibility.id', null);

        // Redirect to the list screen.
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=fieldvisibility'));

        return true;
    }

    /**
     * Validate field visibility data
     *
     * @param   array  $data  The form data
     *
     * @return  boolean  True if valid, false otherwise
     *
     * @since   1.0.0
     */
    protected function validateFieldVisibilityData($data)
    {
        // Basic validation - ensure we have the required fields
        if (!isset($data['ventas_fields']) || !isset($data['produccion_fields'])) {
            $this->setMessage(Text::_('COM_ORDENPRODUCCION_FIELD_VISIBILITY_VALIDATION_ERROR'), 'error');
            return false;
        }

        return true;
    }
}
