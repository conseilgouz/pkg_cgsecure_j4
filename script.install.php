<?php

/**
 * @package    CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
 * @license    GNU/GPLv3
 */
// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Component\Scheduler\Administrator\Model\TaskModel;

class PlgSystemCgsecureInstallerInstallerScript
{
    private $min_joomla_version      = '4.0.0';
    private $min_php_version         = '7.4';
    private $name                    = 'CG Secure';
    private $extname                 = '';
    private $extension_type          = '';
    private $plugin_folder           = '';
    private $previous_version        = '';
    private $dir           = null;
    private $installerName = 'cgsecureinstaller';
    private $cgsecure_force_update_version = "3.3.0";
    private $security;
    private $config;
    public const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
    public const SERVER_CONFIG_FILE_NONE = '';
    public const CGPATH = '/media/com_cgsecure';
    public function __construct()
    {
        $this->dir = __DIR__;
    }

    public function preflight($route, $installer)
    {
        // To prevent installer from running twice if installing multiple extensions
        if (! file_exists($this->dir . '/' . $this->installerName . '.xml')) {
            return true;
        }

        Factory::getApplication()->getLanguage()->load('plgcgsecureinstaller', $this->dir);

        if (! $this->passMinimumJoomlaVersion()) {
            $this->uninstallInstaller();

            return false;
        }

        if (! $this->passMinimumPHPVersion()) {
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
        if (! in_array($route, ['install', 'update'])) {
            return true;
        }

        // To prevent installer from running twice if installing multiple extensions
        if (! file_exists($this->dir . '/' . $this->installerName . '.xml')) {
            return true;
        }

        // First install the Library
        if (! $this->installLibrary()) {
            // Uninstall this installer
            $this->uninstallInstaller();

            return false;
        }

        // Then install the rest of the packages
        if (! $this->installPackages()) {
            // Uninstall this installer
            $this->uninstallInstaller();

            return false;
        }
        $this->postInstall();
        $this->check_cgsecure_task();
        Factory::getApplication()->enqueueMessage(Text::_('PKG_CGSECURE_XML_DESCRIPTION'), 'notice');

        // Uninstall this installer
        $this->uninstallInstaller();

        return true;
    }
    private function postInstall()
    {
        // remove obsolete update sites
        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__update_sites'))
              ->set($db->qn('type') . ' = "extension"')
              ->where($db->qn('type') . ' = "plugin"');
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (RuntimeException $e) {
            Log::add('unable to enable Plugin site_form_override', Log::ERROR, 'jerror');
        }
        // fix old cg secure system plugin in schemas table
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__schemas'))
            ->where($db->quoteName('extension_id').' IN  (select '.$db->qn("extension_id").' FROM '.$db->quoteName("#__extensions").'
				WHERE '.$db->qn("type").' LIKE "plugin" AND '.$db->qn("folder").' LIKE "system"  AND '.$db->qn("element").' LIKE "cgsecure")');
        $db->setQuery($query);
        $db->execute();
        // remove obsolete file
        $this->delete([
            JPATH_ROOT.self::CGPATH . '/cg_no_robot/index.php',
        ]);
        // replace index.php file in cg_no_robot dir by new version
        $norobots = JPATH_ROOT.'/cg_no_robot';
        if (is_dir($norobots)) { // cg_no_robot dir exists : copy new copy of index.php -------------------------------
            $this->delete([$norobots.'/index.php']);
            File::copy(
                JPATH_ROOT.self::CGPATH . '/cg_no_robot/index.txt',
                $norobots. '/index.php'
            );
        }
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
            if (($pos = strpos($line, 'CG SECURE HTACCESS BEGIN')) !== false) { // line already exists in htaccess file
                $version = trim(substr($line, $pos + 24, 10), '-');
                $found = true;
                break;
            }
        }
        if (!$found) {
            return;
        } // No CG Secure line in htacces file => exit
        if (!$version || ($version && ($version < $this->cgsecure_force_update_version))) {
            $this->forceHTAccess(); // update htaccess
            $this->htaccess_other_dirs(); // create htaccess file in media,images/administrator dir
        }
    }
    private function htaccess_other_dirs()
    {
        $source = JPATH_ROOT.self::CGPATH .'/txt/cgaccess_nophp.txt';
        // media : block php
        $f = JPATH_ROOT . '/media/.htaccess';
        if (!@file_exists($f)) { // .htaccess in media dir
            $dest   = JPATH_ROOT.'/media/.htaccess';
            if (!copy($source, $dest)) {
                Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in media error');
            }
        }
        // images/media/files : block php
        $f = JPATH_ROOT . '/images/.htaccess';
        if (!@file_exists($f)) { // .htaccess in images dir
            $dest   = JPATH_ROOT.'/images/.htaccess';
            if (!copy($source, $dest)) {
                Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in media error');
            }
        }
        if (is_dir(JPATH_ROOT . '/files')) { // Joomla 5.3.0 : new dir
            $f = JPATH_ROOT . '/files/.htaccess';
            if (!@file_exists($f)) { // .htaccess in files dir
                $dest   = JPATH_ROOT.'/files/.htaccess';
                if (!copy($source, $dest)) {
                    Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in media error');
                }
            }
        }
        // administrator : block protected directories
        $source = JPATH_ROOT.self::CGPATH .'/txt/cgaccess_admin.txt';
        $f = JPATH_ROOT . '/administrator/.htaccess';
        if (!@file_exists($f)) { // .htaccess in images dir
            $dest   = JPATH_ROOT.'/administrator/.htaccess';
            if (!copy($source, $dest)) {
                Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS in media error');
            }
        }
    }
    // Begin update HTACCESS -----------------------------------------------
    private function forceHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file : copy default htaccess.txt as .htaccess
            $source = JPATH_ROOT.self::CGPATH .'/txt/htaccess.txt';
            $dest = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
            if (!copy($source, $dest)) {
                return Factory::getApplication()->enqueueMessage('CGSECURE : add HTACCESS error');
            }
        }
        // save htaccess file before adding CG Secure lines
        $source = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/htaccess.av'.gmdate('Ymd-His', time());
        if (!copy($source, $dest)) {
            return Factory::getApplication()->enqueueMessage('CGSECURE : save HTACCESS error');
        }
        $current = $this->read_current($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $rejips = $this->get_current_ips($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        if (file_exists(JPATH_ROOT.self::CGPATH .'/txt/custom.txt')) { // custom file exists : use it
            $cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/custom.txt');
        } else { // no custom file : use cgaccess.txt file
            $cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess.txt');
        }
        $this->config  = $this->getParams();
        $specific = isset($this->config->specific) && $this->config->specific;
        if ($specific) {
            $specific  = '#------------------------CG SECURE SPECIFIC CODE BEGIN------------------------'.PHP_EOL.$specific.PHP_EOL;
            $specific .= '#------------------------CG SECURE SPECIFIC CODE END------------------------'.PHP_EOL;
        }

        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, $specific)) {
            return; // everything OK => exit
        }
        Factory::getApplication()->enqueueMessage('CGSECURE : Error during insert');
        return;
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
    private function read_current($afile)
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
            if ($cgLines) {
                // When we are between our makers all content should be removed
                continue;
            }
            $outBuffer .= $line . PHP_EOL;
        }
        return $outBuffer;
    }
    private function read_cgfile($afile)
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $this->config  = $this->getParams();
        $this->security =	$this->config->security;
        if ($this->config->multi == '1') {// site multi-adresse
            $server = '('.str_replace(',', '|', $this->config->get('multisite', '')).')';
            $dir = "";
        } elseif ($this->config->subdir == '1') { // site in subdir ?
            $server = $_SERVER['SERVER_NAME'];
            $dir = "/".$this->config->subdirsite;
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
                $line = str_replace('??security??', $this->security, $line);
            }
            $outBuffer .= $line . PHP_EOL;
        }
        return $outBuffer;
    }
    // get current ips from .htaccess file
    private function get_current_ips($afile)
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
    private function getParams()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
    private function merge_file($file, $current, $cgFile, $rejips, $specific)
    {
        $pathToFile  = $file;
        if (file_exists($pathToFile)) {
            copy($pathToFile, $pathToFile.'.wait');
            if (is_readable($pathToFile)) {
                $records = $rejips.$specific.$cgFile.$current; // pour éviter les conflits, on se met devant....
                // Write the htaccess using the Frameworks File Class
                $bool = File::write($pathToFile, $records);
                if ($bool) {
                    if (self::check_site()) {
                        File::delete($pathToFile.'.wait');
                        return $bool;
                    } else {
                        // restore previous version
                        copy($pathToFile.'.wait', $pathToFile);
                        File::delete($pathToFile.'.wait');
                        return false;
                    }
                }
            }
            File::delete($pathToFile.'.wait');
        }
        return Factory::getApplication()->enqueueMessage('CGSECURE : merge error');
    }
    // check if website is still working
    private function check_site()
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
            curl_close($curl);
            if ($responseCode == 500) {
                return false;
            }
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
        return false;
    }

    // End HTACCESS update -------------------------------------------------------------

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
        $version = $j->getShortVersion();
        if (version_compare($version, $this->min_joomla_version, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
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

        if (version_compare(PHP_VERSION, $this->min_php_version, '<')) {
            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
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
    private function installPackages()
    {
        $packages = Folder::folders($this->dir . '/packages');

        $packages = array_diff($packages, ['library_cgsecure']);

        foreach ($packages as $package) {
            if (! $this->installPackage($package)) {
                return false;
            }
        }
        // enable plugins
        $db = Factory::getContainer()->get(DatabaseInterface::class);
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
        } catch (RuntimeException $e) {
            Log::add('unable to enable Plugins CGSecure', Log::ERROR, 'jerror');
        }

        return true;
    }
    private function installPackage($package)
    {
        $tmpInstaller = new Installer();
        $installed = $tmpInstaller->install($this->dir . '/packages/' . $package);
        return $installed;
    }
    private function installLibrary()
    {
        if (! $this->installPackage('library_cgsecure')
            || ! $this->installPackage('plg_system_cgsecure')) {
            Factory::getApplication()->enqueueMessage(Text::_('ERROR_INSTALLATION_LIBRARY_FAILED'), 'error');
            return false;
        }
        $cachecontroller = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController();
        $cachecontroller->clean('_system');
        return true;
    }
    private function check_cgsecure_task()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('id');
        $query->from('#__scheduler_tasks');
        $query->where('type = ' . $db->quote('cgsecure'));
        $query->where('state = 1');
        $query->setLimit(1);
        $db->setQuery($query);
        $found = $db->loadResult();
        if ($found) {// Found in db => exit
            return;
        }
        $task = new TaskModel(array('ignore_request' => true));
        $table = $task->getTable();
        $data = [];
        $data['id']     = 0;
        $data['title']  = 'CG Secure';
        $data['type']   = 'cgsecure';
        $data['state']  = 1; // activate
        $data['execution_rules'] = ["rule-type" => "interval-days",
                                    "interval-days" => "7",
                                    "exec-day" => "1",
                                    "exec-time" => "00:01"];
        $data['cron_rules']      = ["type" => "interval",
                                    "exp" => "P1D"];
        $notif = ["success_mail" => "0","failure_mail" => "1","fatal_failure_mail" => "1","orphan_mail" => "1"];
        $data['params'] = ["individual_log" => false,"log_file" => "","notifications" => $notif];
        $data['note']   = Text::_('PLG_CGSECURE_CREATE_TASK_NOTE');
        $lastExec = Factory::getDate('now');
        $data['last_execution'] = null;
        $data['next_execution'] = $lastExec->toSql();

        $table->save($data);
        Factory::getApplication()->enqueueMessage(Text::_('PLG_CGSECURE_CREATE_TASK_OK'), 'notice');
    }
    
    private function uninstallInstaller()
    {
        if (! is_dir(JPATH_PLUGINS . '/system/' . $this->installerName)) {
            return;
        }
        $this->delete([
            JPATH_PLUGINS . '/system/' . $this->installerName . '/language',
            JPATH_PLUGINS . '/system/' . $this->installerName,
        ]);
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->delete('#__extensions')
            ->where($db->quoteName('element') . ' = ' . $db->quote($this->installerName))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
        $db->setQuery($query);
        $db->execute();
        $cachecontroller = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController();
        $cachecontroller->clean('_system');
    }

    public function delete($files = [])
    {
        foreach ($files as $file) {
            if (is_dir($file)) {
                Folder::delete($file);
            }

            if (is_file($file)) {
                File::delete($file);
            }
        }
    }
}
