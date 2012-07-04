<?php
/**
 * Calendar_Event_StudentBirthday
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_StudentBirthday extends Calendar_Event_Abstract {

	/* Contextual: The actual birthdate
	 */
	protected $date = '';

	/* Hardcoded?
	 */
	protected $eventType = 'Birthday';

	/* The Birthday person's name and age
	 */
	private $name = '';
	private $age = -1;

	/* Form? What is this
	 */
	private $form = '';

	/**
	 * Sets the event note in a standardised way
	 */
	public function setBirthdayNote() {
		$this->setNote('Student Birthday - ' . $this->getName() . ' in ' . $this->getForm() . ' turns ' . $this->getAge());
	}

	/* Getters/Setters
	 */

	/**
	 * Set name
	 *
	 * @param $name
	 */
	public function setName($name) {
		$this->name = $name;
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Set age
	 *
	 * @param $age
	 */
	public function setAge($age) {
		$this->age = $age;
	}

	/**
	 * Get age
	 *
	 * @return int
	 */
	public function getAge() {
		return $this->age;
	}

	/**
	 * Set form
	 *
	 * @param $form
	 */
	public function setForm($form) {
		$this->form = $form;
	}

	/**
	 * Get form
	 *
	 * @return string
	 */
	public function getForm() {
		return $this->form;
	}
}
