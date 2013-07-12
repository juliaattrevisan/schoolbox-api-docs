<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_Config extends Model {
	/**
	 * @var int
	 */
	public $id;

	/**
	 * @var int
	 */
	public $configDefinitionId;

	/**
	 * @var int
	 */
	public $instanceId;

	/**
	 * @var string
	 */
	public $value;

	/**
	 * @param  int $configDefinitionId
	 *
	 * @return $this
	 */
	public function setConfigDefinitionId($configDefinitionId) {
		$this->configDefinitionId = $configDefinitionId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getConfigDefinitionId() {
		return $this->configDefinitionId;
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
	 * @param  int $instanceId
	 *
	 * @return $this
	 */
	public function setInstanceId($instanceId) {
		$this->instanceId = $instanceId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getInstanceId() {
		return $this->instanceId;
	}

	/**
	 * @param  string $value
	 *
	 * @return $this
	 */
	public function setValue($value) {
		$this->value = $value;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getValue() {
		return $this->value;
	}

}