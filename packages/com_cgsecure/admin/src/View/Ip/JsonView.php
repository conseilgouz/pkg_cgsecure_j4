<?php
/**
 * @component     CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace ConseilGouz\Component\CGSecure\Administrator\View\Ip;

defined('_JEXEC') or die('Restricted access');
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use ConseilGouz\CGSecure\Cgipcheck;

/**
 * Config View
 */
class JsonView extends AbstractView
{
    protected $app;
    /**
     * AJAX Request
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  mixed  A string if successful, otherwise a JError object.
     */
    public function display($tpl = null)
    {
        Session::checkToken('get') or die(Text::_('JINVALID_TOKEN'));
        // Check for errors.
        $this->app = Factory::getApplication();
        $input = Factory::getApplication()->getInput();
        $type = $input->get('type');
        $msg = "";
        if ($type == 'check') {
            $ip = $input->get('ip');
            if ($this->check_rejected($ip)) {
                $msg = "Error : ".Text::_('CG_IPCHECK_ALREADY_REJECTED');
                $arr['retour'] = $msg;
                echo new JsonResponse($arr);
                return;
            }
            $infos = Cgipcheck::abuseIPDBrequest('check', 'GET', [ 'ipAddress' => $ip, 'maxAgeInDays' => 30, 'verbose' => true ]);
            if (isset($infos->errors)) {
                $msg = "Error : ".$infos->errors[0]->detail;
            } elseif (isset($infos->data)) {
                $msg = "Country : ";
                $msg .= $infos->data->countryCode ? $infos->data->countryCode : 'unknown';
                $msg .= ", WhiteList : ";
                $msg .= ($infos->data->isWhiteListed) ? 'true' : 'false';
                $msg .= ', Score : '.$infos->data->abuseConfidenceScore;
                $msg .= ', IsTor : ';
                $msg .= ($infos->data->isTor) ? 'true' : 'false';
                $msg .= ", Report : ".$infos->data->totalReports.' time(s)';
            } else {
                $msg = 'index.php?option=com_cgsecure&view=ip&tmpl=component&type='.$ip;
            }
        } else {
            $arr['error'] = Text::_('JINVALID_MESSAGE');
            die(new JsonResponse($arr));
        }
        $arr['retour'] = $msg;
        echo new JsonResponse($arr);
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
