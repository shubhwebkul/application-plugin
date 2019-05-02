<?php

namespace Webkul\UVDesk\CoreBundle\Package;

use Webkul\UVDesk\PackageManager\Composer\ComposerPackage;
use Webkul\UVDesk\PackageManager\Composer\ComposerPackageExtension;

class Composer extends ComposerPackageExtension
{
    public function loadConfiguration()
    {
        ($composerPackage = new ComposerPackage(new UVDeskCoreConfiguration()))
            ->movePackageConfig('apps', 'apps');

        return $composerPackage;
    }
}
