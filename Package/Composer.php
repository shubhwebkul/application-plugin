<?php

namespace Webkul\UVDesk\AppBundle\Package;

use Webkul\UVDesk\PackageManager\Composer\ComposerPackage;
use Webkul\UVDesk\PackageManager\Composer\ComposerPackageExtension;

class Composer extends ComposerPackageExtension
{
    public function loadConfiguration()
    {
        ($composerPackage = new ComposerPackage(new UVDeskAppConfiguration()))
            ->movePackageConfig('apps', 'apps')
            ->combineProjectConfig('config/packages/twig.yaml', 'Templates/twig.yaml')
            ->movePackageConfig('config/routes/uvdesk_apps.yaml', 'Templates/uvdesk_apps.yaml');

        return $composerPackage;
    }
}
