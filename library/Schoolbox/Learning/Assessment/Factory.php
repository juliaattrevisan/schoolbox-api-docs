<?php
/**
 * Factory
 *
 * Build an IAssessable instance based on the common params
 *
 * The IAssessable instance can later be decorated with their
 * additional specific details
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment;

use \Schoolbox\User;

use \Schoolbox\Learning\Assessment\Summator\Factory as SummatorFactory;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;

class Factory
{

    /**
     * @var int
     */
    protected $type = -1;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var User
     */
    protected $creator;

    /**
     * @var float|int
     */
    protected $weight;

    /**
     * @var string
     */
    protected $publishAt;

    /**
     * @var string
     */
    protected $openAt;

    /**
     * @var string
     */
    protected $dueAt;

    /**
     * @var string
     */
    protected $closeAt;

    /**
     * @var bool
     */
    protected $hasRubric = false;

    /**
     * @var bool
     */
    protected $hasMarkPerCapability = false;

    /**
     * @var int
     */
    protected $summator;

    /**
     * Factory constructor.
     * @param $type
     * @param $title
     * @param $description
     * @param User $creator
     * @param $weight
     * @param $publishAt
     * @param $openAt
     * @param $dueAt
     * @param $closeAt
     */
    public function __construct($type, $title, $description, User $creator, $weight, $publishAt, $openAt, $dueAt, $closeAt, $hasRubric, $hasMarkPerCapability, $summator)
    {
        $this->setType($type);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setCreator($creator);
        $this->setWeight($weight);
        $this->setPublishAt(trim($publishAt));
        $this->setOpenAt(trim($openAt));
        $this->setDueAt(trim($dueAt));
        $this->setCloseAt(trim($closeAt));

        /* Due Work needs this to instantiate correctly
         */
        $this->setHasRubric(trim($hasRubric));
        $this->setHasMarkPerCapability(trim($hasMarkPerCapability));

        /* Projects need this to instantiate
         */
        $this->setSummator($summator);
    }

    /**
     * Produce object
     *
     * @return DueWork|Project|Quiz|Task
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produce()
    {
        /* Check types exist
         */
        switch($this->getType()) {
            case Types::ASSESSMENT_PROJECT:
                $assessment = new Project((new SummatorFactory($this->getSummator()))->produce());
                break;

            case Types::ASSESSMENT_DUE_WORK:
                $assessment = new DueWork();
                break;

            case Types::ASSESSMENT_TASK:
                $assessment = new Task();
                break;

            case Types::ASSESSMENT_QUIZ:
                $assessment = new Quiz();
                break;

            default:
                throw new \DomainException('select a valid assessment type');
        }

        /* Check required data
         */
        if (!strlen(trim($this->getTitle()))) {
            throw new \InvalidArgumentException('set an assessment title');
        }

        if (!strlen(trim($this->getDescription()))) {
            throw new \InvalidArgumentException('set an assessment description');
        }

        /* Check dates are provided
         * Re-throw a more friendly exception message than crappy "Laravel blahblah data missing":
         */
        try {
            $publishAt = \Alaress_Date_Format::newLocal($this->getPublishAt());
        }
        catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('set a publish date');
        }

        try {
            $openAt = \Alaress_Date_Format::newLocal($this->getOpenAt());
        }
        catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('set a open date');
        }

        try {
            $dueAt = \Alaress_Date_Format::newLocal($this->getDueAt());
        }
        catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('set a due date');
        }

        try {
            $closeAt = \Alaress_Date_Format::newLocal($this->getCloseAt());
        }
        catch (\InvalidArgumentException $e) {
            throw new \InvalidArgumentException('set a close date');
        }

        /* Start manufacturing
         */
        $assessment
            ->setTitle($this->getTitle())
            ->setDescription($this->getDescription())
            ->setCreator($this->getCreator())
            ->setWeight($this->getWeight())
            ->setPublishAt($publishAt)
            ->setOpenAt($openAt)
            ->setDueAt($dueAt)
            ->setCloseAt($closeAt)
            ->setCreatedAt(\Alaress_Date_Format::now())
            ->setUpdatedAt(\Alaress_Date_Format::now());

        /* Verify that dates are temporally correct
         */
        if ($assessment->isValidDates()) {
            return $assessment;
        }
        else {
            throw new \UnexpectedValueException('fill in all the date fields correctly');
        }
    }

    /* Getters/Setters
     */

    /**
     * @return int
     */
    protected function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return $this
     */
    protected function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    protected function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return $this
     */
    protected function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    protected function getDescription()
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return $this
     */
    protected function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return User
     */
    protected function getCreator()
    {
        return $this->creator;
    }

    /**
     * @param User $creator
     * @return $this
     */
    protected function setCreator($creator)
    {
        $this->creator = $creator;
        return $this;
    }

    /**
     * @return float|int
     */
    protected function getWeight()
    {
        return $this->weight;
    }

    /**
     * @param float|int $weight
     * @return $this
     */
    protected function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return string
     */
    protected function getPublishAt()
    {
        return $this->publishAt;
    }

    /**
     * @param string $publishAt
     * @return $this
     */
    protected function setPublishAt($publishAt)
    {
        $this->publishAt = $publishAt;
        return $this;
    }

    /**
     * @return string
     */
    protected function getOpenAt()
    {
        return $this->openAt;
    }

    /**
     * @param string $openAt
     * @return $this
     */
    protected function setOpenAt($openAt)
    {
        $this->openAt = $openAt;
        return $this;
    }

    /**
     * @return string
     */
    protected function getDueAt()
    {
        return $this->dueAt;
    }

    /**
     * @param string $dueAt
     * @return $this
     */
    protected function setDueAt($dueAt)
    {
        $this->dueAt = $dueAt;
        return $this;
    }

    /**
     * @return string
     */
    protected function getCloseAt()
    {
        return $this->closeAt;
    }

    /**
     * @param string $closeAt
     * @return $this
     */
    protected function setCloseAt($closeAt)
    {
        $this->closeAt = $closeAt;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasRubric()
    {
        return $this->hasRubric;
    }

    /**
     * @param boolean $hasRubric
     * @return $this
     */
    protected function setHasRubric($hasRubric)
    {
        $this->hasRubric = $hasRubric;
        return $this;
    }

    /**
     * @return boolean
     */
    protected function getHasMarkPerCapability()
    {
        return $this->hasMarkPerCapability;
    }

    /**
     * @param boolean $hasMarkPerCapability
     * @return $this
     */
    protected function setHasMarkPerCapability($hasMarkPerCapability)
    {
        $this->hasMarkPerCapability = $hasMarkPerCapability;
        return $this;
    }

    /**
     * @return int
     */
    protected function getSummator()
    {
        return $this->summator;
    }

    /**
     * @param int $summator
     * @return $this
     */
    protected function setSummator($summator)
    {
        $this->summator = $summator;
        return $this;
    }
}