<?php

/**
 * Collection_Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Calendar\Event\Collection;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use Calendar\Event\Collection;
use Calendar\Event\Type\EventInterface;
use Calendar\Event\Type\RendererInterface;
use Calendar_Api_EventType_Interface;
use Schoolbox\GenericCollection;
use Schoolbox\User;

class Renderer
{
    /** @var Calendar_Api_EventType_Interface $api An instance of a Calendar API class */
    private $api;

    /** @var int $startTimestamp A unix timestamp */
    private $startTimestamp;

    /** @var int $endTimestamp A unix timestamp */
    private $endTimestamp;

    /**
     * @var Authorizer
     */
    private $authorizer;

    /**
     * @var Router
     */
    private $router;


    /** @var User */
    private $user;

    /** @var User */
    private $viewer;

    /**
     * Mega-stack Collection instance which stores all events
     *
     * @var GenericCollection<EventInterface>
     */
    private GenericCollection $allEvents;


    /**
     * Constructor
     *
     * @param Calendar_Api_EventType_Interface $api
     * @param int                              $startTimestamp
     * @param int                              $endTimestamp
     * @param User                             $user
     * @param Authorizer                       $authorizer
     * @param Router                           $router
     * @param User                             $viewer
     * @param array|null                       $allowedEventTypes
     */
    public function __construct(
        Calendar_Api_EventType_Interface $api,
        int $startTimestamp,
        int $endTimestamp,
        User $user,
        Authorizer $authorizer,
        Router $router,
        User $viewer,
        ?array $allowedEventTypes = null
    ) {
        $this->api            = $api;
        $this->startTimestamp = $startTimestamp;
        $this->endTimestamp   = $endTimestamp;
        $this->user           = $user;
        $this->authorizer     = $authorizer;
        $this->router         = $router;
        $this->viewer         = $viewer;

        $this->allEvents = new GenericCollection(EventInterface::class);
        $this->collectAllEvents($allowedEventTypes);
    }


    /**
     * Collects all events from everywhere, for the current day
     *
     * Optionally pass a set of luEventType ids by which to restrict the data further
     */
    private function collectAllEvents(?array $allowedEventTypes = null): void
    {
        $eventList = $this->api->getEvents($this->startTimestamp, $this->endTimestamp, $allowedEventTypes);

        $this->allEvents = $this->allEvents->merge($eventList);

        /* Sort all the events in order of time
         */
        // TODO implement in GenericCollection
        $this->allEvents->sort();
    }


    /**
     * Render all events
     *
     * @param string $format
     * @return string[]
     * @throws \Exception If unable to find a rendering class for the specified type
     */
    public function renderAll(string $format): array
    {
        $output = [];
        foreach ($this->allEvents as $currentEvent) {
            $output[] = $this->renderEvent($currentEvent, $format);
        }
        return $output;
    }


    /**
     * @param EventInterface $event
     * @param string         $format
     * @param string|null    $returnStr
     * @param string|null    $baseUrl
     *
     * @return mixed
     * @throws \Exception If unable to find a rendering class for the specified type
     */
    public function renderEvent($event, $format, ?string $returnStr = null, ?string $baseUrl = null)
    {

        $rendererClass = $event->getRendererClass($format);

        /** @var RendererInterface $renderObj */
        $renderObj = new $rendererClass(
            $this->authorizer,
            $this->router,
            $event,
            $this->user,
            $this->viewer
        );
        if ($returnStr && method_exists($renderObj, 'setReturnStr')) {
            $renderObj->setReturnStr($returnStr);
        }
        if ($baseUrl && method_exists($renderObj, 'setBaseUrl')) {
            $renderObj->setBaseUrl($baseUrl);
        }
        return $renderObj->render();
    }

    /* Getters/Setters
     */


    /**
     * Get end timestamp
     *
     * @return int
     */
    protected function getEndTimestamp(): int
    {
        return $this->endTimestamp;
    }


    /**
     * Get start timestamp
     *
     * @return int
     */
    protected function getStartTimestamp(): int
    {
        return $this->startTimestamp;
    }


    /**
     * Get all events
     *
     * @return GenericCollection<EventInterface>
     */
    public function getAllEvents(): GenericCollection
    {
        return $this->allEvents;
    }


    /**
     * @return User
     */
    protected function getUser(): User
    {
        return $this->user;
    }


    /**
     * @return User
     */
    protected function getViewer(): User
    {
        return $this->viewer;
    }
}
