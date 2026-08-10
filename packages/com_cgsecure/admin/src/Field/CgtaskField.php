<?php

namespace ConseilGouz\Component\CGSecure\Administrator\Field;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\String\StringHelper;
use ConseilGouz\CGSecure\Helper\CGSecureHelper;

// Prevent direct access
defined('_JEXEC') || die;

class CgtaskField extends FormField
{
	/**
	 * Element name
	 *
	 * @var   string
	 */
	protected $_name = 'cgtask';

	function getInput()
	{
		$return = '';
        $taskstatus   = CGSecureHelper::getTaskStatus();
		// Load language
		$return .= $taskstatus;

		return $return;
	}
	public function def($val, $default = '')
	{
	    return ( isset( $this->element[$val] ) && (string) $this->element[$val] != '' ) ? (string) $this->element[$val] : $default;
	}
	
}
