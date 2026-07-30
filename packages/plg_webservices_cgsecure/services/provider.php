<?php
/**
 * @component     Plugin WebServices CG Secure
 * @license https://www.gnu.org/licenses/gpl-3.0.html GNU/GPL
 * @copyright (C) 2026 ConseilGouz. All Rights Reserved.
 * @author ConseilGouz
**/

defined('_JEXEC') || die;

use ConseilGouz\Plugin\WebServices\CGSecure\Extension\Cgsecure;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function register(Container $container)
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$pluginsParams = (array) PluginHelper::getPlugin('webservices', 'cgsecure');
				$dispatcher    = $container->get(DispatcherInterface::class);
				$plugin        = new Cgsecure($dispatcher, $pluginsParams);

				// Joomla 4.2 and later
				if (method_exists($plugin, 'setApplication'))
				{
					$plugin->setApplication(Factory::getApplication());
				}

				return $plugin;
			}
		);
	}
};
