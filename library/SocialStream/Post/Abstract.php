<?php
/**
 * SocialStream_Post_Abstract
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\ISoftDeletable;
use Schoolbox\User;

abstract class SocialStream_Post_Abstract implements ISoftDeletable {

	/**
	 * Primary key
	 *
	 * @var
	 */
	protected $postId;

	/**
	 * The root id (this is so subclass instances know who their
	 * ultimate parent is, for easier querying etc)
	 *
	 * @var int
	 */
	protected $rootId = 0;

	/**
	 * The person who owns this post
	 *
	 * @var User
	 */
	protected $owner;

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
	protected $parentId = 0;

	/**
	 * The place where a thumbnail exists for this post
	 *
	 * @var string
	 */
	protected $thumbnail = '';

    /** @var string */
    protected $cacheHash;

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
	 * The url to the resource
	 *
	 * @var string
	 */
	protected $url = '';

	/**
	 * The title of the resource
	 *
	 * @var string
	 */
	protected $externalTitle = '';

	/**
	 * The description of the resource
	 *
	 * @var string
	 */
	protected $externalBody = '';

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

	/**
	 * Render thumbnail (or equivalents) as determined by subclasses
	 *
	 * @param $context
	 * @param $page
	 * @return mixed
	 */
	abstract public function renderThumbnail($context, $page);

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
	 * Set owner
	 *
	 * @param User $owner
	 */
	public function setOwner($owner) {
		$this->owner = $owner;
	}

	/**
	 * Get owner
	 *
	 * @return User
	 */
	public function getOwner() {
		return $this->owner;
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
	 * @return SocialStream_Post_Abstract[]
	 */
	public function getChildren() {
		return $this->children;
	}

	/**
	 * Set root id
	 *
	 * @param int $rootId
	 */
	public function setRootId($rootId) {
		$this->rootId = $rootId;
	}

	/**
	 * Get root id
	 *
	 * @return int
	 */
	public function getRootId() {
		return $this->rootId;
	}

    /**
     * @return string
     */
    public function getCacheHash()
    {
        return $this->cacheHash;
    }

    /**
     * @param string $cacheHash
     * @return static
     */
    public function setCacheHash($cacheHash)
    {
        $this->cacheHash = $cacheHash;
        return $this;
    }

	/**
	 * Set thumbnail
	 *
	 * @param string $thumbnail
	 */
	public function setThumbnail($thumbnail) {
		$this->thumbnail = $thumbnail;
	}

	/**
	 * Get thumbnail
	 *
	 * @return string
	 */
	public function getThumbnail() {
		return $this->thumbnail;
	}

	/**
	 * @param string $externalBody
	 */
	public function setExternalBody($externalBody) {
		$this->externalBody = $externalBody;
	}

	/**
	 * @return string
	 */
	public function getExternalBody() {
		return $this->externalBody;
	}

	/**
	 * Set external title
	 *
	 * @param string $externalTitle
	 */
	public function setExternalTitle($externalTitle) {
		$this->externalTitle = $externalTitle;
	}

	/**
	 * Get external title
	 *
	 * @return string
	 */
	public function getExternalTitle() {
		return $this->externalTitle;
	}

	/**
	 * Is this deleted?
	 *
	 * @return bool
	 */
	public function isDeleted() {
		return $this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull();
	}
}