<?php
/*
 * @package   panopticon
 * @copyright Copyright (c)2023-2026 Nikolaos Dionysopoulos / Akeeba Ltd
 * @license   https://www.gnu.org/licenses/agpl-3.0.txt GNU Affero General Public License, version 3 or later
 */

namespace ConseilGouz\Plugin\WebServices\CGSecure\Extension;

defined('_JEXEC') || die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\ApiRouter;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Router\Route;

class Cgsecure extends CMSPlugin implements SubscriberInterface
{
	private const API_PREFIX = 'v1/cgsecure/';

	protected $allowLegacyListeners = false;

	/** @inheritDoc */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeApiRoute' => 'registerRoutes',
		];
	}

	public function registerRoutes(Event $event): void
	{
		/** @var ApiRouter $router */
		[$router] = array_values($event->getArguments());

		$defaults = [
			'component' => 'com_cgsecure',
			'public' => true
		];

		$routes = [];

		$routes[] = new Route(
			['GET'],
			self::API_PREFIX . 'secure',
			'cgsecure.secure',
			[],
			$defaults
		);

		$router->addRoutes($routes);
	}
}
