<?php
/**
 * @component CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (c) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\CGSecure\Helper;

defined('_JEXEC') or die();

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Utilities\IpHelper;

class CGSecureHelper
{
    protected static Bool $blockipv6;
    public const CGPATH = '/media/com_cgsecure';
    public const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
    public const SERVER_CONFIG_FILE_NONE = '';
    
    // get CG Secure params
    public static function getParams()
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true);
        $query->select('*')
        ->from($db->quoteName('#__cgsecure_config'));
        $db->setQuery($query);
        try {
            $params = $db->loadObject();
        } catch (\RuntimeException $e) {
            return array();
        }
        $params = json_decode($params->params);
        return $params;
    }

    // check brute force
    public static function getLatest_ips(String $ip): Bool
    {
        $latest_ips = [];
        // read latest_ips file
        $file = JPATH_ROOT . '/media/com_cgsecure/backup/latest_ips.txt';
        $readBuffer = file($file, FILE_IGNORE_NEW_LINES);
        foreach ($readBuffer as $id => $line) {
            $latest_ips[] = $line;
        }
        // check if present
        if (self::whiteList($ip)) {
            return false;
        }
        if (in_array($ip, $latest_ips)) {
            return true;
        }
        $latest_ips[] = $ip;
        if (count($latest_ips) > 20) {
            array_shift($latest_ips);
        }
        $out = '';
        foreach ($latest_ips as $val) {
            $out .= $val.PHP_EOL;
        }
        // not in file yet : store it
        if (is_readable($file)) {
            // Write the htaccess using the Frameworks File Class
            File::write($file, $out);
        }
        return false;
    }
    // Check IP in whitelist or local
    public static function whiteList($ip = null): Bool
    {
        if (!$ip) {
            $ip = IpHelper::getIp();
        }
        $params = self::getParams();
        $whitelist = $params->whitelist;
        $whitelist = str_replace(" ", "", $whitelist); // remove any space
        $whitelist = preg_replace("/(?![a-fA-F0-9.,:]])/", "", $whitelist); // remove unwanted characters
        $arr_whitelist = explode(',', $whitelist);
        if (in_array($ip, $arr_whitelist) || ($ip == '::1') || ($ip == '127.0.0.1')) { // dans liste ou local
            return true;
        }
        return false;
    }
    // Get All Rejected IPs list
    public static function get_rejected(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select($db->quoteName('ip'))
                ->from($db->quoteName('#__cg_rejected_ip'));
        $db->setQuery($query);
        try {
            $list = $db->loadColumn();
        } catch (\RuntimeException $e) {
            return array();
        }
        return $list;
    }
    // Get HTAccess Rejected IPs list
    public static function get_reject_onerror_list() : Array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $where = " errtype LIKE 'e'";
        $query = $db->getQuery(true);
        $query->select($db->quoteName('ip'))
                ->from($db->quoteName('#__cg_rejected_ip'))
                ->where($where)
                ->order('ip ASC');
        $db->setQuery($query);
        try {
            $list = $db->loadColumn();
        } catch (\RuntimeException $e) {
            return array();
        }
        return $list;
    }

    public static function create_ips(array $list, $v6 = false): String
    {
        $ret = "#------------------------CG SECURE IP LIST BEGIN---------------------". PHP_EOL;
        $ret .= "#Type serveur : ".$_SERVER['SERVER_SOFTWARE']. PHP_EOL;
        $ret .= "<IfModule mod_authz_core.c>".PHP_EOL;
        $ret .= "<RequireAll>". PHP_EOL;
        $ret .= "Require all granted". PHP_EOL;
        foreach ($list as $key => $ip) {
            if ((strpos($ip, ':') !== false) && !$v6) {// IPV6 : ignore it : bug in OVH/APACH
                continue;
            }
            $ret .= "require not ip ".$ip.PHP_EOL;
        }
        $ret .= "</RequireAll>". PHP_EOL;
        $ret .= "</IfModule>".PHP_EOL;
        $ret .= '#------------------------CG SECURE IP LIST END--------------------'. PHP_EOL;
        return $ret;
    }

    public static function read_cgfile(String $afile): String
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $config  = self::getParams();
        $security =	$config->security;
        if ($config->multi == '1') {// site multi-adresse
            $server = '('.str_replace(',', '|', $config->get('multisite', '')).')';
            $dir = "";
        } elseif ($config->subdir == '1') { // site in subdir ?
            $server = $_SERVER['SERVER_NAME'];
            $dir = "/".$config->subdirsite;
        } else {
            $server = $_SERVER['SERVER_NAME'];
            $dir = "";
        }
        $sitename = str_replace('.', '\.', $server);
        $sitename = str_replace('www\.', '', $sitename); // remove www

        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        foreach ($readBuffer as $id => $line) {
            if (strpos($line, '??site??') !== false) {
                $line = str_replace('??site??', $sitename, $line);
            }
            if (strpos($line, '??dir??') !== false) {
                $line = str_replace('??dir??', $dir, $line);
            }
            if (strpos($line, '??security??') !== false) {
                $line = str_replace('??security??', $security, $line);
            }
            $outBuffer .= $line . PHP_EOL;
        }
        return $outBuffer;
    }
    // get current ips from .htaccess file
    public static function get_current_ips(String $afile)
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $cgLines = false;
        foreach ($readBuffer as $id => $line) {
            if (($line === '#------------------------CG SECURE IP LIST BEGIN---------------------') && ($outBuffer == '')) {
                $cgLines = true;
            }
            if (($line === '#------------------------CG SECURE IP LIST END--------------------') && $cgLines) {
                $cgLines = false;
                $outBuffer .= $line . PHP_EOL;
            }
            if (!$cgLines) {
                // remove everything outsite the markers
                continue;
            }
            $outBuffer .= $line . PHP_EOL;
        }
        return $outBuffer;
    }
    // read current .htaccess file and remove CG lines
    public static function empty_current(String $afile): String
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $cgLines = false;
        foreach ($readBuffer as $id => $line) {
            if (strpos($line, 'CG SECURE HTACCESS BEGIN') !== false) {
                $cgLines = true;
                continue;
            }
            if (strpos($line, 'CG SECURE HTACCESS END') !== false) {
                $cgLines = false;
                continue;
            }
            if ($line === '#------------------------CG SECURE IP LIST BEGIN---------------------') {
                $cgLines = true;
                continue;
            }
            if ($line === '#------------------------CG SECURE IP LIST END--------------------') {
                $cgLines = false;
                continue;
            }
            if ($line === '#------------------------CG SECURE BAD ROBOTS BEGIN---------------------') {
                $cgLines = true;
                continue;
            }
            if ($line === '#------------------------CG SECURE BAD ROBOTS END---------------------') {
                $cgLines = false;
                continue;
            }
            if ($line === '#------------------------CG SECURE SPECIFIC CODE BEGIN------------------------') {
                $cgLines = true;
                continue;
            }
            if ($line === '#------------------------CG SECURE SPECIFIC CODE END------------------------') {
                $cgLines = false;
                continue;
            }
            if ($line === '#------------------------CG SECURE IA BOTS BEGIN---------------------') {
                $cgLines = true;
                continue;
            }
            if ($line === '#------------------------CG SECURE IA BOTS END---------------------') {
                $cgLines = false;
                continue;
            }
            if ($line === '#------------------------CG SECURE HOTLINK BEGIN---------------------') {
                $cgLines = true;
                continue;
            }
            if ($line === '#------------------------CG SECURE HOTLINK END---------------------') {
                $cgLines = false;
                continue;
            }
            if ($cgLines) {
                // When we are between our makers all content should be removed
                continue;
            }
            $outBuffer .= $line . PHP_EOL;
        }
        return $outBuffer;
    }
    public static function merge_file($file, $current, $cgFile, $rejips, $specific = '', $ai = '', $hotlink = ''): String|Bool
    {
        $pathToFile  = $file;
        if (file_exists($pathToFile)) {
            if (is_readable($pathToFile)) {
                $records = $rejips.$specific.$cgFile.$ai.$hotlink.$current; // pour éviter les conflits, on se met devant....
                // Write the htaccess using the Frameworks File Class
                $bool = File::write($pathToFile, $records);
                if ($bool) {
                    if (self::check_site()) {
                        return $bool;
                    } else {
                        return false;
                    }
                }
            }
        }
        return Text::_('CGSECURE_MERGE_ERROR');
        ;
    }
   // Force recreate HTACCESS -----------------------------------------------
    public static function forceHTAccess($json = false)
    {
        $serverConfigFile = self::getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file : copy default htaccess.txt as .htaccess
            $source = JPATH_ROOT.self::CGPATH .'/txt/htaccess.txt';
            $dest = self::getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
            if (!copy($source, $dest)) {
                if ($json) {
                    return 'err : '.Text::_('CGSECURE_ADD_HTACCESS_ERROR');
                } else {
                    return Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS error');
                }
            }
        }
        // save htaccess file before adding CG Secure lines
        $source = self::getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/htaccess.av'.gmdate('Ymd-His', time());
        if (!copy($source, $dest)) {
            if ($json) {
                return 'err : '.Text::_('CGSECURE_SAVE_HTACCESS_ERROR');
            } else {
                return Factory::getApplication()->enqueueMessage('CGSECURE : save HTACCESS error');
            }
        }
        $config  = self::getParams();
        $v6 = isset($config->blockipv6) && $config->blockipv6 == 1;
        $current = self::empty_current(self::getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));

        $hackers = self::get_rejected();
        $rejips = self::create_ips($hackers,$v6);

        if (file_exists(JPATH_ROOT.self::CGPATH .'/txt/custom.txt')) { // custom file exists : use it
            $cgFile = self::read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/custom.txt');
        } else { // no custom file : use cgaccess.txt file
            $cgFile = self::read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess.txt');
        }
        $cgAI = "";
        if (isset($config->blockai) && $config->blockai) {
            $cgAI = self::read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_ai.txt');
        }
        $specific = isset($config->specific) && $config->specific;
        if ($specific) {
            $specific  = '#------------------------CG SECURE SPECIFIC CODE BEGIN------------------------'.PHP_EOL.$config->specific.PHP_EOL;
            $specific .= '#------------------------CG SECURE SPECIFIC CODE END------------------------'.PHP_EOL;
        }
        $hotlink = "";
        if (isset($config->blockhotlink) && $config->blockhotlink) {
            $hotlink = self::read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_hotlink.txt');
        }
        if (CGSecureHelper::merge_file(CGSecureHelper::getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile,$rejips,$specific, $cgAI, $hotlink)) {
            if ($json) {
                return Text::_('CGSECURE_ADD_HTACCESS');
            } else {
                return ; // everything OK => exit
            }
        }
        // Error : restore saved version
        copy($dest, $source);
        if ($json) {
           return 'err : '.Text::_('CGSECURE_ADD_HTACCESS_INSERT_ERROR');
        } else {
            Factory::getApplication()->enqueueMessage('CGSECURE : Error during insert');
            return;
        }
    }
    // copy CG Secure information in .htaccess from images, media, files, administrator directories
    public static function protectotherdirs($json = false)
    {
        if (file_exists(JPATH_ROOT.'/images/.htaccess')
            && file_exists(JPATH_ROOT.'/media/.htaccess')
            && (is_dir(JPATH_ROOT.'/files') && file_exists(JPATH_ROOT.'/files/.htaccess'))) {
            return; // .htaccess already present in images/media/files directories
        }
        $source = JPATH_ROOT.self::CGPATH .'/txt/cgaccess_nophp.txt';
        $dest = JPATH_ROOT.'/images/.htaccess';
        if (is_file($dest)) {
            File::delete($dest);
        }
        if (!copy($source, $dest)) {
            if ($json) {
                return 'err : ' . Text::_('CGSECURE_PROTECTDIRS_ERROR');
            } else {
                Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in images error');
            }
        }
        $dest = JPATH_ROOT.'/media/.htaccess';
        if (is_file($dest)) {
            File::delete($dest);
        }
        if (!copy($source, $dest)) {
            if ($json) {
                return 'err : '.Text::_('CGSECURE_PROTECTDIRS_ERROR');
            } else {
               Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in media error');
            }
        }
        if (is_dir(JPATH_ROOT.'/files')) {// Joomla 5.3.0 : new directory
            $dest = JPATH_ROOT.'/files/.htaccess';
            if (is_file($dest)) {
                File::delete($dest);
            }
            if (!copy($source, $dest)) {
                if ($json) {
                    return 'err : '.Text::_('CGSECURE_PROTECTDIRS_ERROR');
                } else {
                    Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in files error');
                }
            }
        }
        $dest = JPATH_ROOT.'/administrator/.htaccess';
        $source = JPATH_ROOT.self::CGPATH .'/txt/cgaccess_admin.txt';
        if (!is_file($dest)) {
            copy($source, $dest);
        }
        $current = self::empty_current($dest);
        $cgFile = self::read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_admin.txt');
        if (!CGSecureHelper::merge_file($dest, $current, $cgFile, '')) {
            if ($json) {
                return 'err : '.Text::_('CGSECURE_ADD_ADMIN_INSERT_ERROR');
            } else {
                Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in administrator error');
            }
        }
    }

    public static function check_site(): Bool
    {
        $url = URI::root();
        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, 0);
            curl_setopt($curl, CURLOPT_NOBODY, 0);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
            curl_setopt($curl, CURLOPT_TIMEOUT, 5);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
            curl_exec($curl);
            $responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($responseCode == 500) {
                return false;
            }
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
        return false;
    }

    public static function getServerConfigFile(String $file): String
    {
        if (file_exists(self::getServerConfigFilePath($file))
            && substr(strtolower($_SERVER['SERVER_SOFTWARE']), 0, 6) === 'apache') {
            return self::getServerConfigFilePath($file);
        }
        return '';
    }

    public static function getServerConfigFilePath(String $file): String
    {
        return JPATH_ROOT . DIRECTORY_SEPARATOR . $file;
    }

}
