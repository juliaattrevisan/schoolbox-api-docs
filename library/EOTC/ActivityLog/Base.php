<?php
/**
 * EventActivityLog
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ActivityLog;


abstract class Base {

	/**
	 * Log id
	 *
	 * @var string
	 */
	protected $id = '';

	/**
	 * An instance of \Schoolbox\User
	 *
	 * @var null
	 */
	protected $actioner = null;

	/**
	 * An instance of Event
	 *
	 * @var null
	 */
	protected $event = null;

	/**
	 * Partial message
	 *
	 * @var string
	 */
	protected $text = '';

	/**
	 * The message to display
	 *
	 * @var string
	 */
	protected $message = '';

	/**
	 * An instance of Alaress_Date_Format
	 *
	 * @var null
	 */
	protected $createdAt = null;

	/**
	 * An instance of Alaress_Date_Format
	 *
	 * @var null
	 */
	protected $updatedAt = null;

	/**
	 * An instance of Alaress_Date_Format
	 *
	 * @var null
	 */
	protected $deletedAt = null;

	/**
	 * The final notification string
	 *
	 * @var string
	 */
	protected $result = '';

	/**
	 * Constructor
	 */
	public function __construct($event, $actioner) {
		$this->setEvent($event);
		$this->setActioner($actioner);

		$this->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
		$this->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

		$this->setup();
	}

	/**
	 * Hook method
	 */
	protected function setup() {
	}

	/**
	 * Notify
	 *
	 * @return mixed|void
	 */
	public function notify() {
		$mapper = new Mapper();
		$mapper->save($this);
	}

	/* Getters/Setters
	 */

	/**
	 * Set actioner
	 *
	 * @param null $actioner
	 */
	public function setActioner($actioner) {
		$this->actioner = $actioner;
	}

	/**
	 * Get actioner
	 *
	 * @return null
	 */
	public function getActioner() {
		return $this->actioner;
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
	 * Set event
	 *
	 * @param null $event
	 */
	public function setEvent($event) {
		$this->event = $event;
	}

	/**
	 * Get event
	 *
	 * @return null
	 */
	public function getEvent() {
		return $this->event;
	}

	/**
	 * Set id
	 *
	 * @param string $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get id
	 *
	 * @return string
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set message
	 *
	 * @param string $message
	 */
	public function setMessage($message) {
		$this->message = $message;
	}

	/**
	 * Get message
	 *
	 * @return string
	 */
	public function getMessage() {
		return $this->message;
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
	 * Set text
	 *
	 * @param string $text
	 */
	public function setText($text) {
		$this->text = $text;
	}

	/**
	 * Get text
	 *
	 * @return string
	 */
	public function getText() {
		return $this->text;
	}
}