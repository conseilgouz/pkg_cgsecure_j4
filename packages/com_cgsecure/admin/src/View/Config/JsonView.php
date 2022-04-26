<?php
/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
namespace ConseilGouz\Component\CGSecure\Administrator\View\Config;
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Response\JsonResponse;
use Exception;
/**
 * Config View
 */
class JsonView extends AbstractView
{
	protected $app;
	protected $security;
	const SERVER_CONFIG_FILE_NONE = '';
	const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
	const SERVER_CONFIG_FILE_ADMIN_HTACCESS = 'administrator/.htaccess';
    const CGPATH = '/media/com_cgsecure';
    /**
     * AJAX Request
     * 
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * 
     * @return  mixed  A string if successful, otherwise a JError object.
     */
    function display($tpl = null) {
        // Check for errors.
		$this->app = Factory::getApplication();
 		$input = Factory::getApplication()->input;
		$type = $input->get('type');
		$access = (int)$input->get('access');
		$this->security = $input->get('security');
		$msg = "";
		if ($type == 'robots') {
			if ($access == 0) { // delete CG Secure lines from robots.txt file and delete cg_robots dir
				$msg = $this->delRobots();
			} elseif ($access == 1) {// add CG Secure lines to robots.txt and create cg_robots dir
				$msg = $this->addRobots();
			}
		} elseif ($type == 'htaccess') {
			if ($access == 0) { // delete CG Secure lines from htaccess file
				$msg = $this->delHTAccess();
				$msg = $this->deleteIPSHTAccess();
			} elseif ($access == 1) {// add CG Secure lines to htaccess file
				$msg = $this->addHTAccess();
			} elseif ($access == 2) { // delete hackers IP 
				$msg = $this->deleteIPSHTAccess();
			}
		}
		$arr = [];
		$arr['retour'] = $msg;
		echo new \JResponseJson($arr); 
    }
	// delete CG Secure information in .htaccess file
	private function delHTAccess() {
		$serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
		if (!$serverConfigFile) { // no .htaccess file
		    return Text::_('CGSECURE_NO_HTACCESS');
		}
		$current = $this->read_current($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
		$cgFile = '';
		$rejips = '';
		if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS),$current,$cgFile,$rejips)) {
		    return Text::_('CGSECURE_DEL_HTACCESS');
		} else {
		    return Text::_('CGSECURE_DEL_HTACCESS_ERROR');
		}
	}
	// delete CG Secure information in .htaccess file
	private function deleteIPSHTAccess() {
		$serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
		if (!$serverConfigFile) { // no .htaccess file
		    return Text::_('CGSECURE_NO_HTACCESS');
		}
		$current = $this->read_current_noip($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
		$cgFile = '';
		$rejips = '';
		if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS),$current,$cgFile,$rejips)) {
		    return Text::_('CGSECURE_DEL_IP_HTACCESS');
		} else {
		    return Text::_('CGSECURE_DEL_IP_HTACCESS_ERROR');
		}
	}
	// add CG Secure information from .htaccess file
	private function addHTAccess() {
	    $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
	    if (!$serverConfigFile) { // no .htaccess file : copy default htaccess.txt as .htaccess
	        $source = JPATH_ROOT.self::CGPATH .'/txt/htaccess.txt';
	        $dest = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
	        if (!copy($source,$dest)) return Text::_('CGSECURE_ADD_HTACCESS_ERROR');
	    }
		// save htaccess file before adding CG Secure lines
		$source = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/htaccess.av'.gmdate('Ymd-His', time());
	    if (!copy($source,$dest)) return Text::_('CGSECURE_SAVE_HTACCESS_ERROR');
	    $current = $this->read_current($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
		$rejips = $this->get_current_ips($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
		if (file_exists(JPATH_ROOT.self::CGPATH .'/txt/custom.txt')) { // custom file exists : use it
			$cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/custom.txt');
		} else { // no custom file : use cgaccess.txt file
			$cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess.txt');
		}
	    if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS),$current,$cgFile,$rejips)) {
	        return Text::_('CGSECURE_ADD_HTACCESS');
	    } 
	    return Text::_('CGSECURE_ADD_HTACCESS_INSERT_ERROR');
	}
	// add Bad robots blocking
	// - add lines in robots.txt files if it exists, or copy default robots.txt file
	// - create cg_no_robots dir
	private function addRobots() {
		$filename = "robots.txt";
	    $serverConfigFile = $this->getServerConfigFile($filename);
	    if (!$serverConfigFile) { // no robots.txt file : copy default robots.txt to root dir
	        $source = JPATH_ROOT.self::CGPATH .'/txt/robots.txt';
	        $dest = $this->getServerConfigFilePath($filename);
	        if (!copy($source,$dest)) return Text::_('CGSECURE_ADD_ROBOTS_ERROR');
	    }
		$source = $this->getServerConfigFilePath($filename);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/robots.av'.gmdate('Ymd-His', time());
	    if (!copy($source,$dest)) return Text::_('CGSECURE_SAVE_ROBOTS_ERROR');
	    $current = $this->read_current($this->getServerConfigFilePath($filename));
		$cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgrobots.txt');
	    if (!$this->merge_file($this->getServerConfigFilePath($filename),$current,$cgFile,'')) {
	        return Text::_('CGSECURE_ADD_ROBOTS_INSERT_ERROR');
	    } 
		// copy cg_no_robot folder to root*
		$source = JPATH_ROOT.self::CGPATH .'/cg_no_robot';
		$dest = JPATH_ROOT.'/cg_no_robot';
		// jimport('joomla.filesystem.folder');
		try { // delete cg_no_robot folder if exists
		    Folder::delete($dest);
		} catch(Exception $e) {// ignore error
		}
		try {
		 Folder::copy($source,$dest);
		 }
	      catch(Exception $e) {
	          return Text::_('CGSECURE_ADD_ROBOTS_ERR');
	      }
    	return Text::_('CGSECURE_ADD_ROBOTS');
	}
	// delete CG Secure information in robots.txt file
	private function delRobots() {
		$filename = "robots.txt";
		$serverConfigFile = $this->getServerConfigFile($filename);
		if (!$serverConfigFile) { // no robots.txt file
		    return Text::_('CGSECURE_NO_ROBOTS');
		}
		$current = $this->read_current($this->getServerConfigFilePath($filename));
		$cgFile = '';
		$rejips = '';
		if (!$this->merge_file($this->getServerConfigFilePath($filename),$current,$cgFile,$rejips)) {
		    return  Text::_('CGSECURE_DEL_ROBOTS_ERROR');
		}
		$dest = JPATH_ROOT.'/cg_no_robot';
// 		jimport('joomla.filesystem.folder');
		try {
		    Folder::delete($dest);
		} 
		catch(Exception $e) {
		    return Text::_('CGSECURE_DEL_ROBOTS_ERROR');
		}
		return Text::_('CGSECURE_DEL_ROBOTS');
		    
	}
	
	// read current .htaccess file and remove CG lines
	private function read_current($afile) {
		$readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
		$outBuffer = '';
		if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}
		$cgLines = false;
		foreach ($readBuffer as $id => $line)
		{
			if (strpos($line,'CG SECURE HTACCESS BEGIN') !== false)	{
				$cgLines = true;
				continue;
			}
			if (strpos($line,'CG SECURE HTACCESS END') !== false) {
				$cgLines = false;
				continue;
			}
			if ($line === '#------------------------CG SECURE IP LIST BEGIN---------------------')	{
				$cgLines = true;
				continue;
			}
			if ($line === '#------------------------CG SECURE IP LIST END--------------------') {
				$cgLines = false;
				continue;
			}
			if ($line === '#------------------------CG SECURE BAD ROBOTS BEGIN---------------------')	{
				$cgLines = true;
				continue;
			}
			if ($line === '#------------------------CG SECURE BAD ROBOTS END---------------------') {
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
	// get current ips from .htaccess file
	private function get_current_ips($afile) {
		$readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
		$outBuffer = '';
		if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}
		$cgLines = false;
		foreach ($readBuffer as $id => $line)
		{
			if (($line === '#------------------------CG SECURE IP LIST BEGIN---------------------') && ($outBuffer == ''))	{
				$cgLines = true;
			}
			if (($line === '#------------------------CG SECURE IP LIST END--------------------') && $cgLines ) {
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


	// read current .htaccess file and remove IP lines
	private function read_current_noip($afile) {
		$readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
		$outBuffer = '';
		if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}
		$cgLines = false;
		foreach ($readBuffer as $id => $line)
		{
			if ($line === '#------------------------CG SECURE IP LIST BEGIN---------------------')	{
				$cgLines = true;
				continue;
			}
			if ($line === '#------------------------CG SECURE IP LIST END--------------------') {
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
	private function read_cgfile($afile) {
		$readBuffer = file($afile, FILE_IGNORE_NEW_LINES); 
        $this->config  = $this->getParams();	
		if ($this->config->multi == '1') {// site multi-adresse
			$server = '('.str_replace(',','|',$this->config->get('multisite','')).')';
			$dir = "";
		} elseif ($this->config->subdir == '1') { // site in subdir ?
			$server = $_SERVER['SERVER_NAME'];
			$dir = "/".$this->config->subdirsite;
		} else {
			$server = $_SERVER['SERVER_NAME'];
			$dir = "";
		}
		$sitename = str_replace('.','\.',$server);
		$sitename = str_replace('www\.','',$sitename); // remove www
		
		$outBuffer = '';
		if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}
		foreach ($readBuffer as $id => $line) {
			if (strpos($line,'??site??') !== false) 
				$line = str_replace('??site??',$sitename,$line);
			if (strpos($line,'??dir??') !== false) 
				$line = str_replace('??dir??',$dir,$line);
			if (strpos($line,'??security??') !== false)
				$line = str_replace('??security??',$this->security,$line);
			$outBuffer .= $line . PHP_EOL;
		}
		return $outBuffer;
	}
	private function merge_file($file, $current,$cgFile,$rejips) {
		$pathToFile  = $file;
		if (file_exists($pathToFile)) {
			if (is_readable($pathToFile)) {
				$records = $rejips.$cgFile.$current; // pour éviter les conflits, on se met devant....
				// Write the htaccess using the Frameworks File Class
				return File::write($pathToFile,$records );
			}
		}
		return Text::_('CGSECURE_MERGE_ERROR');;
	}
	private function getServerConfigFile($file)
	{
		if (file_exists($this->getServerConfigFilePath($file))
			&& substr(strtolower($_SERVER['SERVER_SOFTWARE']), 0, 6) === 'apache') {
			return $file;
		}
		return self::SERVER_CONFIG_FILE_NONE;
	}
	private function getServerConfigFilePath($file)
	{
		return JPATH_ROOT . DIRECTORY_SEPARATOR . $file;
	}
	private function getParams() {
        $db = Factory::getDBo();
		$table = Table::getInstance('ConfigTable','ConseilGouz\\Component\\CGSecure\Administrator\\Table\\', array('dbo' => $db));
		$params = json_decode($table->getSecureParams()->params);
		
		return $params;
		
	}
}