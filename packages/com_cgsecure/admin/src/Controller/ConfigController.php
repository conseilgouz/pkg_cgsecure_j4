<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Controller;

defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Config controller class
 */
class ConfigController extends FormController
{
    protected $text_prefix = 'CGSECURE';
    public function cancel($key = null)
    {
        $app = Factory::getApplication();
        $return = Uri::base().'index.php?option=com_cgsecure&view=config';
        $app->redirect($return);
        return true;
    }

}
