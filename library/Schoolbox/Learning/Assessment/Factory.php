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
     * @var array
     */
    protected $attachedFiles = [];

    /**
     * @var int
     */
    protected $acceptOnline = DueWork::ACCEPT_ONLINE_NONE;

    /**
     * @var int
     */
    protected $markType;

    /**
     * @var int
     */
    protected $gradingScheme;

    /**
     * @var bool
     */
    protected $hasRubric = false;

    /**
     * @var bool
     */
    protected $hasMarkPerCapability = false;

    /**
     * @var bool
     */
    protected $hasSelfAssessed = false;

    /**
     * @var int
     */
    protected $maxMark;

    /**
     * @var string
     */
    protected $url;

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
     * @param $attachedFiles
     * @param $acceptOnline
     * @param $markType
     * @param $gradingScheme
     * @param $hasRubric
     * @param $hasMarkPerCapability
     * @param $hasSelfAssessed
     * @param $maxMark
     * @param $url
     * @param $summator
     */
    public function __construct(
        $type, $title, $description, User $creator, $weight,
        $publishAt, $openAt, $dueAt, $closeAt, $attachedFiles,
        $acceptOnline, $markType, $gradingScheme, $hasRubric, $hasMarkPerCapability, $maxMark,
        $hasSelfAssessed, $url,
        $summator
    ) {
        $this->setType($type);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setCreator($creator);
        $this->setWeight($weight);
        $this->setPublishAt(trim($publishAt));
        $this->setOpenAt(trim($openAt));
        $this->setDueAt(trim($dueAt));
        $this->setCloseAt(trim($closeAt));

        /* Projects need this to instantiate
         */
        $this->setSummator($summator);

        /* Due Work needs this to instantiate correctly
         */
        $this->setAcceptOnline(trim($acceptOnline));
        $this->setMarkType(trim($markType));
        $this->setGradingScheme(trim($gradingScheme));
        $this->setHasRubric(trim($hasRubric));
        $this->setHasMarkPerCapability(trim($hasMarkPerCapability));
        $this->setMaxMark(trim($maxMark));

        /* Task needs this to instantiate correctly
         */
        $this->setHasSelfAssessed($hasSelfAssessed);
        $this->setUrl($url);
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

        /* TODO: Attach files to instance
         */
        foreach ($this->getAttachedFiles() as $currentFile) {
            $assessment->getAttachedFiles()->add($currentFile);
        }

        /* Verify that dates are temporally correct
         */
        if ($assessment->isValidDates()) {

            /* TODO: Use Decorator pattern here, imho
             */
            switch($this->getType()) {
                case Types::ASSESSMENT_PROJECT:
                    /* TODO: Set project-specific attributes here
                     */
                    break;

                case Types::ASSESSMENT_DUE_WORK:
                    if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_NONE) {
                        $assessment->setAcceptsNone();
                    }
                    else if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_SINGLE) {
                        $assessment->setAcceptsSingle();
                    }
                    else if ($this->getAcceptOnline() == DueWork::ACCEPT_ONLINE_MULTI) {
                        $assessment->setAcceptsMultiple();
                    }

                    $assessment->setMaxMark($this->getMaxMark());

                    /* TODO: Replace old classes and stuff?
                     */
                    $markType = new \Schoolbox\Assessment\Component\SubmissionBox\MarkType();
                    $markType->setId($this->getMarkType());

                    $assessment->setMarkType($markType);

                    $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
                    $markScheme = $markSchemeFactory->spawnMarkSchemeFor($assessment);

                    $assessment->setMarkScheme($markScheme);

                    /* TODO: Replace old classes and stuff?
                     */
                    $gradingGroup = new \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model();
                    $gradingGroup->setId($this->getGradingScheme());

                    $assessment->setGradeGroup($gradingGroup);

                    if ($this->getHasRubric()) {
                        $assessment->useRubric();
                    }
                    else {
                        $assessment->noRubric();
                    }

                    if ($this->getHasMarkPerCapability()) {
                        $assessment->useMarkPerCapability();
                    }
                    else {
                        $assessment->noMarkPerCapability();
                    }

                    break;

                case Types::ASSESSMENT_TASK:
                    if ($this->getHasSelfAssessed()) {
                        $assessment->selfAssessed();
                    }
                    else {
                        $assessment->teacherAssessed();
                    }

                    $assessment->setUrl($this->getUrl());

                    break;

                case Types::ASSESSMENT_QUIZ:
                    /* TODO: Set quiz-specific attributes here
                     */
                    break;

                default:
                    break;
            }

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
     * @return array
     */
    protected function getAttachedFiles()
    {
        return $this->attachedFiles;
    }

    /**
     * @param array $attachedFiles
     * @return $this
     */
    protected function setAttachedFiles($attachedFiles)
    {
        $this->attachedFiles = $attachedFiles;
        return $this;
    }

    /* Due Work info
     */

    /**
     * @return int
     */
    protected function getAcceptOnline()
    {
        return $this->acceptOnline;
    }

    /**
     * @param int $acceptOnline
     * @return $this
     */
    protected function setAcceptOnline($acceptOnline)
    {
        $this->acceptOnline = $acceptOnline;
        return $this;
    }

    /**
     * @return int
     */
    protected function getMarkType()
    {
        return $this->markType;
    }

    /**
     * @param int $markType
     * @return $this
     */
    protected function setMarkType($markType)
    {
        $this->markType = $markType;
        return $this;
    }

    /**
     * @return int
     */
    protected function getGradingScheme()
    {
        return $this->gradingScheme;
    }

    /**
     * @param int $gradingScheme
     * @return $this
     */
    protected function setGradingScheme($gradingScheme)
    {
        $this->gradingScheme = $gradingScheme;
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

    /* Task info
     */

    /**
     * @return boolean
     */
    protected function getHasSelfAssessed()
    {
        return $this->hasSelfAssessed;
    }

    /**
     * @param boolean $hasSelfAssessed
     * @return $this
     */
    protected function setHasSelfAssessed($hasSelfAssessed)
    {
        $this->hasSelfAssessed = $hasSelfAssessed;
        return $this;
    }

    /**
     * @return int
     */
    protected function getMaxMark()
    {
        return $this->maxMark;
    }

    /**
     * @param int $maxMark
     */
    protected function setMaxMark($maxMark)
    {
        $this->maxMark = $maxMark;
    }

    /**
     * @return string
     */
    protected function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     * @return $this
     */
    protected function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /* Project info
     */

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