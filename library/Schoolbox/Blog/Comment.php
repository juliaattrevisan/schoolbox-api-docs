<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Blog;


use Alaress_Date_Format;
use Schoolbox\User;

class Comment {

	/** @var int */
	protected $id;

	/** @var Blog */
	protected $blog;

	/** @var User */
	protected $owner;

	/** @var Alaress_Date_Format */
	protected $created;

	/** @var Alaress_Date_Format */
	protected $deleted;

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
	 * @param Blog $blog
	 * @return self
	 */
	public function setBlog($blog) {
		$this->blog = $blog;
		return $this;
	}


	/**
	 * @return Blog
	 */
	public function getBlog() {
		return $this->blog;
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
	 * @param Alaress_Date_Format $created
	 * @return self
	 */
	public function setCreated($created) {
		$this->created = $created;
		return $this;
	}


	/**
	 * @return Alaress_Date_Format
	 */
	public function getCreated() {
		return $this->created;
	}


	/**
	 * @param Alaress_Date_Format $deleted
	 * @return self
	 */
	public function setDeleted($deleted) {
		$this->deleted = $deleted;
		return $this;
	}


	/**
	 * @return Alaress_Date_Format
	 */
	public function getDeleted() {
		return $this->deleted;
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