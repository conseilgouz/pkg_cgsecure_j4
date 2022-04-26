<?php
/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
 *	
 * from Blackhole for Bad Bots https://perishablepress.com/blackhole-bad-bots/
*/
use Joomla\CMS\Log\Log;
use Joomla\CMS\Factory;
const _JEXEC = 1;
define('BLACKHOLE_PATH', dirname(__FILE__) .'/');

error_reporting(E_ALL | E_NOTICE);
ini_set('display_errors', 1);

// Load system defines
if (file_exists(dirname(__DIR__) . '/defines.php'))
{
    require_once dirname(__DIR__) . '/defines.php';
}
if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', dirname(__DIR__));
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_LIBRARIES . '/import.legacy.php';
require_once JPATH_LIBRARIES . '/cms.php';

Factory::getApplication('site');  // start application, otherwise JText won't work....
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
	$ip = '::1'; // localhost
} else {
	$ip = $_SERVER['REMOTE_ADDR'];
}
$helperFile = JPATH_SITE . '/libraries/cgsecure/ipcheck.php';
if (!class_exists('CGIpCheckHelper') && is_file($helperFile))	include_once $helperFile;
if (!class_exists('CGIpCheckHelper')) { // library not found
	return  true;
}
$cgsecure_params = \CGIpCheckHelper::getParams();
if (cgsecure_ip_whiteList($cgsecure_params,$ip)) die('Nothing to see here'); // ip in whitelist
if (\CGIpCheckHelper::getLatest_ips($ip)) die('Restricted access'); // already blocked : die
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? blackhole_sanitize($_SERVER['HTTP_USER_AGENT']) : null;

if (blackhole_whitelist($ua)) die('Nothing to see here');
// check CG Secure IP WhiteList
function cgsecure_ip_whitelist($cgsecure_params,$ip) {
	$whitelist = $cgsecure_params->whitelist;
	$arr_whitelist = explode(',',$whitelist);
	if ( in_array($ip, $arr_whitelist) || ($ip == '::1') || ($ip == '127.0.0.1')) { // dans liste ou local
	    return true;
	}
	return false;
}

function blackhole_whitelist($ua) {
	if (preg_match("/(a6-indexer|adsbot-google|ahrefsbot|aolbuild|apis-google|baidu|bingbot|bingpreview|butterfly|cloudflare|duckduckgo|embedly|facebookexternalhit|facebot|googlebot|ia_archiver|linkedinbot|mediapartners-google|msnbot|netcraftsurvey|outbrain|pinterest|quora|rogerbot|showyoubot|slackbot|slurp|sogou|teoma|tweetmemebot|twitterbot|uptimerobot|urlresolver|vkshare|w3c_validator|wordpress|wprocketbot|yandex)/i", $ua)) {
		return true;
	}
	return false;
}
function blackhole_sanitize($string) {
	$string = trim($string); 
	$string = strip_tags($string);
	$string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
	$string = str_replace("\n", "", $string);
	$string = trim($string); 
	return $string;
}
$myname='CGSecureRobots';
$tmp = '<html lang="fr-fr" dir="ltr"><head><meta charset="utf-8" />
      <title>Erreur: CG Secure HtAccess Blocked</title></head>';
$tmp = '<style>.text-center {text-align: center !important;}.align-self-center{align-self: center !important;}</style>';
$tmp .= '<body class="error-page" style="" ><div class="text-center align-self-center">';
$tmp .= '<h1>'.JText::_('CGSECURE_MSG_H1').'</h1>';
$tmp .= '<div>'	 ;
$prefixe = $_SERVER['SERVER_NAME'];
$prefixe = substr(str_replace('www.','',$prefixe),0,2);
$ctl = false;
$errtype = "e"; // supposed blocking error
// init error message
$err = JText::sprintf('CGSECURE_MSG_BAD',$ua);
$tmp .= $err.'</div></body></html>';
echo $tmp;
$err = $prefixe.$errtype.'-'.$err;
\CGIpCheckHelper::block_hacker($myname,$err,$errtype,$ip);
if ($cgsecure_params->logging_bad == 1)  {
	Log::addLogger(array('text_file' => 'cgbadrobots.trace.php'), Log::DEBUG,Array('CGBadRobots'));
	Log::add($ip.' : '.$err, Log::DEBUG, 'CGBadRobots'); 
}
// CG Secure report to AbuseIP and reject it unsing htaccess file (if errortype = e)
$report = $cgsecure_params->report == 1;
if ($report) \CGIpCheckHelper::report_hacker($myname,$err,$errtype,$ip);
