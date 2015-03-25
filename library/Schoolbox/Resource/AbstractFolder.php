<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource;


use Schoolbox\Resource\Folder\IFolder;

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
     * @var int
     */
    protected $id;

    /**
     * Category name
     *
     * @var string
     */
    protected $name;

    /**
     * A category instance
     *
     * @var self
     */
    protected $parent;


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
     * @return int
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
     * Set parent
     *
     * @param  self $parent
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

} 