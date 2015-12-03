<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Wiki;


use Schoolbox\Resource\Folder\Model as Category;

class Wiki {

	/** @var int */
	protected $id;

	/** @var Category */
	protected $category;

	/** @var Revision */
	protected $latestRevision;

	/** @var string */
	protected $slug;

	/** @var \Alaress_Date_Format */
	protected $created;

	/** @var bool */
	protected $deleted;

	/** @var bool */
	protected $locked;


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
	 * @param Revision $latestRevision
	 * @return self
	 */
	public function setLatestRevision($latestRevision) {
		$this->latestRevision = $latestRevision;
		return $this;
	}


	/**
	 * @return Revision
	 */
	public function getLatestRevision() {
		return $this->latestRevision;
	}


	/**
	 * @param string $slug
	 * @return self
	 */
	public function setSlug($slug) {
		$this->slug = $slug;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getSlug() {
		return $this->slug;
	}


	/**
	 * @param \Alaress_Date_Format $created
	 * @return self
	 */
	public function setCreated($created) {
		$this->created = $created;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getCreated() {
		return $this->created;
	}


	/**
	 * @param bool $deleted
	 * @return self
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
		return $this;
	}


	/**
	 * @return bool
	 */
	public function getDeleted() {
		return $this->deleted;
	}


	/**
	 * @param bool $locked
	 * @return self
	 */
	public function setLocked($locked) {
		$this->locked = $locked;
		return $this;
	}


	/**
	 * @return bool
	 */
	public function getLocked() {
		return $this->locked;
	}


} 
