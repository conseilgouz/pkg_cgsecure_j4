<?php
/**
 * @component     Plugin Authentication CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/
namespace Conseilgouz\Plugin\Authentication\CGSecure\Extension;
// No direct access.
defined('_JEXEC') or die();
use Joomla\CMS\Event\User\AuthenticationEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use ConseilGouz\CGSecure\Cgipcheck;

final class Cgsecure extends CMSPlugin implements SubscriberInterface
{
    public $myname = 'AuthentCGSecure';
    public $mymessage = 'Joomla Authentification : try to force the door...';
    public $errtype = 'e'; // error : hacking
    public $cgsecure_params;

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
        return ['onUserAuthenticate' => 'onUserAuthenticate'];
    }

    public function onUserAuthenticate(AuthenticationEvent $event)
    {
        $prefixe = $_SERVER['SERVER_NAME'];
        $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
        $this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;
        Cgipcheck::check_ip($this, $this->myname.' : onUserAuthenticate');
    }
}
