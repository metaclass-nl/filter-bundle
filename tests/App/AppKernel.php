<?php

namespace Metaclass\FilterBundle\Tests\App;

use ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Metaclass\FilterBundle\MetaclassFilterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class AppKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new ApiPlatformBundle(),
            new MetaclassFilterBundle(),
        ];
    }

    /**
     * @param RouteCollectionBuilder|RoutingConfigurator $routes
     */
    protected function configureRoutes($routes): void
    {

    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->setParameter('kernel.project_dir', __DIR__);

        $loader->load(__DIR__ . '/config/config.yml');
    }
}