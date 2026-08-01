<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Site\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;

/**
 * Routing class from com_cgisotope
 *
 * @since  3.9.0
 */
class Router extends RouterView
{
    /**
     * CG ISotope Component router constructor
     *
     * @param   CMSApplication  $app   The application object
     * @param   AbstractMenu    $menu  The menu object to work with
     *
     * @since   Joomla 3.9.0
     */
    public function __construct($app = null, $menu = null)
    {
        $page = new RouterViewConfiguration('secure');
        $page->setKey('sec');
        $page->setKey('t');
        $page->setKey('m');
        $page->setKey('req');
        $this->registerView($page);
        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}
