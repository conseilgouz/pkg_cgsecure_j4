<?php
/**
 * @component     Plugin Task CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
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
        $file = JPATH_ROOT . '/media/com_cgsecure/txt/spammers.txt';
        if (!@copy('https://raw.githubusercontent.com/matomo-org/referrer-spam-list/refs/heads/master/spammers.txt', $file)) {
            $errors = error_get_last();
            return TaskStatus::OK;
        }
        $hash = hash_file('sha256', $file);
        if (!$this->checkHTAccess($hash)) {
            $this->storeHTAccess($hash);
        }
        return TaskStatus::OK;
    }
    // same hash : no need to update
    private function checkHTAccess($hash)
    {
        $htaccess = $this->getServerConfigFilePath('.htaccess');
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

    private function storeHTAccess($hash)
    {
        $wait = self::getServerConfigFilePath('.inprogress'); // create a temp. file to block other requests
        if (file_exists($wait)) {//
            return;
        }
        $msg = 'wait...';
        File::write($wait, $msg);
        $serverConfigFile = $this->getServerConfigFilePath('.htaccess');
        if (!$serverConfigFile) { // no .htaccess file
            return;
        }
        $spammer = $this->getServerConfigFilePath('media/com_cgsecure/txt/spammers.txt');
        $current = $this->replace_matomo($this->getServerConfigFilePath('.htaccess'), $spammer, $hash);
        $this->store_file($this->getServerConfigFilePath('.htaccess'), $current);
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
                    if (self::check_site()) {
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

    // check if website is still working
    private static function check_site()
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

    private function getServerConfigFilePath($file)
    {
        return JPATH_ROOT . DIRECTORY_SEPARATOR . $file;
    }

}
