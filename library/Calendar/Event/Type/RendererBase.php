<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type;

use Alaress\Authorizer\Authorizer;
use Aura\Router\Router;
use \SitePage;
use Twig_Environment;
use \Schoolbox\User;

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
     * @param User $user
     * @param \Group $groupHandler
     * @param \Calendar $calHandler
     * @param User $viewer
     */
    public function __construct(SitePage $page, User $user, \Group $groupHandler, \Calendar $calHandler, User $viewer) {
        $this->setPage($page);
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

    /**
     * Helper function - gets whether access to a route is allowed.
     *
     * @param string $name
     * @param array  $params
     *
     * @return bool|string False if not authorized, a URL if it is.
     */
    public function getRouteIfAuthorized($name, array $params = array())
    {
        if ($this->getAuthorizer()->isPathAuthorizedForUser($name, $params, $this->getViewer())) {
            return $this->getRouter()->generate($name, $params);
        }
        return false;
    }

    /**
     * @return Authorizer
     */
    public function getAuthorizer()
    {
        return $this->getPage()->getDi()->get('authorizer');
    }

    /**
     * @return Router
     */
    public function getRouter()
    {
        return $this->getPage()->getDi()->get('router');
    }
}
