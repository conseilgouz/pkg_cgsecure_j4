<?php
/**
 * @component      CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
 *
**/
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Utilities\IpHelper;
use ConseilGouz\CGSecure\Cgipcheck;

const _JEXEC = 1;

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php')) {
    require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES')) {
    define('JPATH_BASE', dirname(__DIR__).'/../../');
    require_once JPATH_BASE . '/includes/defines.php';
}
// Get the framework.
require_once JPATH_BASE . '/includes/framework.php';

$container = \Joomla\CMS\Factory::getContainer();
$container->alias('session.web', 'session.web.site')
->alias('session', 'session.web.site')
->alias('JSession', 'session.web.site')
->alias(\Joomla\CMS\Session\Session::class, 'session.web.site')
->alias(\Joomla\Session\Session::class, 'session.web.site')
->alias(\Joomla\Session\SessionInterface::class, 'session.web.site');

$app = $container->get(\Joomla\CMS\Application\SiteApplication::class);

// Set the application as global app
\Joomla\CMS\Factory::$application = $app;

$session  = Factory::getApplication()->getSession();
$sec      = $session->get('cgsecure');
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
header('Access-Control-Allow-Origin: *');

$myname = 'CGSecureHTAccess';
// namespace does not work on cli
$helperFile = JPATH_SITE . '/libraries/cgsecure/src/Cgipcheck.php';
if (is_file($helperFile)) {
    include_once $helperFile;
}
$cgsecure_params = Cgipcheck::getParams();
$ip = IpHelper::getIp();//  $_SERVER['REMOTE_ADDR'];
$ip = '218.92.1.234'; // test hackeur chinois

$tmp = '<html lang="fr-fr" dir="ltr"><head><meta charset="utf-8" />
      <title>Erreur: CG Secure HtAccess Blocked</title></head>';
$tmp = '<style>.text-center {text-align: center !important;}.align-self-center{align-self: center !important;}</style>';
$tmp .= '<body class="error-page" style="" ><div class="text-center align-self-center">';
$tmp .= '<h1>'.Text::_('CGSECURE_MSG_H1').'</h1>';
$tmp .= '<div>'	 ;

$req = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); // sanitize command
if (strpos($req, 'cgsecure/htaccess') !== false) {
    $req = "..."; // lost URI in redirects
}

if (Cgipcheck::whiteList($ip)) { // white list : display error message
    $tmp .= '<h3>'.$req.'</h3></body></html>';
    $tmp .= '</body></html>';
    echo $tmp;
    return;
}
if (Cgipcheck::getLatest_ips($ip)) {
    die('Restricted access');
} // already blocked : die

$security = $cgsecure_params->security;

if (isset($_COOKIE['cg_secure']) && ($_COOKIE['cg_secure'] == $security)) {
    $tmp .= '<h3>'.$req.'</h3></body></html>';
    echo $tmp;
    return ;
} // CG Secure OK : on ignore les erreurs htaccess

$prefixe = $_SERVER['SERVER_NAME'];
$prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
$ctl = false;
$errtype = "e"; // supposed blocking error
// init error message
$err = "Wrong message";
$block = "";
if (isset($_SERVER['REDIRECT_STATUS'])) {
    $tmp .= '<h3>';
    $line = substr($req, 0, 100);
    $compl = (strlen($req) < 101) ? '' : '...';
    foreach ($_GET as $key => $value) {
        if (($key == "sec") && ($value == $security)) {
            $ctl = true;
        }
        if ($key == "e") {
            $err = (int)$value.' : '.Text::_('CGSECURE_MSG_'.(int)$value).'=>'.$line.$compl;
        }
        if ($key == "t") {
            $errtype = substr($value, 0, 1);
        } // one char only
        if (strpos($value, '___')) {
            $block = '('.str_replace('___', '', $value).')';
        }
        if (($key != "e") && ($key != "sec") && ($key != "t") && ($key != "m")) {
            $err = "Wrong key : ".substr($key, 0, 5)." =>".$line.$compl;
        }
    }
    if (!$ctl) {
        $err = 'Security key failure';
    }
} else {
    $err = "Direct access to plugin not allowed";
}
$tmp .= $err.$block.'</h3></body></html>';
echo $tmp;
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
die();
