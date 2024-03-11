<?php
/**
 * @package 	CGSecure
 * from karebu secure (kSesure)
 * Version			: 3.0.11
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2024 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use ConseilGouz\CGSecure\Helper\Cgipcheck;

class plgSystemCGSecure extends CMSPlugin
{
    public $myname = 'SystemCGSecure';
    public $mymessage = 'Joomla Admin : try to force the door...';
    public $cgsecure_params;
    public $errtype = 'e'; // error : hacking

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->cgsecure_params = Cgipcheck::getParams();
    }
    public function onAfterDispatch()
    {
        $mainframe 	= Factory::getApplication();
        $user 		= Factory::getApplication()->getIdentity();
        $session	= Factory::getApplication()->getSession();
        if ($session->get('cgsecure')) {
            return;
        } // already checked
        if (!$mainframe->isClient('administrator') || !$user->guest) {
            return;
        }
        if (!$this->cgsecure_params->password) {// no check
            self::createCookie();
            return;
        }
        $prefixe = $_SERVER['SERVER_NAME'];
        $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
        $this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;

        Cgipcheck::check_ip($this, $this->myname);

        if ($this->cgsecure_params->mode) {
            if (substr(php_sapi_name(), 0, 3) == 'cgi') {
                Factory::getApplication()->enqueueMessage(Text::_('CG_SECURE_NOT_APACHE_HANDLER'), 'error');
                return true;
            }

            $logged = @$_SERVER['PHP_AUTH_PW'] == $this->cgsecure_params->password;
            if (!$logged) {
                header('WWW-Authenticate: Basic realm="'.$mainframe->getCfg('sitename').'"');
                header('HTTP/1.0 401 Unauthorized');
                die();
            }
        } else { // Compatibility : looking for ?<password>
            $logged = isset($_GET[$this->cgsecure_params->password]);
            if (!$logged) {
                if (($this->cgsecure_params->selredir == 'LOCAL') || (Cgipcheck::whiteList())) {
                    $mainframe->redirect(URI::root());
                } else {
                    $mainframe->redirect($this->cgsecure_params->redir_ext);
                }
            }
        }
        if ($logged) {
            $session->set('cgsecure', true);
            self::createCookie();
        }
    }
    private function createCookie()
    {
        $secure = array_key_exists("HTTPS", $_SERVER);
        return setcookie('cg_secure', $this->cgsecure_params->security, [
                        'expires' => 'Session',
                        'path' => '/',
                        'domain' => '',
                        'samesite' => 'Lax',
                        'secure' => $secure,
                        'httponly' => false
            ]);
    }
}
