<?php

namespace Webkul\UVDesk\AppBundle\Abstracts;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Webkul\UVDesk\AppBundle\Event\ApplicationEvent;
use Webkul\UVDesk\CoreBundle\Entity\ECommerceChannel;

abstract class ECommerceSubscriberDependency
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function loadApplicationChannelCollection(ApplicationEvent $event)
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $ecommerceChannelRepository = $entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel');
        $platformChannelCollection = $ecommerceChannelRepository->getECommercePlatformChannelCollection($event->getApplication());

        $event->addEventResponse(['content' =>  $platformChannelCollection]);
    }

    public function saveApplication(ApplicationEvent $event)
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');

        $eventData = $event->getEventData();
        $application = $event->getApplication();

        $request = $eventData['request'];
        $session = $request->getSession();
        if(!empty($request)) {
            $channelId = $request->attributes->get('channelId') ?  : ($session->get($request->attributes->get('applicationRouteName') . '-channeId'));
            if($session->get($request->attributes->get('applicationRouteName') . '-channeId')) {
                $session->remove($request->attributes->get('applicationRouteName') . '-channeId');
            }
        }

        $channelDetails = !empty($eventData['channelDetails']) ? $eventData['channelDetails'] : [];
        $channelDetails = array_merge($request->request->all(), $channelDetails);

        if (!empty($channelId)) {
            $ecommerceChannelRepository = $entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel');
            $ecommerceChannel = $ecommerceChannelRepository->getECommercePlatformChannel($channelId, $application);
        } else {
            // Default to new channel
            $ecommerceChannel = new ECommerceChannel();
            $ecommerceChannel->setIsActive(true);
            $ecommerceChannel->setApplication($application);
        }

        // Update Channel Details
        $channelTitle = !empty($channelDetails['title']) ? $channelDetails['title'] : ($application->getName() . ' Channel');
        $ecommerceChannel->setTitle($channelTitle);
        unset($channelDetails['title']);

        $ecommerceChannel->setDetails($this->container->get('uvdesk.service')->objectSerializer($channelDetails, []));
        $entityManager->persist($ecommerceChannel);
        $entityManager->flush();

        // Raise Notification Message
        $event->raiseSessionMessage('success', !empty($channelId) ? 'Application details updated successfully.' : 'Application details saved successfully.');
    }

    public function removeApplicationChannel(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $request = $eventData['request'];
        $channelId = $eventData['channelId'];

        $company = $this->container->get('user.service')->getCompany();
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $ecommerceChannelRepository = $entityManager->getRepository('WebkulAppBundle:ECommerceChannel');
        $ecommerceChannel = $ecommerceChannelRepository->findOneBy([
            'company' => $company,
            'id' => $channelId,
            'application' => $event->getApplication(),
        ]);

        if (!empty($ecommerceChannel)) {
            $entityManager->remove($ecommerceChannel);
            $entityManager->flush();

            $event->addEventResponse([
                'alertClass' => 'success',
                'alertMessage' => $this->translate('Channel removed successfully.'),
            ]);
        } else {
            $event->addEventResponse([
                'alertClass' => 'danger',
                'alertMessage' => $this->translate('An unexpected error occurred while processing your request. Please try again later.'),
            ]);
        }
    }

    public function uninstallApplication(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $applicationUser = !empty($eventData['applicationUser']) ? $eventData['applicationUser'] : null;

        if (!empty($applicationUser) && $applicationUser instanceof \Webkul\AppBundle\Entity\ApplicationUser) {
            $entityManager = $this->container->get('doctrine.orm.entity_manager');

            $application = $applicationUser->getApplication();
            $applicationChannelCollection = $entityManager->getRepository('WebkulAppBundle:ECommerceChannel')->findBy([
                'company' => $this->container->get('user.service')->getCurrentCompany(),
                'application' => $application,
            ]);

            if (!empty($applicationChannelCollection)) {
                foreach($applicationChannelCollection as $applicationChannel) {
                    $entityManager->remove($applicationChannel);
                }
            }

            $entityManager->flush();
        }
    }

    /**
    * translate message
    */
    protected function translate($msg, Array $options = [])
    {
        return $this->container->get('translator.default')->trans($msg, $options);
    }

    /* convert date/date string*/
    protected function formatDateToUtc($date)
    {
        if(gettype($date) == 'string') {
            $date = new \DateTime($date);
        }
        if(!$date) {
            return $this->translate('N/A');
        } else {
            $date->setTimezone(new \DateTimeZone('UTC'));

            return $date->format('Y-m-d H:i:s');
        }
    }
}

?>
