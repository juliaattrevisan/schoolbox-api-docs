<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/* Deprecated
 */
namespace Schoolbox\Resource;


use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Folder\Model as Folder;

class Textbox implements ISoftDeletable{

	/** @var int */
	protected $id;

	/** @var Folder */
	protected $category;

	/** @var string */
	protected $heading;

	/** @var string */
	protected $body;

	/** @var bool */
	protected $deleted;

	/**
	 * Find out if this textbox is deleted
	 *
	 * @return bool
	 */
	public function isDeleted() {
		if ($this->getDeleted() == 1) {
			return true;
		}

		return false;
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
	 * @return object
	 */
	public function getId() {
		return $this->id;
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
} 
