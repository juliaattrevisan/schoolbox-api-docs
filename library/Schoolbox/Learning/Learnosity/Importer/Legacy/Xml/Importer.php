<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Legacy\Xml;

use Alaress\Database\Connector\IConnector;
use Assess\Import\Parser\IParser;
use DebugBar\DebugBarException;
use LearnositySdk\Exceptions\ValidationException;
use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\BlueprintBuilder;
use Schoolbox\Learning\Learnosity\Importer\InlineImageConverter;
use Schoolbox\Learning\Learnosity\Importer\PayloadFactory;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;
use SimpleXMLElement;

class Importer
{
    /**
     * The xml node must contain this string in the availability node
     * so we only accept true legacy quizzes, and not some other random
     * Survey xl
     */
    private const QUIZ_AVAILABILITY = 'assess';


    /**
     * Maximum number of things per learnosity payload
     * (50 questions, 50 features, 50 items, 50 activities)
     */
    public const LEARNOSITY_MAX_PAYLOAD_SIZE = 50;


    /**
     * Number of times to retry association queries
     */
    private const NUM_RETRY = 3;


    /**
     * Associate a newly-imported learnosity quiz with the legacy one
     */
    private const QUERY_ASSOCIATE_LEARNOSITY = '
        UPDATE assess
        SET learnosity_reference = :learnosityReference
        WHERE assess_id = :assessId
        LIMIT 1
    ';


    /**
     * A set of prepared statements we can recycle
     *
     * @var \PDOStatement
     */
    private \PDOStatement $associateStatement;


    /**
     * Constructor.
     *
     * @param Api                                $api
     * @param string                             $schoolCode
     * @param string                             $domain
     * @param IConnector                         $databaseInternal
     * @param AssessmentContext                  $context
     * @param User                               $user
     * @param AssessmentMapperProxy              $assessmentMapperProxy
     * @param \Schoolbox\Learning\Quiz\Mapper    $quizMapper
     * @param Mapper                             $workTypeMapper
     * @param MarkTypeMapper                     $markTypeMapper
     * @param \Schoolbox\Resource\Storage\Mapper $storageMapper
     */
    public function __construct(
        private Api $api,
        private string $schoolCode,
        private string $domain,
        private IConnector $databaseInternal,
        private AssessmentContext $context,
        private \Schoolbox\User $user,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private WorkType\Mapper $workTypeMapper,
        private MarkTypeMapper $markTypeMapper,
        private \Schoolbox\Resource\Storage\Mapper $storageMapper
    ) {
        $this->associateStatement = $this->databaseInternal->prepare(self::QUERY_ASSOCIATE_LEARNOSITY);
    }


    /**
     * Builds an IAssessable instance out of the xml, returns it and the parser itself
     * so you can continue making data with it elsewhere
     *
     * @param string $data
     * @return array
     * @throws Deleted
     * @throws MapperException
     * @throws ValidationException
     * @throws \DOMException
     * @throws \JsonException
     */
    public function import(string $data): array
    {
        /* We need to use this later
         */
        $xml = $this->prepareXml($data);

        /* Ensure that it's actually one of ours
         */
        $this->validate($xml);
        die();

        /* Only accept Quiz xmls; reject Surveys
         */
//        if (self::QUIZ_AVAILABILITY !== trim((string)$xml->xpath('availability')[0])) {
//            throw new \DomainException('xml file did not contain a legacy quiz');
//        }

        /* Set up a parser to traverse the xml
         */
        $parser = $this->produceParser($xml);

        /* Produce IAssessable and quiz subobject instances into an array for convenience
         * (these are already persisted in the db)
         */
        $instances = $this->buildAssessment($parser);

        /* Start manufacturing quiz payloads for learnosity
         * This is mostly stolen from the cli ImporterTool
         */

        /* Used to make representations of questions and answers
         */
        $strategyFactory = new Strategy\Factory($this->domain);

        /* Used to manufacture an array containing all the legacy quiz's contents
         */
        $quizBuilder = new Builder(
            $this->user,
            $strategyFactory,
            new InlineImageConverter($this->storageMapper, null)
        );

        /* Generates sets of tags for Activities' and Items' assignations
         */
        $tagFactory = new TagFactory($this->schoolCode);

        /* Find out if the build had any random errors
         */
        $errors = [];

        /* A context lookup builder thing for conveniently deriving tags
         *
         *  Replaced with usage of AssessmentContext since we have no
         *  idea where this assessment's context details are (it comes from
         *  an xml where there's no record of it in the db)
         */
        $contextLookup = new ContextLookup($tagFactory, $this->context);

        /* Generates payload array structs
         */
        $payloadFactory = new PayloadFactory($strategyFactory);

        /* Generates payload "blueprints" so you can inspect them before committing to
         * sending them to Learnosity
         */
        $blueprintBuilder = new BlueprintBuilder($this->schoolCode, $payloadFactory, $contextLookup, null);

        /* Traverse the xml, loading each question
         */
        $legacyQuiz = $quizBuilder->build($instances, $xml);
        $errors = array_merge($errors, $quizBuilder->getErrors());

        /* Figure out how to consolidate payloads
         */
        $errors = array_merge($errors, $this->importLarge(
            $legacyQuiz,
            $blueprintBuilder,
            $this->api
        ));

        /* Return some constructed objects
         */
        return [
            'assessment' => $instances['assessment'],
            /* The quiz subobject belonging to the assessment instance itself
             */
            'quiz' => $instances['quiz'],
            /* The conversion of quiz xml into the same format as the quiz from db
             */
            'legacyQuiz' => $legacyQuiz,
            /* Any errors we encountered along the way
             * We shouldn't have any, but learnosity introduces a new category of problems to supervise
             */
            'errors' => $errors
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
            $result = simplexml_load_string(html_entity_decode($xml, ENT_QUOTES, 'UTF-8'));
        }

        libxml_use_internal_errors($use_errors);

        return $result;
    }


    /**
     * People have been trying to import invalid xmls (random quiz xmls from other software)
     * but we don't (and have never) supported that ability. We can only import our own quiz xml.
     *
     * This validate() method inspects the first node, ensures that it's <quiz>, as this is a
     * signature of our own xmls. This node contains all the quiz info.
     *
     * We can also check for the four dates and other schoolbox-specific fields to help increase
     * detection of invalid xmls. This won't stop someone who is determined to force their xml
     * but it should help weed out The Casuals who are doing this accidentally.
     *
     * @param SimpleXMLElement $xml
     * @return bool
     * @throws DebugBarException
     */
    private function validate(SimpleXMLElement $xml): bool
    {
        $xml->xpath('availability');

        /* Required to use ->current(), as per php.net
         * https://www.php.net/manual/en/simplexmlelement.current.php
         */
        $xml->rewind();

        /* Check that the first node is <quiz>
         */

        /* Check that the <availability>assess</availability> node exists
         */

        /* Look for the existence of publish, open, due, close dates
         */

        /* Look for Mark Visibility
         */


        new \Alaress_Probe($xml->current());

//        if (self::QUIZ_AVAILABILITY !== trim((string)$xml->xpath('availability')[0])) {
//            throw new \DomainException('xml file did not contain a legacy quiz');
//        }

        return false;
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
     * Import a large quiz by spreading its contents over multiple payloads
     *
     * @param array            $legacyQuiz
     * @param BlueprintBuilder $blueprintBuilder
     * @param Api              $api
     * @return array
     * @throws \JsonException
     * @throws ValidationException
     */
    private function importLarge(
        array $legacyQuiz,
        BlueprintBuilder $blueprintBuilder,
        Api $api
    ): array {
        $errors = [];

        /* Make the overall Learnosity payloads which we then send later with
         * a little postprocessing to put things into the exactly correct format.
         * Learnosity is really particular about such things.
         */
        try {
            $blueprint = $blueprintBuilder->buildFlattenSections($legacyQuiz);
        } catch (\DomainException $e) {
            $errors[] = $e->getMessage();

            return $errors;
        }

        foreach ($blueprint['payloadSequence'] as $payloadSequence) {
            $endpoint = $payloadSequence['endpoint'];

            if (BlueprintBuilder::ENDPOINT_FEATURES === $endpoint) {
                $this->transmitUnconsolidated($api, 'features', $endpoint, $payloadSequence);
            } elseif (BlueprintBuilder::ENDPOINT_QUESTIONS === $endpoint) {
                $this->transmitUnconsolidated($api, 'questions', $endpoint, $payloadSequence);
            } elseif (BlueprintBuilder::ENDPOINT_ITEMS === $endpoint) {
                $this->transmitUnconsolidated($api, 'items', $endpoint, $payloadSequence);
            } elseif (BlueprintBuilder::ENDPOINT_ACTIVITIES === $endpoint) {
                $this->transmitUnconsolidated($api, 'activities', $endpoint, $payloadSequence);
            }
        }

        /* Associations are always done last, once per quiz,
         * and after their guts have been sent to Learnosity
         */
        $association = [
            'quizId' => $blueprint['quizId'],
            'assessmentId' => $blueprint['assessmentId'],
            'activityLearnosityReference' => $blueprint['activityLearnosityReference']
        ];

        /* Associate with a retry mechanism
         */
        try {
            $this->associate($association);
        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }


    /**
     * Sends data to Learnosity and returns updated metrics
     *
     * @param Api    $api
     * @param string $key
     * @param string $endpoint
     * @param array  $payloadSequence
     * @return void
     * @throws \JsonException
     * @throws \LearnositySdk\Exceptions\ValidationException
     */
    private function transmitUnconsolidated(
        Api $api,
        string $key,
        string $endpoint,
        array $payloadSequence,
    ): void {
        $payload = [
            $key => []
        ];

        foreach ($payloadSequence['payload'][$key] as $object) {
            $payload[$key][] = $object;

            /* A special case for Questions endpoint which requires this random
             * meta object outside of everything else -_-
             */
            if (isset($object['meta'])) {
                $payload['meta'] = $object['meta'];
            }

            /* Check if we're full; if so, send it and make a new payload
             * ready for the next iteration
             */
            $currentPayloadSize = \count($payload[$key]);

            if ($currentPayloadSize === self::LEARNOSITY_MAX_PAYLOAD_SIZE) {
                $api->dataSetRequestHub($endpoint, $payload);

                $payload = [
                    $key => []
                ];
            }
        }

        /* Send any remainders
         *
         * When perform mode is on, actually send it as batches of features, questions,
         * items, activities and just...hope/presumed that everything worked out
         *
         * Note that $payload might be empty because the quiz contained an exact multiple
         * of the maximum payload size; in this case, $payload will be an empty array and
         * there are no remainders in any of its keys.
         */
        $payloadSize = \count($payload[$key]);

        /* Skip zero-sized payloads; Learnosity hates that
         */
        if ($payloadSize) {
            $api->dataSetRequestHub($endpoint, $payload);
        }
    }


    /**
     * Associate legacy quiz with learnosity quiz, using a retry system
     * in case our db went away for some reason
     *
     * @param array $association
     * @return void
     */
    private function associate(array $association): void
    {
        $numFails = 0;

        while ($numFails < self::NUM_RETRY) {
            try {
                $this->associateStatement->execute([
                    'learnosityReference' => $association['activityLearnosityReference'],
                    'assessId' => $association['quizId']
                ]);

                return;
            } catch (\PDOException $e) {
                $numFails++;
            }
        }

        throw new \RuntimeException(sprintf(
            'Could not associate learnosity quiz %s with legacy quiz %s after %s tries',
            $association['activityLearnosityReference'],
            $association['quizId'],
            $numFails
        ));
    }
}
