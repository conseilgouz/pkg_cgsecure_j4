<?php
/**
 * @component      CG Secure
 * Version		   2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
 *
**/
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

if(isset($_COOKIE['cg_secure'])) { return ; } // CG Secure OK : on ignore les erreurs htaccess

const _JEXEC = 1;

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
    require_once dirname(__DIR__) . '/defines.php';
}

if (!defined('_JDEFINES'))
{
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

$session = Factory::getSession();
$sec = $session->get('cgsecure');
// Factory::getApplication('site');  // start application, otherwise JText won't work....
$language = Factory::getLanguage();
$lang = null; // default language (gb)
if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ) {
	$lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'],0,2);
	if ($lang == 'fr') {
		$lang = 'fr-FR';
	} 
}
$language->load('com_cgsecure',JPATH_ADMINISTRATOR,$lang,true);
header('Access-Control-Allow-Origin: *');
if (($_SERVER['REMOTE_ADDR'] == '::1') ||  ($_SERVER['REMOTE_ADDR'] == '127.0.0.1')) {
	$ip = '::1';
} else {
	$ip = $_SERVER['REMOTE_ADDR'];
}
//$ip = '218.92.0.11'; // test
$myname='CGSecureHTAccess';
$helperFile = JPATH_SITE . '/libraries/cgsecure/ipcheck.php';
if (!class_exists('CGIpCheckHelper') && is_file($helperFile))	include_once $helperFile;
if (!class_exists('CGIpCheckHelper')) { // library not found
	return  true;
}
if (CGIpCheckHelper::getLatest_ips($ip)) die('Restricted access'); // already blocked : die
$cgsecure_params = CGIpCheckHelper::getParams();
$security = $cgsecure_params->security;
$tmp = '<html lang="fr-fr" dir="ltr"><head><meta charset="utf-8" />
      <title>Erreur: CG Secure HtAccess Blocked</title></head>';
$tmp = '<style>.text-center {text-align: center !important;}.align-self-center{align-self: center !important;}</style>';
$tmp .= '<body class="error-page" style="" ><div class="text-center align-self-center">';
$tmp .= '<h1>'.Text::_('CGSECURE_MSG_H1').'</h1>';
$tmp .= '<div>'	 ;
$prefixe = $_SERVER['SERVER_NAME'];
$prefixe = substr(str_replace('www.','',$prefixe),0,2);
$ctl = false;
$errtype = "e"; // supposed blocking error
// init error message
$err = "Wrong message";
$block = "";
if (isset($_SERVER['REDIRECT_STATUS'])) {
   $req = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES); // sanitize command
   if (strpos('cgsecure/htaccess',$req) !== false) $req = "..."; // lost URI in redirect
   $line = substr($req,0,100);
   $compl = (strlen($req) < 101) ? '' : '...';
   foreach ($_GET as $key => $value) {
	  if (($key == "sec") && ($value == $security)) $ctl = true;
	  if ($key == "e") $err = (int)$value.' : '.Text::_('CGSECURE_MSG_'.(int)$value).'=>'.$line.$compl;
	  if ($key == "t") $errtype = substr($value,0,1); // one char only
	  if ($key == "m") $block = '('.str_replace('___','',$value).')'; 
	  if (($key != "e") && ($key != "sec") && ($key != "t") && ($key != "m")) $err = "Wrong key : ".substr($key,0,5)." =>".$line.$compl; 
   }
   if (!$ctl) $err = 'Security key failure'; 
} else {
   $err = "Direct access to plugin not allowed";
}
$tmp .= $err.$block.'</ul></div></div></body></html>';
echo $tmp;
	
																			
	
  
		  
$err = $prefixe.$errtype.'-'.$err;
if (($cgsecure_params->logging_ht == 1) || (($cgsecure_params->logging_ht == 2) && ($errtype == "e")))  {
	Log::addLogger(array('text_file' => 'cghtaccess.trace.php'), Log::DEBUG,Array('CGHTAccess'));
	Log::add($ip.' : '.$err, Log::DEBUG, 'CGHTAccess'); 
}
// CG Secure report to AbuseIP and reject it unsing htaccess file (if errortype = e)
$report = $cgsecure_params->report;
if ($report) \CGIpCheckHelper::report_hacker($myname,$err.$block,$errtype,$ip);
die();