<?php

namespace Webkul\UVDesk\AppBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Webkul\UVDesk\AppBundle\DependencyInjection\UVDeskAppExtension;

class UVDeskAppBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new UVDeskAppExtension();
    }
}
