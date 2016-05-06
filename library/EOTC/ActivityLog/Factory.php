<?php
/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace EOTC\ActivityLog;

use \EOTC\Event;
use \Schoolbox\User;

class Factory {

	/**
	 * An instance of \EOTC\Event
	 *
 	 * @var Event
	 */
	private $event = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * @var User
	 */
	private $actioner = null;

	/**
	 * Factory constructor.
	 *
	 * @param Event $event
	 * @param User $actioner
	 */
	public function __construct(Event $event, User $actioner) {
		$this->setEvent($event);
		$this->setActioner($actioner);
	}

	/**
	 * Spawn a log
	 *
	 * @param string $type
	 * @return Log
	 */
	public function produce($type) {
		switch($type) {
			case 'StatusChange':
				$str = $this->getActioner()->getFullname() . ' changed status to ' . $this->getEvent()->getMetadata()->getStatus()->getName();
				break;

			case 'CommentChange':
				$str = $this->getActioner()->getFullname() . ' commented: ' . $this->getEvent()->getMetadata()->getComment();
				break;

			default:
				$str = '';
				break;
		}

		$log = new Log();
		$log->setActioner($this->getActioner());
		$log->setMetadataId($this->getEvent()->getMetadata()->getId());
		$log->setMessage($str);
		$log->setCreatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));
		$log->setUpdatedAt(new \Alaress_Date_Format($_SERVER['REQUEST_TIME'], 'U'));

		return $log;
	}

	/* Getters/Setters
	 */

	/**
	 * Set actioner
	 *
	 * @param User $actioner
	 */
	public function setActioner($actioner) {
		$this->actioner = $actioner;
	}

	/**
	 * Get actioner
	 *
	 * @return User
	 */
	public function getActioner() {
		return $this->actioner;
	}

	/**
	 * Set event
	 *
	 * @param Event $event
	 */
	public function setEvent($event) {
		$this->event = $event;
	}

	/**
	 * Get event
	 *
	 * @return Event
	 */
	public function getEvent() {
		return $this->event;
	}
}