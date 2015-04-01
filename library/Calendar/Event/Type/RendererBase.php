<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Calendar\Event\Type;

use EventBase;

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
     * Returns the output from the render action for this event
     *
     * @return string
     */
    public function render() {
        return '';
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





} 