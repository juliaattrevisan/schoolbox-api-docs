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

use \Schoolbox\Collection;
use \Schoolbox\User;

abstract class AbstractAssessment implements IAssessable
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
     * A Collection of IResponse instances that Students submit
     *
     * @var Collection
     */
    protected $allResponses;

    /**
     * The person who created this Assessable
     *
     * @var User
     */
    protected $creator;

    /**
     * Assessable constructor.
     */
    public function __construct()
    {
        $this->setAllResponses(new Collection());
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
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
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
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
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
     * @return $this
     */
    public function setPublishAt($publishAt)
    {
        $this->publishAt = $publishAt;
        return $this;
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
     * @return $this
     */
    public function setOpenAt($openAt)
    {
        $this->openAt = $openAt;
        return $this;
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
     * @return $this
     */
    public function setCloseAt($closeAt)
    {
        $this->closeAt = $closeAt;
        return $this;
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
     * @return $this
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return Collection
     */
    public function getAllResponses()
    {
        return $this->allResponses;
    }

    /**
     * @param Collection $allResponses
     * @return $this
     */
    public function setAllResponses($allResponses)
    {
        $this->allResponses = $allResponses;
        return $this;
    }

    /**
     * @return User
     */
    public function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param User $creator
     * @return $this
     */
    public function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }
}

