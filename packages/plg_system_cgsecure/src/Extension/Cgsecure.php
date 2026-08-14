<?php
/**
 * @package 	CGSecure
 * from karebu secure (kSesure)
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
 */

namespace Conseilgouz\Plugin\System\CGSecure\Extension;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Event\ErrorEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Utilities\IpHelper;
use ConseilGouz\CGSecure\Cgipcheck;

final class Cgsecure extends CMSPlugin implements SubscriberInterface
{
    use UserFactoryAwareTrait;

    public $myname = 'SystemCGSecure';
    public $mymessage = 'Joomla Admin : try to force the door...';
    public $cgsecure_params;
    public $errtype = 'e'; // error : hacking

    public function __construct($subject, $config)
    {
        parent::__construct($subject, $config);
        $this->cgsecure_params = Cgipcheck::getParams();
    }
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onAfterInitialise' => 'onAfterInitialise',
                'onAfterDispatch' => 'onAfterDispatch',
                'onBeforeRender' => 'onBeforeRender',
                'onError' => 'onError'];
    }
    // check if ip is in latest hackers list
    public function onAfterInitialise($event)
    {
        if (isset($this->cgsecure_params->iphtaccess) && $this->cgsecure_params->iphtaccess != 'task') {
            return;
        }
        // in task mode : new hackers IP's are not in htacces
        $ip = IpHelper::getIp();
        if ($this->getLatest_ips($ip)) {
            die(403);
        }
    }
    public function onAfterDispatch($event)
    {
        $mainframe 	= $this->getApplication();
        $user 		= $this->getApplication()->getIdentity();
        $session	= $this->getApplication()->getSession();
        if ($session->get('cgsecure')) {// already checked
            return;
        }
        if (!$mainframe->isClient('administrator') || !$user->guest) {
            return;
        }
        if (!$this->cgsecure_params->password) {// no check
            // self::createCookie();
            return;
        }
        if (isset($_COOKIE['cg_secure']) && ($_COOKIE['cg_secure'] == $this->cgsecure_params->security)) {
            // cookie has beeen created : don't check
            return;
        }
        $prefixe = $_SERVER['SERVER_NAME'];
        $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
        $this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;

        Cgipcheck::check_ip($this, $this->myname);

        if ($this->cgsecure_params->mode) {
            if (substr(php_sapi_name(), 0, 3) == 'cgi') {
                Factory::getApplication()->enqueueMessage(Text::_('CG_SECURE_NOT_APACHE_HANDLER'), 'error');
                return true;
            }

            $logged = @$_SERVER['PHP_AUTH_PW'] == $this->cgsecure_params->password;
            if (!$logged) {
                header('WWW-Authenticate: Basic realm="'.$mainframe->getCfg('sitename').'"');
                header('HTTP/1.0 401 Unauthorized');
                die();
            }
        } else { // Compatibility : looking for ?<password>
            $logged = isset($_GET[$this->cgsecure_params->password]);
            if (!$logged) {
                if (($this->cgsecure_params->selredir == 'LOCAL') || (Cgipcheck::whiteList())) {
                    $mainframe->redirect(URI::root());
                } else {
                    $mainframe->redirect($this->cgsecure_params->redir_ext);
                }
            }
        }
        if ($logged) {
            $session->set('cgsecure', true);
            self::createCookie();
        }
    }
    private function createCookie()
    {
        $secure = array_key_exists("HTTPS", $_SERVER);
        return setcookie('cg_secure', $this->cgsecure_params->security, [
                        'expires' => 'Session',
                        'path' => '/',
                        'domain' => '',
                        'samesite' => 'Lax',
                        'secure' => $secure,
                        'httponly' => false
            ]);
    }
    public function onBeforeRender($event)
    {
        $mainframe 	= $this->getApplication();
        $user 		= $this->getApplication()->getIdentity();
        $session	= $this->getApplication()->getSession();
        if ($session->get('cgsecure')) {// already checked
            return;
        }
        if ($mainframe->isClient('administrator') || !$user->guest) {
            return;
        }

        if (!$this->cgsecure_params->blockbad) {
            return;
        }
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $script = "document.addEventListener('DOMContentLoaded', function() {
                    var link = document.createElement('a');
                    link.rel = 'nofollow';
                    link.style.display = 'none';
                    link.href= '".URI::base()."cg_no_robot';
                    link.innerHTML='Do NOT follow this link or you will be banned from the site!';
                    document.body.appendChild(link);
                    })";
        $wa->addInlineScript($script);
    }
    public function onError(ErrorEvent $event)
    {
        /** @var \Joomla\CMS\Application\CMSApplication $app */
        $app = $event->getApplication();

        if ($app->isClient('administrator') || ((int) $event->getError()->getCode() !== 404)) {
            return;
        }
        if (!isset($this->cgsecure_params->check404) || ($this->cgsecure_params->check404 == 0)) {
            return;
        }
        $ip = IpHelper::getIp();
        if ($this->getLatest_404($ip)) { // too many 404 : kill
            die(404);
        }
    }
    private function getLatest_404(String $ip): Bool
    {
        if ($this->getLatest_ips($ip)) {
            // already rejected : die
            die(403);
        }
        $latest_404 = [];
        // read latest_404 file
        $file = JPATH_ROOT . '/media/com_cgsecure/backup/latest_404.txt';
        $readBuffer = file($file, FILE_IGNORE_NEW_LINES);
        $found = false; // suppose not found
        $count = 0;
        $time = time();
        $start = time();
        foreach ($readBuffer as $id => $line) {
            $split = explode(',', $line);
            if ($split[0] == $ip) {
                $found = true;
                $count = (int)$split[1];
                $count++;
                $start = $split[2];
                $last = $split[3];
                if (($time - $last) > $this->cgsecure_params->page404_delay) {
                    // plus de 30 secondes depuis la derniere erreur 404
                    // on réintialise les compteurs, ce n'est pas un robot
                    $count = 1;
                    $start = $time;
                }
                $latest_404[$split[0]] = $split[0].','.$count.','.$start.','.$time;
                if ($count == $this->cgsecure_params->page404_count + 1) { // block hacker
                    $prefixe = $_SERVER['SERVER_NAME'];
                    $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
                    $message = $prefixe.$this->errtype.'- Too many 404 errors';
                    Cgipcheck::report_hacker($this->myname, $message, 'e', $ip);
                }
            } else {
                $latest_404[$split[0]] = $line;
            }
        }
        if (!$found) {
            $latest_404[$ip] = $ip.',1,'.$start.','.$start;
        }
        if (count($latest_404) > 20) {
            array_shift($latest_404);
        }
        $out = '';
        foreach ($latest_404 as $val) {
            $out .= $val.PHP_EOL;
        }
        // Write the htaccess using the Frameworks File Class
        File::write($file, $out);
        if ($count > $this->cgsecure_params->page404_count) {
            return true;
        }
        return false;
    }
    private function getLatest_ips(String $ip): Bool
    {
        $latest_ips = [];
        // read latest_ips file
        $file = JPATH_ROOT . '/media/com_cgsecure/backup/latest_ips.txt';
        $readBuffer = file($file, FILE_IGNORE_NEW_LINES);
        foreach ($readBuffer as $id => $line) {
            $latest_ips[] = $line;
        }
        if (in_array($ip, $latest_ips)) {
            return true;
        }
        return false;
    }

}
