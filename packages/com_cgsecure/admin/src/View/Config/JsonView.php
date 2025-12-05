<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Config;

defined('_JEXEC') or die('Restricted access');
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
        $msg = "";
        $wait = self::getServerConfigFilePath('.inprogress'); // create a temp. file to block other requests
        if (file_exists($wait)) {
            $arr = [];
            $arr['retour'] = 'err : already in progress';
            echo new JsonResponse($arr);
            return;
        }
        $msg = 'wait...';
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
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
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
        $ips = $this->get_htaccess_List();
        $rejips = $this->create_ips($ips, $v6);
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
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
        $ips = $this->get_htaccess_List();
        $rejips = $this->create_ips($ips, $v6);
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
            return Text::_('CGSECURE_DEL_IP_HTACCESS');
        } else {
            return 'err : '.Text::_('CGSECURE_DEL_IP_HTACCESS_ERROR');
        }
    }
    // Get HTAccess Rejected IPs list
    private function get_htaccess_List()
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
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips)) {
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
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '')) {
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
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '', $ia)) {
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
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '')) {
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
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, '', $hotlink)) {
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
        $serverConfigFile = $this->getServerConfigFile(self::SERVER_CONFIG_FILE_HTACCESS);
        if (!$serverConfigFile) { // no .htaccess file : copy default htaccess.txt as .htaccess
            $source = JPATH_ROOT.self::CGPATH .'/txt/htaccess.txt';
            $dest = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
            if (!copy($source, $dest)) {
                return 'err : '.Text::_('CGSECURE_ADD_HTACCESS_ERROR');
            }
        }
        // save htaccess file before adding CG Secure lines
        $source = $this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS);
        $dest = JPATH_ROOT.self::CGPATH .'/backup/htaccess.av'.gmdate('Ymd-His', time());
        if (!copy($source, $dest)) {
            return 'err : '.Text::_('CGSECURE_SAVE_HTACCESS_ERROR');
        }
        $current = $this->read_current($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS));

        $ips = $this->get_htaccess_List();
        $rejips = $this->create_ips($ips);

        if (file_exists(JPATH_ROOT.self::CGPATH .'/txt/custom.txt')) { // custom file exists : use it
            $cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/custom.txt');
        } else { // no custom file : use cgaccess.txt file
            $cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess.txt');
        }
        $this->config  = $this->getParams();
        $specific = "";
        if (isset($this->config->specific) && $this->config->specific) {
            $specific  = '#------------------------CG SECURE SPECIFIC CODE BEGIN------------------------'.PHP_EOL.$this->config->specific.PHP_EOL;
            $specific .= '#------------------------CG SECURE SPECIFIC CODE END------------------------'.PHP_EOL;
        }
        $ia = "";
        if (isset($this->config->blockai) && $this->config->blockai) {
            $ia = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_ai.txt');
        }
        $hotlink = "";
        if (isset($this->config->blockhotlink) && $this->config->blockhotlink) {
            $hotlink = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_hotlink.txt');
        }
        if ($this->merge_file($this->getServerConfigFilePath(self::SERVER_CONFIG_FILE_HTACCESS), $current, $cgFile, $rejips, $specific, $ia, $hotlink)) {
            return Text::_('CGSECURE_ADD_HTACCESS');
        }
        // Error : restore saved version
        copy($dest, $source);
        return 'err : '.Text::_('CGSECURE_ADD_HTACCESS_INSERT_ERROR');
    }
    // copy CG Secure information in .htaccess from images, media, files, administrator directories
    private function protectdirs()
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
            return 'err : ' . Text::_('CGSECURE_PROTECTDIRS_ERROR');
        }
        $dest = JPATH_ROOT.'/media/.htaccess';
        if (is_file($dest)) {
            File::delete($dest);
        }
        if (!copy($source, $dest)) {
            return 'err : '.Text::_('CGSECURE_PROTECTDIRS_ERROR');
        }
        if (is_dir(JPATH_ROOT.'/files')) {// Joomla 5.3.0 : new directory
            $dest = JPATH_ROOT.'/files/.htaccess';
            if (is_file($dest)) {
                File::delete($dest);
            }
            if (!copy($source, $dest)) {
                return 'err : '.Text::_('CGSECURE_PROTECTDIRS_ERROR');
            }
        }
        $dest = JPATH_ROOT.'/administrator/.htaccess';
        $source = JPATH_ROOT.self::CGPATH .'/txt/cgaccess_admin.txt';
        if (!is_file($dest)) {
            copy($source, $dest);
        }
        $current = $this->read_current($dest);
        $cgFile = $this->read_cgfile(JPATH_ROOT.self::CGPATH .'/txt/cgaccess_admin.txt');
        if (!$this->merge_file($dest, $current, $cgFile, '')) {
            return 'err : '.Text::_('CGSECURE_ADD_ADMIN_INSERT_ERROR');
        }
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
                $this->merge_file($dest, $current, '', '');
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
        if (!$this->merge_file($this->getServerConfigFilePath($filename), $current, $cgFile, '')) {
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
        if (!$this->merge_file($this->getServerConfigFilePath($filename), $current, $cgFile, $rejips)) {
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
        $readBuffer = file($afile, FILE_IGNORE_NEW_LINES);
        $this->config  = $this->getParams();
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
    private function merge_file($file, $current, $cgFile, $rejips, $specific = '', $ai = '', $hotlink = '')
    {
        $pathToFile  = $file;
        if (file_exists($pathToFile)) {
            if (is_readable($pathToFile)) {
                $records = $rejips.$specific.$cgFile.$ai.$hotlink.$current; // pour éviter les conflits, on se met devant....
                // Write the htaccess using the Frameworks File Class
                $bool = File::write($pathToFile, $records);
                if ($bool) {
                    if ($this->check_site()) {
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
    private function getParams()
    {
        $table = Factory::getApplication()->bootComponent('com_cgsecure')->getMVCFactory()->createTable('Config');
        $params = json_decode($table->getSecureParams()->params);
        return $params;

    }
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
            if ($responseCode == 500) {
                return false;
            }
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
        return false;
    }
}
