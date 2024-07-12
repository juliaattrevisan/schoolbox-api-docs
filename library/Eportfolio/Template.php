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

    /**
     * TODO: Is this even used?
     * @var int|null
     */
    private ?int $layout;

    /* Getters/Setters
     */

    /**
     * Set id
     *
     * @param int $id
     * @return self
     */
    public function setId($id): self
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
     * Set left column width
     *
     * @param float $leftColumnWidth
     * @return self
     */
    public function setLeftColumnWidth($leftColumnWidth): self
    {
        $this->leftColumnWidth = $leftColumnWidth;
        return $this;
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
     * @return self
     */
    public function setName($name): self
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
     * Set right column width
     *
     * @param string $rightColumnWidth
     * @return self
     */
    public function setRightColumnWidth($rightColumnWidth): self
    {
        $this->rightColumnWidth = $rightColumnWidth;
        return $this;
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

    /**
     * @param int|null $layout
     * @return self
     */
    public function setLayout(?int $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getLayout(): ?int
    {
        return $this->layout;
    }
}
