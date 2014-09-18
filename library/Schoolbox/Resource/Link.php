<?php
/**
 * Model for links in the resource browser.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\User;
use Schoolbox\ISoftDeletable;

class Link implements ISoftDeletable {
	/**
	 * @var int
	 */
	protected $id;

	/**
	 * @var int
	 */
	protected $owner;

    /**
     * @var int
     */
    protected $folder;


    /**
     * @var string
     */
    protected $url;

	/**
	 * @var string
	 */
	protected $description;

	/**
	 * @var string
	 */
	protected $comment;

	/**
	 * @var boolean
	 */
	protected $hidden;


    /**
   	 * @var \Alaress_Date_Format
   	 */
   	protected $submitted;

   	/**
   	 * @var \Alaress_Date_Format
   	 */
   	protected $modified;

    /**
     * Deleted at
     *
     * @var
     */
    protected $deletedAt;

    /**
     * Is deleted
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format);
    }

    /* Getters/Setters
     */

	/**
	 * @param  int $id
	 * @return $this
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
     * @param int $folder
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * @return int
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

	/**
	 * @param  int $owner
	 * @return $this
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
	 * @param string $comment
	 */
	public function setComment($comment) {
		$this->comment = $comment;
	}

	/**
	 * @return string
	 */
	public function getComment() {
		return $this->comment;
	}

	/**
	 * @param string $description
	 */
	public function setDescription($description) {
		$this->description = $description;
	}

	/**
	 * @return string
	 */
	public function getDescription() {
		return $this->description;
	}

	public function setHidden($hidden) {
		$this->hidden = $hidden;
	}

	public function getHidden() {
		return $this->hidden;
	}

    /**
   	 * @param  \Alaress_Date_Format $submitted
   	 * @return $this
   	 */
   	public function setSubmitted($submitted) {
   		$this->submitted = $submitted;
   		return $this;
   	}

   	/**
   	 * @return \Alaress_Date_Format
   	 */
   	public function getSubmitted() {
   		return $this->submitted;
   	}

    /**
   	 * @param  \Alaress_Date_Format $modified
   	 * @return $this
   	 */
   	public function setModified($modified) {
   		$this->modified = $modified;
   		return $this;
   	}

   	/**
   	 * @return \Alaress_Date_Format
   	 */
   	public function getModified() {
   		return $this->modified;
   	}

    /**
     * Set deleted at
     *
     * @param mixed $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Get deleted at
     *
     * @return mixed
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }
}