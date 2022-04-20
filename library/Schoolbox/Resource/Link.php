<?php

/**
 * Model for links in the resource browser.
 *
 * @author    James Leckie <jleckie@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\ISoftDeletable;
use Url;

class Link implements ISoftDeletable
{
    use Id;
    use DeletedAtDate;

    /**
     * Owner
     *
     * @var User
     */
    protected $owner;

    /**
     * Category
     *
     * @var Folder
     */
    protected $folder;

    /**
     * Url
     *
     * @var string|Url
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

    /** @var string */
    protected $target;

    /**
     * Get link url
     *
     * @return string
     */
    public function getHref()
    {
        if ($this->isLocalUrl()) {
            return $this->getRelativeUrl();
        } elseif ($this->isFtpUrl()) {
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
     * Get url target, local URLs should always attempt to be in the same window
     *
     * @return string
     */
    public function getLinkTarget()
    {
        if ($this->getTarget() != '_blank') {
            return '';
        } else {
            return 'external';
        }
    }

    /**
     * Set url
     *
     * @param  string $url
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = new Url($url);
        return $this;
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
        return $this->url->isLocalUrl();
    }

    /**
     * Get ftp url
     *
     * @return bool
     */
    public function isFtpUrl()
    {
        return $this->url->isFtpUrl();
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

    /* Getters/Setters
     */

    /**
     * Set category id
     *
     * @param  Folder $folder
     * @return $this
     */
    public function setFolder($folder)
    {
        $this->folder = $folder;
        return $this;
    }

    /**
     * Get category
     *
     * @return Folder
     */
    public function getFolder()
    {
        return $this->folder;
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
     * @param  User $owner
     * @return $this
     */
    public function setOwner($owner)
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * @return User|null
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set comment
     *
     * @param  string $comment
     * @return $this
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
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
     * @param  string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
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
     * @param  bool $hidden
     * @return $this
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
        return $this;
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
     * Get target
     *
     * @return mixed
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * Set target
     *
     * @param  string $target
     * @return $this
     */
    public function setTarget($target)
    {
        $this->target = $target;
        return $this;
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
     * @param  \Alaress_Date_Format $modified
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
}
