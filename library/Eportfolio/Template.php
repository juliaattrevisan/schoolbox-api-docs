<?php

/**
 * Template
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Eportfolio;

class Template
{

    /**
     * The primary key
     *
     * @var int
     */
    private $id;

    /**
     * The name of the template
     *
     * @var string
     */
    private $name;

    /**
     * Left column width
     *
     * @var float
     */
    private $leftColumnWidth;

    /**
     * Right column width
     *
     * @var float
     */
    private $rightColumnWidth;

    /* Getters/Setters
     */

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
     * Set left column width
     *
     * @param float $leftColumnWidth
     */
    public function setLeftColumnWidth($leftColumnWidth)
    {
        $this->leftColumnWidth = $leftColumnWidth;
    }

    /**
     * Get left column width
     *
     * @return float
     */
    public function getLeftColumnWidth()
    {
        return $this->leftColumnWidth;
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
     * Set right column width
     *
     * @param string $rightColumnWidth
     */
    public function setRightColumnWidth($rightColumnWidth)
    {
        $this->rightColumnWidth = $rightColumnWidth;
    }

    /**
     * Get right column width
     *
     * @return float
     */
    public function getRightColumnWidth()
    {
        return $this->rightColumnWidth;
    }
}
