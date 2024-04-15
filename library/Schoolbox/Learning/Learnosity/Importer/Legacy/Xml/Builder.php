<?php

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use Evaluation\Import\Parser\IParser;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use SimpleXMLElement;

class Builder
{
    /**
     * Constructor.
     *
     * @param AssessmentContext               $context
     * @param User                            $user
     * @param AssessmentMapperProxy           $assessmentMapperProxy
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     * @param Mapper                          $workTypeMapper
     */
    public function __construct(
        private AssessmentContext $context,
        private \Schoolbox\User $user,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private WorkType\Mapper $workTypeMapper
    ) {
    }


    /**
     * Builds an IAssessable instance out of the xml, returns it and the parser itself
     * so you can continue making data with it elsewhere
     *
     * @param string $data
     * @return array
     * @throws MapperException
     * @throws Deleted
     */
    public function build(string $data): array
    {
        $xml = $this->prepareXml($data);
        $version = $this->detectVersion($xml);
        $parser = $this->produceParser($xml, $version);

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
            ->setDuration($parser->getDuration())
            ->setTitle($parser->getTitle())
            ->setWorkType($workType)
            ->setDescription($parser->getDescription())
//            ->setPublishAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+1 hour')))
//            ->setOpenAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+1 hour')))
//            ->setDueAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
//            ->setCloseAt($inUnitSpec ? $nullDate : new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
            ->setWeight($parser->getWeight())
            ->setDefaultOpenSpan($parser->getDefaultOpenSpan())
            ->setMaxAttempts($parser->getMaxAttempts())
            ->setMarkVisibility($parser->getMarkVisibility());

        if ($this->context instanceof \Schoolbox\Learning\Assessment\Project) {
            // Attach context
            // @see Schoolbox\Learning\Assessment\Factory produceNew()
            $this->context->getAssessables()->add($assessment);
        }

        $assessment->setContext($this->context);

        // FIXME Temporarily disabled
//        $this->assessmentMapperProxy->save($quizAssessable, $this->user);

        // Notify about what was just made
        $this->assessmentMapperProxy->getNotifier()->notify($assessment, $this->user, []);

        /* We might need this quiz instance for saving a learnosity_reference later
         */
        new \Alaress_Probe('test');
        die();
        $quiz = $this->quizMapper->findOneByColumn('assessment_id', $assessment->getId());

        return [
            'assessment' => $assessment,
            'quiz' => $quiz,
            'parser' => $parser
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
     * @param SimpleXMLElement $xml
     * @param string $fileFormatVersion
     * @return IParser
     */
    private function produceParser(SimpleXMLElement $xml, string $fileFormatVersion): IParser
    {
        return match ($fileFormatVersion) {
            IParser::FORMAT_VERSION_160 => new \Assess\Import\Parser\V160($xml),
            IParser::FORMAT_VERSION_165 => new \Assess\Import\Parser\V165($xml),
            IParser::FORMAT_VERSION_170 => new \Assess\Import\Parser\V170($xml),
            default => throw new \DomainException('No parser found for version: ' . $fileFormatVersion),
        };
    }
}
