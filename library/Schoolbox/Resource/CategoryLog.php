<?php
/**
 * Model for category/homepage access logs.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 

namespace Schoolbox\Resource;

class CategoryLog {
	/**
	 * @var \Schoolbox\Resource\Category
	 */
	protected $category;

	/**
	 * @var \Schoolbox\User
	 */
	protected $user;

	/**
	 * @var \Alaress_Date_Format
	 */
	protected $last_access_time;

	/**
	 * @var int
	 */
	protected $hitcount;

	/**
	 * Auto-generated methods
	 */

	/**
	 * @param  \Schoolbox\Resource\Category $category
	 *
	 * @return $this
	 */
	public function setCategory($category) {
		$this->category = $category;
		return $this;
	}

	/**
	 * @return \Schoolbox\Resource\Category
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param  int $hitcount
	 *
	 * @return $this
	 */
	public function setHitcount($hitcount) {
		$this->hitcount = $hitcount;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getHitcount() {
		return $this->hitcount;
	}

	/**
	 * @param  \Alaress_Date_Format $last_access_time
	 *
	 * @return $this
	 */
	public function setLastAccessTime($last_access_time) {
		$this->last_access_time = $last_access_time;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getLastAccessTime() {
		return $this->last_access_time;
	}

	/**
	 * @param  \Schoolbox\User $user
	 *
	 * @return $this
	 */
	public function setUser($user) {
		$this->user = $user;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getUser() {
		return $this->user;
	}

}