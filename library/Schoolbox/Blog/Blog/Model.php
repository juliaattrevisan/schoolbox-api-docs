<?php
/**
 * Model for normal (non-eportfolio) blog posts
 * 
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog\Blog;

use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;

class Model {

	/** @var int */
	protected $id;

	/** @var Folder */
	protected $folder;

	/** @var User */
	protected $owner;

	/** @var \Alaress_Date_Format */
	protected $createdAt;

	/** @var \Alaress_Date_Format */
	protected $deletedAt;

	/** @var string */
	protected $heading;

	/** @var string */
	protected $body;

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
	 * @param Folder $folder
	 * @return self
	 */
	public function setFolder($folder) {
		$this->folder = $folder;
		return $this;
	}

	/**
	 * @return Folder
	 */
	public function getFolder() {
		return $this->folder;
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
	 * @param \Alaress_Date_Format $deleted
	 * @return self
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
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
}