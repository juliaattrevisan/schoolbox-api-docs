<?php

/**
 * Textbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Textbox;

use Alaress\Json\Serializable;
use Alaress\Json\SerializeTrait;
use Schoolbox\ISoftDeletable;
use Schoolbox\Resource\Folder\Model as Folder;

class Model implements ISoftDeletable, Serializable
{
    use SerializeTrait;

    /**
     * Identifier
     *
     * @var int
     */
    private $id;

    /**
     * Category
     *
     * @var Folder
     */
    private $category;

    /**
     * Textbox heading
     *
     * @var string
     */
    private $heading = '';

    /**
     * Textbox body
     *
     * @var string
     */
    private $body = '';

    /**
     * Deleted
     *
     * @var \Alaress_Date_Format|null
     */
    private $deletedAt = '';

    /**
     * Check whether this textbox is softdeleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        return ($this->getDeletedAt() instanceof \Alaress_Date_Format && !$this->getDeletedAt()->isNull());
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setCategory(new Folder());
    }

    /* Getters/Setters
     */

    /**
     * Set body
     *
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Set category
     *
     * @param Folder $category
     * @return $this
     */
    public function setCategory($category)
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Get category
     *
     * @return Folder
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set deleted at
     *
     * @param \Alaress_Date_Format $deletedAt
     * @return $this
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
        return $this;
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
     * Set heading
     *
     * @param string $heading
     * @return $this
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;
        return $this;
    }

    /**
     * Get heading
     *
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * Set id
     *
     * @param int $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
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
     * @return array
     */
    public function getDefaultJsonMap(): array
    {
        return [
            'id',
            'category',
            'heading',
            'body',
            'createdAt',
            'updatedAt',
        ];
    }
}
