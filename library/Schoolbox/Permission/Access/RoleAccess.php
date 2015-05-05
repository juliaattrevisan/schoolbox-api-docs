<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Permission\Access;


use Schoolbox\Resource\Folder\Model as Category;
use Schoolbox\Role;

class RoleAccess {

	/** @var Category */
	protected $category;

	/** @var Role */
	protected $role;

	/** @var int */
	protected $type;

	/**
	 * @param Category $category
	 * @return self
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @return Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param \Schoolbox\Role $role
	 * @return self
	 */
	public function setRole($role) {
		$this->role = $role;
		return $this;
	}

	/**
	 * @return \Schoolbox\Role
	 */
	public function getRole() {
		return $this->role;
	}

	/**
	 * @param int $type
	 * @return self
	 */
	public function setType($type) {
		$this->type = $type;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}



} 