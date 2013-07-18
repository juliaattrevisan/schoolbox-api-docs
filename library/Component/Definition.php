<?php

/**
 * 
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   2011 Alaress Pty Ltd
 * @link        http://www.alaress.com.au
 * @version     SVN: $Id$
 * 
 */

class Component_Definition {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $name;

	/**
	 * @var string
	 */
	protected $class;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var string
	 */
	protected $module;

	/**
	 * @var string
	 */
	protected $visible;

	/**
	 * @var string
	 */
	protected $multiple;

	/**
	 * @var string
	 */
	protected $componentCategory;

	/**
	 * The name of this component's category (as opposed to its ID)
	 *
	 * @var string
	 */
	protected $componentCategoryName;

	/**
	 * An unmapped count of instances of this item.
	 *
	 * @var int
	 */
	protected $instanceCount = 0;

	/**
	 * @param  string $class
	 *
	 * @return $this
	 */
	public function setClass($class) {
		$this->class = $class;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getClass() {
		return $this->class;
	}

	/**
	 * @param  string $componentCategory
	 *
	 * @return $this
	 */
	public function setComponentCategory($componentCategory) {
		$this->componentCategory = $componentCategory;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getComponentCategory() {
		return $this->componentCategory;
	}

	/**
	 * @param  string $componentCategoryName
	 *
	 * @return $this
	 */
	public function setComponentCategoryName($componentCategoryName) {
		$this->componentCategoryName = $componentCategoryName;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getComponentCategoryName() {
		return $this->componentCategoryName;
	}

	/**
	 * @param  string $description
	 *
	 * @return $this
	 */
	public function setDescription($description) {
		$this->description = $description;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
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
	 * @param  string $multiple
	 *
	 * @return $this
	 */
	public function setMultiple($multiple) {
		$this->multiple = $multiple;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getMultiple() {
		return $this->multiple;
	}

	/**
	 * @param  string $name
	 *
	 * @return $this
	 */
	public function setName($name) {
		$this->name = $name;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @param  string $visible
	 *
	 * @return $this
	 */
	public function setVisible($visible) {
		$this->visible = $visible;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getVisible() {
		return $this->visible;
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

}