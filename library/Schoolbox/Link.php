<?php
/**
 * Link
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox;

class Link implements ISoftDeletable {

    /**
     * Identifier
     *
     * @var int
     */
    private $id;

    /**
     * Category Id
     *
     * @var int
     */
    private $categoryId;

    /**
     * Owner Id
     *
     * @var int
     */
    private $ownerId;

    /**
     * Url
     *
     * @var int
     */
    private $url;

    /**
     * Submitted date
     *
     * @var \Alaress_Date_Format
     */
    private $submittedAt;

    /**
     * Modified date
     *
     * @var \Alaress_Date_Format
     */
    private $modifiedAt;

    /**
     * Description
     *
     * @var string
     */
    private $description;

    /**
     * Comment
     *
     * @var string
     */
    private $comment;

    /**
     * Hidden
     *
     * @var int
     */
    private $hidden = 0;

    /**
     * Deleted date
     *
     * @var \Alaress_Date_Format
     */
    private $deletedAt;

    /**
     * Is this link soft deleted
     *
     * @return bool
     */
    public function isDeleted() {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format);
    }

    /* Getters/Setters
     */

    /**
     * Set category id
     *
     * @param int $categoryId
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;
    }

    /**
     * Get category
     *
     * @return int
     */
    public function getCategoryId()
    {
        return $this->categoryId;
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
     * Set deleted at
     *
     * @param \Alaress_Date_Format $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Get deleted at
     *
     * @return \Alaress_Date_Format
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
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
     * @param int $hidden
     */
    public function setHidden($hidden)
    {
        $this->hidden = $hidden;
    }

    /**
     * Get hidden
     *
     * @return int
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Set id
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set modified at
     *
     * @param \Alaress_Date_Format $modifiedAt
     */
    public function setModifiedAt($modifiedAt)
    {
        $this->modifiedAt = $modifiedAt;
    }

    /**
     * Get modified at
     *
     * @return \Alaress_Date_Format
     */
    public function getModifiedAt()
    {
        return $this->modifiedAt;
    }

    /**
     * Set owner id
     *
     * @param int $ownerId
     */
    public function setOwnerId($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * Get owner id
     *
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Set submitted at
     *
     * @param \Alaress_Date_Format $submittedAt
     */
    public function setSubmittedAt($submittedAt)
    {
        $this->submittedAt = $submittedAt;
    }

    /**
     * Get submitted at
     *
     * @return \Alaress_Date_Format
     */
    public function getSubmittedAt()
    {
        return $this->submittedAt;
    }

    /**
     * Set url
     *
     * @param int $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return int
     */
    public function getUrl()
    {
        return $this->url;
    }
}