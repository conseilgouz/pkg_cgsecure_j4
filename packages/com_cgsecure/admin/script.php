<?php
/**
* CG Secure Component For Joomla 4
* Version			: 2.1.5
* Package			: CG Secure Component
* copyright 		: Copyright (C) 2022 ConseilGouz. All rights reserved.
* license    		: http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
*/
// No direct access to this file
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Filesystem\Folder;

class com_cgsecureInstallerScript
{
	private $min_joomla_version      = '4.0';
	private $min_php_version         = '7.2';
	private $name                    = 'CG Secure';
	private $exttype                 = 'component';
	private $extname                 = 'cgsecure';
	private $previous_version        = '';
	private $dir           = null;
	private $lang = null;
	private $installerName = 'cgsecureinstaller';
	public function __construct()
	{
		$this->dir = __DIR__;
	}
    function preflight($type, $parent)
    {
    }
    
    function install($parent)
    {
    }
    
    function uninstall($parent)
    {
		
		$db = Factory::getDbo();
        $query = $db->getQuery(true)
			->delete('#__extensions')
		    ->where($db->quoteName('element') . ' like "%cgsecure%"');
		$db->setQuery($query);
		$result = $db->execute();
		$obsloteFolders = ['/plugins/system/cgsecure', '/plugins/authentication/cgsecure','/plugins/user/cgsecure','/libraries/cgsecure'];
		// Remove plugins' files.
		foreach ($obsloteFolders as $folder)
		{
			$f = JPATH_SITE . $folder;

			if (!@file_exists($f) || !is_dir($f) || is_link($f))
			{
				continue;
			}

			Folder::delete($f);
		}
		
		Factory::getApplication()->enqueueMessage('CG Secure package uninstalled', 'notice');
    }
    
    function update($parent)
    {
    }
    
    function postflight($type, $parent)
    {
     }

}