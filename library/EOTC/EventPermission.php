<?php
/**
 * EventPermission
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;

use Alaress_Date_Format;
use Schoolbox\User;
use Schoolbox\Campus;

class EventPermission {

	/**
	 * Identifier
	 *
	 * @var int
	 */
	private $id = null;

	/**
	 * The user to whom this permissions/subscriptions applies
	 *
	 * @var User
	 */
	private $user;

	/**
	 * The campus to which this permissions/subscriptions applies
	 *
	 * @var Campus
	 */
	private $campus;

	/**
	 * The status to which this permissions/subscriptions applies
	 *
	 * @var EventStatus
	 */
	private $status;

    /**
     * Is this user allowed to edit EOTC events in this status for this campus?
     *
     * @var bool
     */
    protected $allowed = false;

    /**
     * Is this user subscribed to receive messages for EOTC events when they
     * are switched to this status?
     *
     * @var bool
     */
    protected $subscribed = false;

	/**
	 * When was this created?
	 *
	 * @var Alaress_Date_Format
	 */
	private $createdAt = null;

	/**
	 * When was this updated
	 *
	 * @var Alaress_Date_Format
	 */
	private $updatedAt = null;

	/**
	 * When was this deleted?
	 *
	 * @var Alaress_Date_Format
	 */
	private $deletedAt = null;

	/**
	 * Constructor
	 */
	public function __construct() {
	}

	/* Getters/Setters
	 */

	/**
	 * Set campus
	 *
	 * @param Campus $campus
     * @return self $this
	 */
	public function setCampus($campus) {
		$this->campus = $campus;
        return $this;
	}

	/**
	 * Get campus
	 *
	 * @return Campus
	 */
	public function getCampus() {
		return $this->campus;
	}

	/**
	 * Set id
	 *
	 * @param int $id
     * @return self $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * Get id
	 *
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set status
	 *
	 * @param EventStatus $status
     * @return self $this
	 */
	public function setStatus($status) {
		$this->status = $status;
		return $this;
	}

	/**
	 * Get status
	 *
	 * @return EventStatus
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * Set user id
	 *
	 * @param User
     * @return self $this
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * Get user
	 *
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	/**
	 * Set created at
	 *
	 * @param Alaress_Date_Format $createdAt
     * @return self $this
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
		return $this;
	}

	/**
	 * Get created at
	 *
	 * @return Alaress_Date_Format
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param Alaress_Date_Format $deletedAt
     * @return self $this
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
		return $this;
	}

	/**
	 * Get deleted at
	 *
	 * @return Alaress_Date_Format
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
	}

	/**
	 * Set updated at
	 *
	 * @param Alaress_Date_Format $updatedAt
     * @return self $this
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
		return $this;
	}

	/**
	 * Get updated at
	 *
	 * @return Alaress_Date_Format
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

    /**
     * @return boolean
     */
    public function getAllowed()
    {
        return $this->allowed;
    }

    /**
     * @param  boolean $allowed
     *
     * @return $this
     */
    public function setAllowed($allowed)
    {
        $this->allowed = $allowed;
        return $this;
    }

    /**
     * @return boolean
     */
    public function getSubscribed()
    {
        return $this->subscribed;
    }

    /**
     * @param  boolean $subscribed
     *
     * @return $this
     */
    public function setSubscribed($subscribed)
    {
        $this->subscribed = $subscribed;
        return $this;
    }
}
