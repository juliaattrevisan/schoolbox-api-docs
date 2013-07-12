<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_ConfigDefinition extends Model {

	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var int
	 */
	public $definitionId;

	/**
	 * @var string
	 */
	public $key;

	/**
	 * @var string
	 */
	public $defaultValue;

	/**
	 * @var string
	 */
	public $label;

	/**
	 * @param  string $defaultValue
	 *
	 * @return $this
	 */
	public function setDefaultValue($defaultValue) {
		$this->defaultValue = $defaultValue;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDefaultValue() {
		return $this->defaultValue;
	}

	/**
	 * @param  int $definitionId
	 *
	 * @return $this
	 */
	public function setDefinitionId($definitionId) {
		$this->definitionId = $definitionId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getDefinitionId() {
		return $this->definitionId;
	}

	/**
	 * @param  int $id
	 *
	 * @return $this
	 */
	public function setId($id) {
		$this->id = $id;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param  string $key
	 *
	 * @return $this
	 */
	public function setKey($key) {
		$this->key = $key;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getKey() {
		return $this->key;
	}

	/**
	 * @param  string $label
	 *
	 * @return $this
	 */
	public function setLabel($label) {
		$this->label = $label;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getLabel() {
		return $this->label;
	}

}