<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Logs;

defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

/**
 * Config View
 */
class JsonView extends AbstractView
{
    protected $app;
    protected $security;
    public const SERVER_CONFIG_FILE_NONE = '';
    public const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
    public const SERVER_CONFIG_FILE_ADMIN_HTACCESS = 'administrator/.htaccess';
    public const CGPATH = '/media/com_cgsecure';
    /**
     * AJAX Request
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise a JError object.
     */
    public function display($tpl = null)
    {
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        // Check for errors.
        $this->app = Factory::getApplication();
        $input = Factory::getApplication()->getInput();
        $type = $input->get('type');
        $msg = "";
        if ($type == 'logs') {
            $log = $input->get('adLogs');
            $msg = 'index.php?option=com_cgsecure&view=viewlogs&tmpl=component&type='.$log;
        } else {
            die(Text::_('JINVALID_MESSAGE'));
        }
        $arr['retour'] = $msg;
        echo new JsonResponse($arr);
    }
}
