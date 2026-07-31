<?php

/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Api\Controller;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\IpHelper;
use ConseilGouz\CGSecure\Cgipcheck;
use ConseilGouz\CGSecure\Helper\CGSecureHelper;

\defined('_JEXEC') or die;

class CgsecureController extends ApiController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  3.0
     */
    protected $default_view = 'htaccess';

    public function execute($task)
    {
        try {
            parent::execute($task);
        } catch (\RuntimeException $e) {
            $this->failWithError($e);
        }
        $this->app->redirect(URI::root());
        throw new NotAllowed(Factory::getApplication()->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
    }
    public function secure()
    {
        $input = Factory::getApplication()->getInput();
        $session  = Factory::getApplication()->getSession();
        $lang     = null; // default language (gb)
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if ($lang == 'fr') {
                $lang = 'fr-FR';
            }
        }
        $language = Factory::getApplication()->getLanguage();
        if (!$language) {
            Factory::getApplication()->loadLanguage();
            $language = Factory::getApplication()->getLanguage();
        }
        if ($language) {
            $language->load('com_cgsecure', JPATH_ADMINISTRATOR, $lang, true);
        }
        $myname = 'CGSecureHTAccess';
        $cgsecure_params = CGSecureHelper::getParams();
        $security = $cgsecure_params->security;
        $ip = IpHelper::getIp();//
        // $ip = '113.206.183.117'; // test hackeur chinois

        $req = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); // sanitize command

        if (CGSecureHelper::whiteList($ip)) { // white list : display error message
            if (($cgsecure_params->logging_ht == 1) || ($cgsecure_params->logging_ht == 2)) {
                Log::addLogger(array('text_file' => 'cghtaccess.trace.php'), Log::DEBUG, array('CGHTAccess'));
                Log::add('White list : '.$req, Log::DEBUG, 'CGHTAccess');
            }
            return;
        }
        if (Cgipcheck::getLatest_ips($ip)) {
            $this->app->redirect(URI::root());
            die('Restricted access');
        } // already blocked : die
        $user 	  = $this->app->getIdentity();
        if (($this->app->isClient('administrator') || !$user->guest) && (isset($_COOKIE['cg_secure']) && ($_COOKIE['cg_secure'] == $security))) {
            if (($cgsecure_params->logging_ht == 1) || ($cgsecure_params->logging_ht == 2)) {
                Log::addLogger(array('text_file' => 'cghtaccess.trace.php'), Log::DEBUG, array('CGHTAccess'));
                Log::add('White list cookie : '.$req, Log::DEBUG, 'CGHTAccess');
            }
            return ;
        } // CG Secure OK : on ignore les erreurs htaccess
        $prefixe = $_SERVER['SERVER_NAME'];
        $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
        $ctl = false;
        $errtype = "e"; // supposed blocking error
        $err = "Wrong message";
        $block = "";
        $tmp = "";
        if (isset($_SERVER['REQUEST_METHOD'])) {
            $line = "";//substr($req, 0, 300);
            $compl = (strlen($req) < 301) ? '' : '...';
            foreach ($_GET as $key => $value) {
                if (($key == "sec") && ($value == $security)) {
                    $ctl = true;
                }
                if ($key == "e") {
                    $err = (int)$value;//
                }
                if ($key == "t") {
                    $errtype = substr($value, 0, 1);
                } // one char only
                if ($key == "m") {
                    $compl = $value;
                } // one char only
                if ($key == "req") {
                    $line = substr($value, 0, 300);
                    // $line = trim($line, JPATH_BASE);
                    $compl = (strlen($line) < 301) ? '' : '...';
                } // one char only
                if (strpos($value, '___')) {
                    $block = '('.str_replace('___', '', $value).')';
                }
                if (($key != "e") && ($key != "sec") && ($key != "t") && ($key != "m") && ($key != "req")) {
                    $err = "Wrong key : ".substr($key, 0, 5)." =>".$line.$compl;
                }
            }
            $err .= ' : '.Text::_('CGSECURE_MSG_'.(int)$err).'=>'.$line.$compl;
            if (!$ctl) {
                $err = 'Security key failure'.$line;
            }
        } else {
            $err = "Direct access to plugin not allowed.";
        }
        $err = $prefixe.$errtype.'-'.$err;
        if (($cgsecure_params->logging_ht == 1) || (($cgsecure_params->logging_ht == 2) && ($errtype == "e"))) {
            Log::addLogger(array('text_file' => 'cghtaccess.trace.php'), Log::DEBUG, array('CGHTAccess'));
            Log::add($err.$block, Log::DEBUG, 'CGHTAccess');
        }
        // CG Secure report to AbuseIP and reject it unsing htaccess file (if errortype = e)
        $report = $cgsecure_params->report;
        if ($report) {
            Cgipcheck::report_hacker($myname, $err.$block, $errtype, $ip);
        }

        throw new NotAllowed(Factory::getApplication()->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
    }

    private function failWithError(\Throwable $e)
    {
        $errorCode = $e->getCode() ?: 500;
        $this->app->setHeader('status', $errorCode);
        $this->app->redirect(URI::root());
    }

}
