<?php

namespace Webkul\UVDesk\AppBundle\Services;

use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Webkul\UVDesk\CoreBundle\Entity\Ticket;
use Webkul\UVDesk\CoreBundle\Entity\Application;

class ApplicationService
{
    private $request;
    private $container;
    private $entityManager;

    public function __construct(ContainerInterface $container, RequestStack $request, EntityManager $entityManager)
    {
        $this->request = $request;
        $this->container = $container;
        $this->entityManager = $entityManager;
    }

    public function getTicketGyphIcons()
    {
        $response = null;
        $userService = $this->container->get('user.service');

        $validGlyphs = [];
        $validGlyphs[] = 'ecommerce';

        $response = $this->container->get('templating')->render('@UVDeskCore/Snippets/glyphSnippet.html.twig', [
          'validGlyphs' => $validGlyphs,
        ]);

        return $response;
    }

    /**
    * generate snippet(html, js) for apps, return snippet willbe appended to ticketView Page
    */
    public function getTicketPageAppSnippet()
    {
        $response = null;
        $userService = $this->container->get('user.service');

        $request = $this->request->getCurrentRequest();
        $ticketId = $request->attributes->get('ticketId');
        $ticket = $this->entityManager->getRepository('UVDeskCoreBundle:Ticket')->findOneBy([
          'id' => $ticketId,
        ]);

        // for ecommerce snippet, comment line below to disable ecommerce snippet
        $response .= $this->getECommerceAppSnippet($ticket);

        return $response;
    }

    /**
    * returns ecommerce app(s) snippet containing html, js for ticket view page
    */
    private function getECommerceAppSnippet(Ticket $ticket)
    {
        $ticketOrder = $this->entityManager->getRepository('UVDeskCoreBundle:ECommerceOrder')->findOneBy([
            'ticket' => $ticket->getId(),
        ]);

        if ($ticketOrder) {
          // $ticketOrder->getOrderData($ticketOrder->getOrderData() ? json_decode($ticketOrder->getOrderData(), true) : null);
        }

        // // DO NOT PERSIST AFTERWARDS!!!
        // if (!empty($ticketOrder)) {
        //     $modifiedOrderDetails = $this->convertOrderTimeDetails($ticketOrder->getOrderData());
        //     if (!empty($modifiedOrderDetails)) {
        //         $ticketOrder->setOrderData($modifiedOrderDetails);
        //     }
        // }

        $ecommerceChannels = $this->entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel')->findBy([
            'isActive' => 1,
        ]);

        return $this->container->get('templating')->render('@UVDeskCore/Snippets/ecommerceSnippet.html.twig', [
          'id' => $ticket->getId(),
          'ticketOrder' => $ticketOrder,
          'ecommerceChannels' => $ecommerceChannels,
        ]);
    }

    public function getApplicationName()
    {
        $pathInformationArray = explode('/', $this->request->getMasterRequest()->getPathInfo());
        $pathInformationLength = sizeof($pathInformationArray);

        return $pathInformationArray[$pathInformationLength - 1];
    }

    public function getApplicationByRouteName($applicationRouteName)
    {
        $application = $this->entityManager->getRepository('UVDeskCoreBundle:Application')->findOneBy([
            'name' => str_replace('-', ' ', ucwords($applicationRouteName))
        ]);

        return !empty($application) ? $application : null;
    }

    public function getAppScreenshots(Application $application)
    {
        $screenshots = $this->entityManager->getRepository('UVDeskCoreBundle:ApplicationScreenshot')->findBy([
            'application' => $application,
        ]);

        return $screenshots;
    }

    public function installApplication(Application $application)
    {
        $application = $application->setIsInstalled(1);
        $this->entityManager->persist($application);
        $this->entityManager->flush();

        return $application;
    }

    /**
    * Parses DateTime fields and localize them to logged in user session timezone
    */
    public function convertOrderTimeDetails($orderString)
    {
        $orderArray = json_decode($orderString, true);

        if (!empty($orderArray['orders'])) {
            $optedTimezone = 'Asia/Kolkata';
            $currentUser = $this->container->get('user.service')->getCurrentUser();

            foreach ($orderArray['orders'] as $itemIndex => $orderItem) {
                foreach ($orderItem as $orderItemIndex => $orderItemInstance) {
                    if (in_array($orderItemIndex, ['order_details', 'payment_details', 'shipping_details'])) {
						if(!empty($orderItemInstance) ) {
							foreach ($orderItemInstance as $itemFieldIndex => $itemFieldValue) {
								if(gettype($itemFieldValue) == 'string') {
									$dateTime = \DateTime::createFromFormat('Y-m-d H:i:s', $itemFieldValue, new \DateTimeZone('UTC'));
									$dateTimeErrors = \DateTime::getLastErrors();

									if ($dateTime !== false && empty($dateTimeErrors['warning_count']) && empty($dateTimeErrors['error_count'])) {
                                        if (!empty($optedTimezone)) {
                                            $dateTime->setTimeZone(new \DateTimeZone($optedTimezone));
                                        }
										$orderArray['orders'][$itemIndex][$orderItemIndex][$itemFieldIndex] = $dateTime->format($optedTimezone);
									}
								}
							}
						}
                    }
                }
            }

            return json_encode($orderArray);
        }

        return null;
    }

}
