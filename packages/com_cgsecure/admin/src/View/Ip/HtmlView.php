<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Ip;

// No direct access
\defined('_JEXEC') or die;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView {

    protected $form;
    protected $pagination;
    protected $state;
	protected $item;

    /**
     * Display the view
     */
    public function display($tpl = null) {

        $model       = $this->getModel();
        $this->form		= $this->get('Form');
		$this->item		= $this->get('Item');
		$this->formControl = $this->form ? $this->form->getFormControl() : null;
		$this->page_params  = new Registry($this->item->page_params);
	
        $this->addToolbar();

        // $this->sidebar = JHtmlSidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     */
    protected function addToolbar() {
        $state = $this->get('State');
        $input = Factory::getApplication()->input;
        $canDo = ContentHelper::getActions('com_cgsecure');

		$user		= Factory::getApplication()->getIdentity();
		$userId		= $user->id;
		if (!isset($this->item->id)) $this->item->id = 0;
		$isNew		= ($this->item->id == 0);

		ToolBarHelper::title($isNew ? Text::_('COM_CGSECURE_IP_NEW') : Text::_('COM_CGSECURE_IP_EDIT'), '#xs#.png');

		// If not checked out, can save the item.
		if ($canDo->get('core.edit')) {
			ToolBarHelper::apply('ip.apply');
			ToolBarHelper::save('ip.save');
		}
		ToolBarHelper::cancel('ip.cancel', 'JTOOLBAR_CLOSE');
		ToolbarHelper::inlinehelp();
    }

}
