<?php
/**
 * CG Secure Component for Joomla 4.x/5.x
 *
 * @author     ConseilgGouz
 * @copyright (C) 2025 www.conseilgouz.com. All Rights Reserved.
 * @license    GNU/GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 */

namespace ConseilGouz\Component\CGSecure\Administrator\Field;

// Prevent direct access
defined('_JEXEC') || die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\String\StringHelper;

class VersionField extends FormField
{
    /**
     * Element name
     *
     * @var   string
     */
    protected $_name = 'Version';

    public function getInput()
    {
        $return = '';
        // Load language
        $extension = $this->def('extension');

        $version = '';

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('element') . '= :ext')
            ->bind(':ext', $extension, \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query, 0, 1);
        $row = $db->loadAssoc();
        $tmp = json_decode($row['manifest_cache']);
        $version = $tmp->version;

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $css = '';
        $css .= ".version {display:block;text-align:right;color:brown;font-size:12px;}";
        $css .= ".readonly.plg-desc {font-weight:normal;}";
        $css .= "fieldset.radio label {width:auto;}";
        $wa->addInlineStyle($css);
        $margintop = $this->def('margintop');
        if (StringHelper::strlen($margintop)) {
            $js = "document.addEventListener('DOMContentLoaded', function() {
			vers = document.querySelector('.version');
			parent = vers.parentElement.parentElement;
			parent.style.marginTop = '".$margintop."';
			})";
            $wa->addInlineScript($js);
        }
        $return .= '<span class="version">' . Text::_('JVERSION') . ' ' . $version . "</span>";

        return $return;
    }
    public function def($val, $default = '')
    {
        return (isset($this->element[$val]) && (string) $this->element[$val] != '') ? (string) $this->element[$val] : $default;
    }

}
