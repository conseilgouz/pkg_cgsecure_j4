<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Controller;

\defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\MVC\Controller\AdminController;

class LogsController extends AdminController
{
    public function __construct($config = array())
    {
        parent::__construct($config = array());
    }

    public function getModel($name = 'Logs', $prefix = 'CGSecureModel', $config = array('ignore_request' => true))
    {
        $model = parent::getModel($name, $prefix, $config);

        return $model;
    }
}
