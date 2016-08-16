<?php

/**
 * Assessable
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

abstract class AbstractModel implements IAssessable
{

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var \Alaress_Date_Format
     */
    protected $publishAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $openAt;

    /**
     * @var \Alaress_Date_Format
     */
    protected $closeAt;

    /**
     * @var float
     */
    protected $weight;

    /**
     * Assessable constructor.
     */
    public function __construct()
    {
    }

    /**
     * This IAssessable has a mark value of null
     *
     * @return bool
     */
    public function isNullMark()
    {
        return $this->calculateMark() === null;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle($title)
    {
        $this->title = $title;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getPublishAt()
    {
        return $this->publishAt;
    }

    /**
     * @param \Alaress_Date_Format $publishAt
     */
    public function setPublishAt($publishAt)
    {
        $this->publishAt = $publishAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getOpenAt()
    {
        return $this->openAt;
    }

    /**
     * @param \Alaress_Date_Format $openAt
     */
    public function setOpenAt($openAt)
    {
        $this->openAt = $openAt;
    }

    /**
     * @return \Alaress_Date_Format
     */
    public function getCloseAt()
    {
        return $this->closeAt;
    }

    /**
     * @param \Alaress_Date_Format $closeAt
     */
    public function setCloseAt($closeAt)
    {
        $this->closeAt = $closeAt;
    }

    /**
     * @return float
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param float $weight
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
    }
}

