<?php
/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Controller;
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Factory;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Language\Text;

class LogsController extends AdminController
{
	function __construct($config = array()) {
		parent::__construct($config = array());
	}

	public function getModel($name = 'Logs', $prefix = 'CGSecureModel', $config = array('ignore_request' => true)) {
		$model = parent::getModel($name, $prefix, $config);

		return $model;
	}
}
?>
