<?php
/**
 * @component     CG Secure
 * Version			: 2.1.5
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 * @copyright (C) 2022 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz 
**/
defined('_JEXEC') or die('Restricted access');

class JFormRuleCountry extends JFormRule
{
    private $errorMsg = '';
    private $country_names;
    public function test(SimpleXMLElement $element, $value, $group = null, Joomla\Registry\Registry $input = null, JForm $form = null) {
        $arr = explode(',',$value); // get message
        $this->country_names = json_decode(
            file_get_contents("http://country.io/names.json")
            , true);

        foreach ($arr as $country) {
			if (!$this->get_name($country)) {
			    $element->addAttribute('message', JText::sprintf('CGSECURE_BAD_COUNTRY_CODE',$country));
		      return false;
			}
        }
        return $value;
	}

	function get_name($cc) {
	    return $this->country_names[$cc];
	}
	
}
