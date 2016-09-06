<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment;

use Alaress_Date_Format;

/**
 * The base model for an assessment.
 *
 * @package Schoolbox\Learning\Assessment
 */
class Model
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var Project\Model
     */
    protected $project;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var Alaress_Date_Format
     */
    protected $publishDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $openDate;

    /**
     * @var Alaress_Date_Format
     */
    protected $dueDate;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var int
     */
    protected $weight = 0;

    /**
     * @var int
     */
    protected $sequence = 0;

    /**
     * Constructor - ensure the object is in a usable state.
     */
    public function __construct()
    {
        $this->publishDate = new \Alaress_Date_NullDate();
        $this->openDate    = new \Alaress_Date_NullDate();
        $this->dueDate     = new \Alaress_Date_NullDate();
    }

    /* Getters/Setters */

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return static
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
     * @return static
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param int $weight
     * @return static
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return Project\Model
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * @param Project\Model $project
     * @return static
     */
    public function setProject($project)
    {
        $this->project = $project;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getPublishDate()
    {
        return $this->publishDate;
    }

    /**
     * @param Alaress_Date_Format $publishDate
     * @return static
     */
    public function setPublishDate($publishDate)
    {
        $this->publishDate = $publishDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getOpenDate()
    {
        return $this->openDate;
    }

    /**
     * @param Alaress_Date_Format $openDate
     * @return static
     */
    public function setOpenDate($openDate)
    {
        $this->openDate = $openDate;
        return $this;
    }

    /**
     * @return Alaress_Date_Format
     */
    public function getDueDate()
    {
        return $this->dueDate;
    }

    /**
     * @param Alaress_Date_Format $dueDate
     * @return static
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * @return int
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * @param int $sequence
     * @return static
     */
    public function setSequence($sequence)
    {
        $this->sequence = $sequence;
        return $this;
    }
}
