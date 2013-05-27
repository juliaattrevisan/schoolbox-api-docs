<?php
/**
 * Exception_Abstract
 *
 * Contains common methods that all custom exception handling
 * will need. The messages used can be localised to a particular
 * campus/scool by storing it in the db. The school's name can
 * then be used to figger out what $className should be.
 *
 * This is the foundation for all Exceptions e.g. System Exceptions,
 * Database Exceptions, etc.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
abstract class Exception_Abstract extends Exception {
	protected $exceptionType;
	protected $errorMessage;
	protected $definitionClass;

	/**
	 * Remember error message
	 * Localise this via the Config table
	 *
	 * @param string    $message
	 * @param int       $code
	 * @param Exception $previous
	 */
	public function __construct($message = "", $code = 0, Exception $previous = null) {
		parent::__construct($message, $code, $previous);
		$this->setErrorMessage($message);
		$this->setLocalisation('Wenona');
	}

	/**
	 * Get the error message for a specified constant
	 *
	 * @return mixed
	 */
	public function errorMessage() {
		return constant("{$this->getDefinitionClass()}::{$this->getErrorMessage()}");
	}

	/**
	 * Set the localisation class
	 *
	 * @param  $val
	 * @return void
	 */
	public function setLocalisation($schoolName) {
		$className = "Exception_Locale_{$schoolName}_{$this->getExceptionType()}";

		if (class_exists($className, true)) {
			$this->setDefinitionClass($className);
		}
		else {
			$this->setDefinitionClass("Exception_Locale_Default_{$this->getExceptionType()}");
		}
	}

	/**
	 * Get the exception type
	 *
	 * @return string
	 */
	protected function getExceptionType() {
		return $this->exceptionType;
	}

	/**
	 * Set the class used to store definitions
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setDefinitionClass($val) {
		$this->definitionClass = $val;
	}

	/**
	 * Get the class used to store definitions
	 *
	 * @return
	 */
	protected function getDefinitionClass() {
		return $this->definitionClass;
	}

	/**
	 * Set the error key provided by the author
	 *
	 * @param  $val
	 * @return void
	 */
	protected function setErrorMessage($val) {
		$this->errorMessage = $val;
	}

	/**
	 * Get the error key provided by the author
	 *
	 * @return
	 */
	protected function getErrorMessage() {
		return $this->errorMessage;
	}
}
