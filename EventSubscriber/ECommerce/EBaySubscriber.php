<?php

namespace Webkul\UVDesk\AppBundle\EventSubscriber\ECommerce;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

// use Webkul\UserBundle\Entity\User;
// use Webkul\TicketBundle\Lib\Htmlfilter;
// use Webkul\AppBundle\Event\ApplicationEvent;
// use Symfony\Component\HttpFoundation\Request;
// use Webkul\AppBundle\Entity\ECommerceChannel;
// use Webkul\AppBundle\Entity\MessagingChannel;
// use Symfony\Component\HttpFoundation\Response;
// use Symfony\Component\HttpFoundation\JsonResponse;
// use Symfony\Component\HttpFoundation\RedirectResponse;
// use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Webkul\UVDesk\AppBundle\Event\ApplicationEvent;
use Webkul\UVDesk\AppBundle\Modules\EBay as EBayModule;
use Webkul\UVDesk\AppBundle\Abstracts\ECommerceSubscriberDependency;

class EBaySubscriber extends ECommerceSubscriberDependency implements EventSubscriberInterface
{
    use EBayModule\EBayRequestHelper;
    use EBayModule\EBaySubscriberDependency;

    protected $entityManager;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);
        $this->entityManager = $this->container->get('doctrine.orm.entity_manager');
    }

    public static function getSubscribedEvents()
    {
        return ApplicationEvent::getEventCycle('EBay');
    }

    public function loadFormArray(ApplicationEvent $event)
    {
        $formFields = [
            'formFields' => [
                [
                    'type' => 'select',
                    'name' => 'site_code',
                    'label' => 'Site Code',
                    'options' => [
                        '15' => 'Australia',
                        '16' => 'Austria',
                        '123' => 'Belgium',
                        '23' => 'Belgium',
                        '2' => 'Canada',
                        '210' => 'CanadaFrench',
                        '71' => 'France',
                        '77' => 'Germany',
                        '201' => 'Hong',
                        '203' => 'India',
                        '205' => 'Ireland',
                        '101' => 'Italy',
                        '207' => 'Malaysia',
                        '146' => 'Netherlands',
                        '211' => 'Philippines',
                        '212' => 'Poland',
                        '215' => 'Russia',
                        '216' => 'Singapore',
                        '186' => 'Spain',
                        '193' => 'Switzerland',
                        '3' => 'UK',
                        '0' => 'US',
                    ],
                    'info' => 'Select your preferred Country',
                ],
                [
                    'type' => 'password',
                    'name' => 'app_id',
                    'label' => 'App Id',
                    'info' => 'Your EBay Client ID',
                ],
                [
                    'type' => 'password',
                    'name' => 'dev_id',
                    'label' => 'Dev Id',
                    'info' => 'Your EBay Dev ID',
                ],
                [
                    'type' => 'password',
                    'name' => 'cert_id',
                    'label' => 'Cert Id',
                    'info' => 'Your EBay Client Secret',
                ],
                [
                    'type' => 'password',
                    'name' => 'runame',
                    'label' => 'RuName',
                    'info' => 'Your EBay RuName (Redirect URL Name)',
                ],
            ],
        ];

        $event->addEventResponse($formFields);
        return $formFields;
    }

    public function loadTemplateData(ApplicationEvent $event)
    {
        $router = $this->container->get('router');

        $event->addEventResponse([
            'templateData' => [
                'acceptURL' => $router->generate('app_process_application_external_redirect', ['applicationRouteName' => 'ebay'], true),
                'rejectURL' => $router->generate('helpdesk_member_load_application', [
                    'applicationRouteName' => 'ebay',
                ], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ]);
    }

    public function loadApplicationChannelCollection(ApplicationEvent $event)
    {
        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $ecommerceChannelRepository = $entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel');
        // $messagingChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:MessagingChannel');
        $platformChannelCollection = $ecommerceChannelRepository->getECommercePlatformChannelCollection($event->getApplication());

        if (!empty($platformChannelCollection)) {
            $defaultSettings = [
                'order_feedbacks' => false,
                'member_messages' => false,
                'listing_questions' => false,
            ];

            foreach ($platformChannelCollection as $index => $platformChannel) {
                // $messagingChannel = $messagingChannelRepository->findOneBy(['ecommerceChannel' => $platformChannel['id']]);
                $platformChannelCollection[$index]['settings'] = !empty($messagingChannel) ? ($messagingChannel->getDetails() ? json_decode($messagingChannel->getDetails(), true) : $defaultSettings) : $defaultSettings;
            }
        }

        $event->addEventResponse(['content' =>  $platformChannelCollection]);
    }

    public function processApplicationConfiguration(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $submittedFormData = !empty($eventData['request']) ? $eventData['request']->request->all() : [];

        // Validate Form Fields
        $validateFormRequest = true;
        foreach ($this->loadFormArray($event)['formFields'] as $formField) {
            if ($formField['name'] != 'site_code') {
                if (empty($submittedFormData[$formField['name']])) {
                    $validateFormRequest = false;
                    break;
                }
            }
        }

        // Process Configuration Data
        if (true == $validateFormRequest) {
            $channelDetails = [
                'title' => $submittedFormData['title'],
                'site_code' => !empty($submittedFormData['site_code']) ? $submittedFormData['site_code'] : 0,
                'app_id' => $submittedFormData['app_id'],
                'dev_id' => $submittedFormData['dev_id'],
                'cert_id' => $submittedFormData['cert_id'],
                'runame' => $submittedFormData['runame'],
            ];

            $sessionID = $this->getSessionID($channelDetails);
            if (!empty($sessionID)) {
                $channelDetails['session_id'] = $sessionID;

                // Add event data to session for redirect callback
                $event->addEventData(['channelDetails' => $channelDetails]);
                $event->setSessionData('ebay/eventData', $event->getEventData());

                // Delegate response to controller
                $response =  new RedirectResponse($this->getLoginURL($sessionID, $channelDetails['runame']));
                $event->addEventResponse(['response' => $response]);
            } else {
                // Configuration Error
                $event->stopPropagation();
                $event->raiseSessionMessage('warning', 'An unexpected error occurred while connecting with your marketplace. Please check the provided details again.');
            }
        } else {
            // Invalid Form
            $event->stopPropagation();
            $event->raiseSessionMessage('warning', 'An unexpected error occurred while processing your form details. Please check the provided details again.');
        }
    }

    public function saveApplicationChannelSettings(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $application = $event->getApplication();
        $company = $this->container->get('user.service')->getCompany();
        $ecommerceChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel');

        $request = $eventData['request'];
        $session = $request->getSession();

        $submittedFormData = $request->request->all();
        $ecommerceChannel = $ecommerceChannelRepository->findOneBy([
            'id' => $request->attributes->get('channelId'),
            'company' => $company,
            'application' => $application,
        ]);

        if (!empty($ecommerceChannel) && isset($submittedFormData['optedSubscription'])) {
            $optedSubscription = [
                'order_feedbacks' => in_array('od_feeds', $submittedFormData['optedSubscription']) ? true : false,
                'member_messages' => in_array('m2m_messages', $submittedFormData['optedSubscription']) ? true : false,
                'listing_questions' => in_array('seller_iListQuestions', $submittedFormData['optedSubscription']) ? true : false,
                'order_cancellation' => in_array('seller_orderCancellation', $submittedFormData['optedSubscription']) ? true : false,
            ];

            // $messagingChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:MessagingChannel');
            $messagingChannel = $messagingChannelRepository->findOneBy(['ecommerceChannel' => $ecommerceChannel]);

            if (!empty($messagingChannel)) {
                $messagingChannel->setDetails(json_encode($optedSubscription));
            } else {
                // Default to new channel
                $messagingChannel = new MessagingChannel();
                $messagingChannel->setTitle($ecommerceChannel->getTitle());
                $messagingChannel->setIsActive(true);
                $messagingChannel->setCompany($company);
                $messagingChannel->setApplication($application);
                $messagingChannel->setDetails(json_encode($optedSubscription));
                $messagingChannel->setEcommerceChannel($ecommerceChannel);
                $messagingChannel->setLastTicketRefreshedAt(new \DateTime('now'));
            }

            $this->entityManager->persist($messagingChannel);
            $this->entityManager->flush();

            // Subscribe To Notification {Feedback, M2M Messages, ASQ}
            // $subscribedNotificationCollection = $this->getSubscribedNotifications($ecommerceChannel);
            if ($this->subscribeMessagingNotification($optedSubscription, $ecommerceChannel)) {
                // Raise Notification Message
                $event->raiseSessionMessage('success', $this->translate('Channel details updated successfully.'));
            } else {
                $event->stopPropagation();
                $event->raiseSessionMessage('warning', 'An unexpected error occurred while subscribing for notifications. Please try again later.');
            }
        } else {
            $event->stopPropagation();
            $event->raiseSessionMessage('warning', 'An unexpected error occurred while retrieving your channel details. Please try again later.');
        }

        // Clear Channel Session
        $session->remove($request->attributes->get('applicationRouteName') . '-channeId');
    }

    public function handleExternalCallback(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $sessionData = $event->getSessionData('ebay/eventData');

        if (!empty($sessionData['channelDetails']) && !empty($eventData['request'])) {
            $externalRequest = $eventData['request'];
            $channelDetails = $sessionData['channelDetails'];
            $queryParameters = $externalRequest->query->all();

            if (isset($queryParameters['ebaytkn'])) {
                // Request for token has been made. Fetch token here.
                $headerArray = [
                    'X-EBAY-API-APP-NAME' => $channelDetails['app_id'],
                    'X-EBAY-API-DEV-NAME' => $channelDetails['dev_id'],
                    'X-EBAY-API-CERT-NAME' => $channelDetails['cert_id'],
                    'X-EBAY-API-SITEID' => $channelDetails['site_code'],
                ];

                $xmlArray = [
                    'nodeCollection' => [
                        'SessionID' => $channelDetails['session_id'],
                    ],
                ];

                $curlResponse = $this->curlRequest('FetchToken', $headerArray, $xmlArray);
                $xmlResponse = json_decode(json_encode(simplexml_load_string($curlResponse)), true);

                // Check Token Request
                if (!empty($xmlResponse['Ack']) && 'Success' == $xmlResponse['Ack'] && !empty($xmlResponse['eBayAuthToken'])) {
                    $channelDetails['auth_token'] = $xmlResponse['eBayAuthToken'];
                    $event->addEventData(['channelDetails' => $channelDetails]);
                } else {
                    // Request failed. Unable to fetch token.
                    $event->stopPropagation();
                    $event->raiseSessionMessage('warning', 'An unexpected error occurred during the authentication process. Please try again later.');
                }
            } else {
                // Request has not been made for login yet. Integrity fail.
                $event->stopPropagation();
                $event->raiseSessionMessage('warning', 'An unexpected error occurred during the authentication process. Please try again later.');
            }
        } else {
            // Session not maintained.
            $event->stopPropagation();
            $event->raiseSessionMessage('warning', 'An unexpected error occurred while processing your form details. Please check the provided details again.');
        }

        // Clear session data
        $event->removeSessionData('ebay/eventData');
    }

    public function getOrderDetails(ApplicationEvent $event)
    {
        $orderCollection = [];
        $eventData = $event->getEventData();
        $channelDetails = $eventData['channelDetails'];
        $collectedOrders = ['validOrders' => [], 'invalidOrders' => []];
        $requestOrderCollection = array_map('trim', explode(',', $eventData['orderId']));
        // $requestOrderCollection = [];
        // $rawRequestOrderCollection = array_map('trim', explode(',', $eventData['orderId']));

        // foreach ($rawRequestOrderCollection as $requestOrderIncrementId) {
        //     if (!strpos($requestOrderIncrementId, '-')) {
        //         $itemTransactionCollection = $this->getItemTransactionIDs($requestOrderIncrementId, $channelDetails);
        //         if (!empty($itemTransactionCollection)) {
        //             $requestOrderCollection = array_merge($requestOrderCollection, $itemTransactionCollection);
        //         }
        //     } else {
        //         $requestOrderCollection[] = $requestOrderIncrementId;
        //     }
        // }

        // $requestOrderCollection = array_values(array_unique($requestOrderCollection, SORT_REGULAR));

        foreach ($requestOrderCollection as $requestOrderIncrementId) {
            $headerArray = [
                'X-EBAY-API-CALL-NAME' => 'GetOrders',
                'X-EBAY-API-SITEID' => (!empty($channelDetails['site_code'])) ? $channelDetails['site_code'] : 0,
            ];

            $xmlArray = [
                'nodeCollection' => [
                    'OrderIDArray' => '<OrderID>' . $requestOrderIncrementId . '</OrderID>',
                    'RequesterCredentials' => '<eBayAuthToken>' . $channelDetails['auth_token'] . '</eBayAuthToken>'
                ],
            ];

            $curlResponse = $this->curlRequest('GetOrders', $headerArray, $xmlArray);
            $orderResponse = json_decode(json_encode(simplexml_load_string($curlResponse)), true);

            if (!empty($orderResponse['OrderArray']['Order'])) {
                if (strpos($requestOrderIncrementId, '-')) {
                    $responseOrderId = $orderResponse['OrderArray']['Order']['OrderID'] . ' (' . $requestOrderIncrementId . ')';

                    $xmlArray['nodeCollection']['OrderIDArray'] = '<OrderID>' . $orderResponse['OrderArray']['Order']['OrderID'] . '</OrderID>';
                    $curlResponse = $this->curlRequest('GetOrders', $headerArray, $xmlArray);
                    $orderResponse = json_decode(json_encode(simplexml_load_string($curlResponse)), true);
                } else {
                    $responseOrderId = $requestOrderIncrementId;
                }

                // Add to Collection
                $orderCollection[] = [
                    'orderID' => $responseOrderId,
                    'currencyCode' => substr($curlResponse, strpos($curlResponse, 'AmountPaid currencyID') + 23, 3),
                    'order' => $orderResponse['OrderArray']['Order']
                ];
                $collectedOrders['validOrders'][] = $requestOrderIncrementId;
            } else {
                $collectedOrders['invalidOrders'][] = $requestOrderIncrementId;
            }
        }

        if (!empty($orderCollection)) {
            $event->addEventData(['orderCollection' => $orderCollection]);
            $event->addEventResponse(['collectedOrders' => $collectedOrders]);
        } else {
            // Failed to retrieve meaningful data. Stop Propagation
            $event->stopPropagation();
            $event->addEventResponse(['propagationMessage' => $this->translate('Warning! Unable to retrieve order details.')]);
        }
    }

    public function formatOrderDetails(ApplicationEvent $event)
    {
        // Format Data
        $eventData = $event->getEventData();
        $formattedOrderDetails = ['orders' => []];
        $orderCollection = $eventData['orderCollection'];
        $channelDetails = $eventData['channelDetails'];

        foreach ($orderCollection as $orderInstance) {
            $orderId = $orderInstance['orderID'];
            $orderDetails = $orderInstance['order'];
            $currencyCode = $orderInstance['currencyCode'];

            // Order Basics
            $formattedOrderInstance = [
                'id' => $orderId,
                'total_price' => implode(' ', [$currencyCode, $orderDetails['Total']]),
            ];

            if (!empty($orderDetails['RefundArray']['Refund'])) {
                $formattedOrderInstance['total_refund'] = implode(' ', [$currencyCode, $orderDetails['RefundArray']['Refund']['RefundFromSeller']]);
            }

            // Order Information
            $orderPlacedTime = new \DateTime($orderDetails['CreatedTime']);
            $formattedOrderInstance['order_details']['Order Placed'] = $orderPlacedTime->format('Y-m-d H:i:s');
            $formattedOrderInstance['order_details']['Order Status'] = ucwords($orderDetails['OrderStatus']);

            // Payment Information
            $formattedOrderInstance['payment_details']['Payment Status'] = $orderDetails['CheckoutStatus']['Status'];
            $formattedOrderInstance['payment_details']['Payment Method'] = (
                !empty($orderDetails['CheckoutStatus']['PaymentMethod'])
                ? $orderDetails['CheckoutStatus']['PaymentMethod']
                : (
                    !empty($orderDetails['PaymentMethods'])
                    ? $orderDetails['PaymentMethods']
                    : 'NA'
                )
            );
            $formattedOrderInstance['payment_details']['Total Amount Paid'] = implode(' ', [$currencyCode, $orderDetails['AmountPaid']]);
            $formattedOrderInstance['payment_details']['Total Amount Saved'] = implode(' ', [$currencyCode, $orderDetails['AmountSaved']]);

            if (!empty($orderDetails['RefundArray']['Refund'])) {
                $formattedOrderInstance['payment_details']['Refund Amount'] = implode(' ', [$currencyCode, $orderDetails['RefundArray']['Refund']['RefundAmount']]);
                $formattedOrderInstance['payment_details']['Received Refunded Amount'] = implode(' ', [$currencyCode, $orderDetails['RefundArray']['Refund']['TotalRefundToBuyer']]);
            }

            // Shipping Information
            // Shipping Address
            if (!empty($orderDetails['ShippingDetails']['ShippingServiceOptions'])) {
                $shippingDetails = $orderDetails['ShippingDetails']['ShippingServiceOptions'];
                if (!empty($shippingDetails['ShippingServiceCost'])) {
                    // Single Shipping Details
                    $formattedOrderInstance['shipping_details']['Shipping Cost'] = implode(' ', [$currencyCode, $shippingDetails['ShippingServiceCost']]);
                    $formattedOrderInstance['shipping_details']['Shipping Service'] = $shippingDetails['ShippingService'];
                    if (!empty($shippingDetails['ShippingTimeMin'])) {
                        $formattedOrderInstance['shipping_details']['Estimated Shipping Time'] = implode(' - ', [$shippingDetails['ShippingTimeMin'], $shippingDetails['ShippingTimeMax']]) . ' Days';
                    }
                } elseif (!empty($shippingDetails[0]['ShippingServiceCost'])) {
                    // Multiple Shipping Details
                    $tempShippingCost = [];
                    $tempShippingTime = [];
                    $tempShippingService = [];

                    foreach ($shippingDetails as $shippingDetailInstance) {
                        $tempShippingCost[] = implode(' ', [$currencyCode, $shippingDetailInstance['ShippingServiceCost']]);
                        $tempShippingService[] = $shippingDetailInstance['ShippingService'];
                        if (!empty($shippingDetailInstance['ShippingTimeMin'])) {
                            $tempShippingTime[] = implode(' - ', [$shippingDetailInstance['ShippingTimeMin'], $shippingDetailInstance['ShippingTimeMax']]) . ' Days';
                        } else {
                            $tempShippingTime[] = 'N.A.';
                        }
                    }

                    $formattedOrderInstance['shipping_details']['Shipping Cost'] = '[' . implode(', ', $tempShippingCost) . ']';
                    $formattedOrderInstance['shipping_details']['Shipping Service'] = '[' . implode(', ', $tempShippingService) . ']';
                    $formattedOrderInstance['shipping_details']['Estimated Shipping Time'] = '[' . implode(', ', $tempShippingTime) . ']';
                }
            }

            if (!empty($orderDetails['ShippingAddress'])) {
                $shippingAddressItems = [];
                $shippingAddressItems[] = $orderDetails['ShippingAddress']['Name'];
                $shippingAddressItems[] = implode(', ', array_filter([$orderDetails['ShippingAddress']['Street1'], $orderDetails['ShippingAddress']['Street2']]));
                $shippingAddressItems[] = implode(', ', array_filter([$orderDetails['ShippingAddress']['CityName'], $orderDetails['ShippingAddress']['StateOrProvince']]));
                $shippingAddressItems[] = $orderDetails['ShippingAddress']['PostalCode'];
                $shippingAddressItems[] = $orderDetails['ShippingAddress']['CountryName'];

                $formattedOrderInstance['shipping_details']['Shipping Address'] = ucwords(implode('</br>', array_filter($shippingAddressItems)));
            }

            // Product Information
            if (!empty($orderDetails['TransactionArray']['Transaction'])) {
                if (!empty($orderDetails['TransactionArray']['Transaction']['Item'])) {
                    // Single Item
                    $productDetails = [];
                    $orderItem = $orderDetails['TransactionArray']['Transaction'];

                    $productDetails['title'] = $orderItem['Item']['Title'];
                    $productDetails['price'] = implode(' ', [$currencyCode, $orderItem['TransactionPrice']]);
                    $productDetails['quantity'] = $orderItem['QuantityPurchased'];

                    $orderItemDetails = $this->getProductDetails($orderItem['Item']['ItemID'], $channelDetails);
                    if (!empty($orderItemDetails['ListingDetails']['ViewItemURL'])) {
                        $productDetails['link'] = $orderItemDetails['ListingDetails']['ViewItemURL'];
                    }

                    $formattedOrderInstance['product_details'][] = $productDetails;
                    if (!empty($orderItem['Buyer']['Email']) && $orderItem['Buyer']['Email'] != 'Invalid Request') {
                        $formattedOrderInstance['ticket_details']['buyer_id'] = $orderDetails['BuyerUserID'];
                        $formattedOrderInstance['ticket_details']['buyer_email'] = $orderItem['Buyer']['Email'];
                    }
                } else {
                    foreach ($orderDetails['TransactionArray']['Transaction'] as $orderItem) {
                        $productDetails = [];
                        $productDetails['title'] = $orderItem['Item']['Title'];
                        $productDetails['price'] = implode(' ', [$currencyCode, $orderItem['TransactionPrice']]);
                        $productDetails['quantity'] = $orderItem['QuantityPurchased'];

                        $orderItemDetails = $this->getProductDetails($orderItem['Item']['ItemID'], $channelDetails);
                        if (!empty($orderItemDetails['ListingDetails']['ViewItemURL'])) {
                            $productDetails['link'] = $orderItemDetails['ListingDetails']['ViewItemURL'];
                        }

                        $formattedOrderInstance['product_details'][] = $productDetails;
                        if (!empty($orderItem['Buyer']['Email']) && $orderItem['Buyer']['Email'] != 'Invalid Request') {
                            $formattedOrderInstance['ticket_details']['buyer_id'] = $orderDetails['BuyerUserID'];
                            $formattedOrderInstance['ticket_details']['buyer_email'] = $orderItem['Buyer']['Email'];
                        }
                    }
                }
            }

            $formattedOrderDetails['orders'][] = $formattedOrderInstance;
        }

        $event->addEventResponse(['orderDetails' => $formattedOrderDetails]);
    }

    public function replyTicket(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $ticket = $eventData['ticket'];
        $request = $eventData['request'];

        $ebayThreadRepository = $this->entityManager->getRepository('WebkulTicketBundle:EBayThread');
        $ebayThread = $ebayThreadRepository->findOneBy(['ticket' => $ticket]);

        if (!empty($ebayThread)) {
            $requestParam = $request->request->all();
            $ebayThreadType = $ebayThread->getMessageType();
            $ecommerceChannel = $ebayThread->getEcommerceChannel();

            // Prcoess Ticket Message
            $ebayMessage = strip_tags($requestParam['reply']);
            $ebayMessage = str_replace('&nbsp;', ' ', $ebayMessage);

            switch ($ebayThreadType) {
                case 'Feedback':
                    // 80 characters limit
                    $ticketThreadData = [
                        'message' => $ebayMessage,
                        'messageType' => $ebayThreadType,
                        'itemId' => $ebayThread->getItemId(),
                        'transactionId' => $ebayThread->getTransactionId(),
                        'orderItemId' => $ebayThread->getOrderItemId(),
                    ];

                    if ($this->leaveSellerFeedback($ebayMessage, $ebayThread)) {
                        $ticketThreadData['messageType'] = 'Feedback Response';
                        $this->addTicketThread($ticketThreadData, $ebayThread, 'seller');
                    }
                    break;
                case 'AskSellerQuestion':
                case 'ResponseToASQQuestion':
                    $ticketThreadData = [
                        'message' => $ebayMessage,
                        'messageType' => $ebayThreadType,
                        'messageId' => $ebayThread->getMessageId(),
                        'itemId' => $ebayThread->getItemId(),
                        'transactionId' => $ebayThread->getTransactionId(),
                        'orderItemId' => $ebayThread->getOrderItemId(),
                    ];

                    // Attachments
                    $ebay_image_attachments = [];
                    $ticketService = $this->container->get('ticket.service');
                    $lastTicketThread = $ticketService->getLastReply($ticket->getId(), false, 'agent');

                    try {
                        $dom_document = new \DOMDocument();
                        $dom_document->preserveWhiteSpace = false;
                        $dom_document->loadHTML($lastTicketThread['reply']);
                        $thread_images = $dom_document->getElementsByTagName('img');

                        if ($thread_images->length) {
                            $valid_content_types = ['jpeg', 'png', 'jpg'];

                            foreach ($thread_images as $image) {
                                $image_path = $image->getAttribute('src');
                                $image_type_iterations = explode('.', $image_path);
                                $image_type = array_pop($image_type_iterations);

                                // Check if image is of valid type
                                if (in_array($image_type, $valid_content_types)) {
                                    $uploaded_eps_media_uri = $this->uploadEPSMedia($ecommerceChannel, $image_path);

                                    if (!empty($uploaded_eps_media_uri)) {
                                        $image_name_iterations = explode('/', array_pop($image_type_iterations));
                                        $image_name = array_pop($image_name_iterations);

                                        $ebay_image_attachments[] = [
                                            'name' => $image_name,
                                            'url' => $uploaded_eps_media_uri,
                                        ];
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Do nothing
                    }

                    if ($this->leaveASQMessageResponse($ebayMessage, $ebayThread, $ebay_image_attachments)) {
                        $this->addTicketThread($ticketThreadData, $ebayThread, 'seller');
                    }
                    break;
                // case 'ContactEbayMember':
                default:
                    break;
            }
        }
    }

    public function handleAPIRequest(ApplicationEvent $event)
    {
        $eventData = $event->getEventData();
        $application = $event->getApplication();
        $company = $this->container->get('user.service')->getCompany();

        if (!empty($eventData['request']) && !empty($eventData['endpoint'])) {
            $request = $event->getEventData()['request'];
            $ecommerceChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel');
            // $messagingChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:MessagingChannel');

            dump($eventData['endpoint']);
            die;
            switch ($eventData['endpoint']) {
                case 'refresh-messages':
                    $requestData = $request->request->all();
                    $ecommerceChannel = $ecommerceChannelRepository->findOneBy([
                        'id' => !empty($requestData['referenceId']) ? $requestData['referenceId'] : null,
                        'company' => $company,
                        'application' => $application,
                    ]);

                    // Process Refresh Tickets Request
                    if (!empty($ecommerceChannel)) {
                        $ebayMessengerChannel = $messagingChannelRepository->findOneBy([
                            'ecommerceChannel' => $ecommerceChannel
                        ]);

                        if (!empty($ebayMessengerChannel)) {
                            $optedNotifications = json_decode($ebayMessengerChannel->getDetails(), true);

                            foreach ($optedNotifications as $ebayNotification => $ebayNotificationStatus) {
                                if ($ebayNotificationStatus == true) {
                                    switch ($ebayNotification) {
                                        case 'order_feedbacks':
                                            $this->processEBaySellerFeedbacks($ebayMessengerChannel);
                                            break;
                                        case 'member_messages':
                                            $this->processEBayMemberMessages($ebayMessengerChannel);
                                            break;
                                        case 'listing_questions':
                                            $this->processEBaySellerQuestions($ebayMessengerChannel);
                                            break;
                                        case 'order_cancellation':
                                            $this->processEBayOrderCancellation($ebayMessengerChannel);
                                            break;
                                        default:
                                            break;
                                    }
                                }
                            }

                            $ebayMessengerChannel->setLastTicketRefreshedAt(new \DateTime('now'));
                            $this->entityManager->persist($ebayMessengerChannel);
                            $this->entityManager->flush();

                            $response = new JsonResponse([
                                'alertClass' => 'success',
                                'alertMessage' => $this->translate('Tickets updated successfully.'),
                            ], 200);
                        } else {
                            $event->stopPropagation();
                            $response = new JsonResponse([
                                'alertClass' => 'danger',
                                'alertMessage' => $this->translate('You are not subscribed to any platform messages. Please subscribe to platform message.'),
                            ], 403);
                        }
                    } else {
                        $event->stopPropagation();
                        $response = new JsonResponse([
                            'alertClass' => 'danger',
                            'alertMessage' => $this->translate('Unable to retrieve your ebay channel. Please try again later.'),
                        ], 403);
                    }

                    $event->addEventResponse(['response' => $response]);
                    break;
                default:
                    $response = new JsonResponse([
                        'alertClass' => 'danger',
                        'alertMessage' => $this->translate('An unexpected error occurred while processing your request. Please try again later.'),
                    ], 403);
                    $event->addEventResponse(['response' => $response]);
                    break;
            }
        } else {
            $response = new JsonResponse([
                'alertClass' => 'danger',
                'alertMessage' => $this->translate('An unexpected error occurred while processing your request. Please try again later.'),
            ], 403);
            $event->addEventResponse(['response' => $response]);
        }
    }

    public function handleWebhookCallback(ApplicationEvent $event)
    {
        $response = new Response(Response::HTTP_OK);
        $response->send();

        // Process Requests
        $application = $event->getApplication();
        $logger = $this->container->get('logger');
        $request = $event->getEventData()['request'];
        $company = $this->container->get('user.service')->getCompany();
        $logger->info('Ebay Webhook Request: ' . $request->getMethod());

        // Process Webhook Notification
        $soapAction = $request->headers->get('soapaction');
        $logger->info('Ebay Webhook Request Soap Action: ' . $soapAction);
        if (!empty($soapAction)) {
            // $soapActionParams = explode('/', $soapAction);
            // $webhookNotificationType = end($soapActionParams);
            $ecommerceChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:ECommerceChannel');
            // $messagingChannelRepository = $this->entityManager->getRepository('UVDeskCoreBundle:MessagingChannel');

            $ecommerceChannelCollection = $ecommerceChannelRepository->findBy([
                'company' => $company,
                'application' => $application,
            ]);

            if (!empty($ecommerceChannelCollection)) {
                foreach ($ecommerceChannelCollection as $ecommerceChannel) {
                    $ebayMessengerChannel = $messagingChannelRepository->findOneBy([
                        'ecommerceChannel' => $ecommerceChannel
                    ]);


                    $optedNotifications = json_decode($ebayMessengerChannel->getDetails(), true);

                    foreach ($optedNotifications as $ebayNotification => $ebayNotificationStatus) {
                        if ($ebayNotificationStatus == true) {
                            switch ($ebayNotification) {
                                case 'order_feedbacks':
                                    $this->processEBaySellerFeedbacks($ebayMessengerChannel);
                                    break;
                                case 'member_messages':
                                    $this->processEBayMemberMessages($ebayMessengerChannel);
                                    break;
                                case 'listing_questions':
                                    $this->processEBaySellerQuestions($ebayMessengerChannel);
                                    break;
                                default:
                                    break;
                            }
                        }
                    }

                    $ebayMessengerChannel->setLastTicketRefreshedAt(new \DateTime('now'));
                    $this->entityManager->persist($ebayMessengerChannel);
                    $this->entityManager->flush();
                }
            }
        }
    }
}

?>
