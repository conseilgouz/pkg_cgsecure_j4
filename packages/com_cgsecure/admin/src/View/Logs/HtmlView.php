<?php
/**
 * @component     CG Secure
 * Version			: 3.0.11
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2024 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Logs;

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

    public function display($tpl = null)
    {

        // Get data from the model
        $this->form	= $this->get('Form');
        $this->items = $this->get('Items');
        $this->state = $this->get('State');
        $this->canDo = ContentHelper::getActions('com_cgsecure');
        $this->pagination = $this->get('Pagination');  // 4.0
        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolBarHelper::title(Text::_('COM_CGSECURE_LOGS'), 'logs');
        if ($this->canDo->get('core.delete')) {
            ToolBarHelper::deleteList('COM_CGSECURE_DELETE', 'logs.delete');
        }
    }
}
