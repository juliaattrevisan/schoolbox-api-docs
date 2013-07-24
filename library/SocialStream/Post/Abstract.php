<?php
/**
 * SocialStream_Post_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

abstract class SocialStream_Post_Abstract {

	/**
	 * Primary key
	 *
	 * @var
	 */
	protected $postId;

	/**
	 * The person who owns this post
	 *
	 * @var
	 */
	protected $ownerId;

	/**
	 * The homepage on which this post can be found
	 *
	 * @var
	 */
	protected $homepageId;

	/**
	 * The postId to which this post is subordinate
	 *
	 * @var
	 */
	protected $parentId;

	/**
	 * One of Website, Video, Image, File or String
	 *
	 * @var string
	 */
	protected $type = '';

	/**
	 * The mime type of the resource
	 *
	 * @var string
	 */
	protected $mimetype = '';

	/**
	 * THe url to the resource
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * What they typed into that box
	 *
	 * @var string
	 */
	protected $body = '';

	/**
	 * Children posts
	 *
	 * @var null
	 */
	protected $children = null;

	/**
	 * Created at
	 *
	 * @var string
	 */
	protected $createdAt = '';

	/**
	 * Updated at
	 *
	 * @var string
	 */
	protected $updatedAt = '';

	/**
	 * Deleted at
	 *
	 * @var string
	 */
	protected $deletedAt = '';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setChildren(new Schoolbox\Collection());
	}

	/* Getters/Setters
	 */

	/**
	 * Set body
	 *
	 * @param $body
	 */
	public function setBody($body) {
		$this->body = $body;
	}

	/**
	 * Get body
	 *
	 * @return string
	 */
	public function getBody() {
		return $this->body;
	}

	/**
	 * Set created at
	 *
	 * @param $createdAt
	 */
	public function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}

	/**
	 * Get created at
	 *
	 * @return string
	 */
	public function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param $deletedAt
	 */
	public function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
	}

	/**
	 * Get deleted at
	 *
	 * @return string
	 */
	public function getDeletedAt() {
		return $this->deletedAt;
	}

	/**
	 * Set homepage id
	 *
	 * @param $homepageId
	 */
	public function setHomepageId($homepageId) {
		$this->homepageId = $homepageId;
	}

	/**
	 * Get homepage id
	 *
	 * @return mixed
	 */
	public function getHomepageId() {
		return $this->homepageId;
	}

	/**
	 * Set mimetype
	 *
	 * @param $mimetype
	 */
	public function setMimetype($mimetype) {
		$this->mimetype = $mimetype;
	}

	/**
	 * Get mimetype
	 *
	 * @return string
	 */
	public function getMimetype() {
		return $this->mimetype;
	}

	/**
	 * Set owner id
	 *
	 * @param $ownerId
	 */
	public function setOwnerId($ownerId) {
		$this->ownerId = $ownerId;
	}

	/**
	 * Get owner id
	 *
	 * @return mixed
	 */
	public function getOwnerId() {
		return $this->ownerId;
	}

	/**
	 * Set parent id
	 *
	 * @param $parentId
	 */
	public function setParentId($parentId) {
		$this->parentId = $parentId;
	}

	/**
	 * Get parent id
	 *
	 * @return mixed
	 */
	public function getParentId() {
		return $this->parentId;
	}

	/**
	 * Set post id
	 *
	 * @param $postId
	 */
	public function setPostId($postId) {
		$this->postId = $postId;
	}

	/**
	 * Get post id
	 *
	 * @return mixed
	 */
	public function getPostId() {
		return $this->postId;
	}

	/**
	 * Set type
	 *
	 * @param $type
	 */
	public function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Set updated at
	 *
	 * @param $updatedAt
	 */
	public function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Get updated at
	 *
	 * @return string
	 */
	public function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set url
	 *
	 * @param $url
	 */
	public function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	public function getUrl() {
		return $this->url;
	}

	/**
	 * Set children
	 *
	 * @param null $children
	 */
	public function setChildren($children) {
		$this->children = $children;
	}

	/**
	 * Get children
	 *
	 * @return null
	 */
	public function getChildren() {
		return $this->children;
	}
}