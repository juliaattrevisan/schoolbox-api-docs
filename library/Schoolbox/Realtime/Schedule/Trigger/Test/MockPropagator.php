<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Schedule\Trigger\Test;

use Schoolbox\Mapper;
use Schoolbox\Realtime\Schedule\Event\CrudEventInterface;
use Schoolbox\Realtime\Schedule\Trigger\IPropagator;
use Schoolbox\User;

class MockPropagator implements IPropagator
{
    /**
     * Take CRUD parameters and propagate triggers. Returns the key used to store the event in Redis.
     *
     * @param User   $subject
     * @param string $verb
     * @param object $object
     * @param Mapper $mapper
     * @param array  $diff
     * @return string
     */
    public function propagate(User $subject, $verb, $object, $mapper, array $diff = null)
    {
        return '';
    }


    /**
     * Take a CRUD event and propagate triggers. Returns the key used to store the event in Redis.
     *
     * @param CrudEventInterface $event
     * @return string
     */
    public function propagateEvent(CrudEventInterface $event)
    {
        return '';
    }
}
