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

use Schoolbox\Learning\AssessmentContext;
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
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

class Factory
{
    use DueWorkTrait;
    use QuizTrait;
    use TaskTrait;
    use ProjectTrait;

    /**
     * Unique identifier
     *
     * @var string
     */
    protected $assessmentType;

    /**
     * What context is this within?
     *
     *
     * @var AssessmentContext
     */
    protected $context;

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
     * Factory constructor.
     * @param User $creator
     * @param $post (an array of post vars from the create/update forms)
     */
    public function __construct(User $creator, $post) {
        $this->setAssessmentType(trim($post['assessmentType']));
        $this->setContext($post['context']);
        $this->setTitle(trim($post['title']));
        $this->setDescription(trim($post['description']));
        $this->setCreator($creator);
        $this->setWeight(intval($post['weight']));
        $this->setPublishAt(trim($post['publishDate'] . ' ' . $post['publishTime']));
        $this->setOpenAt(trim($post['openDate'] . ' ' . $post['openTime']));
        $this->setDueAt(trim($post['dueDate'] . ' ' . $post['dueTime']));
        $this->setCloseAt(trim($post['closeDate'] . ' ' . $post['closeTime']));

        /* Projects need this to instantiate
         */
        $this->setSummator($post['summator']);

        /* Due Work needs this to instantiate correctly
         */
        $this->setAcceptOnline(trim($post['acceptOnline']));
        $this->setWorkType(trim($post['workType']));
        $this->setMarkType(trim($post['markType']));
        $this->setGradingScheme(trim($post['gradingScheme']));
        $this->setUseRubric(trim($post['useRubric']));
        $this->setMarkPerCapability(trim($post['markPerCapability']));
        $this->setMaxMark(trim($post['maxMark']));

        $this->setRubricData($post[$post['formField']]);

        /* Check if any attached files existed in POST data
         * Use their array if it existed
         */
        if (is_array($post['attachment'])) {
            $this->setAttachedFiles($post['attachment']);
        }

        /* Task needs this to instantiate correctly
         */
        $this->setHasSelfAssessed(trim($post['hasSelfAssessed']));
        $this->setUrl(trim($post['url']));
        /* Set just a single attached file for task
         */
        if (is_array($post['attachment'])) {
            $this->setAttachedFile(isset($this->getAttachedFiles()[0]) ? $this->getAttachedFiles()[0] : null);
        }

        /* Quiz needs this to instantiate correctly
         */
        $this->setDurationHours(trim($post['durationHours']));
        $this->setDurationMinutes(trim($post['durationMinutes']));
        $this->setMaxAttempts(intval($post['maxAttempts']));
    }

    /**
     * Produce an assessment
     *
     * Either spawn a new one, or decorate an existing one
     * based on the arg
     *
     * @param IAssessable|null $assessment
     * @return IAssessable
     */
    public function produce(IAssessable $assessment = null)
    {
        if ($assessment instanceof IAssessable) {
            return $this->produceFromExistingAssessment($assessment);
        }

        return $this->produceNew();
    }

    /**
     * Produce object
     *
     * @return IAssessable
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function produceNew()
    {
        // Instantiate empty Assessment model
        $assessment = AssessmentType::stringToObject($this->getAssessmentType());
        if($assessment instanceof Project){
            $assessment->setSummator((new SummatorFactory($this->getSummator()))->produce());
        }

        // Attach context
        $assessment->setContext($this->getContext());

        return $this->produceFromExistingAssessment($assessment);
    }

    /**
     * Produce object from an existing assessment
     *
     * @param IAssessable $assessment
     * @return IAssessable
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    private function produceFromExistingAssessment(IAssessable $assessment)
    {
        if ($assessment instanceof Project) {
            $decorator = new ProjectDecorator(
                $this->getMarkType(),
                $this->getGradingScheme(),
                $this->getSummator()
            );

        } elseif ($assessment instanceof DueWork) {
            $decorator = new DueWorkDecorator(
                $this->getAcceptOnline(),
                $this->getWorkType(),
                $this->getMarkType(),
                $this->getGradingScheme(),
                $this->getUseRubric(),
                $this->getMarkPerCapability(),
                $this->getMaxMark(),
                $this->getRubricData(),
                $this->getAttachedFiles()
            );

        } elseif ($assessment instanceof Task) {
            $decorator = new TaskDecorator(
                $this->getHasSelfAssessed(),
                $this->getUrl(),
                $this->getAttachedFile()
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
            $typeName = $assessment->getTypeName();
            throw new \InvalidArgumentException("Insufficient information. You need to set a title for this $typeName");
        }

        if(!$assessment->getBaseContext() instanceof UnitSpecification) {
            /* Check dates are provided
             * Re-throw a more friendly exception message than crappy "Laravel blahblah data missing":
             */

            try {
                $publishAt = \Alaress_Date_Format::newLocal($this->getPublishAt());
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('set a publish date');
            }

            if($assessment instanceof Task) {
                $openAt = new \Alaress_Date_NullDate();
            } else {
                try {
                    $openAt = \Alaress_Date_Format::newLocal($this->getOpenAt());
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException('set a open date');
                }
            }

            try {
                $dueAt = \Alaress_Date_Format::newLocal($this->getDueAt());
            } catch (\InvalidArgumentException $e) {
                throw new \InvalidArgumentException('set a due date');
            }

            if($assessment instanceof Task) {
                $closeAt = new \Alaress_Date_NullDate();
            } else {
                try {
                    $closeAt = \Alaress_Date_Format::newLocal($this->getCloseAt());
                } catch (\InvalidArgumentException $e) {
                    throw new \InvalidArgumentException('set a close date');
                }
            }
        }
        else {
            $publishAt = new \Alaress_Date_NullDate();
            $openAt = new \Alaress_Date_NullDate();
            $dueAt = new \Alaress_Date_NullDate();
            $closeAt = new \Alaress_Date_NullDate();
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

        /* Decorate with Assessable-specific data
         */
        $decorator->decorate($assessment);

        return $assessment;
    }

    /* Getters/Setters
     */

    /**
     * @return string
     */
    protected function getAssessmentType()
    {
        return $this->assessmentType;
    }

    /**
     * @param string $assessmentType
     * @return $this
     */
    protected function setAssessmentType($assessmentType)
    {
        $this->assessmentType = $assessmentType;
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
     * @return AssessmentContext
     */
    protected function getContext()
    {
        return $this->context;
    }

    /**
     * @param AssessmentContext $context
     * @return static
     */
    protected function setContext($context)
    {
        $this->context = $context;
        return $this;
    }
}
