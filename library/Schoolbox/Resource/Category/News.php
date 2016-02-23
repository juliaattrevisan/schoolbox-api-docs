<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Category;


use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class News {

	/** @var int */
	protected $id;

	/** @var string */
	protected $heading;

	/** @var \Alaress_Date_Format */
	protected $postDate;

	/** @var \Alaress_Date_Format */
	protected $publishDate;

	/** @var \Alaress_Date_Format */
	protected $expiryDate;

	/** @var string */
	protected $body;

	/** @var bool */
	protected $deleted;

	/** @var Folder */
	protected $category;

	/** @var int */
	protected $owner;


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
	 * @param string $heading
	 * @return self
	 */
	public function setHeading($heading) {
		$this->heading = $heading;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getHeading() {
		return $this->heading;
	}


	/**
	 * @param \Alaress_Date_Format $datetimePosted
	 * @return self
	 */
	public function setPostDate($datetimePosted) {
		$this->postDate = $datetimePosted;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getPostDate() {
		return $this->postDate;
	}


	/**
	 * @param \Alaress_Date_Format $datetimePublished
	 * @return self
	 */
	public function setPublishDate($datetimePublished) {
		$this->publishDate = $datetimePublished;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getPublishDate() {
		return $this->publishDate;
	}


	/**
	 * @param \Alaress_Date_Format $datetimeExpires
	 * @return self
	 */
	public function setExpiryDate($datetimeExpires) {
		$this->expiryDate = $datetimeExpires;
		return $this;
	}


	/**
	 * @return \Alaress_Date_Format
	 */
	public function getExpiryDate() {
		return $this->expiryDate;
	}


	/**
	 * @param string $body
	 * @return self
	 */
	public function setBody($body) {
		$this->body = $body;
		return $this;
	}


	/**
	 * @return string
	 */
	public function getBody() {
		return $this->body;
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
	 * @param User $owner
	 * @return self
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		return $this;
	}


	/**
	 * @return User
	 */
	public function getOwner() {
		return $this->owner;
	}

} 