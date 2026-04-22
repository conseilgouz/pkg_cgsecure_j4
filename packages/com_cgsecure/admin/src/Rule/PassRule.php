<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Rule;

defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

class PassRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {

        if (!empty($value)) {
            $nHits = preg_match_all('/[%&+]/', $value, $imatch);
            if ($nHits > 0) {
                Factory::getApplication()->enqueueMessage(Text::_('CGSECURE_INVALID_SPECIALS'), 'error');
                return false;
            }
        }
        return true;

    }
}
