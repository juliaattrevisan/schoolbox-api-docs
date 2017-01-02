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

use Alaress_Date_Format;
use Alaress_Date_NullDate;
use InvalidArgumentException;
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
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;

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
     * @var int
     */
    protected $duration;

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
     * @var int
     */
    private $markVisibility;


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
        $this->setDuration(intval($post['duration']));
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
        $this->setMarkVisibility(intval($post['visibility']));
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
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
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
            throw new \DomainException('Select a valid assessment type');
        }

        /* Check required data
         */
        if (!strlen(trim($this->getTitle()))) {
            $typeName = $assessment->getTypeName();
            throw new InvalidArgumentException("set a title for this $typeName");
        }

        /* Start manufacturing
         */
        $assessment
            ->setTitle($this->getTitle())
            ->setDescription($this->getDescription())
            ->setCreator($this->getCreator())
            ->setWeight($this->getWeight())
            ->setDuration($this->getDuration())
            ->setMarkVisibility($this->getMarkVisibility())
            ->setPublishAt($this->deriveDate($assessment->getBaseContext(), $this->getPublishAt()))
            ->setOpenAt($this->deriveDate($assessment->getBaseContext(), $this->getOpenAt()))
            ->setDueAt($this->deriveDate($assessment->getBaseContext(), $this->getDueAt()))
            ->setCloseAt($this->deriveDate($assessment->getBaseContext(), $this->getCloseAt()))
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        // find out if the dates are all g - if not, a mage-friendly exception will be thrown
        $assessment->assertValidDates();

        /* Decorate with Assessable-specific data
         */
        $decorator->decorate($assessment);

        return $assessment;
    }


    /**
     * @param AssessmentContext $context
     * @param string            $dateString
     * @return Alaress_Date_Format
     */
    private function deriveDate(AssessmentContext $context, $dateString)
    {
        // no dates exist in spec-land
        if ($context instanceof UnitSpecification) {
            return new Alaress_Date_NullDate();
        }

        try {
            // attempt to parse the date
            return Alaress_Date_Format::newLocal($dateString);

        } catch (InvalidArgumentException $e) {
            // unparseable - safely return null
            return new Alaress_Date_NullDate();
        }
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
     * @return float|int
     */
    protected function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param float|int $duration
     * @return $this
     */
    protected function setDuration($duration)
    {
        $this->duration = $duration;
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

    /**
     * @return int
     */
    protected function getMarkVisibility()
    {
        return $this->markVisibility;
    }

    /**
     * @param int $markVisibility
     * @return static
     */
    protected function setMarkVisibility($markVisibility)
    {
        $this->markVisibility = $markVisibility;
        return $this;
    }
}
