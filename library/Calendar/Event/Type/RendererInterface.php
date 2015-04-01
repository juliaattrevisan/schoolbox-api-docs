<?php
/**
 * This provides the interface for all renderers that are employed by events to use
 * Possible implementations include; json, vcal, html
 *
 * @author	  James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 */

namespace Calendar\Event\Type;

interface RendererInterface {

    /**
     * Returns the output from the render action for this event
     *
     * @return string
     */
    public function render();

    /**
     * Set the event that you want to render
     *
     * @param EventBase
     */
    public function setEvent($event);

    /**
     * Get the event that you want to render
     *
     * @return EventBase
     */
    public function getEvent();
}
