<?php
/**
 * Test model used by TestMapper.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Test;

class TestModel {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var string
	 */
	protected $filename;

	/**
	 * @var bool
	 */
	protected $is_viewed = true;

	/**
	 * @var string
	 */
	protected $file;

	/**
	 * @var TestModel
	 */
	protected $parent;

	/**
	 * @param string $file
	 * @return $this;
	 */
	public function setFile($file) {
		$this->file = $file;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFile() {
		return $this->file;
	}

	/**
	 * @param string $filename
	 * @return $this;
	 */
	public function setFilename($filename) {
		$this->filename = $filename;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
	}

	/**
	 * @param int $id
	 * @return $this;
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
	 * @param boolean $is_viewed
	 * @return $this;
	 */
	public function setIsViewed($is_viewed) {
		$this->is_viewed = $is_viewed;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getIsViewed() {
		return $this->is_viewed;
	}

	/**
	 * @param  \Schoolbox\Test\TestModel $parent
	 *
	 * @return $this
	 */
	public function setParent($parent) {
		$this->parent = $parent;
		return $this;
	}

	/**
	 * @return \Schoolbox\Test\TestModel
	 */
	public function getParent() {
		return $this->parent;
	}
}