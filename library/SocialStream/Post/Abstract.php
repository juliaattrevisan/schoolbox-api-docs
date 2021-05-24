<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Collection;
use Schoolbox\ISoftDeletable;
use Schoolbox\Mapper\Traits\Model\CreatedAtDate;
use Schoolbox\Mapper\Traits\Model\DeletedAtDate;
use Schoolbox\Mapper\Traits\Model\UpdatedAtDate;
use Schoolbox\User;

/**
 * Class SocialStream_Post_Abstract
 */
abstract class SocialStream_Post_Abstract implements ISoftDeletable
{
    use CreatedAtDate;
    use UpdatedAtDate;
    use DeletedAtDate;

    /**
     * Primary key
     *
     * @var int
     */
    protected $postId;

    /**
     * The root id (this is so subclass instances know who their
     * ultimate parent is, for easier querying etc)
     *
     * @var int
     */
    protected $rootId;

    /**
     * The person who owns this post
     *
     * @var User
     */
    protected $owner;

    /**
     * The homepage on which this post can be found
     *
     * @var int
     */
    protected $homepageId;

    /**
     * The postId to which this post is subordinate
     *
     * @var int
     */
    protected $parentId;

    /**
     * The place where a thumbnail exists for this post
     *
     * @var string
     */
    protected $thumbnail = '';

    /**
     * @var string
     */
    protected $cacheHash;

    /**
     * One of Website, Video, Image, File or String
     *
     * @var string
     */
    protected $type;

    /**
     * The mime type of the resource
     *
     * @var string
     */
    protected $mimetype;

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
    protected $externalTitle;

    /**
     * The description of the resource
     *
     * @var string
     */
    protected $externalBody;

    /**
     * What they typed into that box
     *
     * @var string
     */
    protected $body;

    /**
     * Children posts
     *
     * @var Collection
     */
    protected $children;


    /**
     * Constructor
     *
     * @throws UnexpectedValueException
     */
    public function __construct()
    {
        $this->setChildren(new Collection());
        $this->setOwner(new User());
    }

    /**
     * Render thumbnail (or equivalents) as determined by subclasses
     *
     * @param string   $context
     * @param SitePage $page
     * @return mixed
     */
    abstract public function renderThumbnail(string $context, SitePage $page);

    /* Getters/Setters
     */

    /**
     * Set body
     *
     * @param null|string $body
     * @return SocialStream_Post_Abstract
     */
    public function setBody(?string $body): \SocialStream_Post_Abstract
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get body
     *
     * @return null|string
     */
    public function getBody(): ?string
    {
        return $this->body;
    }


    /**
     * Set homepage id
     *
     * @param null|int $homepageId
     * @return SocialStream_Post_Abstract
     */
    public function setHomepageId(?int $homepageId): \SocialStream_Post_Abstract
    {
        $this->homepageId = $homepageId;
        return $this;
    }

    /**
     * Get homepage id
     *
     * @return null|int
     */
    public function getHomepageId(): ?int
    {
        return $this->homepageId;
    }


    /**
     * Set mimetype
     *
     * @param null|string $mimetype
     * @return SocialStream_Post_Abstract
     */
    public function setMimetype(?string $mimetype): \SocialStream_Post_Abstract
    {
        $this->mimetype = $mimetype;
        return $this;
    }

    /**
     * Get mimetype
     *
     * @return null|string
     */
    public function getMimetype(): ?string
    {
        return $this->mimetype;
    }


    /**
     * Set owner
     *
     * @param null|User $owner
     * @return SocialStream_Post_Abstract
     */
    public function setOwner(?User $owner): \SocialStream_Post_Abstract
    {
        $this->owner = $owner;
        return $this;
    }

    /**
     * Get owner
     *
     * @return null|User
     */
    public function getOwner(): ?\Schoolbox\User
    {
        return $this->owner;
    }


    /**
     * Set parent id
     *
     * @param null|int $parentId
     * @return SocialStream_Post_Abstract
     */
    public function setParentId(?int $parentId): \SocialStream_Post_Abstract
    {
        $this->parentId = $parentId;
        return $this;
    }

    /**
     * Get parent id
     *
     * @return null|int
     */
    public function getParentId(): ?int
    {
        return $this->parentId;
    }


    /**
     * Set post id
     *
     * @param null|int $postId
     * @return SocialStream_Post_Abstract
     */
    public function setPostId(?int $postId): \SocialStream_Post_Abstract
    {
        $this->postId = $postId;
        return $this;
    }

    /**
     * Get post id
     *
     * @return null|int
     */
    public function getPostId(): ?int
    {
        return $this->postId;
    }

    /**
     * Set type
     *
     * @param null|string $type
     * @return SocialStream_Post_Abstract
     */
    public function setType(?string $type): \SocialStream_Post_Abstract
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return null|string
     */
    public function getType(): ?string
    {
        return $this->type;
    }


    /**
     * Set url
     *
     * @param null|string $url
     * @return SocialStream_Post_Abstract
     */
    public function setUrl(?string $url): \SocialStream_Post_Abstract
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get url
     *
     * @return null|string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }


    /**
     * Set children
     *
     * @param Collection $children
     * @return SocialStream_Post_Abstract
     */
    public function setChildren(Collection $children): \SocialStream_Post_Abstract
    {
        $this->children = $children;
        return $this;
    }

    /**
     * Get children
     *
     * @return Collection|SocialStream_Post_Abstract[]
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }


    /**
     * Set root id
     *
     * @param null|int $rootId
     * @return SocialStream_Post_Abstract
     */
    public function setRootId(?int $rootId): \SocialStream_Post_Abstract
    {
        $this->rootId = $rootId;
        return $this;
    }

    /**
     * Get root id
     *
     * @return null|int
     */
    public function getRootId(): ?int
    {
        return $this->rootId;
    }

    /**
     * @return null|string
     */
    public function getCacheHash(): ?string
    {
        return $this->cacheHash;
    }

    /**
     * @param null|string $cacheHash
     * @return static
     */
    public function setCacheHash(?string $cacheHash)
    {
        $this->cacheHash = $cacheHash;
        return $this;
    }


    /**
     * Set thumbnail
     *
     * @param null|string $thumbnail
     * @return SocialStream_Post_Abstract
     */
    public function setThumbnail(?string $thumbnail): \SocialStream_Post_Abstract
    {
        $this->thumbnail = $thumbnail;
        return $this;
    }

    /**
     * Get thumbnail
     *
     * @return null|string
     */
    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }


    /**
     * @param null|string $externalBody
     * @return SocialStream_Post_Abstract
     */
    public function setExternalBody(?string $externalBody): \SocialStream_Post_Abstract
    {
        $this->externalBody = $externalBody;
        return $this;
    }

    /**
     * @return null|string
     */
    public function getExternalBody(): ?string
    {
        return $this->externalBody;
    }


    /**
     * Set external title
     *
     * @param null|string $externalTitle
     * @return SocialStream_Post_Abstract
     */
    public function setExternalTitle(?string $externalTitle): \SocialStream_Post_Abstract
    {
        $this->externalTitle = $externalTitle;
        return $this;
    }

    /**
     * Get external title
     *
     * @return null|string
     */
    public function getExternalTitle(): ?string
    {
        return $this->externalTitle;
    }

    /**
     * Is this deleted?
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->getDeletedAt() instanceof Alaress_Date_Format && !$this->getDeletedAt()->isNull();
    }
}
