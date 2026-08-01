<?php

/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

\defined('_JEXEC') or die;

class DisplayController extends BaseController
{
    /**
     * The default view for the display method.
     *
     * @var    string
     * @since  3.0
     */
    protected $default_view = 'secure';

    public function display($cachable = false, $urlparams = false)
    {
        $input = Factory::getApplication()->getInput();
        $view = $input->getCmd('view', 'secure');
        $input->set('view', $view);
        $input->set('layout', 'default');
        $cachable = (bool)$this->app->getConfig()->get('caching');
        parent::display(false, $urlparams);
        return $this;

        //return $this->secure();
    }

}
