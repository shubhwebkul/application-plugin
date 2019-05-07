<?php

namespace Webkul\UVDesk\AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Webkul\UVDesk\CoreBundle\Entity\ECommerceOrder;
use Webkul\UVDesk\CoreBundle\Event\ApplicationEvent;
use Webkul\UVDesk\CoreBundle\Extras\Snippet\TwigConfiguration;
use Webkul\UVDesk\CoreBundle\EventDispatcher\AppEventDispatcher;

class ECommerce extends Controller
{
    public function retrieveTicketOrder(Request $request, $ticketId)
    {
        $response = [];

        if ($request->isXmlHttpRequest()) {
            $data = json_decode($request->getContent(), true);
            $orderId = $data['orderId'];
            $channelId = $data['channelId'];

            if (isset($orderId) && ($orderId || 0 === (int)$orderId) && !empty($channelId)) {
                $entityManager = $this->getDoctrine()->getManager();

                // Validate User Access Level For Ticket
                $ticket = $entityManager->getRepository('UVDeskCoreBundle:Ticket')->findOneBy(['id' => $ticketId]);

                // Check if the channel exists
                $ecommerceChannel = $entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel')->findOneBy(['id' => $channelId]);

                if (empty($ecommerceChannel) || !($ecommerceApplication = $ecommerceChannel->getApplication())) {
                  $response['alertClass'] = 'success';
                  $response['alertMessage'] = 'Warning! Requested an invalid channel.';
                  return new Response(json_encode($response));
                }
                // Load Event Dispatcher and Event Subscriber
                $dispatcher = new AppEventDispatcher($ecommerceApplication, $this->container);
                $event = new ApplicationEvent($ecommerceApplication);

                // Attach and dispatch event
                $event->addEventData([
                    'orderId' => $orderId,
                    'ecommerceChannel' => $ecommerceChannel,
                    'channelDetails' => json_decode($ecommerceChannel->getDetails(), true),
                ]);
                $dispatcher->dispatch(ApplicationEvent::ROUTINE_APPLICATION_RETRIEVE_ORDER, $event);
                $eventResponse = $event->getEventResponse();

                if (!$event->isPropagationStopped()) {
                    // Retrieve any existing ticket order else create one
                    $ecommerceOrder = $entityManager->getRepository('UVDeskCoreBundle:ECommerceOrder')->findOneBy(['ticket' => $ticket->getId()]);
                    if (empty($ecommerceOrder)) {
                        $orderExistsFlag = 1;
                        $ecommerceOrder = new ECommerceOrder();
                    }

                    // Set ECom. Order Details
                    $ecommerceOrder->setTicket($ticket);
                    $ecommerceOrder->setOrderId(!empty($eventResponse['collectedOrders']['validOrders']) ? implode(', ', $eventResponse['collectedOrders']['validOrders']) : $orderId);
                    $ecommerceOrder->setEcommerceChannel($ecommerceChannel);
                    $ecommerceOrder->setOrderData(json_encode($eventResponse['orderDetails']));

                    // Persist Ticket Order and Flush
                    $entityManager->persist($ecommerceOrder);
                    $entityManager->flush();

                    // Response Message
                    if (empty($eventResponse['collectedOrders']['invalidOrders'])) {
                      $responseMessage = $this->get('translator')->trans(!empty($orderExistsFlag) ? 'Success! Order updated successfully.' : 'Success! Order Added to ticket.');
                    } else {
                      $responseMessage = $this->translate('Warning! Unable to retrieve order details for order #%invalidOrder%', ['%invalidOrder%' => implode(', #', $eventResponse['collectedOrders']['invalidOrders'])]);
                    }

                    // DO NOT PERSIST AFTERWARDS!!!
                    if (!empty($ecommerceOrder)) {
                      $applicationServiceContainer = $this->get('application.service');
                      $modifiedOrderDetails = $applicationServiceContainer->convertOrderTimeDetails($ecommerceOrder->getOrderData());
                      if (!empty($modifiedOrderDetails)) {
                        $ecommerceOrder->setOrderData($modifiedOrderDetails);
                      }
                    }

                    // Setup Response
                    $response = [
                      'success' => true,
                      'orderDetails' => json_decode($ecommerceOrder->getOrderData(), true),
                      'alertClass' => empty($eventResponse['collectedOrders']['invalidOrders']) ? 'success' : 'danger',
                      'alertMessage' => $responseMessage,
                    ];

                    if (!empty($eventResponse['collectedOrders']['validOrders'])) {
                      $response['collectedOrders'] = implode(', ', $eventResponse['collectedOrders']['validOrders']);
                    }
                } else {
                    $responseMessage = (!empty($eventResponse['propagationMessage']) ? $eventResponse['propagationMessage'] : 'Warning! Unable to retrieve order details.'
                    );
                    $response['alertClass'] = 'danger';
                    $response['alertMessage'] = $this->get('translator')->trans($responseMessage);
                }
            }
        }

        return new JsonResponse($response);
    }
}
