<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Config;

defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Scheduler\Administrator\Model\TaskModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use ConseilGouz\CGSecure\Helper\CGSecureHelper;

/**
 * Config View
 */
class JsonView extends AbstractView
{
    protected $app;
    protected $security;
    protected $config;
    public const SERVER_CONFIG_FILE_NONE = '';
    public const SERVER_CONFIG_FILE_HTACCESS = '.htaccess';
    public const SERVER_CONFIG_FILE_ADMIN_HTACCESS = 'administrator/.htaccess';
    public const CGPATH = '/media/com_cgsecure';
    /**
     * AJAX Request
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise a JError object.
     */
    public function display($tpl = null)
    {
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        // Check for errors.
        $this->app = Factory::getApplication();
        $input = Factory::getApplication()->getInput();
        $type = $input->get('type');
        $access = (int)$input->get('access');
        $this->security = $input->get('security');
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.manage', 'com_cgsecure')) {
            $arr = [];
            $arr['retour'] = 'err : not authorized';
            echo new JsonResponse($arr);
            return;
        }
        $msg = "";
        $wait = self::getServerConfigFilePath('.working'); // create a temp. file to block other requests
        if (file_exists($wait)) {
            $readBuffer = file($wait, FILE_IGNORE_NEW_LINES);
            if (!$readBuffer) {
                // `file` couldn't read the htaccess we can't do anything at this point
                File::delete($wait);
                return;
            }
            foreach ($readBuffer as $id => $line) {
                $current = time();
                $diff = $current - $line;
                if ($diff > 60) { // plus d'une minute : on est perdu ?
                    File::delete($wait);
                } else {
                    $arr = [];
                    $arr['retour'] = 'err : already in progress';
                    echo new JsonResponse($arr);
                    return;
                }
            }
        }
        $msg = time();
        File::write($wait, $msg);
        $table = Factory::getApplication()->bootComponent('com_cgsecure')->getMVCFactory()->createTable('Config');
        $this->config  = $this->getParams();
        if ($type == 'robots') {
            if ($access == 0) { // delete CG Secure lines from robots.txt file and delete cg_robots dir
                $msg = $this->delRobots();
            } elseif ($access == 1) {// add CG Secure lines to robots.txt and create cg_robots dir
                $msg = $this->addRobots();
            }
            $this->config->blockbad = $access;
            $table->updateSecureParams(json_encode($this->config));
        } elseif ($type == 'htaccess') {
            $this->config  = $this->getParams();
            if ($access == 0) { // delete CG Secure lines from htaccess file
                $msg = $this->delHTAccess();
                $msg .= '<br>'.$this->deleteIPSHTAccess();
                $this->config->htaccess = 0;
                $this->config->blockip = 0;
                $this->config->blockipv6 = 0;
                $this->config->blockai = 0;
                $this->config->blockhotlink = 0;
                // remove cg secure infos from .htaccess in images, media, files, administrator directories
                $this->unprotectdirs();

            } elseif ($access == 1) {// add CG Secure lines to htaccess file
                $msg = $this->addHTAccess();
                $this->config->htaccess = 1;
                if (strpos($msg, 'err : ') === false) {// no error : start update task
                    $this->goCGSecureTask();
                }
                // add .htaccess in images, media, files, administrator directories
                $this->protectdirs();
            } elseif ($access == 2) { // delete hackers IP
                $msg = $this->deleteIPSHTAccess();
                $this->config->blockip = 0;
            } elseif ($access == 3) { // delete AI bots
                $msg = $this->deleteAIHTAccess();
                $this->config->blockai = 0;
            } elseif ($access == 4) { // add AI bots
                $msg = $this->addAIHTAccess();
                $this->config->blockai = 1;
            } elseif ($access == 5) { // add hackers IP
                $this->config  = $this->getParams();
                $blockipv6  = isset($this->config->blockipv6) && $this->config->blockipv6 == 1;
                $msg = $this->addIPSHTAccess($blockipv6);
                $this->config->blockip = 1;
            } elseif ($access == 6) { // delete hackers IP V6
                $msg = $this->addIPSV6HTAccess(false);
                $this->config->blockipv6 = 0;
            } elseif ($access == 7) { // add hackers IP
                $msg = $this->addIPSV6HTAccess(true);
                $this->config->blockipv6 = 1;
            } elseif ($access == 8) { // delete hotlink block
                $msg = $this->deleteHotlinkHTAccess();
                $this->config->blockhotlink = 0;
            } elseif ($access == 9) { // add hotlink block
                $msg = $this->addHotlinkHTAccess();
                $this->config->blockhotlink = 1;
            }
            $table->updateSecureParams(json_encode($this->config));
        }
        File::delete($wait);
        $arr = [];
        $arr['retour'] = $msg;
        echo new JsonResponse($arr);
    }
    // delete CG Secure information in .htaccess file
    private function delHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        if (!$current) { // empty .htaccess
            File::delete($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
            return "";
        }
        $cgFile = '';
        $rejips = '';
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
            return Text::_('CGSECURE_DEL_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_HTACCESS_ERROR');
        }
    }
    // add CG Secure information in .htaccess file
    private function addIPSHTAccess($v6 = false)
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_noip($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $ips = CGSecureHelper::get_reject_onerror_list();
        $rejips = CGSecureHelper::create_ips($ips, $v6);
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
            return Text::_('CGSECURE_DEL_IP_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_IP_HTACCESS_ERROR');
        }
    }
    // add IP V6 information in .htaccess file
    private function addIPSV6HTAccess($v6 = true)
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_noip($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $ips = CGSecureHelper::get_reject_onerror_list();
        $rejips = CGSecureHelper::create_ips($ips, $v6);
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
            return Text::_('CGSECURE_DEL_IP_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_IP_HTACCESS_ERROR');
        }
    }
    private function create_ips($list, $v6 = false)
    {
        $this->config  = $this->getParams();
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

    // delete CG Secure information in .htaccess file
    private function deleteIPSHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_noip($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $rejips = '';
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
            return Text::_('CGSECURE_DEL_IP_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_IP_HTACCESS_ERROR');
        }
    }
    // delete CG Secure information in .htaccess file
    private function deleteAIHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_noai($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $rejips = '';
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '')) {
            return Text::_('CGSECURE_DEL_AI_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_AI_HTACCESS_ERROR');
        }
    }
    // delete CG Secure information in .htaccess file
    private function addAIHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_noai($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $rejips = '';
        $this->config  = $this->getParams();
        $ia = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_ai.txt');
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '', $ia)) {
            return Text::_('CGSECURE_ADD_AI_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_ADD_AI_HTACCESS_ERROR');
        }
    }
    // delete CG Secure information in .htaccess file
    private function deleteHotlinkHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_nohotlink($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $rejips = '';
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '')) {
            return Text::_('CGSECURE_DEL_HOTLINK_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_HOTLINK_HTACCESS_ERROR');
        }
    }
    // delete CG Secure information in .htaccess file
    private function addHotlinkHTAccess()
    {
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file
            return Text::_('CGSECURE_NO_HTACCESS');
        }
        $current = $this->read_current_nohotlink($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));
        $cgFile = '';
        $rejips = '';
        $this->config  = $this->getParams();
        $hotlink = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_hotlink.txt');
        if (CGSecureHelper::merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '', $hotlink)) {
            return Text::_('CGSECURE_ADD_HOTLINK_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_ADD_HOTLINK_HTACCESS_ERROR');
        }
    }

    // start CG Secure Task to force update HTAccess File
    private function goCGSecureTask()
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('*');
        $query->from('#__scheduler_tasks');
        $query->where('type = ' . $db->quote('cgsecure'));
        $query->where('state = 1');
        $query->setLimit(1);
        $db->setQuery($query);
        $found = $db->loadAssoc();
        if (!$found) {// not found in db => exit
            return;
        }
        $task = new TaskModel(array('ignore_request' => true));
        $table = $task->getTable();
        $data = $found;
        $nextExec = Factory::getDate('now');
        $data['next_execution'] = $nextExec->toSql();
        $table->save($data);
        return true;
    }
    // add CG Secure information from .htaccess file
    private function addHTAccess()
    {
        return CGSecureHelper::forceHTAccess(true); 
    }
    // copy CG Secure information in .htaccess from images, media, files, administrator directories
    private function protectdirs()
    {
        return CGSecureHelper::protectotherdirs();
    }
    // remove CG Secure information in .htaccess from images, media, files, administrator directories
    private function unprotectdirs()
    {
        $dest = JPATH_ROOT.'/images/.htaccess';
        if (is_file($dest)) {
            File::delete($dest);
        }
        $dest = JPATH_ROOT.'/media/.htaccess';
        if (is_file($dest)) {
            File::delete($dest);
        }
        if (is_dir(JPATH_ROOT.'/files')) {// Joomla 5.3.0 : new directory
            $dest = JPATH_ROOT.'/files/.htaccess';
            if (is_file($dest)) {
                File::delete($dest);
            }
        }
        $dest = JPATH_ROOT.'/administrator/.htaccess';
        if (is_file($dest)) {
            $current = $this->read_current($dest);
            if (!$current) {// empty : remove it
                File::delete($dest);
            } else { // not empty : save it without CG SSecure infos
                CGSecureHelper::merge_file($dest, $current, '', '');
            }
        }
    }
    // add Bad robots blocking
    // - add lines in robots.txt files if it exists, or copy default robots.txt file
    // - create cg_no_robots dir
    private function addRobots()
    {
        $filename = "robots.txt";
        $serverConfigFile = $this->getServerConfigFile($filename);
        if (!$serverConfigFile) { // no robots.txt file : copy default robots.txt to root dir
            $source = JPATH_ROOT.self::CGPATH .'/txt/robots.txt';
            $dest = $this->getServerConfigFilePath($filename);
            if (!copy($source, $dest)) {
                return 'err : '.Text::_('CGSECURE_ADD_ROBOTS_ERROR');
            }
        }
        $source = $this->getServerConfigFilePath($filename);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/robots.av'.gmdate('Ymd-His', time());
        if (!copy($source, $dest)) {
            return 'err : '.Text::_('CGSECURE_SAVE_ROBOTS_ERROR');
        }
        $current = $this->read_current($this->getServerConfigFilePath($filename));
        $cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgrobots.txt');
        if (!CGSecureHelper::merge_file($this->getServerConfigFilePath($filename), $current, $cgFile, '')) {
            return 'err : '.Text::_('CGSECURE_ADD_ROBOTS_INSERT_ERROR');
        }
        // copy cg_no_robot folder to root*
        $source = JPATH_ROOT.self::CGPATH .'/cg_no_robot';
        $dest = JPATH_ROOT.'/cg_no_robot';
        // jimport('joomla.filesystem.folder');
        try { // delete cg_no_robot folder if exists
            Folder::delete($dest);
        } catch (\Exception $e) {// ignore error
        }
        try {
            Folder::copy($source, $dest);
        } catch (\Exception $e) {
            return 'err : '.Text::_('CGSECURE_ADD_ROBOTS_ERR');
        }
        $source = JPATH_ROOT.'/cg_no_robot/index.txt';
        $dest = JPATH_ROOT.'/cg_no_robot/index.php';
        if (is_file($dest)) {
            File::delete($dest);
        } // remove index.php file if present
        if (!rename($source, $dest)) {
            return 'err : '.Text::_('CGSECURE_ADD_ROBOTS_ERR');
        }

        return Text::_('CGSECURE_ADD_ROBOTS');
    }
    // delete CG Secure information in robots.txt file
    private function delRobots()
    {
        $filename = "robots.txt";
        $serverConfigFile = $this->getServerConfigFile($filename);
        if (!$serverConfigFile) { // no robots.txt file
            return Text::_('CGSECURE_NO_ROBOTS');
        }
        $current = $this->read_current($this->getServerConfigFilePath($filename));
        $cgFile = '';
        $rejips = '';
        if (!CGSecureHelper::merge_file($this->getServerConfigFilePath($filename), $current, $cgFile, $rejips)) {
            return  'err : '.Text::_('CGSECURE_DEL_ROBOTS_ERROR');
        }
        $dest = JPATH_ROOT.'/cg_no_robot';
        try {
            Folder::delete($dest);
        } catch (\Exception $e) {
            return 'err : '.Text::_('CGSECURE_DEL_ROBOTS_ERROR');
        }
        return Text::_('CGSECURE_DEL_ROBOTS');

    }
    // read current .htaccess file and remove CG lines
    private function read_current($afile)
    {
        return CGSecureHelper::empty_current($afile);
    }
    // get current ips from .htaccess file
    private function get_current_ips(String $afile) : String
    {
        return CGSecureHelper::get_current_ips($afile);
    }
    // read current .htaccess file and remove IP lines
    private function read_current_noip($afile)
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $cgLines = false;
        foreach ($readBuffer as $id => $line) {
            if ($line === '#------------------------CG SECURE IP LIST BEGIN---------------------') {
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
    // read current .htaccess file and remove AI lines
    private function read_current_noai($afile)
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $cgLines = false;
        foreach ($readBuffer as $id => $line) {
            if ($line === '#------------------------CG SECURE IA BOTS BEGIN---------------------') {
                $cgLines = true;
                continue;
            }
            if ($line === '#------------------------CG SECURE IA BOTS END---------------------') {
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
    // read current .htaccess file and remove hotlink lines
    private function read_current_nohotlink($afile)
    {
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $cgLines = false;
        foreach ($readBuffer as $id => $line) {
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
    private function read_cgfile($afile)
    {
        
        $outBuffer = CGSecureHelper::read_cgfile($afile);
        
        return $outBuffer;
    }
    private function getServerConfigFile(String $file) : String
    {
        return CGSecureHelper::getServerConfigFile($file);
    }
    private function getServerConfigFilePath(String $file) : String
    {
        return CGSecureHelper::getServerConfigFilePath($file);
    }
    private function getParams()
    {
        $table = Factory::getApplication()->bootComponent('com_cgsecure')->getMVCFactory()->createTable('Config');
        $params = json_decode($table->getSecureParams()->params);
        return $params;

    }
}
