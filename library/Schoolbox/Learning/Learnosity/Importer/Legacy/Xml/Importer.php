<?php

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use Alaress\Database\Connector\IConnector;
use Evaluation\Import\Parser\IParser;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Learnosity\Importer\BlueprintBuilder;
use Schoolbox\Learning\Learnosity\Importer\Cli\ImporterTool;
use Schoolbox\Learning\Learnosity\Importer\ContextLookup;
use Schoolbox\Learning\Learnosity\Importer\PayloadFactory;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use SimpleXMLElement;
use Symfony\Component\Lock\LockInterface;

class Importer
{
    /**
     * Constructor.
     *
     * @param LoggerInterface                 $logger
     * @param Api                             $api
     * @param string                          $schoolCode
     * @param string                          $domain
     * @param IConnector                      $databaseInternal
     * @param AssessmentContext               $context
     * @param User                            $user
     * @param AssessmentMapperProxy           $assessmentMapperProxy
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     * @param Mapper                          $workTypeMapper
     * @param MarkTypeMapper                  $markTypeMapper
     */
    public function __construct(
        private LoggerInterface $logger,
        private Api $api,
        private string $schoolCode,
        private string $domain,
        private IConnector $databaseInternal,
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
        /* We need to use this later
         */
        $xml = $this->prepareXml($data);

        /* Set up a parser to traverse the xml
         */
        $parser = $this->produceParser($xml);

        /* Produce IAssessable and quiz subobject instances into an array for convenience
         * (these are already persisted in the db)
         */
        $instances = $this->buildAssessment($parser);

        /* Start manufacturing quiz payloads for learnosity
         * This is mostly stolen from the cli ImpoterTool
         */

        /* Used to make representations of questions and answers
         */
        $strategyFactory = new Strategy\Factory($this->domain);

        /* Used to manufacture an array containing all the legacy quiz's contents
         */
        $quizBuilder = new Builder($strategyFactory);

        /* Generates sets of tags for Activities' and Items' assignations
         */
        $tagFactory = new TagFactory($this->schoolCode);

        /* A context lookup builder thing for conveniently deriving tags
        */
        /* FIXME Replace with usage of AssessmentContext since we have no
         *  idea where this assessment's context details are (it comes from
         *  an xml where there's no record of it in the db)
         */
        $contextLookup = new ContextLookup($this->databaseInternal, $tagFactory, $this->logger);

        /* Generates payload array structs
         */
        $payloadFactory = new PayloadFactory($strategyFactory);

        /* Generates payload "blueprints" so you can inspect them before committing to
         * sending them to Learnosity
         */
        $blueprintBuilder = new BlueprintBuilder($this->schoolCode, $payloadFactory, $contextLookup, $this->logger);

        /* Traverse the xml, loading each question
         */
        $legacyQuiz = $quizBuilder->build($xml);

        /* Figure out how to consolidate payloads
         */
        if ($legacyQuiz['numQuestions'] <= ImporterTool::LEARNOSITY_MAX_PAYLOAD_SIZE) {
            $this->importSmall(
                $legacyQuiz,
                $blueprintBuilder,
                $this->api
            );
        } else {
            $this->importLarge(
                $legacyQuiz,
                $blueprintBuilder,
                $this->api
            );
        }

        // TODO Continue stealing from ImporterTool

        /* TODO probably display a list of questions and their status, maybe
         */
        return [
            'assessment' => $instances['assessment'],
            'quiz'       => $instances['quiz']
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
        $result     = simplexml_load_string($xml);

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
     * @param \SimpleXmlElement $xml
     * @return IParser
     */
    private function produceParser(\SimpleXmlElement $xml): IParser
    {
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
            ->setMarkType($this->markTypeMapper->findOneByColumn('mark_type_id', MarkType::TYPE_TOTAL));

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
            'quiz'       => $quiz,
        ];
    }


    /**
     * Import a small quiz
     *
     * @param array            $legacyQuiz
     * @param BlueprintBuilder $blueprintBuilder
     * @param Api              $api
     * @return void
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function importSmall(
        array $legacyQuiz,
        BlueprintBuilder $blueprintBuilder,
        Api $api
    ): void {
        /* Make the overall Learnosity payloads which we then send later with
         * a little postprocessing to put things into the exactly correct format.
         * Learnosity is really particular about such things.
         */
        try {
            $blueprint = $blueprintBuilder->buildFlattenSections($legacyQuiz);
        } catch (\DomainException $e) {
            return;
        }

        /* We can perhaps minimise more Learnosity requests by batching $blueprints
         * which typically represent a single quiz. There's a limit on the number of
         * quizzes, but there's also a limit on number of features/questions/items
         * which isn't particularly obvious
         */
        $consolidatedBlueprint = $blueprintBuilder->consolidateNew($blueprint);

        /* Send it
         */
        $this->transmitConsolidated($api, $consolidatedBlueprint);
    }


    /**
     * Sends data to Learnosity and returns updated metrics
     *
     * @param Api   $api
     * @param array $consolidatedBlueprint
     * @return void
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function transmitConsolidated(Api $api, array $consolidatedBlueprint): void
    {
        $this->logger->info(sprintf(
            'Importing %s consolidated quizzes into Learnosity.',
            $consolidatedBlueprint[BlueprintBuilder::ENDPOINT_ASSOCIATIONS]['count']
        ));

        foreach ($consolidatedBlueprint as $endpoint => $data) {
            /* Associate the learnosity reference back to our legacy quiz
             */
            if (BlueprintBuilder::ENDPOINT_ASSOCIATIONS === $endpoint) {
                foreach ($data['payload']['associations'] as $association) {
                    $this->logger->info(sprintf(
                        'Associating quiz (assessment_id: %s, assess_id: %s) with %s...',
                        $association['assessmentId'],
                        $association['quizId'],
                        substr($association['activityLearnosityReference'], 0, 13)
                    ));

                    /* Associate with a retry mechanism
                     */
                    try {
                        $this->associate($association);
                    } catch (\RuntimeException $e) {
                        $this->logger->warning($e->getMessage());
                        continue;
                    }
                }
            } else {
                $this->logger->debug(sprintf(
                    '%s: transmitting payload (size: %s) to Learnosity...',
                    $data['endpoint'],
                    $data['count']
                ));

                /* Skip zero-sized payloads; Learnosity hates that
                 *
                 * (alternatively capture exception but probably not helpful most of the time)
                 *
                 * The main way this has happened to me is when you create a quiz
                 * using Learnosity, then artificially reset its learnosity_reference
                 * to null, then try to reimport that back into Learnosity - this is
                 * an exceedingly particular scenario that shouldn't happen organically.
                 *
                 * Or, there's just an empty section somehow.
                 */
                if ($data['count'] > 0) {
                    try {
                        $api->dataSetRequestHub($data['endpoint'], $data['payload']);
                    } catch (ApiException $e) {
                        /* Warn us, then keep going
                         */
                        $this->logger->warning($e->getMessage());
                        $this->logger->warning('Consolidated payload abandoned.');

                        continue;
                    }
                }
            }
        }
    }


    /**
     * Import a large quiz by spreading its contents over multiple payloads
     *
     * @param array            $legacyQuiz
     * @param BlueprintBuilder $blueprintBuilder
     * @param Api              $api
     * @return void
     */
    private function importLarge(
        array $legacyQuiz,
        BlueprintBuilder $blueprintBuilder,
        Api $api
    ) {
    }
}
