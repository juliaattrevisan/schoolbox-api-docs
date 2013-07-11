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

	/* Getters/Setters
	 */

	/**
	 * Set body
	 *
	 * @param $body
	 */
	protected function setBody($body) {
		$this->body = $body;
	}

	/**
	 * Get body
	 *
	 * @return string
	 */
	protected function getBody() {
		return $this->body;
	}

	/**
	 * Set created at
	 *
	 * @param $createdAt
	 */
	protected function setCreatedAt($createdAt) {
		$this->createdAt = $createdAt;
	}

	/**
	 * Get created at
	 *
	 * @return string
	 */
	protected function getCreatedAt() {
		return $this->createdAt;
	}

	/**
	 * Set deleted at
	 *
	 * @param $deletedAt
	 */
	protected function setDeletedAt($deletedAt) {
		$this->deletedAt = $deletedAt;
	}

	/**
	 * Get deleted at
	 *
	 * @return string
	 */
	protected function getDeletedAt() {
		return $this->deletedAt;
	}

	/**
	 * Set homepage id
	 *
	 * @param $homepageId
	 */
	protected function setHomepageId($homepageId) {
		$this->homepageId = $homepageId;
	}

	/**
	 * Get homepage id
	 *
	 * @return mixed
	 */
	protected function getHomepageId() {
		return $this->homepageId;
	}

	/**
	 * Set mimetype
	 *
	 * @param $mimetype
	 */
	protected function setMimetype($mimetype) {
		$this->mimetype = $mimetype;
	}

	/**
	 * Get mimetype
	 *
	 * @return string
	 */
	protected function getMimetype() {
		return $this->mimetype;
	}

	/**
	 * Set owner id
	 *
	 * @param $ownerId
	 */
	protected function setOwnerId($ownerId) {
		$this->ownerId = $ownerId;
	}

	/**
	 * Get owner id
	 *
	 * @return mixed
	 */
	protected function getOwnerId() {
		return $this->ownerId;
	}

	/**
	 * Set parent id
	 *
	 * @param $parentId
	 */
	protected function setParentId($parentId) {
		$this->parentId = $parentId;
	}

	/**
	 * Get parent id
	 *
	 * @return mixed
	 */
	protected function getParentId() {
		return $this->parentId;
	}

	/**
	 * Set post id
	 *
	 * @param $postId
	 */
	protected function setPostId($postId) {
		$this->postId = $postId;
	}

	/**
	 * Get post id
	 *
	 * @return mixed
	 */
	protected function getPostId() {
		return $this->postId;
	}

	/**
	 * Set type
	 *
	 * @param $type
	 */
	protected function setType($type) {
		$this->type = $type;
	}

	/**
	 * Get type
	 *
	 * @return string
	 */
	protected function getType() {
		return $this->type;
	}

	/**
	 * Set updated at
	 *
	 * @param $updatedAt
	 */
	protected function setUpdatedAt($updatedAt) {
		$this->updatedAt = $updatedAt;
	}

	/**
	 * Get updated at
	 *
	 * @return string
	 */
	protected function getUpdatedAt() {
		return $this->updatedAt;
	}

	/**
	 * Set url
	 *
	 * @param $url
	 */
	protected function setUrl($url) {
		$this->url = $url;
	}

	/**
	 * Get url
	 *
	 * @return string
	 */
	protected function getUrl() {
		return $this->url;
	}
}