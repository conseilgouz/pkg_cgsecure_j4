<?php
/**
 * @component     Plugin Task CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Plugin\Task\CGSecure\Extension;

defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use ConseilGouz\CGSecure\Helper\CGSecureHelper;

final class Cgsecure extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var boolean
     * @since 4.1.0
     */
    protected $autoloadLanguage = true;
    /**
     * @var string[]
     *
     * @since 4.1.0
     */
    protected const TASKS_MAP = [
        'cgsecure' => [
            'langConstPrefix' => 'PLG_TASK_CGSECURE',
            'form'            => 'cgsecure',
            'method'          => 'cgsecure',
        ],
    ];
    protected $myparams;
    protected $pluginParams;
    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 4.1.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    protected function cgsecure(ExecuteTaskEvent $event): int
    {
        $this->myparams = $event->getArgument('params');
        $this->goSecure();
        return TaskStatus::OK;
    }
    private function goSecure()
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('plg_task_automsg');
        $cgsecure_params = CGSecureHelper::getParams();
        if (isset($cgsecure_params->matomo) && $cgsecure_params->matomo) { // use matomo bad referers list ?
            $this->checkMatomo();  // Update Matomo spammers list
        } elseif (!isset($cgsecure_params->matomo)) { // CG Secure before 3.9.4 : suppose update required
            $this->checkMatomo();  // Update Matomo spammers list
        }
        if ($cgsecure_params->blockai) { // blocking AI ?
            $this->checkAI();      // update perishablepress AI list
        }
        if (isset($cgsecure_params->iphtaccess) && $cgsecure_params->iphtaccess == 'task') { // mise à jour IP dans htaccess ?
            CGSecureHelper::forceHTAccess();
            $this->cleanup_backups();
        }
        return TaskStatus::OK;
    }
    private function checkMatomo()
    {
        $file = JPATH_ROOT . '/media/com_cgsecure/txt/spammers.txt';
        if (!@copy('https://raw.githubusercontent.com/matomo-org/referrer-spam-list/refs/heads/master/spammers.txt', $file)) {
            $errors = error_get_last();
            return TaskStatus::OK;
        }
        $hash = hash_file('sha256', $file);
        if (!$this->checkHTAccessMatomo($hash)) {
            $this->storeHTAccessMatomo($hash);
        }
    }
    // same hash : no need to update
    private function checkHTAccessMatomo($hash)
    {
        $htaccess = CGSecureHelper::getServerConfigFilePath('.htaccess');
        $readBuffer = file($htaccess, FILE_IGNORE_NEW_LINES);
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return false;
        }
        $currenthash = '';
        foreach ($readBuffer as $id => $line) {
            if (substr($line, 0, 12) === '#----> hash ') {
                $currenthash = substr($line, 12, strpos($line, ',updated') - 12);
                break;
            }
        }
        return $currenthash == $hash;
    }

    private function storeHTAccessMatomo($hash)
    {
        $wait = CGSecureHelper::getServerConfigFilePath('.inprogress'); // create a temp. file to block other requests
        if (file_exists($wait)) {//
            return;
        }
        $msg = 'wait...';
        File::write($wait, $msg);
        $serverConfigFile = CGSecureHelper::getServerConfigFilePath('.htaccess');
        if (!$serverConfigFile) { // no .htaccess file
            return;
        }
        $spammer = CGSecureHelper::getServerConfigFilePath('media/com_cgsecure/txt/spammers.txt');
        $current = $this->replace_matomo(CGSecureHelper::getServerConfigFilePath('.htaccess'), $spammer, $hash);
        $this->store_file(CGSecureHelper::getServerConfigFilePath('.htaccess'), $current);
        File::delete($wait);
    }
    // read current .htaccess file and remove CG lines
    private function replace_matomo($htaccess, $matomo, $hash)
    {
        $readBuffer = file($htaccess, FILE_IGNORE_NEW_LINES);
        $outBuffer = '';
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $cgLines = false;
        foreach ($readBuffer as $id => $line) {
            if ($line === '#------MATOMO') {
                $outBuffer .= $line . PHP_EOL;
                $date = HTMLHelper::date(time(), 'Y-m-d H:i:s');
                $outBuffer .= '#----> hash '.$hash.',updated '.$date . PHP_EOL;
                $cgLines = true;
                continue;
            }
            if ($line === '#------END MATOMO') {
                $outBuffer = $this->merge($outBuffer, $matomo);
                $outBuffer .= $line . PHP_EOL;
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
    private function checkAI()
    {
        $file = JPATH_ROOT . '/media/com_cgsecure/txt/cgaccess_ai.txt';
        if (!@copy('https://raw.githubusercontent.com/conseilgouz/pkg_cgsecure_j4/master/packages/com_cgsecure/media/txt/cgaccess_ai.txt', $file)) {
            $errors = error_get_last();
            return TaskStatus::OK;
        }
        $version = $this->getAIVersion($file); // ligne de version du fichier AI
        if (!$version) {
            return;
        } // pas de version ??????
        if (!$this->checkHTAccessAI($version)) {
            $this->storeHTAccessAI();
        }
    }
    private function getAIVersion($file)
    {
        $readBuffer = file($file, FILE_IGNORE_NEW_LINES);
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return false;
        }
        $version = '';
        foreach ($readBuffer as $id => $line) {
            if (substr($line, 0, 24) === '# Ultimate AI Block List') {
                $version = $line;
                break;
            }
        }
        return $version;
    }
    // same version : no need to update
    private function checkHTAccessAI($version)
    {
        $htaccess = CGSecureHelper::getServerConfigFilePath('.htaccess');
        $readBuffer = file($htaccess, FILE_IGNORE_NEW_LINES);
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return false;
        }
        $currentversion = '';
        foreach ($readBuffer as $id => $line) {
            if (substr($line, 0, 24) === '# Ultimate AI Block List') {
                $currentversion = $line;
                break;
            }
        }
        return $currentversion == $version;
    }
    private function storeHTAccessAI()
    {
        $wait = CGSecureHelper::getServerConfigFilePath('.inprogress'); // create a temp. file to block other requests
        if (file_exists($wait)) {//
            return;
        }
        $msg = 'wait...';
        File::write($wait, $msg);
        $serverConfigFile = CGSecureHelper::getServerConfigFilePath('.htaccess');
        if (!$serverConfigFile) { // no .htaccess file
            return;
        }
        $ai = CGSecureHelper::getServerConfigFilePath('media/com_cgsecure/txt/cgaccess_ai.txt');
        $current = $this->replace_AI(CGSecureHelper::getServerConfigFilePath('.htaccess'), $ai);
        $this->store_file(CGSecureHelper::getServerConfigFilePath('.htaccess'), $current);
        File::delete($wait);
    }
    // read current .htaccess file and remove CG lines
    private function replace_AI($htaccess, $ai)
    {
        $readBuffer = file($htaccess, FILE_IGNORE_NEW_LINES);
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
                $outBuffer .= $this->mergeAI($ai);
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
    private function mergeAI($ai)
    {
        $readBuffer = file($ai, FILE_IGNORE_NEW_LINES);
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $buffer = "";
        foreach ($readBuffer as $id => $line) {
            $buffer .= $line. PHP_EOL;
        }
        return $buffer;
    }
    private function merge($buffer, $matomo)
    {
        $readBuffer = file($matomo, FILE_IGNORE_NEW_LINES);
        if (!$readBuffer) {// `file` couldn't read the htaccess we can't do anything at this point
            return '';
        }
        $oneline = '';
        $i = 0;
        foreach ($readBuffer as $id => $line) {
            if ($i > 20) { // write one line
                $buffer .= $oneline .') [NC,OR]'. PHP_EOL;
                $oneline = "";
                $i = 0;
            }
            if ($oneline == "") {
                $oneline = "RewriteCond %{HTTP_REFERER} (";
            }
            if ($i > 0) {
                $oneline .= '|';
            }
            $oneline .= trim($line);
            $i++;
        }
        if ($i > 0) { // write last line
            $buffer .= $oneline .') [NC,OR]'. PHP_EOL;
        }
        return $buffer;
    }

    private function store_file($htaccess, $current)
    {
        $pathToHtaccess  = $htaccess;
        if (file_exists($pathToHtaccess)) {
            copy($pathToHtaccess, $pathToHtaccess.'.wait');
            if (is_readable($pathToHtaccess)) {
                $records = $current;
                // Write the htaccess using the Frameworks File Class
                $bool = File::write($pathToHtaccess, $records);
                if ($bool) {
                    if (CGSecureHelper::check_site()) {
                        File::delete($pathToHtaccess.'.wait');
                        return $bool;
                    } else {
                        // restore previous version
                        copy($pathToHtaccess.'.wait', $pathToHtaccess);
                        File::delete($pathToHtaccess.'.wait');
                        return false;
                    }
                }
            }
            File::delete($pathToHtaccess.'.wait');
        }
    }
    private function cleanup_backups()
    {
        $dir = JPATH_ROOT.'/media/com_cgsecure/backup';
        $files = glob($dir.'/htaccess*');
        rsort($files);
        $keep = 5; // keep only 5 latest files
        $count = 0;
        foreach ($files as $file) {
            if ($count => $keep) {
                continue;
            }
            unset($files[$count]);
            $count++;
        }
        $this->delete($files);
    }
    private function delete($files = [])
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
