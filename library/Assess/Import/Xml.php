<?php
/**
 * Assess_Import_Xml
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizAssessable;
use \Schoolbox\Learning\Assessment\AssessmentContextType;
use \Evaluation\Import\Parser\IParser;
use Assess\Import\Parser\IParser as AssessParser;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Mapper\Exception as MapperException;


/**
 * Quiz-specific import-logic extension
 */
class Assess_Import_Xml extends Evaluation_Import_Xml
{

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * The newly created quiz
     *
     * @var QuizAssessable|null
     */
    private $quiz;

    /**
     * One of the enumerated context types - without trailing 's'
     *
     * @var string
     * @see AssessmentContextType::TYPE_FOLDER
     * @see AssessmentContextType::TYPE_PROJECT
     * @see AssessmentContextType::TYPE_UNIT_IMPLEMENTATION
     * @see AssessmentContextType::TYPE_UNIT_SPECIFICATION
     */
    private $contextType;

    /**
     * @var int
     */
    private $contextId;

    /**
     * @param int $categoryId
     * @param Evaluation_Main $assessHandler
     * @param Evaluation_Section $sectionHandler
     * @param Evaluation_Factory $factory
     * @param string $data
     * @param SitePage $page
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param string $contextType
     * @param int $contextId
     */
    public function __construct(
        $categoryId,
        Evaluation_Main $assessHandler,
        Evaluation_Section $sectionHandler,
        Evaluation_Factory $factory,
        $data,
        SitePage $page,
        AssessmentMapperProxy $assessmentMapperProxy,
        $contextType,
        $contextId
    ) {
        parent::__construct($categoryId, $assessHandler, $sectionHandler, $factory, $data, $page);
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->contextType = substr($contextType, 0, strlen($contextType) - 1);  // strip off trailing 's'
        $this->contextId = $contextId;
    }


    /**
     * Return the newly created Quiz
     *
     * @return IAssessable|null
     */
    public function getQuiz()
    {
        return $this->quiz;
    }


    /**
     * Gets a version-dependent parser that knows how to interpret a specific version format, but
     * respect a consistent API of representing the file's data.
     *
     * @param SimpleXMLElement $xml
     * @param string $fileFormatVersion
     * @return \Assess\Import\Parser\IParser|IParser
     */
    protected function getParser(SimpleXMLElement $xml, $fileFormatVersion)
    {
        switch($fileFormatVersion){
            case IParser::FORMAT_VERSION_160:
                return new \Assess\Import\Parser\V160($xml);
            case IParser::FORMAT_VERSION_165:
                return new \Assess\Import\Parser\V165($xml);
            case IParser::FORMAT_VERSION_170:
                return new \Assess\Import\Parser\V170($xml);
            default:
                throw new DomainException('No parser found for version: ' . $fileFormatVersion);
        }
    }

    /**
     * Creates the evaluation (without sections, questions or solutions)
     *
     * Returns the newly assigned 'assess_id' (not assessment.id)
     *
     * Responsible for context-specific behaviour (the parser is not responsible for this - it knows nothing of context)
     *
     * @param IParser|AssessParser $parser
     * @return int
     */
    protected function createEvaluation(IParser $parser)
    {
        $context = $this->getAssessmentContext();
        $inUnitSpec = $context->getBaseContext() instanceof UnitSpec;
        $nullDate = new Alaress_Date_NullDate();

        $workType = null;
        if ($parser->getWorkType()) {
            // TODO inject
            try {
                $workType = WorkType\Mapper::getInstance()->findOneByColumn('name', $parser->getWorkType());
            } catch (MapperException $e) {
                // noop
            }
        }

        // Create the IAssessable
        $quizAssessable = (new QuizAssessable())
            ->setCreator($_SESSION['user'])
            ->setCompletionMessage($parser->getCompletionMessage())
            ->setDuration($parser->getDuration())
            ->setContext($context)
            ->setTitle($parser->getTitle())
            ->setWorkType($workType)
            ->setDescription($parser->getDescription())
            ->setPublishAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+1 hour')))
            ->setOpenAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+1 hour')))
            ->setDueAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
            ->setCloseAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
            ->setWeight($parser->getWeight())
            ->setDefaultOpenSpan($parser->getDefaultOpenSpan())
            ->setMaxAttempts($parser->getMaxAttempts())
            ->setMarkVisibility($parser->getMarkVisibility());

        $this->assessmentMapperProxy->save($quizAssessable);

        // Notify about what was just made
        $this->assessmentMapperProxy->getNotifier()->notify($quizAssessable, $_SESSION['user'], []);

        $this->quiz = $quizAssessable;
        $this->assess_id = QuizMapper::getInstance()->findIdsByColumn('assessment_id', $this->quiz->getId())[0];
        return $this->assess_id;
    }


    /**
     * Gets the data-model context
     *
     * @return AssessmentContext
     */
    protected function getAssessmentContext()
    {
        switch($this->contextType){
            case AssessmentContextType::TYPE_FOLDER:
                return FolderMapper::getInstance()->findById($this->contextId);
            case AssessmentContextType::TYPE_UNIT_SPECIFICATION:
                return UnitSpecMapper::getInstance()->findById($this->contextId);
            case AssessmentContextType::TYPE_UNIT_IMPLEMENTATION:
                return UnitImplMapper::getInstance()->findById($this->contextId);
            case AssessmentContextType::TYPE_PROJECT:
                /** @var Project $project */
                $project = $this->assessmentMapperProxy->findById($this->contextId);
                return $project;
            default:
                throw new DomainException('Unknown context type: ' . $this->contextType);
        }
    }


    /**
     * @inheritdoc
     */
    protected function getAvailability()
    {
        return Quiz::AVAILABILITY_ASSESS;
    }
}
