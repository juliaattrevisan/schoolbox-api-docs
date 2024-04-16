<?php

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use Evaluation\Import\Parser\IParser;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use SimpleXMLElement;

class Importer
{
    /**
     * Constructor.
     *
     * @param AssessmentContext               $context
     * @param User                            $user
     * @param AssessmentMapperProxy           $assessmentMapperProxy
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     * @param Mapper                          $workTypeMapper
     * @param MarkTypeMapper                  $markTypeMapper
     */
    public function __construct(
        private AssessmentContext $context,
        private \Schoolbox\User $user,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private WorkType\Mapper $workTypeMapper,
        private MarkTypeMapper $markTypeMapper
    ) {
    }


    /**
     * Builds an IAssessable instance out of the xml, returns it and the parser itself
     * so you can continue making data with it elsewhere
     *
     * @param string $data
     * @return array
     * @throws Deleted
     * @throws MapperException
     */
    public function import(string $data): array
    {
        /* Set up a parser to traverse the xml
         */
        $parser = $this->produceParser($data);

        /* Produce IAssessable and quiz subobject instances into an array for convenience
         * (these are already persisted in the db)
         */
        $instances = $this->buildAssessment($parser);

        /* TODO Traverse the xml, loading each question
         */

        /* TODO probably display a list of questions and their status, maybe
         */
        return [
            'assessment' => $instances['assessment'],
            'quiz' => $instances['quiz']
        ];
    }


    /**
     * Convert raw string of xml $xml into a SimpleXmlElement instance
     *
     * @param string $xml
     * @return SimpleXMLElement
     */
    private function prepareXml(string $xml): SimpleXMLElement
    {
        $use_errors = libxml_use_internal_errors(true);
        $result = simplexml_load_string($xml);

        if (!($result instanceof SimpleXMLElement)) {
            $result = simplexml_load_string(html_entity_decode($this->getData(), ENT_QUOTES, 'UTF-8'));
        }

        libxml_use_internal_errors($use_errors);

        return $result;
    }


    /**
     * Returns what version file the user is trying to import.
     *
     * Prior to 16.5, no version numbering existed, so assume 16.0
     *
     * @param SimpleXMLElement $xml
     * @return string
     */
    protected function detectVersion(SimpleXMLElement $xml): string
    {
        $fileVersion = (string)$xml->version;

        return $fileVersion ?: \Evaluation\Import\Parser\Base::FORMAT_VERSION_160;
    }


    /**
     * Gets a version-dependent parser that knows how to interpret a specific version format, but
     * respect a consistent API of representing the file's data.
     *
     * @param string $data
     * @return IParser
     */
    private function produceParser(string $data): IParser
    {
        $xml = $this->prepareXml($data);
        $version = $this->detectVersion($xml);

        return match ($version) {
            IParser::FORMAT_VERSION_160 => new \Assess\Import\Parser\V160($xml),
            IParser::FORMAT_VERSION_165 => new \Assess\Import\Parser\V165($xml),
            IParser::FORMAT_VERSION_170 => new \Assess\Import\Parser\V170($xml),
            default => throw new \DomainException('No parser found for version: ' . $version),
        };
    }


    /**
     * Persist IAssessable and quiz subobject instances
     *
     * @param IParser $parser
     * @return array
     * @throws Deleted
     * @throws MapperException
     */
    private function buildAssessment(IParser $parser): array
    {
        $inUnitSpec = $this->context->getBaseContext() instanceof \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;

        /* Find a matchign work type if one was specified
         */
        if ($parser->getWorkType()) {
            try {
                $workType = $this->workTypeMapper->findOneByColumn('name', $parser->getWorkType());
            } catch (MapperException $e) {
                /* The specified work type doesn't exist; find the first meaningful one
                 */
                $workType = $this->workTypeMapper->findAll()->sortBy('sequence', 'ASC')->first();
            }
        } else {
            /* Work Type wasn't provided, but it's mandatory now
             * Find the first meaningful one
             */
            $workType = $this->workTypeMapper->findAll()->sortBy('sequence', 'ASC')->first();
        }

        // Create the IAssessable
        $assessment = (new \Schoolbox\Learning\Assessment\Quiz\Model())
            ->setCreator($this->user)
            ->setCompletionMessage($parser->getCompletionMessage())
            ->setTitle($parser->getTitle())
            ->setWorkType($workType)
            ->setDescription($parser->getDescription())
            ->setPublishAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+1 hour')))
            ->setOpenAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+1 hour')))
            ->setDueAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
            ->setCloseAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
            ->setWeight($parser->getWeight())
            ->setDefaultOpenSpan($parser->getDefaultOpenSpan())
            ->setMaxAttempts($parser->getMaxAttempts())
            ->setMarkVisibility($parser->getMarkVisibility())
            /* Learnosity-era unlimited durations are represented by zero, not -1
             */
            ->setDuration(max($parser->getDuration(), Model::DURATION_UNLIMITED))
            /* Learnosity doesn't support randomised items
             */
            ->setShuffleItems(false)
            /* Legacy XML never knew about Prompts Mode
             */
            ->setPromptsMode(false)
            /* Legacy XML only ever supported Total Mark
             */
            ->setMarkType($this->markTypeMapper->findOneByColumn('mark_type_id', MarkType::TYPE_TOTAL))
        ;

        /* Update context if it's a Project
         * (for whatever reason, Project contexts need a list of their constituent assessments)
         */
        if ($this->context instanceof \Schoolbox\Learning\Assessment\Project) {
            // Attach context
            // @see Schoolbox\Learning\Assessment\Factory produceNew()
            $this->context->getAssessables()->add($assessment);
        }

        $assessment->setContext($this->context);

        $this->assessmentMapperProxy->save($assessment, $this->user);

        // Notify about what was just made
        $this->assessmentMapperProxy->getNotifier()->notify($assessment, $this->user, []);

        /* We might need this quiz instance for saving a learnosity_reference later
         */
        $quiz = $this->quizMapper->findOneByColumn('assessment_id', $assessment->getId());

        return [
            'assessment' => $assessment,
            'quiz' => $quiz,
        ];
    }
}
