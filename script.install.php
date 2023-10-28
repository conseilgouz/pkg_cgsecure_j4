<?php

/**
 * @package    CG Secure
 * Version			: 3.0.2
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2023 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
 * @license    GNU/GPLv2
 */
// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text as JText;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Version;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Log\Log;

class PlgSystemCgsecureInstallerInstallerScript
{
	private $min_joomla_version      = '4.0.0';
	private $min_php_version         = '7.4';
	private $name                    = 'CG Secure';
	private $extname                 = '';
	private $previous_version        = '';
	private $dir           = null;
	private $installerName = 'cgsecureinstaller';
	private $cgsecure_force_update_version = "2.2.7";
	const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
	const SERVER_CONFIG_FILE_NONE = '';
    const CGPATH = '/media/com_cgsecure';
	public function __construct()
	{
		$this->dir = __DIR__;
	}

	public function preflight($route, $installer)
	{
		// To prevent installer from running twice if installing multiple extensions
		if ( ! file_exists($this->dir . '/' . $this->installerName . '.xml'))
		{
			return true;
		}

		Factory::getLanguage()->load('plgcgsecureinstaller', $this->dir);

		if ( ! $this->passMinimumJoomlaVersion())
		{
			$this->uninstallInstaller();

			return false;
		}

		if ( ! $this->passMinimumPHPVersion())
		{
			$this->uninstallInstaller();

			return false;
		}
		$this->previous_version = null;
		
		if (file_exists(JPATH_ADMINISTRATOR . '/components/com_cgsecure/cgsecure.xml')) {
			$xml = simplexml_load_file(JPATH_ADMINISTRATOR . '/components/com_cgsecure/cgsecure.xml');
			$this->previous_version = $xml->version;
		}
		// To prevent XML not found error
		$this->createExtensionRoot();

		return true;
	}

	public function postflight($route, $installer)
	{
		if ( ! in_array($route, ['install', 'update']))
		{
			return true;
		}

		// To prevent installer from running twice if installing multiple extensions
		if ( ! file_exists($this->dir . '/' . $this->installerName . '.xml'))
		{
			return true;
		}

		// First install the Library
		if ( ! $this->installLibrary())
		{
			// Uninstall this installer
			$this->uninstallInstaller();

			return false;
		}

		// Then install the rest of the packages
		if ( ! $this->installPackages())
		{
			// Uninstall this installer
			$this->uninstallInstaller();

			return false;
		}
		$this->postInstall();
		Factory::getApplication()->enqueueMessage(JText::_('PKG_CGSECURE_XML_DESCRIPTION'), 'notice');

		// Uninstall this installer
		$this->uninstallInstaller();

		return true;
	}
    private function postInstall() {
		// remove obsolete update sites
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->delete('#__update_sites')
			->where($db->quoteName('location') . ' like "%432473037d.url-de-test.ws/%"');
		$db->setQuery($query);
		$db->execute();
		// CG Secure is now on Github
		$query = $db->getQuery(true)
			->delete('#__update_sites')
			->where($db->quoteName('location') . ' like "%conseilgouz.com/updates/com_cgsecure%"');
		$db->setQuery($query);
		$db->execute();
	// fix wrong type in update_sites
	    $db = Factory::getContainer()->get(DatabaseInterface::class);;
	    
        $query = $db->getQuery(true);
		$query->update($db->quoteName('#__update_sites'))
			  ->set($db->qn('type') . ' = "extension"')
			  ->where($db->qn('type') . ' = "plugin"');
		$db->setQuery($query);
        try {
	        $db->execute();
        }
        catch (RuntimeException $e) {
            Log::add('unable to enable Plugin site_form_override', Log::ERROR, 'jerror');
        }
		// remove obsolete file 
		$this->delete([
			JPATH_ROOT.self::CGPATH . '/cg_no_robot/index.php',
		]);		
		// Check if HTACCESS file has to be updated
		$serverConfigFile = $this->getServerConfigFile('.htaccess');
		if (!$serverConfigFile) { // no .htaccess file
			return;
		}
		$readBuffer = file($serverConfigFile, FILE_IGNORE_NEW_LINES); 
		if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
			return '';
		}
		$found = false;
		$version = "";
		foreach ($readBuffer as $id => $line) {
			if (($pos = strpos($line,'CG SECURE HTACCESS BEGIN')) !== false) { // line already exists in htaccess file
				$version = trim(substr($line,$pos+24,10),'-');
				$found = true;
				break;
			}
		}
		if (!$found) return; // No CG Secure line in htacces file => exit
		if (!$version || ($version && ($version < $this->cgsecure_force_update_version))) {
			$this->forceHTAccess(); // update htaccess
		}
	}
	// Begin update HTACCESS -----------------------------------------------
	private function forceHTAccess() {
		$this->security = $cgsecure_params->security; // htaccess has been created => security must contain a value
	    $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
	    if (!$serverConfigFile) { // no .htaccess file : copy default htaccess.txt as .htaccess
	        $source = JPATH_ROOT.self::CGPATH .'/txt/htaccess.txt';
	        $dest = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
	        if (!copy($source,$dest)) return Factory::getApplication()->enqueueMessage(JText::_('CGSECURE : add HTACCESS error'));
	    }
		// save htaccess file before adding CG Secure lines
		$source = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/htaccess.av'.gmdate('Ymd-His', time());
        if (!copy($source,$dest)) return Factory::getApplication()->enqueueMessage(JText::_('CGSECURE : save HTACCESS error'));
	    $current = $this->read_current($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
		$rejips = $this->get_current_ips($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
		if (file_exists(JPATH_ROOT.self::CGPATH .'/txt/custom.txt')) { // custom file exists : use it
			$cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/custom.txt');
		} else { // no custom file : use cgaccess.txt file
			$cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess.txt');
		}
	    if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS),$current,$cgFile,$rejips)) {
			return; // everything OK => exit
	    } 
	    Factory::getApplication()->enqueueMessage(JText::_('CGSECURE : Error during insert'));
		return;
	}
	// from https://www.php.net/manual/en/function.lcg-value.php#75562 
	private function random_float($min,$max) {
		return ($min+lcg_value()*(abs($max-$min)));
	}
	private function getServerConfigFile($file)
	{
		if (file_exists($this->getServerConfigFilePath($file))
			&& substr(strtolower($_SERVER['SERVER_SOFTWARE']), 0, 6) === 'apache') {
			    return $this->getServerConfigFilePath($file);
		}
		return '';
	}
	private function getServerConfigFilePath($file)
	{
	    return JPATH_ROOT . DIRECTORY_SEPARATOR . $file;
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
	private function read_cgfile($afile) {
		$readBuffer = file($afile, FILE_IGNORE_NEW_LINES); 
        $this->config  = $this->getParams();
		$this->security =	$this->config->security;
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
	private function getParams() {
        $db = Factory::getDBo();
		$table = Table::getInstance('ConfigTable','ConseilGouz\\Component\\CGSecure\Administrator\\Table\\', array('dbo' => $db));
		$params = json_decode($table->getSecureParams()->params);
		
		return $params;
		
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
		return Factory::getApplication()->enqueueMessage(JText::_('CGSECURE : merge error'));
	}
	
// End HTACCESS update -------------------------------------------------------------
   	
	public function getMainFolder()
	{
		switch ($this->extension_type)
		{
			case 'plugin' :
				return JPATH_PLUGINS . '/' . $this->plugin_folder . '/' . $this->extname;

			case 'component' :
				return JPATH_ADMINISTRATOR . '/components/com_' . $this->extname;

			case 'module' :
				return JPATH_ADMINISTRATOR . '/modules/mod_' . $this->extname;

			case 'library' :
				return JPATH_SITE . '/libraries/' . $this->extname;
		}
	}

	private function getMajorVersionPart($string)
	{
		return preg_replace('#^([0-9]+)\..*$#', '\1', $string);
	}

	private function createExtensionRoot()
	{
		$destination = JPATH_PLUGINS . '/system/' . $this->installerName;

		Folder::create($destination);

		File::copy(
			$this->dir . '/' . $this->installerName . '.xml',
			$destination . '/' . $this->installerName . '.xml'
		);
	}

	// Check if Joomla version passes minimum requirement
	private function passMinimumJoomlaVersion()
	{
		$j = new Version();
		$version=$j->getShortVersion(); 
		if (version_compare($version, $this->min_joomla_version, '<'))
		{
			Factory::getApplication()->enqueueMessage(
				JText::sprintf(
					'NOT_COMPATIBLE_UPDATE',
					'<strong>' . JVERSION . '</strong>',
					'<strong>' . $this->min_joomla_version . '</strong>'
				),
				'error'
			);

			return false;
		}

		return true;
	}

	// Check if PHP version passes minimum requirement
	private function passMinimumPHPVersion()
	{

		if (version_compare(PHP_VERSION, $this->min_php_version, '<'))
		{
			Factory::getApplication()->enqueueMessage(
				JText::sprintf(
					'NOT_COMPATIBLE_PHP',
					'<strong>' . PHP_VERSION . '</strong>',
					'<strong>' . $this->min_php_version . '</strong>'
				),
				'error'
			);

			return false;
		}

		return true;
	}
	private function installPackages() {
		$packages = Folder::folders($this->dir . '/packages');

		$packages = array_diff($packages, ['library_cgsecure']);

		foreach ($packages as $package)
		{
			if ( ! $this->installPackage($package))
			{
				return false;
			}
		}
		// enable plugins
		$db = Factory::getDbo();
        $conditions = array(
            $db->qn('type') . ' = ' . $db->q('plugin'),
            $db->qn('element') . ' = ' . $db->quote('cgsecure')
        );
        $fields = array($db->qn('enabled') . ' = 1');

        $query = $db->getQuery(true);
		$query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
		$db->setQuery($query);
        try {
	        $db->execute();
        }
        catch (RuntimeException $e) {
            Log::add('unable to enable Plugins CGSecure', Log::ERROR, 'jerror');
        }

		return true;
	}
	private function installPackage($package) {
		$tmpInstaller = new Installer;
		$installed = $tmpInstaller->install($this->dir . '/packages/' . $package);
		return $installed;
	}
	private function installLibrary() {
		if (   ! $this->installPackage('library_cgsecure')
			|| ! $this->installPackage('plg_system_cgsecure') )	{
			Factory::getApplication()->enqueueMessage(JText::_('ERROR_INSTALLATION_LIBRARY_FAILED'), 'error');
			return false;
		}
		Factory::getCache()->clean('_system');
		return true;
	}
	private function uninstallInstaller()
	{
		if ( ! is_dir(JPATH_PLUGINS . '/system/' . $this->installerName)) {
			return;
		}
		$this->delete([
			JPATH_PLUGINS . '/system/' . $this->installerName . '/language',
			JPATH_PLUGINS . '/system/' . $this->installerName,
		]);
		$db = Factory::getDbo();
		$query = $db->getQuery(true)
			->delete('#__extensions')
			->where($db->quoteName('element') . ' = ' . $db->quote($this->installerName))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
		$db->setQuery($query);
		$db->execute();
		Factory::getCache()->clean('_system');
	}

	public function delete($files = [])	{
		foreach ($files as $file) {
			if (is_dir($file)) {
				Folder::delete($file);
			}

			if (is_file($file))	{
				File::delete($file);
			}
		}
	}
}

