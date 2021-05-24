<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Event;

class BlankEvent implements EventInterface
{

    /**
     * Returns the principal that caused this event
     *
     * @return object
     */
    public function getEffector()
    {
        return null;
    }

    /**
     * Returns the name of the action that was taken by the effector against the object
     *
     * @return string
     */
    public function getAction()
    {
        return '';
    }

    /**
     * Returns the object that is being acted upon
     *
     * @return object
     */
    public function getObject()
    {
        return null;
    }

    /**
     * Returns an array that represents the difference between the object in its current state
     * and its previous state (empty array means "no change"), or null if not applicable for the given action.
     *
     * @return array|null
     */
    public function getDiff()
    {
        return null;
    }
}
