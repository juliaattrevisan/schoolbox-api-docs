<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_Instance extends Model {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $definitionId;

	/**
	 * @var Component_Definition
	 */
	protected $definition;

	/**
	 * @var string
	 */
	protected $module;

	/**
	 * @var int
	 */
	protected $identifier;

	/**
	 * @var string
	 */
	protected $position;

	/**
	 * @var int
	 */
	protected $sequence;

	/**
	 * An unmapped count of instances of this item.
	 *
	 * @var int
	 */
	protected $instanceCount = 0;

	/**
	 * @var Schoolbox\Collection
	 */
	protected $config;

	public function __construct() {
		$this->setConfig(new \Schoolbox\Collection());
	}

	/**
	 * @param  \Schoolbox\Collection $config
	 *
	 * @return $this
	 */
	public function setConfig($config) {
		$this->config = $config;
		return $this;
	}

	/**
	 * @return \Schoolbox\Collection
	 */
	public function getConfig() {
		return $this->config;
	}

	/**
	 * @param  Component_Definition $definition
	 *
	 * @return $this
	 */
	public function setDefinition($definition) {
		$this->definition = $definition;
		return $this;
	}

	/**
	 * @return Component_Definition
	 */
	public function getDefinition() {
		return $this->definition;
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
	 * @param  int $identifier
	 *
	 * @return $this
	 */
	public function setIdentifier($identifier) {
		$this->identifier = $identifier;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getIdentifier() {
		return $this->identifier;
	}

	/**
	 * @param  string $module
	 *
	 * @return $this
	 */
	public function setModule($module) {
		$this->module = $module;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getModule() {
		return $this->module;
	}

	/**
	 * @param  string $position
	 *
	 * @return $this
	 */
	public function setPosition($position) {
		$this->position = $position;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getPosition() {
		return $this->position;
	}

	/**
	 * @param  int $sequence
	 *
	 * @return $this
	 */
	public function setSequence($sequence) {
		$this->sequence = $sequence;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getSequence() {
		return $this->sequence;
	}

	/**
	 * @param  int   $instanceCount
	 * @return $this
	 */
	public function setInstanceCount($instanceCount) {
		$this->instanceCount = $instanceCount;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getInstanceCount() {
		return $this->instanceCount;
	}

	/**
	 * Increments the instance count for this definition.
	 *
	 * @return void
	 */
	public function incrementInstanceCount() {
		$this->instanceCount++;
	}



	public function getConfigArray($compat = true) {

		if(isset($this->config)) {

			// dead index, this is actually not a config
			$dumb_index = $this->config->getOffset('');
			if(count($this->config) == 1 && isset($dumb_index)) {
				return array();
			}

			$output = array();
			/** @var Component_ConfigDefinition $opt */
			foreach($this->config as $opt) {
				$key = $compat ? $this->compatMap($opt->getKey()) : $opt->getKey();
				$val = $opt->getValue();
				if (!isset($val)) {
					$val = $opt->getDefaultValue();
				}
				$output[$key] = array(
					'label' => $opt->getLabel(),
					'value' => $val,
				);
			}
			return $output;
		}
		return array();
	}

	/**
	 * Converts new keys to old keys to preserve integrity with old components
	 * @param $key
	 * @return mixed
	 */
	protected function compatMap($key) {
		$map = array(
			'limit'  => 'size',
			'title'  => 'componentname',
		);
		if(isset($map[$key])) {
			return $map[$key];
		}
		return $key;
	}
}
