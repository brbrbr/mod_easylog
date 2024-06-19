<?php

/**
 * @package  mod_easylog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The module C2PDF service provider.
 *
 * @since  4.4
 */
return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.4
     */
    public function register(Container $container): void
    {

        $container->registerServiceProvider(new ModuleDispatcherFactory('\\Brambring\\Module\\Easylog'));
        $container->registerServiceProvider(new HelperFactory('\\Brambring\\Module\\Easylog\\Administrator\\Helper'));
        $container->registerServiceProvider(new Module());
    }
};
