<?php
/**
 * Assess_Import_Xml
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\DataModel\Model as Assessment;
use \Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use \Evaluation\Import\Parser\IParser;
use Assess\Import\Parser\IParser as AssessParser;

/**
 * Quiz-specific import-logic extension
 */
class Assess_Import_Xml extends Evaluation_Import_Xml
{
    /**
     * @var AssessmentMapper
     */
    private $assessmentMapper;

    /**
     * The newly assigned assessment.id
     *
     * @var int|null
     */
    private $assessmentId;

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
     * @param AssessmentMapper $assessmentMapper
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
        AssessmentMapper $assessmentMapper,
        $contextType,
        $contextId
    ) {
        parent::__construct($categoryId, $assessHandler, $sectionHandler, $factory, $data, $page);
        $this->assessmentMapper = $assessmentMapper;
        $this->contextType = substr($contextType, 0, strlen($contextType) - 1);  // strip off trailing 's'
        $this->contextId = $contextId;
    }


    /**
     * Return the newly assigned assessment.id
     *
     * @return int|null
     */
    public function getAssessmentId()
    {
        return $this->assessmentId;
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
            default:
                throw new DomainException('No parser found for version: ' . $fileFormatVersion);
        }
    }

    /**
     * Creates the evaluation (without sections, questions or solutions)
     *
     * Returns the newly assigned 'assess_id'
     *
     * Responsible for context-specific behaviour (the parser is not responsible for this - it knows nothing of context)
     *
     * @param IParser|AssessParser $parser
     * @return int
     */
    protected function createEvaluation(IParser $parser)
    {
        $nullDate = new Alaress_Date_NullDate();

        // Create the IAssessable
        $quizAssessable = $this->assessmentMapper->createModel()
            ->setAssessmentContext(AssessmentContextType::stringToObject($this->contextType)->setId($this->contextId))
            ->setTitle($parser->getTitle())
            ->setDescription($parser->getDescription())
            ->setPublishDate(AssessmentContextType::TYPE_UNIT_SPECIFICATION == $this->contextType ? $nullDate : $parser->getPublishDate())
            ->setOpenDate(AssessmentContextType::TYPE_UNIT_SPECIFICATION == $this->contextType ? $nullDate : $parser->getOpenDate())
            ->setDueDate(AssessmentContextType::TYPE_UNIT_SPECIFICATION == $this->contextType ? $nullDate : $parser->getDueDate())
            ->setCloseDate(AssessmentContextType::TYPE_UNIT_SPECIFICATION == $this->contextType ? $nullDate : $parser->getCloseDate())
            ->setWeight($parser->getWeight())
            ->setDefaultOpenSpan($parser->getDefaultOpenSpan());

        $this->assessmentId = $this->assessmentMapper->save($quizAssessable)[0];

        $quiz = $this->getQuizMapper()->createModel()
            ->setCategory($quizAssessable->getAssessmentContext()->getFolder())
            ->setAssessmentModel((new Assessment())->setId($this->assessmentId))
            ->setUser($_SESSION['user'])
            ->setWorkType((new WorkType())->setId(WorkType::TYPE_ONLINE_ASSESSMENT))
            ->setTitle($quizAssessable->getTitle())
            ->setDescription($quizAssessable->getDescription())
            ->setCompletionMessage($parser->getCompletionMessage())
            ->setStart(AssessmentContextType::TYPE_UNIT_SPECIFICATION == $this->contextType ? $nullDate : $quizAssessable->getPublishDate())
            ->setFinish(AssessmentContextType::TYPE_UNIT_SPECIFICATION == $this->contextType ? $nullDate : $quizAssessable->getDueDate())
            ->setDuration($parser->getDuration())
            ->setMaxAttempts($parser->getMaxAttempts())
            ->setWeight($quizAssessable->getWeight())
            ->setAvailability($this->getAvailability())
            ->setMarkVisibility($parser->getMarkVisibility());

        return $this->getQuizMapper()->save($quiz)[0];
    }

    /**
     * @inheritdoc
     */
    protected function getAvailability()
    {
        return Quiz::AVAILABILITY_ASSESS;
    }
}
