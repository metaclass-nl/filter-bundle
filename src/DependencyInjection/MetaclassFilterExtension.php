<?php

namespace Metaclass\FilterBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class MetaclassFilterExtension extends Extension
{
    /**
     * {@inheritDoc}
     * @return void
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
//        $configuration = new Configuration();
//        $config = $this->processConfiguration($configuration, $configs);
//        $container->setParameter('metaclass_auth_guard.db_connection.name', $config['db_connection']['name']);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));

        $loader->load('services.yaml');
    }
}
