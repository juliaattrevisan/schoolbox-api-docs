<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Permission\Access;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\CreateCollection;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;

/**
 * @method static GenericCollection|self[] createCollection()
 */
class RoleAccess {

    use CreateCollection;

	/** @var Folder */
	protected $category;

	/** @var Role */
	protected $role;

	/** @var int */
	protected $type;

	/**
	 * @param Folder $category
	 * @return self
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @return Folder
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
