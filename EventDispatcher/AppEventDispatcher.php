<?php

namespace Webkul\UVDesk\AppBundle\EventDispatcher;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

use Webkul\UVDesk\AppStore;

class AppEventDispatcher extends EventDispatcher implements EventDispatcherInterface
{
    public function __construct($application, ContainerInterface $container)
    {
        switch ($application->getName()) {
            case 'Shopify':
                $this->addSubscriber(new AppStore\Ecommerce\Shopify\EventSubscriber\ShopifySubscriber($container));
                break;
            case 'EBay':
                $this->addSubscriber(new AppStore\Ecommerce\Shopify\EventSubscriber\EBaySubscriber($container));
                break;
            default:
                break;
        }
    }
}
