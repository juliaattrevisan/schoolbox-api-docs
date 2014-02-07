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


class Factory {

	/**
	 * An instance of \EOTC\Event
	 *
 	 * @var null
	 */
	private $event = null;

	/**
	 * An instance of \Schoolbox\User
	 *
	 * @var null
	 */
	private $actioner = null;

	/**
	 * Constructor
	 */
	public function __construct($event, $actioner) {
		$this->setEvent($event);
		$this->setActioner($actioner);
	}

	/**
	 * Spawn a log
	 *
	 * @param $type
	 * @return Base
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

		$log = new Base();
		$log->setActioner($this->getActioner());
		$log->setEvent($this->getEvent());
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
}