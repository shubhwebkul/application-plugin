<?php

namespace Webkul\UVDesk\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Webkul\UVDesk\CoreBundle\Event\ApplicationEvent;
use Webkul\UVDesk\CoreBundle\Extras\Snippet\TwigConfiguration;
use Webkul\UVDesk\CoreBundle\EventDispatcher\AppEventDispatcher;

class Application extends Controller
{
    use TwigConfiguration;

    public function renderApplicationList()
    {
        dump("called");
        die;
    }

    /**
     * Renders Application View (is called on page load by ajax)
     * @param  string  $applicationRouteName Application Route Name
     * @param  Request $request              Request Object
     */
    public function loadApplication(Request $request, $applicationRouteName = null)
    {
        $applicationService = $this->container->get('application.service');

        if (!$applicationRouteName)
            $applicationRouteName = $applicationService->getApplicationName();
        $application = $applicationService->getApplicationByRouteName($applicationRouteName);

        if (empty($application)) {
            $this->addFlash(
                    'warning',
                    "Warning! The requested application doesn't exist."
                );
            return new RedirectResponse($this->generateUrl('helpdesk_member_application_base'));
        }

        $dispatcher = new AppEventDispatcher($application, $this->container);
        $applicationEvent = new ApplicationEvent($application);
        $dispatcher->dispatch(ApplicationEvent::ROUTINE_APPLICATION_LOAD_TEMPLATE, $applicationEvent);
        $eventResponse = $applicationEvent->getEventResponse();

        $this->appendTwigResponse('application', $application);
        $this->appendTwigResponse('applicationData', $applicationEvent->getEventResponse());
        $this->appendTwigResponse('activeTab', ($request->attributes->get('activeTab') ?: 'overview'));
        if (array_key_exists('formFields', $eventResponse)) {
            $this->appendTwigResponse('formFields', $eventResponse['formFields'], false);
        }

        $appScreenshots = $this->get('application.service')->getAppScreenshots($application);
        $this->appendTwigResponse('screenshots', $appScreenshots, false);

        // $appVideos = $this->get('application.service')->getAppVideos($application);
        // $this->appendTwigResponse('videos', $appVideos, false);

        return $this->render('@UVDeskCore/Application/applicationDetail.html.twig', $this->getTwigResponse());
    }

    /**
    * install application by xhr
    *
    * @param Request $request
    *
    * @return JsonResponse Object
    */
    public function installXhr(Request $request)
    {
        // Check if request is get. Update this in route by adding method
        if ($request->isMethod('GET'))
            return new RedirectResponse($this->generateUrl('helpdesk_member_application_base'));

        $response = [
            'code' => Response::HTTP_NOT_FOUND,
            'content' => [
                'alertClass' => 'danger',
                'alertMessage' => "An unexpected error occurred while processing your request. The requested application does not exist."
            ],
        ]; // Default XHR Response

        if ($request->isXmlHttpRequest()) {
            $requestBody = json_decode($request->getContent(), true);

            $entityManager = $this->getDoctrine()->getManager();
            $application = $entityManager->getRepository('UVDeskCoreBundle:Application')->findOneBy(['id' => $requestBody['id']]);

            if (!empty($application)) {
                $response['code'] = Response::HTTP_OK;
                $response['content']['alertClass'] = 'success';
                $response['content']['alertMessage'] = 'The requested application is already installed.';

                $applicationService = $this->get('application.service');
                // $applicationUser = $applicationService->getInstalledApplication($application);
                if (empty($applicationUser)) {
                    $applicationUser = $applicationService->installApplication($application);
                    $response['content']['alertMessage'] = 'Application installed successfully.';
                    $response['code'] = Response::HTTP_OK;
                }
                if ($applicationUser) {
                    $response['content']['id'] = $application->getId();
                }
                $response['content']['url'] = $this->generateUrl('helpdesk_member_load_application', ['applicationRouteName' => str_replace(' ', '-', strtolower($application->getName()))], true);
                $response['content']['installed'] = 1;
            }
        }

        return new JsonResponse($response['content'], $response['code']);
    }

    public function uninstallXhr(Request $request)
    {
        // Check if request is get. Update this in route by adding method
        if ($request->isMethod('GET'))
            return new RedirectResponse($this->generateUrl('helpdesk_member_application_base'));

        $response = [
            'code' => Response::HTTP_NOT_FOUND,
            'content' => [
                'alertClass' => 'danger',
                'alertMessage' => "An unexpected error occurred while processing your request. The requested application does not exist."
            ],
        ]; // Default XHR Response
        $requestBody = json_decode($request->getContent(), true);

        $entityManager = $this->getDoctrine()->getManager();
        $application = $entityManager->getRepository('UVDeskCoreBundle:Application')->findOneBy(['id' => $requestBody['id']]);

        if (!empty($application)) {
            $response = [
                'code' => Response::HTTP_OK,
                'content' => [
                    'alertClass' => 'success',
                    'alertMessage' => "The requested application hasn't been installed yet.",
                ],
            ];

            if ($application->getIsInstalled()) {
                $application->setIsInstalled(0);

                $entityManager->persist($application);
                $entityManager->flush();

                // Uninstall successfull
                $response['content']['installed'] = 0;
                $response['content']['alertMessage'] = 'Application uninstalled successfully.';
            }
        }

        return new JsonResponse($response['content'], $response['code']);
    }

    public function getApplicationChannelCollectionXhr($applicationRouteName, Request $request)
    {
        $application = $this->get('application.service')->getApplicationByRouteName($applicationRouteName);

        if ($application) {
            $dispatcher = new AppEventDispatcher($application, $this->container);
            $applicationEvent = new ApplicationEvent($application);
            $dispatcher->dispatch(ApplicationEvent::ROUTINE_APPLICATION_LOAD_CHANNEL_COLLECTION, $applicationEvent);
            $response = $applicationEvent->getEventResponse();
        }

        return new JsonResponse(!empty($response['content']) ? $response['content'] : []);
    }

    /**
    * create application channel from submitted data (called on form submit)
    */
    public function createApplicationChannel($applicationRouteName, Request $request)
    {
        $applicationService = $this->get('application.service');
        $application = $applicationService->getApplicationByRouteName($applicationRouteName);

        if ($application->getIsInstalled()) {
            $session = $request->getSession();
            if ($request->attributes->get('channelId') || $request->attributes->get('channelId') === "0") {
                $session->set($applicationRouteName . '-channeId', $request->attributes->get('channelId'));
            } else {
                $session->remove($applicationRouteName . '-channeId');
            }
            $dispatcher = new AppEventDispatcher($application, $this->container);
            $applicationEvent = new ApplicationEvent($application);
            $applicationEvent->addEventData(['request' => $request]);
            $dispatcher->dispatch(ApplicationEvent::ROUTINE_APPLICATION_CREATE_CHANNEL, $applicationEvent);

            $eventResponse = $applicationEvent->getEventResponse();
        } else {
            // Application is not installed. Redirect to app about
            $session = new Session();
            $session->getFlashBag()->add('danger', "The requested application hasn't been installed yet.");
        }

        return !empty($eventResponse['response']) ? $eventResponse['response'] : new RedirectResponse($this->generateUrl('helpdesk_member_load_application', ['applicationRouteName' => $applicationRouteName, 'activeTab'=> 'configure']));
    }

    public function applicationExternalRedirect($applicationRouteName, Request $request)
    {
        $applicationService = $this->get('application.service');
        $application = $applicationService->getApplicationByRouteName($applicationRouteName);

        // Application is not installed. Redirect to app about
        if (!$application->getIsInstalled()) {
            $session = new Session();
            $session->getFlashBag()->add('danger', "The requested application hasn't been installed yet.");
            return new RedirectResponse($this->generateUrl('helpdesk_member_load_application', ['applicationRouteName' => $applicationRouteName]));
        }

        $dispatcher = new AppEventDispatcher($application, $this->container);
        $applicationEvent = new ApplicationEvent($application);
        $applicationEvent->addEventData(['request' => $request]);
        $dispatcher->dispatch(ApplicationEvent::ROUTINE_APPLICATION_PROCESS_REDIRECT, $applicationEvent);

        $eventResponse = $applicationEvent->getEventResponse();
        return !empty($eventResponse['response']) ? $eventResponse['response'] : new RedirectResponse($this->generateUrl('helpdesk_member_load_application', ['applicationRouteName' => $applicationRouteName, 'activeTab'=> 'configure']));
    }

}
