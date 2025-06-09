<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Rule;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;

class WhitelistRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, Registry $input = null, Form $form = null)
    {

        if ($value == "") {
            return $value;
        }
        $ips = str_replace(" ", "", $value);
        $ips = preg_replace("/(?![a-fA-F0-9.,:]])/", "", $ips);
        if ($ips != $value) {
            $element->addAttribute('message', Text::sprintf('CGSECURE_BAD_WHITELIST', $value));
            return false;
        }
        $arr = explode(',', $ips);
        $err = "";
        foreach ($arr as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
                continue;
            $err = $err == "" ? $ip : ','.$ip;
        }
        if ($err) {
            $element->addAttribute('message', Text::sprintf('CGSECURE_WHITELIST_ERR', $err));
            return false;
        }
            
        return $value;
    }
}
