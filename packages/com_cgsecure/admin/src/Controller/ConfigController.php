<?php
/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
namespace ConseilGouz\Component\CGSecure\Administrator\Controller;
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;
/**
 * Config controller class
 */
class ConfigController extends FormController
{
	protected $text_prefix = 'CGSECURE';
    public function ssave($key = null, $urlVar = null)
    {       
        // Check for request forgeries.
        Session::checkToken() or jexit(JText::_('JINVALID_TOKEN'));

        // Initialise variables.
        $app = Factory::getApplication();
        $model= $this->getModel('config'); 
        $data = $app->input->getVar('jform', array(), 'post', 'array');
        $task = $this->getTask();
        $context = 'com_cgsecure.edit.config';
        $form = $model->getForm($data, false);
        $validData = $model->validate($form, $data);
        
        if ($model->save($data)) {
			$this->setMessage(Text::_('Save sucess!'));		
		}
	}

}
