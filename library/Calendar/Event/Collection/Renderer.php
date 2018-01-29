<?php
/**
 * Collection_Renderer
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Calendar\Event\Collection;

use Calendar;
use Calendar_Api_EventType_Interface;
use Group;
use SitePage;
use Calendar\Event\Collection;
use Calendar\Event\Type\EventInterface;
use Calendar\Event\Type\RendererInterface;
use Schoolbox\User;

class Renderer
{

    /** @var Calendar_Api_EventType_Interface $api An instance of a Calendar API class */
    private $api;

    /** @var int $startTimestamp A unix timestamp */
    private $startTimestamp;

    /** @var int $endTimestamp A unix timestamp */
    private $endTimestamp;

    /** @var SitePage $page An instance of sitepage */
    private $page;

    /** @var User */
    private $user;

    /** @var User */
    private $viewer;

    /** @var Group $groupHandler */
    private $groupHandler;

    /** @var Calendar $calendarHandler */
    private $calendarHandler;

    /** @var Collection $allEvents Mega-stack Collection instance which stores all events */
    private $allEvents;


    /**
     * Constructor
     *
     * @param Calendar_Api_EventType_Interface $api
     * @param int                              $startTimestamp
     * @param int                              $endTimestamp
     * @param User                             $user
     * @param SitePage                         $page
     * @param User                             $viewer
     */
    public function __construct(
        Calendar_Api_EventType_Interface $api,
        int $startTimestamp,
        int $endTimestamp,
        User $user,
        Sitepage $page,
        User $viewer
    ) {
        $this->api            = $api;
        $this->startTimestamp = $startTimestamp;
        $this->endTimestamp   = $endTimestamp;
        $this->user           = $user;
        $this->page           = $page;
        $this->viewer         = $viewer;

        //TODO remove these deprecated dependencies
        $this->groupHandler    = new Group();
        $this->calendarHandler = new Calendar();

        $this->allEvents = new Collection();
        $this->collectAllEvents();
    }


    /**
     * Collects all events from everywhere, for the current day
     */
    private function collectAllEvents(): void
    {
        $eventList = $this->api->getEvents($this->startTimestamp, $this->endTimestamp);
        /* Only attempt to merge collections
         */
        if ($eventList instanceof Collection) {
            $this->allEvents->merge($eventList);
        }

        /* Sort all the events in order of time
         */
        $this->allEvents->sort();
    }


    /**
     * Render all events
     *
     * @param string $type
     * @return string
     * @throws \Exception If unable to find a rendering class for the specified type
     */
    public function renderAll($type)
    {
        $output = '';
        foreach ($this->allEvents as $currentEvent) {
            $output .= $this->renderEvent($currentEvent, $type);
        }
        return $output;
    }


    /**
     * @param EventInterface $event
     * @param string         $type
     *
     * @return mixed
     * @throws \Exception If unable to find a rendering class for the specified type
     */
    public function renderEvent($event, $type)
    {
        $rendererClass = $event->getRendererClass($type);
        /** @var RendererInterface $renderObj */
        $renderObj = new $rendererClass(
            $this->page,
            $event,
            $this->user,
            $this->groupHandler,
            $this->calendarHandler,
            $this->viewer
        );
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
     * Get page
     *
     * @return Sitepage
     */
    protected function getPage(): SitePage
    {
        return $this->page;
    }


    /**
     * Set all events
     *
     * @param Collection $allEvents
     * @return $this
     */
    protected function setAllEvents(Collection $allEvents): self
    {
        $this->allEvents = $allEvents;
        return $this;
    }


    /**
     * Get all events
     *
     * @return Collection
     */
    public function getAllEvents(): Collection
    {
        return $this->allEvents;
    }


    /**
     * @return Calendar
     */
    protected function getCalendarHandler(): Calendar
    {
        return $this->calendarHandler;
    }


    /**
     * @return Group
     */
    protected function getGroupHandler(): Group
    {
        return $this->groupHandler;
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
