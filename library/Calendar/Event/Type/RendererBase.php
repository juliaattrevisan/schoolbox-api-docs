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

class RendererBase implements RendererInterface
{
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
     * @param SitePage $page
     * @param EventBase $event
     */
    public function __constructor($page, $event) {
        $this->setPage($page);
        $this->setEvent($event);
    }

    /**
     * Returns the output from the render action for this event
     *
     * @return string
     */
    public function render() {
        return '';
    }

    /**
     * Return templating engine
     *
     * @return Twig_Environment
     */
    public function getTemplate() {
        return $this->page->getDi()->get('templateEngine');
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





} 