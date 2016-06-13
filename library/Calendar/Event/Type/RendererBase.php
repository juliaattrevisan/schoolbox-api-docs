<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type;

use Calendar\Event\Type\EventBase;
use \SitePage;
use Twig_Environment;
use \Schoolbox\User;
use \Alaress_Date_Format;

abstract class RendererBase implements RendererInterface
{
    /**
     * The user who the event relates to
     *
     * @var \Schoolbox\User
     */
    var $user;

    /**
     * The user who is viewing this event
     *
     * @var \Schoolbox\User
     */
    var $viewer;

    /**
     * The event that will be rendered
     *
     * @var EventBase
     */
    var $event;

    /**
     * SitePage
     *
     * @var SitePage
     */
    var $page;

    /**
     * Group Handler
     * TODO: refactor this out
     *
     * @var \Group
     */
    var $groupHandler;

    /**
     * Calendar Handler
     * TODO: refactor this out
     *
     * @var \Calendar
     */
    var $calendarHandler;

    /**
     * @param SitePage $page
     * @param EventBase $event
     * @param User $user
     * @param \Group $groupHandler
     * @param \Calendar $calHandler
     * @param User $viewer
     */
    public function __construct(SitePage $page, EventBase $event, User $user, \Group $groupHandler, \Calendar $calHandler, User $viewer) {
        $this->setPage($page);
        $this->setEvent($event);
        $this->setUser($user);
        $this->setViewer($viewer);

        //TODO remove these
        $this->setGroupHandler($groupHandler);
        $this->setCalendarHandler($calHandler);
    }

    /**
     * Returns the output from the render action for this event
     *
     * @return string
     */
    abstract public function render();

    /**
     * Is this event visible by this user, sometimes we can't tell till we render if we should show it
     * or not apparently.  Would of thought that this would of been solved in the collection, but it might be render
     * specific
     *
     * @return bool
     */
    abstract public function isVisible();

    /**
     * Return templating engine
     *
     * @return Twig_Environment
     */
    public function getTemplate() {
        return $this->page->getDi()->get('templateEngine');
    }


    /**
    * @param \Schoolbox\User $user
    */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param \Schoolbox\User $viewer
     */
    public function setViewer($viewer)
    {
        $this->viewer = $viewer;
    }

    /**
     * @return \Schoolbox\User
     */
    public function getViewer()
    {
        return $this->viewer;
    }

    /**
     * @param EventBase $event
     */
    public function setEvent($event)
    {
        $this->event = $event;
    }

    /**
     * @return EventBase
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @return SitePage
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param SitePage $page
     */
    public function setPage($page)
    {
        $this->page = $page;
    }

    /**
     * @param mixed $calendarHandler
     */
    public function setCalendarHandler($calendarHandler)
    {
        $this->calendarHandler = $calendarHandler;
    }

    /**
     * @return mixed
     */
    public function getCalendarHandler()
    {
        return $this->calendarHandler;
    }

    /**
     * @param mixed $groupHandler
     */
    public function setGroupHandler($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return mixed
     */
    public function getGroupHandler()
    {
        return $this->groupHandler;
    }





} 