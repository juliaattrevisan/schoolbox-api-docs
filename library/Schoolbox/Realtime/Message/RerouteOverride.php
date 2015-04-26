<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime\Message;


use Schoolbox\User;

class RerouteOverride {

    const DEFAULT_UNFOLLOW = 0;
    const DEFAULT_FOLLOW   = 1;
    const FORCE_FOLLOW     = 2;


	/** @var User */
	protected $user;

	/** @var string */
	protected $routeKey;

    /** @var array */
    protected $delivery;

    /**
     * @deprecated
     * @var int
     */
	protected $outgoingLevel;


	/**
	 * @param string $msgRouteKey
	 */
	public function setRouteKey($msgRouteKey) {
		$this->routeKey = $msgRouteKey;
	}

	/**
	 * @return string
	 */
	public function getRouteKey() {
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
     * @deprecated use setDelivery()
	 * @param int $outgoingLevel
	 */
	public function setOutgoingLevel($outgoingLevel) {
		$this->outgoingLevel = $outgoingLevel;
	}

	/**
     * @deprecated use getDelivery()
	 * @return int
	 */
	public function getOutgoingLevel() {
		return $this->outgoingLevel;
	}

	/**
	 * @param User $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

} 