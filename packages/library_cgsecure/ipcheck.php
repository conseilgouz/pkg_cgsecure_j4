<?php
/**
 * @component      CG Secure
 * Version		   2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (c) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
 *
 * AbuseIPDB access from https://www.webniraj.com/2019/03/12/auto-reporting-lfd-block-reports-to-abuse-ip-db-v2/
**/
// No direct access.
defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Log\Log;
use Joomla\Utilities\IpHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Table\Table;

class CGIpCheckHelper{
    private static $my_name = "CGIpCheck";
	private static $abuseIPDB = "https://api.abuseipdb.com/api/v2/";
	private static $iplocate = 'https://www.iplocate.io/api/lookup/';	
	private static $report, $logging,$blockip;
	private static $latest_rejected;
	private static $latest_hacker;
	private static $params;
	private static $latest_ips = array();
	private static $context,$caller, $message,$errtype;
	const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';

	// get CG Secure params
	public static function getParams() {

        $db = Factory::getDBo();
		$table = Table::getInstance('ConfigTable','ConseilGouz\\Component\\CGSecure\Administrator\\Table\\', array('dbo' => $db));
		if (!$table) {// appel par fichier .htaccess
		    $query = $db->getQuery(true);
		    $query->select('*')
		    ->from($db->quoteName('#__cgsecure_config'));
		    $db->setQuery($query);
		    try {
		        $params = $db->loadObject();
		        self::$params = json_decode($params->params);
		        return self::$params;
		    }
		    catch (RuntimeException $e) {
		        return array();
		    }
		}
		self::$params = json_decode($table->getSecureParams()->params);
	    return self::$params;
	}
	// check brute force
	public static function getLatest_ips($ip) {
		// read latest_ips file
	    $file = JPATH_ROOT . '/media/com_cgsecure/backup/latest_ips.txt';
		$readBuffer = file($file, FILE_IGNORE_NEW_LINES);
		$outBuffer = '';
		foreach ($readBuffer as $id => $line){
		    self::$latest_ips[] = $line; 
		}
		// check if present
		if (self::whiteList($ip)) return false;	
		if (in_array($ip,self::$latest_ips)) return true;
										  
		self::$latest_ips[] = $ip;
		if (count(self::$latest_ips) > 20) array_shift(self::$latest_ips);
		$out = '';
		foreach (self::$latest_ips as $val) {
		    $out .= $val.PHP_EOL; 
		}
		// not in file yet : store it
		if (is_readable($file))	{
				// Write the htaccess using the Frameworks File Class
				File::write($file,$out );
						  
					
		}
		return false;
	}
	// Check if IP is allowed or not
	public static function check_ip($plugin, $context) {
        $plugin->loadLanguage();
		self::$caller = $plugin->myname;
		self::$message = $plugin->mymessage;
		self::$errtype = $plugin->errtype;
		self::$context = $context;
        self::$latest_rejected = self::get_rejected();
		// $ip = IpHelper::getIp(); 
		$ip = $_SERVER['REMOTE_ADDR'];
 // $ip = '218.92.1.534'; // test hackeur chinois 
 // $ip = '54.36.148.179'; // in abuseip whitelist
		if (self::whiteList($ip)) return true; 
		self::$logging = self::$params->logging == 1;
		self::$report = self::$params->report == 1;
		self::$blockip  = self::$params->blockip == 1;
		if (self::$logging) Log::addLogger(array('text_file' => 'cgipcheck.trace.log'), Log::DEBUG, array(self::$caller));
		if (in_array($ip,self::$latest_rejected)) {
		    if (self::check_hacker(self::$errtype,$ip)) { // no errtype change : ok
				if (self::$logging) Log::add(self::$context.' : '.JText::_('CG_IPCHECK_ALREADY_REJECTED').$ip, Log::DEBUG, self::$caller); 
				self::redir_out();
				return false;
			}
		}
		if (extension_loaded('curl')) {
			$countries = self::$params->country;
			$pays_autorise = explode(',',$countries);
			$resp = self::abuseIPDBrequest('check', 'GET', [ 'ipAddress' => $ip, 'maxAgeInDays' => 30, 'verbose' => true ]);
			if (!isset($resp->data)) { // AbuseIP Error
				if (isset($resp->errors)) {
					if (self::$logging) Log::add(self::$context.' : '.'AbuseIPDB not working : '.$ip.', err: '.$resp->errors->detail, Log::DEBUG, self::$caller);
				} else {
					if (self::$logging) Log::add(self::$context.' : '.'AbuseIPDB not working : '.$ip, Log::DEBUG, self::$caller);
				}
				// mode dégradé : check only country code, no reporting
				$response = self::getIPLocate_via_curl(self::$iplocate.$ip);
				$json_array = json_decode($response);
				if ($json_array->country_code == "") { // IPLocate perdu : on suppose hackeur
					if (self::$logging) Log::add('IP Locate error : '.$json_array->country_code, Log::DEBUG, self::$caller);
					self::set_rejected(self::$caller,self::$errtype,$ip,'unknown',self::$params->keep);
					self::redir_out();
				} elseif (($countries != '*') && (!in_array($json_array->country_code,$pays_autorise))) {
					if (self::$logging) Log::add(JText::_("CG_IPCHECK_UNAUTHORIZED").$json_array->country_code, Log::DEBUG, self::$caller);
					self::set_rejected(self::$caller,self::$errtype,$ip,$json_array->country_code,self::$params->keep);
					self::redir_out();
				}				
				return;
			}
			if ($resp->data->isWhitelisted) { // in AbuseIPDB whitelist 
			    self::redir_out(); 
			    return true; 
			}
			// Verifie si l'IP du visiteur est dans la liste des pays que j'ai autorise
			if ($resp->data->countryCode == "") { // AbuseIPDB : no country
			    if (self::$logging) Log::add(self::$context.' : '.'Country not found in AbuseIPDB, ip '.$ip.','.$resp->data->countryCode, Log::DEBUG, self::$caller);
				// mode dégradé
				$response = self::getIPLocate_via_curl(self::$iplocate.$ip);
				if ($response) { // IPLocate OK
					$json_array = json_decode($response);
					if ($json_array->country_code == "") { // IPLocate perdu : on suppose hackeur
						if (self::$logging) Log::add('IP Locate error : unknown country', Log::DEBUG, self::$caller);
						if (self::$report) self::report(self::$context,$ip);
						self::set_rejected(self::$caller,self::$errtype,$ip,'unknown',self::$params->keep);
						self::redir_out();
					} elseif (($countries != '*') && (!in_array($json_array->country_code,$pays_autorise))) { 
						if (self::$report) self::report(self::$context,$ip);
						if (self::$logging) Log::add(JText::_("CG_IPCHECK_UNAUTHORIZED").$json_array->country_code, Log::DEBUG, self::$caller);
						self::set_rejected(self::$caller,self::$errtype,$ip,$json_array->country_code,self::$params->keep);
						self::redir_out();
					
					}
				} else { // IPLocate error
					if (self::$logging) Log::add('IP Locate error : unknown country', Log::DEBUG, self::$caller);
					if (self::$report) self::report(self::$context,$ip);
					self::set_rejected(self::$caller,self::$errtype,$ip,'unknown',self::$params->keep);
					self::redir_out();
				}
			} elseif (($countries != '*') && (!in_array($resp->data->countryCode,$pays_autorise))) {
			    if (self::$logging) Log::add(self::$context.' : '.JText::_("CG_IPCHECK_UNAUTHORIZED").$ip.','.$resp->data->countryCode, Log::DEBUG, self::$caller);
			    if (self::$report) self::report(self::$context,$ip);
			    self::set_rejected(self::$caller,self::$errtype,$ip,$resp->data->countryCode,self::$params->keep);
			    self::redir_out();
			} elseif (isset($resp->data->reports) && (count($resp->data->reports) > 0)) { // country OK : check if already reported
			    if (self::$logging) Log::add(self::$context.' : SPAM, ip: '.$ip.', reported = '.count($resp->data->reports), Log::DEBUG, self::$caller);
			    if (self::$report) self::report(self::$context,$ip);
			    self::set_rejected(self::$caller,self::$errtype,$ip,$resp->data->countryCode,self::$params->keep);
			    self::redir_out();
			}
		} else { // pas de controle possible : on sort
		    if (self::$logging) Log::add(self::$context.' : '."Curl not installed: ", Log::DEBUG,  self::$caller);
		}
	}
	// Check Spammer status in AbuseIPDB 
	// return OK: a spammer
	public static function check_spammer($plugin, $context) {
        $plugin->loadLanguage();
		self::$caller = $plugin->myname;
		self::$message = $plugin->mymessage;
		self::$context = $context;
		$ip = $_SERVER['REMOTE_ADDR'];
        if (self::$context  != 'SystemCGSecure') { // no test when system, otherwise, you'll loose your admin....
 // $ip = '222.186.42.7'; // test hackeur chinois 
		}
		if (self::whiteList($ip)) return false;

		self::$logging = self::$params->logging == 1;
		if (self::$logging) Log::addLogger(array('text_file' => 'cgipcheck.trace.log'), Log::DEBUG, array(self::$caller));
		if (extension_loaded('curl')) {
			$resp = self::abuseIPDBrequest('check', 'GET', [ 'ipAddress' => $ip, 'maxAgeInDays' => 30, 'verbose' => true ]);
			if (!isset($resp->data)) {
				if (isset($resp->errors)) {
					if (self::$logging) Log::add(self::$context.' : '.'AbuseIPDB not working : '.$ip.', err: '.$resp->errors->detail, Log::DEBUG, self::$caller);
				} else {
					if (self::$logging) Log::add(self::$context.' : '.'AbuseIPDB not working : '.$ip, Log::DEBUG, self::$caller);
				}
				return false; // suppose OK
			}
			if ($resp->data->isWhitelisted) return false; // in AbuseIPDB whitelist
      	// Verifie si l'IP du visiteur est dans la liste des pays que j'ai autorise
			if (isset($resp->data->countryCode) && ( $resp->data->countryCode == "") ) { // AbuseIPDB perdu
			    if (self::$logging) Log::add(self::$context.' : '.'Country not found in AbuseIPDB, ip '.$ip.','.$resp->data->countryCode, Log::DEBUG, self::$caller);
				return true; // spammeur
			} elseif (isset($resp->data->reports) && (count($resp->data->reports) > 0) ) { // country OK : check if already reported
			    if (self::$logging) Log::add(self::$context.' : SPAM, ip: '.$ip.', reported = '.count($resp->data->reports), Log::DEBUG, self::$caller);
				return true; // spammeur
			}
		} else { // pas de controle possible : on sort
		    if (self::$logging) Log::add(self::$context.' : '."Curl not installed: ", Log::DEBUG,  self::$caller);
		}
		return false; // OK
	}
	// Check IP in whitelist or local
    public static function whiteList($ip = NULL) {
		if (!$ip) $ip = $_SERVER['REMOTE_ADDR'];
		$whitelist = self::$params->whitelist;
		$arr_whitelist = explode(',',$whitelist);
		if ( in_array($ip, $arr_whitelist) || ($ip == '::1') || ($ip == '127.0.0.1')) { // dans liste ou local
		    return true;
		}
		return false;
	}
	// Report hacking blocked by htaccess
	public static function report_hacker($plugin,$message,$errtype,$ip) {
		self::$message = $message;
        self::$latest_hacker = self::get_rejected();
//         $ip = '222.186.42.7'; // test hackeur chinois 
		if (self::whiteList($ip)) return true;
		if (in_array($ip,self::$latest_hacker)) { // already in database
		    if (self::check_hacker($errtype,$ip)) return; // no errtype change : ok
		}
		if ($errtype == "e") { // report only errors to abuseipdb
			try {
				self::report($plugin.' : '.$message,$ip); // report hacker
			} catch (Exception $e){
				$err = $plugin.' : Exception Report : '.$e->getMessage();
				if (self::$logging) Log::add($err,'debug',  $plugin);
			}
		}
		try {
		  self::reject_hacker($plugin,$message,$errtype,$ip); // store in DB
		} catch (Exception $e) {
		    $err = $plugin.' : Exception Reject_Hacker : '.$e->getMessage();
		    if (self::$logging) Log::add($err,'debug',  $plugin);
		}
	}
	// store IP address in .htaccess file
	public static function block_hacker($myname,$err,$errtype,$ip) {
		if (self::$params->report == 0) {// not stored yet in DB
			self::$latest_hacker = self::get_rejected();
			if (self::whiteList($ip)) return;
			if (in_array($ip,self::$latest_hacker)) { // already in database
				if (self::check_hacker($errtype,$ip)) return; // no errtype change : ok
			}
			try {
			 self::reject_hacker($myname,$err,$errtype,$ip); // store it
			} catch (Exception $e) {
			    $err = $myname.' : Exception Reject_Hacker : '.$e->getMessage();
			    if (self::$logging) Log::add($err,'debug',  $myname);
		    
			}
		}
		$hackers = self::get_htaccess_List(); // hackers with blocking errors
		self::store_htaccess($hackers);
	}
	// store hacker's IP in database
	private static function reject_hacker($plugin,$message,$errtype,$ip) {
		$response = self::getIPLocate_via_curl(self::$iplocate.$ip);
		if ($response) {
		    $json_array = json_decode($response);
    		if ($json_array->country_code == "") { // IPLocate perdu : on suppose hackeur
			 self::set_rejected($plugin,$errtype,$ip,'unknown',self::$params->keep);
		    } else { 
			 self::set_rejected($plugin,$errtype,$ip,$json_array->country_code,self::$params->keep);
		    }		
		} else { // iplocate not working
			self::set_rejected($plugin,$errtype,$ip,'unknown',self::$params->keep);
		}
	}
	// check if new errortype = old errortype for current hacker
	private static function check_hacker($errtype,$ip) {
		$db = Factory::getDBO();
		$query = $db->getQuery(true);
        $query->select($db->quoteName('errtype'))
	            ->from($db->quoteName('#__cg_rejected_ip'))
	            ->where($db->quoteName('ip').'="'.$ip.'"');
        $db->setQuery($query);
        try {
	        $type = $db->loadResult();
        }
        catch (RuntimeException $e) {
        	return true; // db error
        }
		if (($type == $errtype) || ($errtype != "e")) return true;

		$query = $db->getQuery(true);
        $query->delete($db->quoteName('#__cg_rejected_ip'))
        ->where($db->quoteName('ip').'= "'.$ip.'"');
        $db->setQuery($query);
		$db->execute();
		return false; // force new record creation
	}
	// redirect unwanted guests
	private static function redir_out() {
	    $mainframe 	= Factory::getApplication();
	    if ((self::$params->selredir == 'LOCAL') || (self::whiteList())) {
	        $mainframe->redirect(URI::root());
	    } else {
	        $mainframe->redirect(self::$params->redir_ext);
	    }
	}
	// from https://stackoverflow.com/questions/3003145/how-to-get-the-client-ip-address-in-php
	private static function get_ip() {
	    if (($_SERVER['REMOTE_ADDR'] == '::1') ||  ($_SERVER['REMOTE_ADDR'] == '127.0.0.1')) return '::1';
		return $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
	}
// curl request function
	private static function abuseIPDBrequest($path, $method, $data) {
		$key = self::$params->api_key;
		if ($key == '') $key = 'e7d05d2a802351a36dfe63bcc65d7a33e52c6ccd2fe18f0342abe688a7b2f68e0eb72043d135a38d';
		$url = self::$abuseIPDB . $path;
    // open curl connection
		$ch = curl_init();
    // set the method and data to send
		if ($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		} else {
			$url .= '?' . http_build_query($data);
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Accept: application/json;',
			'Key: ' .$key ,
		]);
		$result = curl_exec($ch);
		curl_close($ch);
		return json_decode($result);
	}
/* report IP to AbuseIPDB */
	private static function report ($context,$ip) {	

	    if  (self::$params->api_key != "") { // il faut une cle API
	        $json = self::abuseIPDBrequest('report','POST',['ip'=>$ip,'categories'=>15,'comment'=>self::$message]);
			$msg = "other error";
			if ($json) {
				if (isset($json->data)) {
					$msg = "OK : abuseConfidenceScore : ".$json->data->abuseConfidenceScore;
				} elseif (isset($json->errors) && is_object($json->errors)) {
					$msg = "error : ".isset($json->errors->detail)? $json->errors->detail : "other erreor" ;
				} 
			} 
			if (self::$logging) Log::add(self::$context.' : '."AbuseIPDB : ".$msg, Log::DEBUG,  self::$caller);
	    } else {
	        if (self::$logging) Log::add(self::$context.' : AbuseIPDB API key required', Log::DEBUG, self::$caller);
	    }
	}
	// Get Rejected IPs list
	private static function get_rejected() {
		$db = Factory::getDBO();
		$query = $db->getQuery(true);
        $query->select($db->quoteName('ip'))
	            ->from($db->quoteName('#__cg_rejected_ip'));
        $db->setQuery($query);
        try {
	        $list = $db->loadColumn();
        }
        catch (RuntimeException $e) {
        	return array();
        }
		return $list;
	}
	// Store rejected IP after deleting out-dated IP's
	private static function set_rejected($action, $errtype, $ip, $country,$keep) {
		$db = Factory::getDBO();		
	// delete older ip as defined in params
		$delete = gmdate("Y-m-d H:i:s", time() -  ((int)$keep * 24 * 60 * 60)); 
		$query = $db->getQuery(true);
		$conditions = array(
				$db->quoteName('attempt_date') . ' < ' . $db->quote($delete)
		);
		$query->delete($db->quoteName('#__cg_rejected_ip'));
		$query->where($conditions);
		$db->setQuery($query);
		$db->execute();		
	// insert new rejected ip	
        $sDate = gmdate("Y-m-d H:i:s", time());
		$query = $db->getQuery(true);
		$columns = array('action','errtype','ip','country','attempt_date');
		$values = array($db->quote($action),$db->quote($errtype),$db->quote($ip), $db->quote($country),$db->quote($sDate));
		$query->insert($db->quoteName('#__cg_rejected_ip'))
			->columns($db->quoteName($columns))
			->values(implode(',', $values));
        $db->setQuery($query);
        try {
            $db->execute();
        }
        catch (Exception $e) {
            return false;
        }
		self::$blockip  = self::$params->blockip == 1;
		if ((self::$blockip == 1) && ($errtype = "e")) { // new hacker 
			$hackers = self::get_htaccess_List(); //get hackers list
			self::store_htaccess($hackers); // block them
		}
		return true;
	}
	// mode dégradé : on cherche la pays par IPLocate
	private static function getIPLocate_via_curl($url)
    {
		try {
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_TIMEOUT, 10);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($curl, CURLOPT_URL, $url);
			$response = curl_exec($curl);
			curl_close ($curl);
			return $response;
		} catch (Exception $e) {
			return null;
		}
    }
	// Get HTAccess Rejected IPs list
	private static function get_htaccess_List() {
		$db = Factory::getDBO();
		$where = " errtype LIKE 'e'";
		$query = $db->getQuery(true);
        $query->select($db->quoteName('ip'))
	            ->from($db->quoteName('#__cg_rejected_ip'))
				->where($where)
				->order('ip ASC');
        $db->setQuery($query);
        try {
	        $list = $db->loadColumn();
        }
        catch (RuntimeException $e) {
        	return array();
        }
		return $list;
	}
	private static function store_htaccess($list) {
	    $wait = self::getServerConfigFilePath('.inprogress'); // create a temp. file to block other requests
		if (file_exists($wait)) {// 
			return; 
		} 
		$msg = 'wait...';
		File::write($wait,$msg);
		$serverConfigFile = self::getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
		if (!$serverConfigFile) 
		{ // no .htaccess file 
		    return;
		}
		$current = self::read_current(self::getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS),$list);
		if ($current == '') { // read error : don't store anything
			File::delete($wait);
			return JText::_('CGSECURE_ADD_ADMIN_HTACCESS_INSERT_ERROR');		
		}
		if (self::store_file(self::getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS),$current)) {
			File::delete($wait);
	        return;
	    } 
		File::delete($wait);
	    return JText::_('CGSECURE_ADD_ADMIN_HTACCESS_INSERT_ERROR');		
	}
	// read current .htaccess file, add new IP list and remove old IP list
	private static function read_current($afile,$list) {
		$readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
		$outBuffer = '';
		if (!$readBuffer)
		{
			// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}
		$cgLines = false;

		foreach ($readBuffer as $id => $line)
		{
			if (strpos($line,'CG SECURE HTACCESS BEGIN') !== false)	 
			{ // insert new hackers' table before CG htccess lines 
				$outBuffer .= self::create_ips($list);
			}
			if ($line === '#------------------------CG SECURE IP LIST BEGIN---------------------')
			{
				$cgLines = true;
				continue;
			}

			if ($line === '#------------------------CG SECURE IP LIST END--------------------')
			{
				$cgLines = false;
				continue;
			}

			if ($cgLines)
			{
				// When we are between our makers all content should be removed
				continue;
			}

			$outBuffer .= $line . PHP_EOL;
		}
		return $outBuffer;
	}
	private static function create_ips($list) {
		$ret = "#------------------------CG SECURE IP LIST BEGIN---------------------". PHP_EOL;
		$ret .= "#Type serveur : ".$_SERVER['SERVER_SOFTWARE']. PHP_EOL;
		$ret .= "<IfModule mod_authz_core.c>".PHP_EOL;
		$ret .= "<RequireAll>". PHP_EOL;
		$ret .= "Require all granted". PHP_EOL;
		foreach($list as $key => $ip) {
			$ret .= "require not ip ".$ip.PHP_EOL;
		}
		$ret .= "</RequireAll>". PHP_EOL;
		$ret .= "</IfModule>".PHP_EOL;
		$ret .= '#------------------------CG SECURE IP LIST END--------------------'. PHP_EOL;
		return $ret;
	}
	private static function store_file($htaccess, $current) {
		$pathToHtaccess  = $htaccess;
		if (file_exists($pathToHtaccess))
		{
			if (is_readable($pathToHtaccess))
			{
			    $records = $current;
				// Write the htaccess using the Frameworks File Class
				return File::write($pathToHtaccess,$records );
			}
		}
		return JText::_('CGSECURE_ADD_ADMIN_HTACCESS_MERGE_ERROR');;
	}
	
	private static function getServerConfigFile($file)
	{
		if (file_exists(self::getServerConfigFilePath($file))
			&& substr(strtolower($_SERVER['SERVER_SOFTWARE']), 0, 6) === 'apache')
		{
			return $file;
		}
		return self::SERVER_CONFIG_FILE_NONE;
	}
	private static function getServerConfigFilePath($file)
	{
		return JPATH_ROOT . DIRECTORY_SEPARATOR . $file;
	}
}

