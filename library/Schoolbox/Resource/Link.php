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

class Link implements ISoftDeletable
{
    /**
     * Identifier
     *
     * @var int
     */
    protected $id;

    /**
     * Owner Id
     *
     * @var int
     */
    protected $owner;

    /**
     * Category Id
     *
     * @var int
     */
    protected $folder;

    /**
     * Url
     *
     * @var int
     */
    protected $url;

    /**
     * Description
     *
     * @var string
     */
    protected $description;

    /**
     * Comment
     *
     * @var string
     */
    protected $comment;

    /**
     * Hidden
     *
     * @var int
     */
    protected $hidden;

    /**
     * Submitted date
     *
     * @var \Alaress_Date_Format
     */
    protected $submitted;

    /**
     * Modified date
     *
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
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

    /* Getters/Setters
     */

    /**
     * @param  int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set category id
     *
     * @param Category $folder
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * Get category
     *
     * @return Category
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = new Url($url);
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        if ($this->url instanceof Url) {
            return $this->url->getUrl();
        } else {
            // account for stale messages (that somehow still have self::$url as a primitive)
            return $this->url;
        }
    }

    /**
     * Is this a valid url
     *
     * @return bool
     */
    public function isUrl()
    {
        return $this->url->isUrl();
    }

    /**
     * Get local url
     *
     * @return bool
     */
    public function isLocalUrl()
    {
        return $this->url->isLocalURL();
    }

    /**
     * Get ftp url
     *
     * @return bool
     */
    public function isFtpUrl()
    {
        return $this->url->isFTPURL();
    }

    /**
     * Check if hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return (bool)$this->getHidden();
    }

    /**
     * Get relative url
     *
     * @return mixed
     */
    public function getRelativeUrl()
    {
        return $this->url->getRelativeURL();
    }

    /**
     * Get redirect string
     *
     * @return string
     */
    public function getBase64EncodedRedirect()
    {
        return $this->url->getBase64EncodedRedirect();
    }

    /**
     * Get url slug
     *
     * @return string
     */
    public function getSlug()
    {
        return $this->url->getSlug();
    }

    /**
     * Get url target
     *
     * @return string
     */
    public function getTarget()
    {
        if ($this->isLocalUrl()) {
            return '';
        }

        return 'external';
    }

    /**
     * Get link url
     *
     * @return string
     */
    public function getHref()
    {
        if ($this->isLocalUrl()) {
            return $this->getRelativeURL();
        } elseif ($this->isFTPURL()) {
            return $this->getUrl();
        }
        /* If this has an id, then log the visit for this instance
         */
        else if (is_numeric($this->getId())) {
            return "/redir.php?id={$this->getId()}";
        } else {
            return $this->getBase64EncodedRedirect();
        }
    }

    /**
     * @param  int $owner
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set comment
     *
     * @param string $comment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    /**
     * Get comment
     *
     * @return string
     */
    public function getComment()
    {
        return $this->comment;
    }

    /**
     * Set description
     *
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set hidden
     *
     * @param bool $hidden
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * Get hidden
     *
     * @return bool
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set submitted
     *
     * @param  \Alaress_Date_Format $submitted
     * @return $this
     */
    public function setSubmitted($submitted)
    {
        $this->submitted = $submitted;
        return $this;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getSubmitted()
    {
        return $this->submitted;
    }

    /**
     * Set modified
     *
     * @param  \Alaress_Date_Format|null $modified
     * @return $this
     */
    public function setModified($modified)
    {
        $this->modified = $modified;
        return $this;
    }

    /**
     * Get modified
     *
     * @return \Alaress_Date_Format
     */
    public function getModified()
    {
        return $this->modified;
    }

    /**
     * Set deleted at
     *
     * @param \Alaress_Date_Format|null $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Get deleted at
     *
     * @return \Alaress_Date_Format|null $deletedAt
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }
}