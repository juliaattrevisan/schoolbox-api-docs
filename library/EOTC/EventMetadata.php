<?php
/**
 * EventMetadata
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC;


class EventMetadata {

	/**
	 * Primary key
	 * 
	 * @var null
	 */
	private $id = null;

	/**
	 * An instance of Calendar\EventType
	 *
	 * @var int
	 */
	private $eventType = -1;

	/**
	 * An instance of EventStatus
	 *
	 * @var int
	 */
	private $status = 1;

	/**
	 * Whether RAMS is selected for this event
	 *
	 * @var bool
	 */
	private $ramsRequired = false;

	/**
	 * Created at
	 *
	 * @var null
	 */
	private $createdAt = null;

	/**
	 * Updated at
	 *
	 * @var null
	 */
	private $updatedAt = null;

	/**
	 * Deleted at
	 *
	 * @var null
	 */
	private $deletedAt = null;

	/**
	 * Construtor
	 */
	public function __construct() {
	}

	/* Getters/Setters
	 */

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
	 * Set event type
	 *
	 * @param int $eventType
	 */
	public function setEventType($eventType) {
		$this->eventType = $eventType;
	}

	/**
	 * Get event type
	 *
	 * @return int
	 */
	public function getEventType() {
		return $this->eventType;
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
	 * Set rams required
	 *
	 * @param boolean $ramsRequired
	 */
	public function setRamsRequired($ramsRequired) {
		$this->ramsRequired = $ramsRequired;
	}

	/**
	 * Get rams required
	 *
	 * @return boolean
	 */
	public function getRamsRequired() {
		return $this->ramsRequired;
	}

	/**
	 * Set status
	 *
	 * @param int $status
	 */
	public function setStatus($status) {
		$this->status = $status;
	}

	/**
	 * Get status
	 *
	 * @return int
	 */
	public function getStatus() {
		return $this->status;
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
}