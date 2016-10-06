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

use \Schoolbox\Learning\Assessment\DueWork\Decorator\DueWorkTrait as DueWorkTrait;
use \Schoolbox\Learning\Assessment\DueWork\Decorator\Decorator as DueWorkDecorator;

use \Schoolbox\Learning\Assessment\Quiz\Decorator\QuizTrait as QuizTrait;
use \Schoolbox\Learning\Assessment\Quiz\Decorator\Decorator as QuizDecorator;

use \Schoolbox\Learning\Assessment\Task\Decorator\TaskTrait as TaskTrait;
use \Schoolbox\Learning\Assessment\Task\Decorator\Decorator as TaskDecorator;

use \Schoolbox\Learning\Assessment\Project\Decorator\ProjectTrait as ProjectTrait;
use \Schoolbox\Learning\Assessment\Project\Decorator\Decorator as ProjectDecorator;

class Factory
{
    use DueWorkTrait;
    use QuizTrait;
    use TaskTrait;
    use ProjectTrait;

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
     * @param $maxMark
     * @param $hasSelfAssessed
     * @param $url
     * @param $durationHours
     * @param $durationMinutes
     * @param $summator
     */
    public function __construct(
        $type, $title, $description, User $creator, $weight,
        $publishAt, $openAt, $dueAt, $closeAt, $attachedFiles,
        $acceptOnline, $markType, $gradingScheme, $hasRubric, $hasMarkPerCapability, $maxMark,
        $hasSelfAssessed, $url,
        $durationHours, $durationMinutes,
        $summator
    ) {
        $this->setType(trim($type));
        $this->setTitle(trim($title));
        $this->setDescription(trim($description));
        $this->setCreator($creator);
        $this->setWeight(trim($weight));
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
        $this->setHasSelfAssessed(trim($hasSelfAssessed));
        $this->setUrl(trim($url));

        /* Quiz needs this to instantiate correctly
         */
        $this->setDurationHours(trim($durationHours));
        $this->setDurationMinutes(trim($durationMinutes));
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
                $decorator = new ProjectDecorator();
                break;

            case Types::ASSESSMENT_DUE_WORK:
                $assessment = new DueWork();
                $decorator = new DueWorkDecorator(
                    $this->getAcceptOnline(),
                    $this->getMarkType(),
                    $this->getGradingScheme(),
                    $this->getHasRubric(),
                    $this->getHasMarkPerCapability(),
                    $this->getMaxMark()
                );
                break;

            case Types::ASSESSMENT_TASK:
                $assessment = new Task();
                $decorator = new TaskDecorator(
                    $this->getHasSelfAssessed(),
                    $this->getUrl()
                );
                break;

            case Types::ASSESSMENT_QUIZ:
                $assessment = new Quiz();
                $decorator = new QuizDecorator(
                    $this->getDurationHours(),
                    $this->getDurationMinutes()
                );
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
            $decorator->decorate($assessment);

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
}