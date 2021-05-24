<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Scheduler\Trigger;

use Alaress\Scheduler\Event\EventInterface;

/**
 * At its most basic, a Trigger is a "what, when, how, why":
 * what: a label that defines this trigger
 * when: a timestamp of when this trigger is to be fired
 * how:  one or more callbacks to be executed when the timestamp arrives
 * why:  the event that precipitated this trigger
 *
 * Interface TriggerInterface
 * @package Alaress\Schedule\Trigger
 */
interface TriggerInterface
{

    /**
     * Return the timestamp at which this trigger should fire (can be in the past)
     *
     * @return int
     */
    public function getTimestamp();

    /**
     * Return an array of callables that are appropriate for this trigger
     *
     * @return callable[]
     */
    public function getCallbacks();

    /**
     * Return the trigger label that this trigger is based on
     *
     * @return string
     */
    public function getLabel();

    /**
     * Return the event that precipitated this trigger
     *
     * @return EventInterface
     */
    public function getEvent();


    /**
     * Return a representation of self in the form "objectClass:label"
     *
     * @return string
     */
    public function getIdString();


    /**
     * Return a hash representation of self
     *
     * @return string
     */
    public function getHash();


    /**
     * Return an identifier of attached event object, if available
     *
     * @return string
     */
    public function getEventObjectId(): string;


    /**
     * Return a hash representation of attached event object
     *
     * @return string
     */
    public function getEventObjectHash(): string;
}
