<?php
/**
 * @component     Plugin Contact CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2025 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

namespace Conseilgouz\Plugin\Contact\CGSecure\Extension;

// No direct access.
defined('_JEXEC') or die();
use Joomla\CMS\Event\Contact\SubmitContactEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use ConseilGouz\CGSecure\Cgipcheck;

final class Cgsecure extends CMSPlugin implements SubscriberInterface
{
    public $myname = 'ContactCGSecure';
    public $mymessage = 'Joomla Contact : wrong language detected : ';
    public $errtype = 'w';	 // warning
    public $cgsecure_params;
    public function __construct($subject, $config)
    {
        parent::__construct($subject, $config);

        $this->cgsecure_params = Cgipcheck::getParams();
    }
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onSubmitContact' => 'submitContact'
                ];
    }

    // Check IP on prepare Forms
    public function submitContact(SubmitContactEvent $event) // ($contact, $data)
    {
        $contact = $event[0];
        $data = $event[1];
        $message = $data['contact_message'];
        $prefixe = $_SERVER['SERVER_NAME'];
        $prefixe = substr(str_replace('www.', '', $prefixe), 0, 2);
        $this->mymessage = $prefixe.$this->errtype.'-'.$this->mymessage;

        Cgipcheck::check_language($this, $contact, $message);
    }
}
