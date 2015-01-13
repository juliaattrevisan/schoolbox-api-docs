<?php
/**
 * Textbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Textbox;

use Schoolbox\ISoftDeletable;

class Model implements ISoftDeletable
{

    /**
     * Identifier
     *
     * @var int
     */
    private $id;

    /**
     * Category
     *
     * @var
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
     * @var string
     */
    private $deletedAt = '';

    /**
     * Check whether this textbox is softdeleted
     *
     * @return bool
     */
    public function isDeleted()
    {
        if ($this->getDeletedAt() === 'true') {
            return true;
        }

        return false;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /* Getters/Setters
     */

    /**
     * Set body
     *
     * @param string $body
     */
    public function setBody($body)
    {
        $this->body = $body;
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
     * @param mixed $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * Get category
     *
     * @return mixed
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set deleted at
     *
     * @param string $deletedAt
     */
    public function setDeletedAt($deletedAt)
    {
        $this->deletedAt = $deletedAt;
    }

    /**
     * Get deleted at
     *
     * @return string
     */
    public function getDeletedAt()
    {
        return $this->deletedAt;
    }

    /**
     * Set heading
     *
     * @param string $heading
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;
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
}