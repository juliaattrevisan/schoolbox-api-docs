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


class EventPermission {

	/**
	 * Identifier
	 *
	 * @var null
	 */
	private $id = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * @var int
	 */
	private $userId = -1;

	/**
	 * An instance of \Schoolbox\Campus
	 *
	 * @var int
	 */
	private $campusId = -1;

	/**
	 * An instance of EventStatus
	 *
	 * @var int
	 */
	private $statusId = -1;

    /**
     * Is this user allowed to edit EOTC events in this status?
     *
     * @var bool
     */
    protected $allowed = false;

    /**
     * Is this user subscribed to receive messgaes for EOTC events when they
     * are switched to this status?
     *
     * @var bool
     */
    protected $subscribed = false;

	/**
	 * An instance of \Alaress_Date_Format
	 *
	 * @var null
	 */
	private $createdAt = null;

	/**
	 * An instance of \Alaress_Date_Format
	 *
	 * @var null
	 */
	private $updatedAt = null;

	/**
	 * An instance of \Alaress_Date_Format
	 *
	 * @var null
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
	 * Set campus id
	 *
	 * @param int $campusId
	 */
	public function setCampusId($campusId) {
		$this->campusId = $campusId;
	}

	/**
	 * Get campus id
	 *
	 * @return int
	 */
	public function getCampusId() {
		return $this->campusId;
	}

	/**
	 * Set id
	 *
	 * @param null $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set status id
	 *
	 * @param int $statusId
	 */
	public function setStatusId($statusId) {
		$this->statusId = $statusId;
	}

	/**
	 * Get status id
	 *
	 * @return int
	 */
	public function getStatusId() {
		return $this->statusId;
	}

	/**
	 * Set user id
	 *
	 * @param int $userId
	 */
	public function setUserId($userId) {
		$this->userId = $userId;
	}

	/**
	 * Get user id
	 *
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * Set created at
	 *
	 * @param null $createdAt
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}

	/**
	 * Get created at
	 *
	 * @return null
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param null $deletedAt
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
	}

	/**
	 * Get deleted at
	 *
	 * @return null
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
	}

	/**
	 * Set updated at
	 *
	 * @param null $updatedAt
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Get updated at
	 *
	 * @return null
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
