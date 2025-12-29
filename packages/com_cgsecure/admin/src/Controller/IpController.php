<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Controller;

\defined('_JEXEC') or die;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use ConseilGouz\CGSecure\Cgipcheck;

class IpController extends FormController
{
    protected function allowAdd($data = array())
    {
        // Initialise variables.
        $user		= $this->app->getIdentity();
        $allow		= null;
        if ($allow === null) {
            // In the absense of better information, revert to the component permissions.
            return parent::allowAdd($data);
        } else {
            return $allow;
        }
    }

    protected function allowEdit($data = array(), $key = 'id')
    {
        $recordId = (int) isset($data[$key]) ? $data[$key] : 0;
        $user = $this->app->getIdentity();

        // Zero record (id:0), return component edit permission by calling parent controller method
        if (!$recordId) {
            return parent::allowEdit($data, $key);
        }

        // Check edit on the record asset (explicit or inherited)
        if ($user->authorise('core.edit', 'com_cgsecure.ip.' . $recordId)) {
            return true;
        }

        // Check edit own on the record asset (explicit or inherited)
        if ($user->authorise('core.edit.own', 'com_cgsecure.ip.' . $recordId)) {
            // Existing record already has an owner, get it
            $record = $this->getModel()->getItem($recordId);

            if (empty($record)) {
                return false;
            }

            // Grant if current user is owner of the record
            return $user->id == $record->created_by;
        }

        return false;

    }
    public function cancel($key = null)
    {
        // $result = parent::cancel();
        $app = Factory::getApplication();
        $return = Uri::base().'index.php?option=com_cgsecure&view=logs';
        $app->redirect($return);
        return true;
    }


    public function save($key = null, $urlVar = null)
    {
        // Check for request forgeries.
        Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

        // Initialise variables.
        $app = Factory::getApplication();
        $model = $this->getModel('ip');
        $data = $app->getInput()->getVar('jform', array(), 'post', 'array');
        $task = $this->getTask();
        $context = 'com_cgsecure.edit.ip';
        $recordId = $app->getInput()->getInt('id');

        if (!$this->checkEditId($context, $recordId)) {
            // Somehow the person just went to the form and saved it - we don't allow that.
            $this->setError(Text::sprintf('JLIB_APPLICATION_ERROR_UNHELD_ID', $recordId));
            $this->setMessage($this->getError(), 'error');
            $this->setRedirect(Route::_('index.php?option=com_cgsecure&view=logs' . $this->getRedirectToListAppend(), false));

            return false;
        }

        // Populate the row id from the session.
        $data['id'] = $recordId;
        $form = $model->getForm($data, false);
        $valid = $model->validate($form, $data);

        // Check for validation errors.
        if ($valid === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $app->enqueueMessage($errors[$i], 'warning');
                }
            }
            // Save the data in the session.
            $app->setUserState('com_cgsecure.edit.ip.data', $data);
            // Redirect back to the edit screen.
            $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend($recordId), false));
            return false;
        }
        // fill up info from abuseIPDB
        $data['errtype'] = 'e';
        $data['action'] = 'Manual';
        $data['country'] = 'unkown';
        $infos = Cgipcheck::abuseIPDBrequest('check', 'GET', [ 'ipAddress' => $data['ip'], 'maxAgeInDays' => 30, 'verbose' => true ]);
        if ($infos->data->countryCode) {
            $data['country'] = $infos->data->countryCode;
        }
        $timezone = Factory::getApplication()->getIdentity()->getTimezone();
        $date = new Date('now');
        $date->setTimezone($timezone);
        $data['attempt_date'] = $date->format(Text::_('DATE_FORMAT_FILTER_DATETIME'));
        // Attempt to save the data.
        if (!$model->save($data)) {
            // Save the data in the session.
            $app->setUserState('com_cgsecure.edit.ip.data', $data);
            // Redirect back to the edit screen.
            $this->setMessage(Text::sprintf('JLIB_APPLICATION_ERROR_SAVE_FAILED', $model->getError()), 'warning');
            $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend($recordId), false));
            return false;
        }

        $this->setMessage(Text::_('Save sucess!'));
        // Redirect the user and adjust session state based on the chosen task.
        switch ($task) {
            case 'apply':
                // Set the row data in the session.
                $recordId = $model->getState($this->context . '.id');
                $this->holdEditId($context, $recordId);
                $app->setUserState('com_cgsecure.edit.ip.data', null);

                // Redirect back to the edit screen.
                $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend($recordId), false));
                break;
            case 'save2new':
                // Clear the row id and data in the session.
                $this->releaseEditId($context, $recordId);
                $app->setUserState('com_cgsecure.edit.ip.data', null);
                // Redirect back to the edit screen.
                $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=' . $this->view_item . $this->getRedirectToItemAppend(), false));
                break;
            default:
                // Clear the row id and data in the session.
                $this->releaseEditId($context, $recordId);
                $app->setUserState('com_cgsecure.edit.ip.data', null);
                // Redirect to the list screen.
                $this->setRedirect(Route::_('index.php?option=' . $this->option . '&view=logs' . $this->getRedirectToListAppend(), false));
                break;
        }
    }

}
