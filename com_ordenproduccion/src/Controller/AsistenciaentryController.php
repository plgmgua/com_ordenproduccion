<?php
/**
 * @package     Grimpsa.Component
 * @subpackage  com_ordenproduccion
 *
 * @copyright   Copyright (C) 2025 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;

defined('_JEXEC') or die;

/**
 * Asistencia Entry Controller (for manual entry)
 *
 * @since  3.2.0
 */
class AsistenciaentryController extends FormController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  3.2.0
     */
    protected $text_prefix = 'COM_ORDENPRODUCCION_ASISTENCIA';

    /**
     * Method to add a new record.
     *
     * @return  boolean  True if the record can be added, false if not.
     *
     * @since   3.2.0
     */
    public function add()
    {
        $user = Factory::getUser();

        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        return parent::add();
    }

    /**
     * Method to edit an existing record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   3.2.0
     */
    public function edit($key = null, $urlVar = null)
    {
        $user = Factory::getUser();

        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        return parent::edit($key, $urlVar);
    }

    /**
     * Method to save a record.
     *
     * @param   string  $key     The name of the primary key of the URL variable.
     * @param   string  $urlVar  The name of the URL variable if different from the primary key.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   3.2.0
     */
    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries
        if (!Session::checkToken()) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return false;
        }

        $user = Factory::getUser();

        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $data = $this->input->post->get('jform', [], 'array');
        $model = $this->getModel('Asistenciaentry');

        // Validate the posted data
        $form = $model->getForm($data, false);

        if (!$form) {
            $this->app->enqueueMessage($model->getError(), 'error');
            return false;
        }

        // Validate the form data
        $validData = $model->validate($form, $data);

        if ($validData === false) {
            $errors = $model->getErrors();
            foreach ($errors as $error) {
                if ($error instanceof \Exception) {
                    $this->app->enqueueMessage($error->getMessage(), 'error');
                } else {
                    $this->app->enqueueMessage($error, 'error');
                }
            }

            // Save the data in the session
            $this->app->setUserState('com_ordenproduccion.edit.asistenciaentry.data', $data);

            // Redirect back to the edit screen
            $id = (int) $this->input->getInt('id');
            $this->setRedirect(
                Route::_('index.php?option=com_ordenproduccion&view=asistenciaentry&layout=edit&id=' . $id, false)
            );

            return false;
        }

        // Attempt to save the data
        if (!$model->save($validData)) {
            // Save the data in the session
            $this->app->setUserState('com_ordenproduccion.edit.asistenciaentry.data', $validData);

            // Redirect back to the edit screen
            $this->app->enqueueMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'error');
            $id = (int) $this->input->getInt('id');
            $this->setRedirect(
                Route::_('index.php?option=com_ordenproduccion&view=asistenciaentry&layout=edit&id=' . $id, false)
            );

            return false;
        }

        // Clear the data in the session
        $this->app->setUserState('com_ordenproduccion.edit.asistenciaentry.data', null);

        // Redirect to the list screen
        $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_SAVE_SUCCESS'), 'success');
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));

        return true;
    }

    /**
     * Method to cancel an edit.
     *
     * @param   string  $key  The name of the primary key of the URL variable.
     *
     * @return  boolean  True if access level checks pass, false otherwise.
     *
     * @since   3.2.0
     */
    public function cancel($key = null)
    {
        // Clear the data in the session
        $this->app->setUserState('com_ordenproduccion.edit.asistenciaentry.data', null);

        // Redirect to the list screen
        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));

        return true;
    }

    /**
     * Method to delete a record.
     *
     * @return  boolean  True if successful, false otherwise.
     *
     * @since   3.2.0
     */
    public function delete()
    {
        // Check for request forgeries
        if (!Session::checkToken('get')) {
            $this->app->enqueueMessage(Text::_('JINVALID_TOKEN'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return false;
        }

        $user = Factory::getUser();

        if ($user->guest) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_LOGIN_REQUIRED'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_users&view=login', false));
            return false;
        }

        $id = $this->input->getInt('id', 0);

        if ($id <= 0) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ERROR_INVALID_ID'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));
            return false;
        }

        $model = $this->getModel('Asistenciaentry');

        if ($model->delete($id)) {
            $this->app->enqueueMessage(Text::_('COM_ORDENPRODUCCION_ASISTENCIA_DELETE_SUCCESS'), 'success');
        } else {
            $this->app->enqueueMessage(Text::sprintf('JLIB_APPLICATION_ERROR_DELETE_FAILED', $model->getError()), 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ordenproduccion&view=asistencia', false));

        return true;
    }

    /**
     * Gets the URL arguments to append to an item redirect.
     *
     * @param   integer  $recordId  The primary key id for the item.
     * @param   string   $urlVar    The name of the URL variable for the id.
     *
     * @return  string  The arguments to append to the redirect URL.
     *
     * @since   3.2.0
     */
    protected function getRedirectToItemAppend($recordId = null, $urlVar = 'id')
    {
        $append = parent::getRedirectToItemAppend($recordId, $urlVar);
        $append .= '&view=asistenciaentry&layout=edit';

        return $append;
    }

    /**
     * Gets the URL arguments to append to a list redirect.
     *
     * @return  string  The arguments to append to the redirect URL.
     *
     * @since   3.2.0
     */
    protected function getRedirectToListAppend()
    {
        return '&view=asistencia';
    }
}

