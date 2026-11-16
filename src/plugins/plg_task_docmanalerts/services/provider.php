<?php

/**
 * @package    Kunena.Plugin
 * @license    GNU General Public License version 3 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Kunena\Plugin\Task\DocmanAlerts\Extension\DocmanAlerts;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new DocmanAlerts(
                (array) PluginHelper::getPlugin('task', 'docmanalerts'),
                $container->get(MailerFactoryInterface::class)
            );
            $plugin->traitSetDispatcher($container->get(DispatcherInterface::class));
            $plugin->setApplication(Factory::getApplication());
            $plugin->setDatabase($container->get('DatabaseDriver'));

            return $plugin;
        });
    }
};
