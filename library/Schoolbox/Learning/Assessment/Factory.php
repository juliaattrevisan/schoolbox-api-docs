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

use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as Folder;
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
     * What context is this within?
     *
     * Forms a unique identifier along with $contextIdentifier.
     *
     * @var string
     */
    protected $contextType;

    /**
     * What's the identifier of the context?
     *
     * Forms a unique identifier along with $contextType.
     *
     * @var int
     */
    protected $contextIdentifier;

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
     * @param User $creator
     * @param $post (an array of post vars from the create/update forms)
     */
    public function __construct(User $creator, $post) {
        $this->setType(trim($post['assessmentType']));
        $this->setContextType(trim($post['contextType']));
        $this->setContextIdentifier(trim($post['contextId']));
        $this->setTitle(trim($post['title']));
        $this->setDescription(trim($post['description']));
        $this->setCreator($creator);
        $this->setWeight(intval($post['weight']));
        $this->setPublishAt(trim($post['publishDate'] . ' ' . $post['publishTime']));
        $this->setOpenAt(trim($post['openDate'] . ' ' . $post['openTime']));
        $this->setDueAt(trim($post['dueDate'] . ' ' . $post['dueTime']));
        $this->setCloseAt(trim($post['closeDate'] . ' ' . $post['closeTime']));

        /* Check if any attached files existed in POST data
         * Use their array if it existed
         */
        if (is_array($post['attachment'])) {
            $this->setAttachedFiles($post['attachment']);
        }

        /* Projects need this to instantiate
         */
        $this->setSummator($post['summator']);

        /* Due Work needs this to instantiate correctly
         */
        $this->setAcceptOnline(trim($post['acceptOnline']));
        $this->setMarkType(trim($post['markType']));
        $this->setGradingScheme(trim($post['gradingScheme']));
        $this->setHasRubric(trim($post['hasRubric']));
        $this->setHasMarkPerCapability(trim($post['hasMarkPerCapability']));
        $this->setMaxMark(trim($post['maxMark']));

        /* Task needs this to instantiate correctly
         */
        $this->setHasSelfAssessed(trim($post['hasSelfAssessed']));
        $this->setUrl(trim($post['url']));

        /* Quiz needs this to instantiate correctly
         */
        $this->setDurationHours(trim($post['durationHours']));
        $this->setDurationMinutes(trim($post['durationMinutes']));
        $this->setMaxAttempts(intval($post['maxAttempts']));
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

        // Figure out which context it's in
        // TODO make sure these are real
        // TODO move these constants
        $context = null;
        switch ($this->getContextType()) {
            case Controller::SPAWN_CONTEXT_FOLDER:
                $context = new Folder();
                break;

            case Controller::SPAWN_CONTEXT_UNIT_IMPLEMENTATION:
                $context = new UnitImplementation();
                break;

            case Controller::SPAWN_CONTEXT_UNIT_SPECIFICATION:
                $context = new UnitSpecification();
                break;

            case Controller::SPAWN_CONTEXT_PROJECT:
                $context = new Project();
                break;

            default:
                throw new \DomainException('select a valid context');
        }
        $assessment->setContext($context->setId($this->getContextIdentifier()));

        return $this->produceFromExistingAssessment($assessment);
    }

    /**
     * Produce object from an existing assessment
     *
     * @param IAssessable $assessment
     * @return DueWork|Project|Quiz|Task
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    public function produceFromExistingAssessment(IAssessable $assessment)
    {
        if ($assessment instanceof Project) {
            $decorator = new ProjectDecorator(
                $this->getMarkType()
            );

        } elseif ($assessment instanceof DueWork) {
            $decorator = new DueWorkDecorator(
                $this->getAcceptOnline(),
                $this->getMarkType(),
                $this->getGradingScheme(),
                $this->getHasRubric(),
                $this->getHasMarkPerCapability(),
                $this->getMaxMark()
            );

        } elseif ($assessment instanceof Task) {
            $decorator = new TaskDecorator(
                $this->getHasSelfAssessed(),
                $this->getUrl()
            );

        } elseif ($assessment instanceof Quiz) {
            $decorator = new QuizDecorator(
                $this->getDurationHours(),
                $this->getDurationMinutes(),
                $this->getMaxAttempts()
            );

        } else {
            throw new \DomainException('select a valid assessment type');
        }

        /* Check required data
         */
        if (!strlen(trim($this->getTitle()))) {
            throw new \InvalidArgumentException('set an assessment title');
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

        /* Decorate with Assessable-specific data
         */
        $decorator->decorate($assessment);

        return $assessment;
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

    /**
     * @return string
     */
    protected function getContextType()
    {
        return $this->contextType;
    }

    /**
     * @param string $contextType
     * @return static
     */
    protected function setContextType($contextType)
    {
        $this->contextType = $contextType;
        return $this;
    }

    /**
     * @return int
     */
    protected function getContextIdentifier()
    {
        return $this->contextIdentifier;
    }

    /**
     * @param int $contextIdentifier
     * @return static
     */
    protected function setContextIdentifier($contextIdentifier)
    {
        $this->contextIdentifier = $contextIdentifier;
        return $this;
    }
}
