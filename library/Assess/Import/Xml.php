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

class Assess_Import_Xml extends Evaluation_Import_Xml
{
    /**
     * @var AssessmentMapper
     */
    private $assessmentMapper;

    /**
     * @var string
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
     * Creates the evaluation (without sections, questions or solutions)
     *
     * @param SimpleXMLElement $xml
     * @return int                   The newly assigned assess_id
     */
    protected function createEvaluation(SimpleXMLElement $xml)
    {
        // Create the IAssessable
        $quizAssessable = $this->assessmentMapper->createModel()
            ->setAssessmentContext(AssessmentContextType::stringToObject($this->contextType)->setId($this->contextId))
            ->setTitle((string)$xml->title)
            ->setDescription($this->parseRichTextNode($xml, 'description'))
            ->setPublishDate($this->createDateFromString($xml->publishdate))
            ->setOpenDate($this->createDateFromString($xml->opendate))
            ->setDueDate($this->createDateFromString($xml->duedate))
            ->setCloseDate($this->createDateFromString($xml->closedate))
            ->setWeight((int)$xml->weight)
            ->setDefaultOpenSpan((int)$xml->defaultopenspan);

        $assessmentId = $this->assessmentMapper->save($quizAssessable)[0];

        $quiz = $this->getQuizMapper()->createModel()
            ->setCategory($quizAssessable->getAssessmentContext()->getFolder())
            ->setAssessmentModel((new Assessment())->setId($assessmentId))
            ->setUser($_SESSION['user'])
            ->setWorkType((new WorkType())->setId(WorkType::TYPE_ONLINE_ASSESSMENT))
            ->setTitle($quizAssessable->getTitle())
            ->setDescription($quizAssessable->getDescription())
            ->setCompletionMessage($this->parseRichTextNode($xml, 'completionmessage'))
            ->setStart($quizAssessable->getPublishDate())
            ->setFinish($quizAssessable->getDueDate())
            ->setDuration((int)$xml->duration)
            ->setMaxAttempts($this->getMaxAttempts($xml))
            ->setWeight($quizAssessable->getWeight())
            ->setAvailability(Quiz::AVAILABILITY_ASSESS)
            ->setMarkVisibility((int)$xml->markvisibility);

        return $this->getQuizMapper()->save($quiz)[0];
    }
}
