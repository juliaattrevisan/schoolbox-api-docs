<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource;

use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Resource\Storage\IStorage;
use Alaress\Color\Color;

/**
 * Class AbstractFolder
 *
 * Provides common functionality for tree-based filesystem implementations (ie. can have an IFolder parent)
 *
 * @package Schoolbox\Resource
 */
abstract class AbstractFolder implements IFolder
{
    /**
     * Identifier
     *
     * @var int|string
     */
    protected $id;

    /**
     * Category name
     *
     * @var string
     */
    protected $name;

    /**
     * Inherits permissions flag
     *
     * @var boolean
     */
    protected $inherit;

    /**
     * A category instance
     *
     * @var self
     */
    protected $parent;

    /**
     * Folder colour
     *
     * @var null|Color
     */
    private $colour;

    /**
     * Cover image
     *
     * @var null|IStorage
     */
    private $coverImage;


    /**
     * Set id
     *
     * @param  int $id
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
     * @return int|string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set name
     *
     * @param  string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set inherit permissions flag
     *
     * @param  boolean $inherit
     * @return $this
     */
    public function setInherit($inherit)
    {
        $this->inherit = $inherit;
        return $this;
    }

    /**
     * Enable inherit permissions
     *
     * @return $this
     */
    public function enableInherit()
    {
        $this->inherit = true;
        return $this;
    }

    /**
     * Disable inherit permissions
     *
     * @return $this
     */
    public function disableInherit()
    {
        $this->inherit = false;
        return $this;
    }

    /**
     * Get inherit permissions flag
     *
     * @return boolean
     */
    public function getInherit()
    {
        return $this->inherit;
    }

    /**
     * Set parent
     *
     * @param  AbstractFolder $parent
     * @return $this
     */
    public function setParent($parent)
    {
        $this->parent = $parent;
        return $this;
    }

    /**
     * Get parent
     *
     * @return self
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Get folder colour
     *
     * @return Color
     */
    public function getColour(): ?Color
    {
        return $this->colour;
    }

    /**
     * @param Color $colour
     * @return $this
     */
    public function setColour(?Color $colour): self
    {
        $this->colour = $colour;
        return $this;
    }

    /**
     * Get folder cover image
     *
     * @return IStorage
     */
    public function getCoverImage(): ?IStorage
    {
        return $this->coverImage;
    }

    /**
     * @param IStorage $coverImage
     * @return $this
     */
    public function setCoverImage(?IStorage $coverImage): self
    {
        $this->coverImage = $coverImage;
        return $this;
    }
}
