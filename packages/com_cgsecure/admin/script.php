<?php
/**
* CG Secure Component For Joomla 4/5/6
* Version			: 3.9.0
* Package			: CG Secure Component
* copyright 		: Copyright (C) 2026 ConseilGouz. All rights reserved.
* license    		: https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
*/
// No direct access to this file
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use ConseilGouz\CGSecure\Helper\CGSecureHelper;

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

    public function uninstall($parent)
    {
        // remove CG Secure infos from .htaccess
        $dest = CGSecureHelper::getServerConfigFilePath(CGSecureHelper::SERVER_CONFIG_FILE_HTACCESS);
        $current = CGSecureHelper::empty_current($dest);
        CGSecureHelper::merge_file($dest, $current, '', '');
        $dest = JPATH_ROOT.'/images/.htaccess';
        if (is_file($dest)) {
            $current = CGSecureHelper::empty_other($dest);
            if (!$current) {// empty : remove it
                File::delete($dest);
            } else {
                CGSecureHelper::merge_file($dest, $current, '', '');
            }
        }
        $dest = JPATH_ROOT.'/media/.htaccess';
        if (is_file($dest)) {
            $current = CGSecureHelper::empty_other($dest);
            if (!$current) {// empty : remove it
                File::delete($dest);
            } else {
                CGSecureHelper::merge_file($dest, $current, '', '');
            }
        }
        if (is_dir(JPATH_ROOT.'/files')) {// Joomla 5.3.0 : new directory
            $dest = JPATH_ROOT.'/files/.htaccess';
            if (is_file($dest)) {
                $current = CGSecureHelper::empty_other($dest);
                if (!$current) {// empty : remove it
                    File::delete($dest);
                } else {
                    CGSecureHelper::merge_file($dest, $current, '', '');
                }
            }
        }
        $dest = JPATH_ROOT.'/administrator/.htaccess';
        if (is_file($dest)) {
            $current = CGSecureHelper::empty_other($dest);
            if (!$current) {// empty : remove it
                File::delete($dest);
            } else { // not empty : save it without CG SSecure infos
                CGSecureHelper::merge_file($dest, $current, '', '');
            }
        }
        // uninstall all plugins
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->delete('#__extensions')
            ->where($db->quoteName('element') . ' like "%cgsecure%"');
        $db->setQuery($query);
        $result = $db->execute();
        $obsloteFolders = ['/plugins/system/cgsecure', '/plugins/authentication/cgsecure','/plugins/user/cgsecure','/libraries/cgsecure','/media/com_cgsecure'];
        // Remove plugins' files.
        foreach ($obsloteFolders as $folder) {
            $f = JPATH_SITE . $folder;

            if (!@file_exists($f) || !is_dir($f) || is_link($f)) {
                continue;
            }

            Folder::delete($f);
        }
        Factory::getApplication()->enqueueMessage('CG Secure package uninstalled', 'notice');
    }
}
