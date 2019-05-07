<?php

namespace Webkul\UVDesk\AppBundle\Event;

use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

abstract class ApplicationLifeCycle
{
    public static function getEventLifeCycle($applicationName, $routine)
    {
        switch ($routine) {
            case ApplicationEvent::ROUTINE_APPLICATION_LOAD_TEMPLATE:
                switch ($applicationName) {
                    default:
                        return [
                            ['loadFormArray', 1],
                        ];
                    break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_LOAD_CHANNEL_COLLECTION:
                return [
                    ['loadApplicationChannelCollection', 1],
                ];
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_CREATE_CHANNEL:
                switch ($applicationName) {
                    case 'Shopify':
                        return [
                            ['processApplicationConfiguration', 2],
                            ['saveApplication', 1],
                        ];
                        break;
                    case 'EBay':
                        return [
                            ['processApplicationConfiguration', 1],
                        ];
                        break;
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_SAVE_CHANNEL_SETTINGS:
                switch ($applicationName) {
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_PROCESS_REDIRECT:
                switch ($applicationName) {
                    case 'EBay':
                        return [
                            ['handleExternalCallback', 9],
                            ['saveApplication', 8],
                        ];
                        break;
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_PROCESS_WEBHOOK:
                switch ($applicationName) {
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_PROCESS_BACKGROUND:
                switch ($applicationName) {
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_RETRIEVE_ORDER:
                return [
                    ['getOrderDetails', 2],
                    ['formatOrderDetails', 1],
                ];
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_TICKET_REPLY:
                switch ($applicationName) {
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_FRONT_LOGIN:
                switch ($applicationName) {
                    default:
                        return [];
                    break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_REMOVE_CHANNEL:
                switch ($applicationName) {
                    default:
                        return [
                            ['removeApplicationChannel', 1]
                        ];
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_UNINSTALL_APPLICATION:
                return [
                    ['uninstallApplication', 1]
                ];
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_CUSTOM_API_REQUEST:
                switch ($applicationName) {
                    default:
                        break;
                }
                break;
            case ApplicationEvent::ROUTINE_APPLICATION_GET_DATA:
                switch ($applicationName) {
                    default:
                        return [];
                    break;
                }
                break;
            default:
                break;
        }

        return [];
    }
}

class ApplicationEvent extends Event
{
    /** also events below to getEventCycle function  **/
    const ROUTINE_APPLICATION_TICKET_REPLY = 'application.ticket.reply';
    const ROUTINE_APPLICATION_LOAD_TEMPLATE = 'application.load.template';
    const ROUTINE_APPLICATION_CREATE_CHANNEL = 'application.create.channel';
    const ROUTINE_APPLICATION_REMOVE_CHANNEL = 'application.remove.channel';
    const ROUTINE_APPLICATION_RETRIEVE_ORDER = 'application.getOrderDetails';
    const ROUTINE_APPLICATION_PROCESS_WEBHOOK = 'application.process.webhook';
    const ROUTINE_APPLICATION_PROCESS_REDIRECT = 'application.process.redirect';
    const ROUTINE_APPLICATION_FRONT_LOGIN = 'application.process.customer.login';
    const ROUTINE_APPLICATION_PROCESS_BACKGROUND = 'application.process.background';
    const ROUTINE_APPLICATION_LOAD_CHANNEL_COLLECTION = 'application.load.channel.collection';
    const ROUTINE_APPLICATION_UNINSTALL_APPLICATION = 'application.process.uninstall';
    const ROUTINE_APPLICATION_CUSTOM_API_REQUEST = 'application.api.action';
    const ROUTINE_APPLICATION_SAVE_CHANNEL_SETTINGS = 'application.save.channel.settings';
    const ROUTINE_APPLICATION_GET_DATA = 'application.get.data';

    private $session;
    private $application;
    private $applicationRouteName;
    private $eventData = [];
    private $eventResponse = [];

    public function __construct(\Webkul\UVDesk\CoreBundle\Entity\Application $application)
    {
        $this->session = new Session();
        $this->setApplication($application);
    }

    public static function getEventCycle($applicationRouteName)
    {
        $routines = [
            self::ROUTINE_APPLICATION_TICKET_REPLY,
            self::ROUTINE_APPLICATION_LOAD_TEMPLATE,
            self::ROUTINE_APPLICATION_LOAD_CHANNEL_COLLECTION,
            self::ROUTINE_APPLICATION_CREATE_CHANNEL,
            self::ROUTINE_APPLICATION_REMOVE_CHANNEL,
            self::ROUTINE_APPLICATION_PROCESS_REDIRECT,
            self::ROUTINE_APPLICATION_PROCESS_WEBHOOK,
            self::ROUTINE_APPLICATION_FRONT_LOGIN,
            self::ROUTINE_APPLICATION_PROCESS_BACKGROUND,
            self::ROUTINE_APPLICATION_RETRIEVE_ORDER,
            self::ROUTINE_APPLICATION_UNINSTALL_APPLICATION,
            self::ROUTINE_APPLICATION_CUSTOM_API_REQUEST,
            self::ROUTINE_APPLICATION_SAVE_CHANNEL_SETTINGS,
            self::ROUTINE_APPLICATION_GET_DATA,
        ];

        $eventLifeCycle = [];
        foreach($routines as $routine) {
            $eventLifeCycle[$routine] = ApplicationLifeCycle::getEventLifeCycle(
                                            $applicationRouteName, $routine
                                        );
        }

        foreach ($eventLifeCycle as $eventName => $eventSubRoutine) {
            if (empty($eventSubRoutine)) {
                unset($eventLifeCycle[$eventName]);
            }
        }

        return !empty($eventLifeCycle) ? $eventLifeCycle : [];
    }

    private function setApplication(\Webkul\UVDesk\CoreBundle\Entity\Application $application)
    {
        $this->application = $application;
        $this->setApplicationRouteName($application->getName());

        return $this;
    }

    public function getApplication()
    {
        return $this->application;
    }

    private function setApplicationRouteName($applicationName)
    {
        $this->applicationRouteName = str_replace(' ', '-', strtolower($applicationName));

        return $this;
    }

    public function getApplicationRouteName()
    {
        return $this->applicationRouteName;
    }

    public function addEventResponse(array $response)
    {
        $this->eventResponse = array_unique(array_merge($this->eventResponse, $response), SORT_REGULAR);
        return $this;
    }

    public function getEventResponse()
    {
        return $this->eventResponse;
    }

    public function removeEventResponse($index)
    {
        if (!empty($this->eventResponse[$index]))
            unset($this->eventResponse[$index]);

        return $this;
    }

    public function clearEventResponse()
    {
        $this->eventResponse = [];
        return $this;
    }

    public function addEventData(array $data)
    {
        $this->eventData = array_unique(array_merge($this->eventData, $data), SORT_REGULAR);
        return $this;
    }

    public function getEventData()
    {
        return $this->eventData;
    }

    public function removeEventData($index)
    {
        if (!empty($this->eventData[$index]))
            unset($this->eventData[$index]);

        return $this;
    }

    public function clearEventData()
    {
        $this->eventData = [];
        return $this;
    }

    public function setSessionData($index, $indexData)
    {
        $this->session->set($index, $indexData);

        return $this;
    }

    public function getSessionData($index)
    {
        return $this->session->get($index);
    }

    public function removeSessionData($index)
    {
        $this->session->remove($index);

        return $this;
    }

    public function raiseSessionMessage($messageType = 'warning', $sessionMessage)
    {
        $this->session->getFlashBag()->add($messageType, $sessionMessage);

        return $this;
    }
}

?>
