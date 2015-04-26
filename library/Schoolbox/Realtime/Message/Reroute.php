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

class Reroute {

	/** @var User */
	protected $user;

	/** @var Source */
	protected $source;

    /**
     * @deprecated
     * @var int
     */
	protected $outgoingLevel;

    /** @var array */
    protected $delivery;

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
	 * @param \Schoolbox\Realtime\Message\Source $source
	 */
	public function setSource($source) {
		$this->source = $source;
	}

	/**
	 * @return \Schoolbox\Realtime\Message\Source
	 */
	public function getSource() {
		return $this->source;
	}

	/**
	 * @param \Schoolbox\User $user
	 */
	public function setUser($user) {
		$this->user = $user;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
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