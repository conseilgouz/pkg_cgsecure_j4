<?php
/**
 * @component     Plugin Authentication CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2024 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

// No direct access.
defined('_JEXEC') or die();
use Joomla\CMS\Plugin\CMSPlugin;
use ConseilGouz\CGSecure\Helper\Cgipcheck;

class PlgAuthenticationCGSecure extends CMSPlugin
{
    public $myname = 'AuthentCGSecure';
    public $mymessage = 'Joomla Authentification : try to force the door...';
    public $errtype = 'e'; // error : hacking
    public $cgsecure_params;

    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);

        $this->cgsecure_params = Cgipcheck::getParams();

    }

    public function onUserAuthenticate($credentials, $options, &$response)
    {
        $prefixe = $_SERVER['SERVER_NAME'];
        $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
        $this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;
        Cgipcheck::check_ip($this, $this->myname.' : onUserAuthenticate');
    }
}
