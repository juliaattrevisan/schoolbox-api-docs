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
	public $id;

	/**
	 * @var int
	 */
	public $definitionId;

	/**
	 * @var Component_Definition
	 */
	public $definition;

	/**
	 * @var string
	 */
	public $module;

	/**
	 * @var int
	 */
	public $identifier;

	/**
	 * @var string
	 */
	public $position;

	/**
	 * @var int
	 */
	public $sequence;

	/**
	 * @var Schoolbox\Collection
	 */
	public $config;

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
	 * @param  string $definition
	 *
	 * @return $this
	 */
	public function setDefinition($definition) {
		$this->definition = $definition;
		return $this;
	}

	/**
	 * @return string
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



	public function getConfigArray($compat = true) {

		if(isset($this->config)) {

			// dead index, this is actually not a config
			if(count($this->config) == 1 && isset($this->config[''])) {
				return array();
			}

			$output = array();
			foreach($this->config as $opt) {
				$key = $compat ? $this->compatMap($opt['key']) : $opt['key'];
				$val = isset($opt['value']) ? $opt['value'] : $opt['defaultValue'];
				$output[$key] = array(
					'label' => $opt['label'],
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