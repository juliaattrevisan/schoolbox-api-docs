<?php
/**
 * Node
 *
 * This is a node in the Old Resource Browser tree view.
 * Its destiny is to be merged with Doctor's Nodes
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Resource\Browser\Tree;

class Node {

    /**
     * Node id
     *
     * @var int
     */
    private $nodeId = 0;

    /**
     * Node type
     *
     * @var string
     */
    private $type = '';

    /**
     * Node icon
     *
     * @var string
     */
    private $icon = '';

    /**
     * Node name
     *
     * @var string
     */
    private $name = '';

    /**
     * Node size
     *
     * A human-readable non-int representation of file size
     *
     * @var string
     */
    private $size = '';

    /**
     * Category
     *
     * @var \Schoolbox\Resource\Category
     */
    private $category = null;

    /**
     * CSS for the icons and stuff
     *
     * @var string
     */
    private $css = '';

    /**
     * Last modified date
     *
     * @var \Alaress_Date_Format
     */
    private $modifiedAt = null;

    /**
     * Constructor
     */
    public function __construct() {
    }

    /* Getters/Setters
     */

    /**
     * Set category
     *
     * @param \Schoolbox\Resource\Category $category
     */
    public function setCategory($category)
    {
        $this->category = $category;
    }

    /**
     * Get category
     *
     * @return \Schoolbox\Resource\Category
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set css
     *
     * @param string $css
     */
    public function setCss($css)
    {
        $this->css = $css;
    }

    /**
     * Get css
     *
     * @return string
     */
    public function getCss()
    {
        return $this->css;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
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
     * Set node id
     *
     * @param int $nodeId
     */
    public function setNodeId($nodeId)
    {
        $this->nodeId = $nodeId;
    }

    /**
     * Get node id
     *
     * @return int
     */
    public function getNodeId()
    {
        return $this->nodeId;
    }

    /**
     * Set size
     *
     * @param string $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * Get size
     *
     * @return string
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Set type
     *
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
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
     * Set icon
     *
     * @param string $icon
     */
    public function setIcon($icon)
    {
        $this->icon = $icon;
    }

    /**
     * Get icon
     *
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }
}