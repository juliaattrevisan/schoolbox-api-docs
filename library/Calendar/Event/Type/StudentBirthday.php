<?php
/**
 * Calendar_Event_Type_StudentBirthday
 *
 * Uses the Visitor pattern to render via an external class, finally decoupling
 * the data structure from rendering methodology in a highly granular environment
 * without needing to use singletons.
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 * @version	 SVN: $Id$
 */
class Calendar_Event_Type_StudentBirthday extends Calendar_Event_Type_Abstract {

	/**
	 * Renderer
	 *
	 * @var string
	 */
	protected $rendererClass = 'Calendar_Event_Type_StudentBirthday_Renderer_Javascript';

	/**
	 * Actual birthdate
	 *
	 * @var string
	 */
	protected $date = '';

	/* Hardcoded?
	 */
	protected $eventType = 'Birthday';

	/**
	 * The Birthday Person's name
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * The Birthday Person's age
	 *
	 * @var int
	 */
	private $age = -1;

	/* Form? What is this
	 */
	private $form = '';

	/**
	 * Render via external class
	 *
	 * @param \Calendar_Event_StudentBirthday_Renderer $visitor
	 *
	 * @return mixed
	 */
	public function accept($visitor) {
		return $visitor->visit($this);
	}

	/**
	 * Sets the event note in a standardised way
	 */
	public function setBirthdayNote() {
		$this->setTitle('Student Birthday - ' . $this->getName() . ' in ' . $this->getForm() . ' turns ' . $this->getAge());
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
