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
use Url;

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
     * @var Category
     */
    protected $folder;

    /** @var Url */
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
     * @param Category $folder
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * @return Category
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
        $this->url = new Url($url);
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url->getUrl();
    }

    /**
     * @return bool
     */
    public function isUrl()
    {
        return $this->url->isUrl();
    }

    /**
     * @return bool
     */
    public function isLocalUrl()
    {
        return $this->url->isLocalURL();
    }

    /**
     * @return bool
     */
    public function isFtpUrl()
    {
        return $this->url->isFTPURL();
    }

    /**
     * @return mixed
     */
    public function getRelativeUrl()
    {
        return $this->url->getRelativeURL();
    }

    /**
     * @return string
     */
    public function getBase64EncodedRedirect()
    {
        return $this->url->getBase64EncodedRedirect();
    }

    /**
     * @return string
     */
    public function getSlug()
    {
        return $this->url->getSlug();
    }

    /**
     * @return string
     */
    public function getHref()
    {
        if ($this->isLocalUrl()) {
            return $this->getRelativeURL();
        } elseif ($this->isFTPURL()) {
            return $this->getUrl();
        } else {
            return $this->getBase64EncodedRedirect();
        }
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


    /**
     * @param bool $hidden
     */
	public function setHidden($hidden) {
		$this->hidden = $hidden;
	}


    /**
     * @return bool
     */
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