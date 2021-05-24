<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

use Schoolbox\User;

class Reroute
{

    /** @var User */
    protected $user;

    /** @var Source */
    protected $source;

    /** @var array */
    protected $delivery;

    /**
     * @param \Schoolbox\Realtime\Message\Source $source
     */
    public function setSource($source)
    {
        $this->source = $source;
    }

    /**
     * @return \Schoolbox\Realtime\Message\Source
     */
    public function getSource()
    {
        return $this->source;
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
     * @return array
     */
    public function getDelivery()
    {
        return $this->delivery;
    }


    /**
     * @param array $delivery
     * @return static
     */
    public function setDelivery($delivery)
    {
        $this->delivery = $delivery;
        return $this;
    }
}
