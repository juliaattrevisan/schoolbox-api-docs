<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Message;

use Schoolbox\User;

class RerouteOverride
{

    const DEFAULT_UNFOLLOW = 0;
    const DEFAULT_FOLLOW   = 1;
    const FORCE_FOLLOW     = 2;

    /** User ID to be set for message disabled folder */
    public const USER_ID_ALL = 0;


    /** @var User */
    protected $user;

    /** @var string */
    protected $routeKey;

    /** @var array */
    protected $delivery;


    /**
     * @param string $msgRouteKey
     * @return static
     */
    public function setRouteKey($msgRouteKey)
    {
        $this->routeKey = $msgRouteKey;
        return $this;
    }

    /**
     * @return string
     */
    public function getRouteKey()
    {
        return $this->routeKey;
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

    /**
     * @param User $user
     * @return static
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}
