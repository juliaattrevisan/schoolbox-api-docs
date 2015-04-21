<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\News;


use Schoolbox\Campus;
use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Role;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\User;

class Article implements ISoftDeletable {

	/** @var int */
	protected $id;

	/** @var Topic */
	protected $topic;

	/** @var string */
	protected $heading;

	/** @var \Alaress_Date_Format */
	protected $postDate;

	/** @var \Alaress_Date_Format */
	protected $publishDate;

	/** @var \Alaress_Date_Format */
	protected $expiryDate;

	/** @var \Alaress_Date_Format */
	protected $modifiedDate;

	/** @var string */
	protected $body;

	/** @var bool */
	protected $deleted;

	/** @var User */
	protected $owner;

	/** @var IStorage */
	protected $image;

	/** @var Role[]|Collection */
	protected $roles;

	/** @var Campus[]|Collection */
	protected $campuses;

	/** @var User[]|Collection */
	protected $users;

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
	 * @param boolean $deleted
	 * @return self
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
		return $this;
	}

	/**
	 * @return boolean
	 */
	public function getDeleted() {
		return $this->deleted;
	}

	/**
	 * @param \Alaress_Date_Format $expiryDate
	 * @return self
	 */
	public function setExpiryDate($expiryDate) {
		$this->expiryDate = $expiryDate;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getExpiryDate() {
		return $this->expiryDate;
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
	 * @param IStorage $image
	 * @return self
	 */
	public function setImage($image) {
		$this->image = $image;
		return $this;
	}

	/**
	 * @return IStorage
	 */
	public function getImage() {
		return $this->image;
	}

	/**
	 * @param \Alaress_Date_Format $modifiedDate
	 * @return self
	 */
	public function setModifiedDate($modifiedDate) {
		$this->modifiedDate = $modifiedDate;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getModifiedDate() {
		return $this->modifiedDate;
	}

	/**
	 * @param \Schoolbox\User $owner
	 * @return self
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
		return $this;
	}

	/**
	 * @return \Schoolbox\User
	 */
	public function getOwner() {
		return $this->owner;
	}

	/**
	 * @param \Alaress_Date_Format $postDate
	 * @return self
	 */
	public function setPostDate($postDate) {
		$this->postDate = $postDate;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getPostDate() {
		return $this->postDate;
	}

	/**
	 * @param \Alaress_Date_Format $publishDate
	 * @return self
	 */
	public function setPublishDate($publishDate) {
		$this->publishDate = $publishDate;
		return $this;
	}

	/**
	 * @return \Alaress_Date_Format
	 */
	public function getPublishDate() {
		return $this->publishDate;
	}

	/**
	 * @param \Schoolbox\News\Topic $topic
	 * @return self
	 */
	public function setTopic($topic) {
		$this->topic = $topic;
		return $this;
	}

	/**
	 * @return \Schoolbox\News\Topic
	 */
	public function getTopic() {
		return $this->topic;
	}

	/**
	 * Is this object deleted?
	 *
	 * @return bool
	 */
	public function isDeleted() {
		return !!$this->getDeleted();
	}

	/**
	 * @param \Schoolbox\Campus[]|\Schoolbox\Collection $campuses
	 * @return self
	 */
	public function setCampuses($campuses) {
		$this->campuses = $campuses;
		return $this;
	}

	/**
	 * @return \Schoolbox\Campus[]|\Schoolbox\Collection
	 */
	public function getCampuses() {
		return $this->campuses;
	}

	/**
	 * @param \Schoolbox\Collection|\Schoolbox\Role[] $roles
	 * @return self
	 */
	public function setRoles($roles) {
		$this->roles = $roles;
		return $this;
	}

	/**
	 * @return \Schoolbox\Collection|\Schoolbox\Role[]
	 */
	public function getRoles() {
		return $this->roles;
	}

	/**
	 * @param \Schoolbox\Collection|\Schoolbox\User[] $users
	 * @return self
	 */
	public function setUsers($users) {
		$this->users = $users;
		return $this;
	}

	/**
	 * @return \Schoolbox\Collection|\Schoolbox\User[]
	 */
	public function getUsers() {
		return $this->users;
	}


}
