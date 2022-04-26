<?php
/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
namespace ConseilGouz\Component\CGSecure\Administrator\View\Config;
defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
 
/**
 * Config View
 */
class HtmlView extends BaseHtmlView 
{
    protected $form;
    protected $pagination;
    protected $state;
	protected $item;
	
    /**
     * Items view display method
     * 
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * 
     * @return  mixed  A string if successful, otherwise a JError object.
     */
    function display($tpl = null)
    {
        // Check for errors.
        if (count($errors = $this->get('Errors')))
        {
            Factory::getApplication()->enqueueMessage(implode("\n", $errors), 'error');
            return false;
        }
        // $app = Factory::getApplication(); 
        $model       = $this->getModel();
        
        $this->form    = $this->get('Form');
		$this->formControl = $this->form ? $this->form->getFormControl() : null;	
		
        // Set the toolbar
        $this->addToolBar();
		// $this->sidebar = HtmlSidebar::render();
        // Display the template
        parent::display($tpl);
    }

    /**
     *  Add Toolbar to layout
     */
    protected function addToolBar() 
    {
        $canDo = ContentHelper::getActions('com_cgsecure');

        ToolBarHelper::title(Text::_('CGSECURE_CONFIG'));

        if ($canDo->get('core.admin'))
        {
            ToolbarHelper::preferences('com_cgsecure');
        }

        ToolbarHelper::apply('config.apply');

    }
}