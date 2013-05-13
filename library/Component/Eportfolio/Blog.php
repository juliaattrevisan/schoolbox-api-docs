<?php
/**
 * Component_Eportfolio_Blog
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Component\Eportfolio;

class Blog {

	/**
	 * Primary key
	 *
	 * @var
	 */
	public $id;

	/**
	 * The page on which this blog post will be shown
	 *
	 * @var
	 */
	public $pageId;

	/**
	 * The owner of the blog post
	 *
	 * @var
	 */
	public $uid;

	/**
	 * The owner of the blog post
	 *
	 * @var
	 */
	private $author;

	/**
	 * The heading of the blog post
	 *
	 * @var
	 */
	public $title;

	/**
	 * The body content of the blog post
	 *
	 * @var
	 */
	public $content;

	/**
	 * Comments collection gets stored here
	 *
	 * @var
	 */
	public $comments;

	/**
	 * When the blog item was created
	 *
	 * @var
	 */
	public $created_at;

	/**
	 * When the blog item was updated
	 * @var
	 */
	public $updated_at;

	/**
	 * When the blog item was softdeleted
	 * @var
	 */
	public $deleted_at;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setComments(new \Schoolbox\Collection());
	}

	/* Getters/Setters
	 */

	/**
	 * Set list item id
	 *
	 * @param  $id
	 */
	public function setId($id) {
		$this->id = $id;
	}

	/**
	 * Get list item id
	 *
	 * @return
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * Set title
	 *
	 * @param  $title
	 */
	public function setTitle($title) {
		$this->title = $title;
	}

	/**
	 * Get title
	 *
	 * @return
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * Set author id
	 *
	 * @param  $uid
	 */
	public function setUid($uid) {
		$this->uid = $uid;
	}

	/**
	 * Get author id
	 *
	 * @return
	 */
	public function getUid() {
		return $this->uid;
	}

	/**
	 * Set content
	 *
	 * @param  $content
	 */
	public function setContent($content) {
		$this->content = $content;
	}

	/**
	 * Get content
	 *
	 * @return
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * Set created at
	 *
	 * @param  $created_at
	 */
	public function setCreatedAt($created_at) {
		$this->created_at = $created_at;
	}

	/**
	 * Get created at
	 *
	 * @return
	 */
	public function getCreatedAt() {
		return $this->created_at;
	}

	/**
	 * Set deleted at
	 *
	 * @param  $deleted_at
	 */
	public function setDeletedAt($deleted_at) {
		$this->deleted_at = $deleted_at;
	}

	/**
	 * Get deleted at
	 *
	 * @return
	 */
	public function getDeletedAt() {
		return $this->deleted_at;
	}

	/**
	 * Set updated at
	 *
	 * @param  $updated_at
	 */
	public function setUpdatedAt($updated_at) {
		$this->updated_at = $updated_at;
	}

	/**
	 * Get updated at
	 *
	 * @return
	 */
	public function getUpdatedAt() {
		return $this->updated_at;
	}

	/**
	 * Set page id
	 *
	 * @param  $pageId
	 */
	public function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * Get page id
	 *
	 * @return
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * Set comments
	 *
	 * @param  $comments
	 */
	public function setComments($comments) {
		$this->comments = $comments;
	}

	/**
	 * Get comments
	 *
	 * @return
	 */
	public function getComments() {
		return $this->comments;
	}

	/**
	 * Set author
	 *
	 * @param  $author
	 */
	public function setAuthor($author) {
		$this->author = $author;
	}

	/**
	 * Get author
	 *
	 * @return
	 */
	public function getAuthor() {
		return $this->author;
	}
}