<?php
/**
 * @component     Plugin User CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/

// No direct access.
defined('_JEXEC') or die();
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;

class PlgUserCGSecure extends CMSPlugin
{
    public $myname='UserCGSecure';
	public $mymessage='Joomla User : try to access forms...';
	public $errtype = 'w';	 // warning 
	public $cgsecure_params;
    private $debug; 
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

	// more info on UserLoginFailure
    function onUserLoginFailure( $user)  {
        $this->debug = $this->cgsecure_params->debug;
        if (!$this->debug) return;
		JLog::addLogger(array('text_file' => 'cgipcheck.trace.log'), JLog::INFO);
		$cmd = $_SERVER['REQUEST_URI'];
		JLog::add('Cmd : '.htmlspecialchars($cmd, ENT_QUOTES), JLog::INFO, "Auth Fail");
		foreach($_REQUEST as $key => $value) {
		    JLog::add('key : '.htmlspecialchars($key, ENT_QUOTES).":".htmlspecialchars($value, ENT_QUOTES), JLog::INFO, "Auth Fail");
		}
	}
	// Check IP on prepare Forms
	function onContentPrepareForm(Form $form, $data)
	{
		$form_name = $form->getName();
		$name = explode('.',$form_name);
		$components = $this->cgsecure_params->components;
		$arr_components = explode(',',$components);
		if (!in_array($name[0], $arr_components))
		{
		    return true;
		}
		if (($name[0] == 'com_users')  && ($name[1] == 'profile') ) return true; // Contact forms call com_user.profile, so ignore this one
		if (!class_exists('CGIpCheckHelper')) { // library not found
			Factory::getApplication()->enqueueMessage(Text::_('CGSECURE_LIB_NOTFOUND'),'error');
			return  true;
		}
		$prefixe = $_SERVER['SERVER_NAME'];
		$prefixe = substr(str_replace('www.','',$prefixe),0,2);
		$this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;

		\CGIpCheckHelper::check_ip($this,$this->myname.' : '.$form_name);
	}
}
