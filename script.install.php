<?php

/**
 * @package    CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Component\Scheduler\Administrator\Model\TaskModel;
use ConseilGouz\CGSecure\Helper\CGSecureHelper;
use ConseilGouz\CGSecure\Cgipcheck;

class PlgSystemCgsecureInstallerInstallerScript
{
    private $min_joomla_version      = '4.0.0';
    private $min_php_version         = '7.4';
    private $name                    = 'CG Secure';
    private $extname                 = '';
    private $extension_type          = '';
    private $plugin_folder           = '';
    private $previous_version        = '';
    private $newlib_version	         = '';
    private $dir           = null;
    private $installerName = 'cgsecureinstaller';
    private $cgsecure_force_update_version = "3.9.0";
    private $security;
    private $config;
    private $db;
    public const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
    public const SERVER_CONFIG_FILE_NONE = '';
    public const CGPATH = '/media/com_cgsecure';
    public function __construct()
    {
        $this->dir = __DIR__;
        $this->db = Factory::getContainer()->get(DatabaseInterface::class);
    }

    public function preflight($route, $installer)
    {
        // To prevent installer from running twice if installing multiple extensions
        if (! file_exists($this->dir . '/' . $this->installerName . '.xml')) {
            return true;
        }
        // check if multiple config records have been created
        $db = $this->db;
        $count = 2;
        while ($count > 1) {
            try {
                $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from('#__cgsecure_config')
                ;
                try {
                    $db->setQuery($query);
                } catch (\Exception $e) {
                    $count = 0;
                    continue;
                }
                $count = $db->loadResult();
                if ($count > 1) { // remove one occurence
                    $query = $db->getQuery(true)
                        ->delete('#__cgsecure_config')
                        ->where($db->quoteName('name') . ' = "config"')
                        ->setLimit(1);
                    $db->setQuery($query);
                    $db->execute();
                    $count -= 1;
                }
            } catch (RuntimeException $e) {
                // ignore it
            }
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
        if (!$this->checkLibrary('conseilgouz')) { // need library installation
            $ret = $this->installPackageCG('lib_conseilgouz');
            if ($ret) {
                Factory::getApplication()->enqueueMessage('ConseilGouz Library ' . $this->newlib_version . ' installed', 'notice');
            }
        }
        // delete obsolete version.php file
        $this->delete([
            JPATH_ADMINISTRATOR . '/components/com_cgsecure/src/Field/VersionField.php',
        ]);

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
        $db = $this->db;
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

        // get previous versions parameters
        $plugin = PluginHelper::getPlugin('content', 'phocacheckip');
        if ($plugin) { // PhocaIp possible conflict
            $conditions = array(
                $db->qn('type') . ' = ' . $db->q('plugin'),
                $db->qn('element') . ' = ' . $db->quote('phocacheckip')
            );
            $query = $db->getQuery(true)
                    ->select('manifest_cache')
                    ->from($db->quoteName('#__extensions'))
                    ->where($conditions);
            $db->setQuery($query);
            $manif = $db->loadObject();
            if ($manif) {
                $manifest = json_decode($manif->manifest_cache);
                if ($manifest->version < '3.5.0') { // conflict : disable plugin
                    $fields = array($db->qn('enabled') . ' = 0');
                    $query = $db->getQuery(true);
                    $query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
                    $db->setQuery($query);
                    try {
                        $db->execute();
                    } catch (RuntimeException $e) {
                        Log::add('unable to enable plugin phocacheckip', Log::ERROR, 'jerror');
                    }
                    Factory::getApplication()->enqueueMessage('CGSECURE : Phocacheckip plugin has been disabled : please update it', 'warning');
                }
            }
        }
        // remove obsolete file
        $this->delete([
            JPATH_ROOT.self::CGPATH . '/cg_no_robot/index.php',
            JPATH_ROOT . '/libraries/cgsecure/Helper/Cgipcheck.php'
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
        $serverConfigFile = CGSecureHelper::getServerConfigFile('.htaccess');
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
        return CGSecureHelper::protectotherdirs();
    }
    // Begin update HTACCESS -----------------------------------------------
    private function forceHTAccess()
    {
        CGSecureHelper::forceHTAccess();
    }

    private function getParams()
    {
        $params = CGSecureHelper::getParams();
        return $params;
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
        $db = $this->db;
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
        $this->pluginsOrder();
        //@todo : check plugins order : cg secure contact plugin must be first.
        return true;
    }
    private function pluginsOrder()
    {
        $db = $this->db;
        $query = $db->getQuery(true);
        $query->select('extension_id,element,ordering');
        $query->from('#__extensions');
        $query->where('type = ' . $db->quote('plugin'));
        $query->where('folder = '. $db->quote('contact'));
        $db->setQuery($query);
        $plugins = $db->loadObjectList();
        if (count($plugins) == 1) {// only one => exit
            return;
        }
        $ok = false;
        foreach ($plugins as $plugin) {
            if (($plugin->element == 'cgsecure') && ($plugin->ordering == 1)) {
                $ok = true;
                break;
            }
        }
        if ($ok) { // already first
            return;
        }
        // rearrange plugins order : add 1 to all ordering
        $conditions = array(
            $db->qn('type') . ' = ' . $db->q('plugin'),
            $db->qn('folder') . ' = ' . $db->quote('contact')
        );
        $fields = array($db->qn('ordering') . ' = 1+'.$db->qn('ordering'));
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (RuntimeException $e) {
            Log::add('unable to add 1 Contact Plugins order', Log::ERROR, 'jerror');
        }
        // set cgsecure plugin ordering to 1
        $conditions = array(
            $db->qn('type') . ' = ' . $db->q('plugin'),
            $db->qn('folder') . ' = ' . $db->quote('contact'),
            $db->qn('element') . ' = ' . $db->quote('cgsecure'),
        );
        $fields = array($db->qn('ordering') . ' = 1');
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__extensions'))->set($fields)->where($conditions);
        $db->setQuery($query);
        try {
            $db->execute();
        } catch (RuntimeException $e) {
            Log::add('unable to set Contact CGSecure Plugins order to 1', Log::ERROR, 'jerror');
        }

    }
    private function installPackage($package)
    {
        $tmpInstaller = new Installer();
        $tmpInstaller->setDatabase($this->db);
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
        $db = $this->db;
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
    private function checkLibrary($library)
    {
        $file = $this->dir.'/lib_conseilgouz/conseilgouz.xml';
        if (!is_file($file)) {// library not installed
            return false;
        }
        $xml = simplexml_load_file($file);
        $this->newlib_version = $xml->version;
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $conditions = array(
             $db->qn('type') . ' = ' . $db->q('library'),
             $db->qn('element') . ' = ' . $db->quote($library)
            );
        $query = $db->getQuery(true)
                ->select('manifest_cache')
                ->from($db->quoteName('#__extensions'))
                ->where($conditions);
        $db->setQuery($query);
        $manif = $db->loadObject();
        if ($manif) {
            $manifest = json_decode($manif->manifest_cache);
            if ($manifest->version >= $this->newlib_version) { // compare versions
                return true; // library ok
            }
        }
        return false; // need library
    }
    private function installPackageCG($package)
    {
        $tmpInstaller = new Joomla\CMS\Installer\Installer();
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $tmpInstaller->setDatabase($db);
        $installed = $tmpInstaller->install($this->dir . '/' . $package);
        return $installed;
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
        $db = $this->db;
        $query = $db->getQuery(true)
            ->delete('#__extensions')
            ->where($db->quoteName('element') . ' = ' . $db->quote($this->installerName))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'));
        $db->setQuery($query);
        $db->execute();
        // nettoyage du cache
        $cacheModel = Factory::getApplication()->bootComponent('com_cache')->getMVCFactory()->createModel('Cache', 'Administrator', ['ignore_request' => true]);
        $cache = $cacheModel->getCache() ?? null;
        if ($cache) {
            foreach ($cache->getAll() as $group) {
                $cache->clean($group->group);
            }
            Factory::getApplication()->enqueueMessage('<p>Cache cleared.</p>');
        }
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
    
    /**
     * Method to uninstall the extension
     * $parent is the class calling this method
     *
     * @return void
     */
    public function uninstall($parent)
    {
        // remove CG Secure infos from htaccess file
        CGSecureHelper::empty_current(CGSecureHelper::getServerConfigFilePath(CGSecureHelper::SERVER_CONFIG_FILE_HTACCESS))

        
        
        echo('<p>CG Secure uninstalled</p>');
    }

}
