<?php

class Exception_User extends Exception {
	private $definitionClass;
	private $defaultDefinitionClass = 'Exception_Locale_Default_User';
	private $errorMessage;

	public function __construct($errorMessage) {

		/* Remember error message
		 */
		$this->setErrorMessage($errorMessage);

		/* Localise this via config table
		 */
		$className = 'Exception_Locale_Wenona_User';

		if (class_exists($className, true)) {
			$this->setDefinitionClass($className);
		}
		else {
			$this->setDefinitionClass($this->getDefaultDefinitionClass());
		}
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
	 * Get the class used to store default definitions
	 *
	 * @param  $val
	 * @return void
	 */
	private function setDefaultDefinitionClass() {
		$this->defaultDefinitionClass;
	}

	/**
	 * Set the class used to store definitions
	 *
	 * @param  $val
	 * @return void
	 */
	private function setDefinitionClass($val) {
		$this->definitionClass = $val;
	}

	/**
	 * Get the class used to store definitions
	 *
	 * @return
	 */
	private function getDefinitionClass() {
		return $this->definitionClass;
	}

	/**
	 * Set the error key provided by the author
	 *
	 * @param  $val
	 * @return void
	 */
	private function setErrorMessage($val) {
		$this->errorMessage = $val;
	}

	/**
	 * Get the error key provided by the author
	 *
	 * @return
	 */
	private function getErrorMessage() {
		return $this->errorMessage;
	}
}