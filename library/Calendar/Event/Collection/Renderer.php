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
    /** @var string $interfaceClass */
    protected $interfaceClass = 'Calendar_Api_EventType_Interface';

    /** @var Calendar_Api_EventType_Interface $api An instance of a Calendar API class */
    protected $api = null;

    /** @var int $startTimestamp A unix timestamp */
    protected $startTimestamp = -1;

    /** @var int $endTimestamp A unix timestamp */
    protected $endTimestamp = -1;

    /** @var SitePage $page An instance of sitepage */
    protected $page;

    /** @var User */
    private $user;

    /** @var User */
    protected $viewer;

    /** @var Group $groupHandler */
    protected $groupHandler;

    /** @var Calendar $calendarHandler */
    protected $calendarHandler;

    /** @var Collection $allEvents Mega-stack Collection instance which stores all events */
    protected $allEvents = null;

    /**
     * Constructor
     *
     * @param $api
     * @param $startTimestamp
     * @param $endTimestamp
     * @param User $user
     * @param Sitepage $page
     * @param User $viewer
     */
    public function __construct($api, $startTimestamp, $endTimestamp, User $user, Sitepage $page, User $viewer)
    {
        $this->setApi($api);
        $this->setStartTimestamp($startTimestamp);
        $this->setEndTimestamp($endTimestamp);
        $this->user = $user;
        $this->setPage($page);
        $this->setViewer($viewer);

        //TODO remove these deprecated dependencies
        $this->setGroupHandler(new Group());
        $this->setCalendarHandler(new Calendar());

        $this->setAllEvents(new Collection());
        $this->collectAllEvents();
    }

    /**
     * Collects all events from everywhere, for the current day
     */
    protected function collectAllEvents()
    {
        $eventList = $this->getApi()->getEvents($this->getStartTimestamp(), $this->getEndTimestamp());
        /* Only attempt to merge collections
         */
        if ($eventList instanceof Collection) {
            $this->getAllEvents()->merge($eventList);
        }

        /* Sort all the events in order of time
         */
        $this->getAllEvents()->sort();
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
        foreach ($this->getAllEvents() as $currentEvent) {
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
            $this->getPage(),
            $event,
            $this->user,
            $this->getGroupHandler(),
            $this->getCalendarHandler(),
            $this->getViewer()
        );
        return $renderObj->render();
    }

    /* Getters/Setters
     */

    /**
     * Set api
     *
     * @param Calendar_Api_EventType_Interface $api
     */
    protected function setApi($api)
    {
        $this->api = $api;
    }

    /**
     * Get api
     *
     * @return Calendar_Api_EventType_Interface
     */
    protected function getApi()
    {
        return $this->api;
    }

    /**
     * Set end timestamp
     *
     * @param int $endTimestamp
     */
    protected function setEndTimestamp($endTimestamp)
    {
        $this->endTimestamp = $endTimestamp;
    }

    /**
     * Get end timestamp
     *
     * @return int
     */
    protected function getEndTimestamp()
    {
        return $this->endTimestamp;
    }

    /**
     * Set start timestamp
     *
     * @param int $startTimestamp
     */
    protected function setStartTimestamp($startTimestamp)
    {
        $this->startTimestamp = $startTimestamp;
    }

    /**
     * Get start timestamp
     *
     * @return int
     */
    protected function getStartTimestamp()
    {
        return $this->startTimestamp;
    }

    /**
     * Set page
     *
     * @param Sitepage $page
     */
    protected function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * Get page
     *
     * @return Sitepage
     */
    protected function getPage()
    {
        return $this->page;
    }

    /**
     * Set all events
     *
     * @param Collection $allEvents
     */
    protected function setAllEvents($allEvents)
    {
        $this->allEvents = $allEvents;
    }

    /**
     * Get all events
     *
     * @return Collection
     */
    public function getAllEvents()
    {
        return $this->allEvents;
    }

    /**
     * The interface which defines accessible methods
     *
     * @return string
     */
    public function getInterfaceClass()
    {
        return $this->interfaceClass;
    }

    /**
     * @param Calendar $calendarHandler
     */
    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * @return Calendar
     */
    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }

    /**
     * @param Group $groupHandler
     */
    public function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return Group
     */
    public function getGroupHandler()
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
     * @param User $viewer
     */
    public function setViewer($viewer)
    {
        $this->viewer = $viewer;
    }

    /**
     * @return User
     */
    public function getViewer()
    {
        return $this->viewer;
    }
}
