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
	 * @var int
	 */
	private $userId = -1;

	/**
	 * The campus to which this permissions/subscriptions applies
	 *
	 * @var Campus
	 */
	private $campusId = -1;

	/**
	 * The status to which this permissions/subscriptions applies
	 *
	 * @var int
	 */
	private $statusId = -1;

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
	 * Set campus id
	 *
	 * @param Campus $campusId
	 */
	public function setCampusId($campusId) {
		$this->campusId = $campusId;
	}

	/**
	 * Get campus id
	 *
	 * @return Campus
	 */
	public function getCampusId() {
		return $this->campusId;
	}

	/**
	 * Set id
	 *
	 * @param int $id
	 */
	public function setId($id) {
		$this->id = $id;
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
	 * @param Alaress_Date_Format $createdAt
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
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
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
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
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
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
