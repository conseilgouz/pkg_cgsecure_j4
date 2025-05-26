<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\Rule;

\defined('JPATH_PLATFORM') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormRule;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

class IpRule extends FormRule
{
    public function test(\SimpleXMLElement $element, $value, $group = null, ?Registry $input = null, ?Form $form = null)
    {
        if (filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($this->check_rejected($value)) {
                $element->addAttribute('message', Text::sprintf('CG_IPCHECK_ALREADY_REJECTED', $value));
                return false;
            }
            return $value;
        }
        $element->addAttribute('message', Text::sprintf('CGSECURE_BAD_IP', $value));
        return false;
    }
    // Check if already in Rejected IPs list
    private static function check_rejected($ip)
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select($db->quoteName('ip'))
                ->from($db->quoteName('#__cg_rejected_ip'))
                ->where($db->quoteName('ip') . ' = :ip')
                ->bind(':ip', $ip, \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query);
        try {
            $found = $db->loadResult();
        } catch (\RuntimeException $e) {
            return array();
        }
        return $found;
    }

}
