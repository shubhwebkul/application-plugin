<?php

namespace Webkul\UVDesk\AppBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Webkul\UVDesk\PackageManager\Extensions as UVDeskPackageExtensions;
use Webkul\UVDesk\PackageManager\ExtensionOptions as UVDeskPackageExtensionOptions;

class UVDeskAppExtension extends Extension
{
    public function getAlias()
    {
        return 'uvdesk_apps';
    }

    public function getConfiguration(array $configs, ContainerBuilder $container)
    {
        return new Configuration();
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }
}
