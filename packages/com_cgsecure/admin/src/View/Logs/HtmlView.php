<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Logs;

defined('_JEXEC') or die('Restricted access');
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
        $model = $this->getModel();
        $this->items = $model->getItems();
        $this->state = $model->getState();
        $this->canDo = ContentHelper::getActions('com_cgsecure');
        $this->pagination = $model->getPagination();
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
