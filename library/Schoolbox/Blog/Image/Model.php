<?php
/**
 * Model for normal (non-eportfolio) images
 * 
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Image;

class Model {

	/** @var int */
	protected $id;

	/** @var int */
	protected $blogId;

	/** @var string */
	protected $hash;

	/** @var \Alaress_Date_Format */
	protected $createdAt;

	/** @var \Alaress_Date_Format */
	protected $updatedAt;

	/** @var \Alaress_Date_Format */
	protected $deletedAt;

	/**
	 * @param int $id
	 * @return self
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
	 * @param int $blogId
	 * @return self
	 */
	public function setBlogId($blogId) {
		$this->blogId = $blogId;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getBlogId() {
		return $this->blogId;
	}

	/**
	 * @param string $hash
	 * @return self
	 */
	public function setHash($hash) {
		$this->hash = $hash;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getHash() {
		return $this->hash;
	}

	/**
	 * @param \Alaress_Date_Format $createdAt
	 * @return self
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * @param \Alaress_Date_Format $updatedAt
	 * @return self
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * @param \Alaress_Date_Format $deletedAt
	 * @return self
	 */
	public function setDeleted($deletedAt) {
		$this->deletedAt = $deletedAt;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
	}
} 