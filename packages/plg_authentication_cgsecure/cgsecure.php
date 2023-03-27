<?php
/**
 * @component     Plugin Authentication CG Secure
 * Version			: 2.2.0
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2023 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/

// No direct access.
defined('_JEXEC') or die();
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Language\Text;

class PlgAuthenticationCGSecure extends CMSPlugin
{
    public $myname='AuthentCGSecure';
	public $mymessage='Joomla Authentification : try to force the door...';
	public $errtype = 'e'; // error : hacking
	public $cgsecure_params;

	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);

		$helperFile = JPATH_SITE . '/libraries/cgsecure/ipcheck.php';

		if (!class_exists('CGIpCheckHelper') && is_file($helperFile))
		{
			include_once $helperFile;
		}
		if (!class_exists('CGIpCheckHelper')) { // library not found
			return  true;
		}
		$this->cgsecure_params = \CGIpCheckHelper::getParams();
		
	}
	
	public function onUserAuthenticate($credentials, $options, &$response)
	{
		if (!class_exists('CGIpCheckHelper')) { // library not found
			Factory::getApplication()->enqueueMessage(Text::_('CGSECURE_LIB_NOTFOUND'),'error');
			return  true;
		}
		$prefixe = $_SERVER['SERVER_NAME'];
		$prefixe = substr(str_replace('www.','',$prefixe),0,2);
		$this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;
		\CGIpCheckHelper::check_ip($this,$this->myname.' : onUserAuthenticate');
	}
}
