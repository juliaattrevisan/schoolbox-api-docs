<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Json;

use Alaress\Database\Connector\IConnector;
use Assess\Import\Parser\IParser;
use LearnositySdk\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Importer\BlueprintBuilder;
use Schoolbox\Learning\Learnosity\Importer\InlineImageConverter;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Builder;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\ContextLookup;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\Strategy;
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
     * @param Api $api
     * @param string $schoolCode
     * @param string $domain
     * @param IConnector $databaseInternal
     * @param AssessmentContext $context
     * @param User $user
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     * @param Mapper $workTypeMapper
     * @param MarkTypeMapper $markTypeMapper
     * @param \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper $gradeGroupMapper
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
        private \Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper $gradeGroupMapper,
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
        /* Convert the raw data into a json format...hopefully
         */
        try {
            $json = json_decode($data, true, 512, JSON_THROW_ON_ERROR);

            /* A Shortcut To Content
             */
            $learnosityContent = $json['content'] ?? [];
        } catch (\JsonException $e) {
            throw new \RuntimeException('Could not decode this json data; could be corrupted or otherwise malformed.');
        }

        /* Produce IAssessable and quiz subobject instances into an array for convenience
         * (these are already persisted in the db)
         */
        $instances = $this->buildAssessment($json['settings']);

        /* Process storage instances from assets included in the json
         */
        $this->processAssets($json['assets']);

        /* Start manufacturing quiz payloads for learnosity in this order because learnosity build upwards
         * Features, Questions, Items, Activity
         */
        $learnosityReference = $this->assemble($learnosityContent);


        //        /* Rewrite identifiers and remember them
//         */
//        foreach ($learnosityContent['activity']['data']['items'] as &$activityItem) {
//            $oldRef = $activityItem['reference'];
//            $newRef = (string)UUid::uuid4();
//
//            $itemMapping[$oldRef] = $newRef;
//
//            $activityItem['reference'] = $newRef;
//            $activityItem['id'] = $newRef;
//        }
//
//        /* Rewrite identifiers and remember them
//         */
//        $updatedItems = [];
//
//        foreach ($learnosityContent['items'] as &$item) {
//            $oldRef = $item['reference'];
//            $newRef = $itemMapping[$oldRef];
//
//            $item['reference'] = $newRef;
//
//            $updatedItems[$newRef] = $item;
//        }
//
//        $learnosityContent['items'] = $updatedItems;
//
//        new \Alaress_Probe($learnosityContent, $itemMapping);

//
//        foreach ($learnosityContent['items'] as $itemRef => $item) {
//            foreach ($item['features'] as $featureRef) {
//                $feature = $learnosityContent['features'][$featureRef['reference']];
//
//                /* Rewrite identifiers and remember them
//                 */
//                $feature['reference'] = (string)UUid::uuid4();
//                $itemStruct[$itemRef][] = $feature;
//            }
//
//            foreach ($item['questions'] as $questionRef) {
//                $question = $learnosityContent['questions'][$questionRef['reference']];
//
//                /* Rewrite identifiers and remember them
//                 */
//                $question['reference'] = (string)UUid::uuid4();
//                $itemStruct[$itemRef][] = $question;
//            }
//        }

//        new \Alaress_Probe($learnosityContent, $itemStruct);

//        foreach ($json['content']['features'] as $feature) {
//            $newFeature = $feature;
//
//            /* Rewrite identifier
//             */
//            $newFeature['reference'] = (string)UUid::uuid4()
//        }

//        /*
//
//        /* Used to make representations of questions and answers
//         */
//        $strategyFactory = new Strategy\Factory($this->domain);
//
//        /* Used to manufacture an array containing all the legacy quiz's contents
//         */
//        $quizBuilder = new Builder(
//            $this->user,
//            $strategyFactory,
//            new InlineImageConverter($this->storageMapper, null)
//        );
//
//        /* Generates sets of tags for Activities' and Items' assignations
//         */
//        $tagFactory = new TagFactory($this->schoolCode);
//
//        /* Find out if the build had any random errors
//         */
//        $errors = [];
//
//        /* A context lookup builder thing for conveniently deriving tags
//         *
//         *  Replaced with usage of AssessmentContext since we have no
//         *  idea where this assessment's context details are (it comes from
//         *  an xml where there's no record of it in the db)
//         */
//        $contextLookup = new ContextLookup($tagFactory, $this->context);
//
//        /* Generates payload array structs
//         */
//        $payloadFactory = new PayloadFactory($strategyFactory);
//
//        /* Generates payload "blueprints" so you can inspect them before committing to
//         * sending them to Learnosity
//         */
//        $blueprintBuilder = new BlueprintBuilder($this->schoolCode, $payloadFactory, $contextLookup, null);
//
//        /* Traverse the xml, loading each question
//         */
//        $legacyQuiz = $quizBuilder->build($instances, $xml);
//        $errors = array_merge($errors, $quizBuilder->getErrors());
//
//        /* Figure out how to consolidate payloads
//         */
//        $errors = array_merge($errors, $this->importLarge(
//            $legacyQuiz,
//            $blueprintBuilder,
//            $this->api
//        ));
//
//
//        /* Return some constructed objects
//         */
//        return [
//            'assessment' => $instances['assessment'],
//            /* The quiz subobject belonging to the assessment instance itself
//             */
//            'quiz' => $instances['quiz'],
//            /* The conversion of quiz xml into the same format as the quiz from db
//             */
//            'legacyQuiz' => $legacyQuiz,
//            /* Any errors we encountered along the way
//             * We shouldn't have any, but learnosity introduces a new category of problems to supervise
//             */
//            'errors' => $errors
//        ];

        return [];
    }


    /**
     * Persist IAssessable and quiz subobject instances
     *
     * @param array $settings
     * @return array
     * @throws Deleted
     * @throws MapperException
     */
    private function buildAssessment(array $settings): array
    {
        $inUnitSpec = $this->context->getBaseContext() instanceof \Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;

        /* Find a matching work type if one was specified
         */
        if ($settings['workType']) {
            try {
                $workType = $this->workTypeMapper->findOneByColumn('name', $settings['workType']);
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

        /* Find a matching Mark Type if one was specified
         * Note that quiz lets you have letter grades now, too: you'll also have to check grading schemes
         */
        if ($settings['markType']) {
            try {
                $markType = $this->markTypeMapper->findOneByColumn('slug', $settings['markType']);
            } catch (MapperException $e) {
                /* The specified mark type doesn't exist; find the first meaningful one
                 * Use percentage, as it's requires the least configuration
                 *
                 * This really shouldn't happen because users can't configure their own mark types,
                 * but anyway, in case someone malforms it, or we rename something.
                 */
                $markType = $this->markTypeMapper->findByColumn('slug', MarkType::SLUG_PERCENT)->first();
            }
        } else {
            /* Mark Type wasn't provided, but it's mandatory
             * Use percentage, as it's requires the least configuration
             */
            $markType = $this->markTypeMapper->findByColumn('slug', MarkType::SLUG_PERCENT)->first();
        }

        /* Check for grade groups too
         */
        if ($settings['gradeGroup']) {
            try {
                $gradeGroup = $this->gradeGroupMapper->findOneByColumn('name', $settings['gradeGroup']);
            } catch (MapperException $e) {
                /* The specified grade group doesn't exist; find the first meaningful one
                 */
                $gradeGroup = $this->gradeGroupMapper->findAll()->sortBy('sequence', 'ASC')->first();
            }
        } else {
            /* The specified grade group doesn't exist; find the first meaningful one
             */
            $gradeGroup = $this->gradeGroupMapper->findAll()->sortBy('sequence', 'ASC')->first();
        }

        // Create the IAssessable
        $assessment = (new \Schoolbox\Learning\Assessment\Quiz\Model())
            ->setCreator($this->user)
            /* TODO: Apply InlineImageConverter here
             */
            ->setCompletionMessage($settings['completionMessage'])
            ->setTitle($settings['title'])
            ->setWorkType($workType)
            /* TODO: Apply InlineImageConverter here
             */
            ->setDescription($settings['description'])
            ->setPublishAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+1 hour')))
            ->setOpenAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+1 hour')))
            ->setDueAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
            ->setCloseAt($inUnitSpec ? null : new \Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
            ->setWeight($settings['weight'])
            ->setDefaultOpenSpan($settings['defaultOpenSpan'])
            ->setMaxAttempts($settings['maxAttempts'])
            ->setMarkVisibility($settings['markVisibility'])
            /* Learnosity-era unlimited durations are represented by zero, not -1
             */
            ->setDuration(max($settings['duration'], Model::DURATION_UNLIMITED))
            /* Learnosity doesn't support randomised items
             */
            ->setShuffleItems(false)
            ->setPromptsMode($settings['promptsMode'])
            ->setMarkType($markType)
            ->setGradeGroup($gradeGroup);

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


    /**
     * Import base64 encoded media into storage area
     *
     * @param array $assets
     * @return void
     */
    private function processAssets(array $assets): void
    {
        foreach ($assets as $hash => $asset) {
            $imageData = base64_decode($asset['contents']);
            $newFilename = $asset['basename'];

            /* Shank it into storage
             */
            $this->storageMapper->acceptStream($newFilename, $imageData);

            /* FIXME Maybe do a double-verify comparing $hash to the output of acceptStream()
             * to ensure they match. Not sure if that's necessary though.
             */
        }
    }


    /**
     * Transmits the $data[$key] data into learnosity via its Data API,
     * returning a mapping of old refs to new refs.
     *
     * @param array $data
     * @param string $endpoint
     * @param string $key
     * @param bool $perform
     * @return array
     * @throws ValidationException
     * @throws \JsonException
     */
    private function transmit(array $data, string $endpoint, string $key, bool $perform = false): array
    {
        $payload = [
            $key => []
        ];

        /* A remembering of old refs and their corresponding new learnosity-friendly refs
         */
        $mapping = [];

        foreach ($data as $object) {
            $oldRef = $object['reference'];
            $newRef = (string)UUid::uuid4();

            $mapping[$oldRef] = $newRef;
            $object['reference'] = $newRef;

            /* Stick it into the payload
             */
            $payload[$key][] = $object;

            /* Maximum size reached. Send it and start another.
             */
            if (\count($payload[$key]) === self::LEARNOSITY_MAX_PAYLOAD_SIZE) {
                if ($perform) {
                    $this->api->dataSetRequestHub($endpoint, $payload);
                }

                $payload = [
                    $key => []
                ];
            }
        }

        /* Send any remainders
         */
        if (\count($payload[$key])) {
            if ($perform) {
                $this->api->dataSetRequestHub($endpoint, $payload);
            }
        }

        return $mapping;
    }


    /**
     * Puts all your json content into learnosity in its hierarchical way.
     * Returns the learnosity reference for the quiz/activity that we just made.
     *
     * The order of items is based on their ordering in the activity node,
     * since learnosity couldn't figure out how to provide an explicit sequence
     *
     * Also, you can only send 50 at a time per payload, which is annoying
     *
     * @param array $learnosityContent
     * @return string
     * @throws ValidationException
     * @throws \JsonException
     */
    private function assemble(array $learnosityContent): string
    {
        /* For each feature,
         * rewrite its old identifier and store the data struct in a temporary payload.
         * If the payload contains 50 items, send it and start a new one until all are uploaded.
         *
         * While you're doing that, remember all the temporary sb- references
         * and how they related to new ones you generated en route
         */
        $featureMapping = $this->transmit($learnosityContent['features'], 'itembank/features', 'features');

        /* For each question,
         * rewrite its old identifier and store the data struct in a temporary payload.
         * If the payload contains 50 items, send it and start a new one until all are uploaded.
         *
         * While you're doing that, remember all the temporary sb- references
         * and how they related to new ones you generated en route
         */
        $questionMapping = $this->transmit($learnosityContent['questions'], 'itembank/questions', 'questions');

        /* For each item
         * rewrite its old identifier and store the data struct in a temporary payload.
         * If the payload contains 50 items, send it and start a new one until all are uploaded.
         *
         * While you're doing that, remember all the temporary sb- references
         * and how they related to new ones you generated en route
         */
        foreach ($learnosityContent['items'] as $item) {
            foreach ($item['features'] as &$feature) {
                $feature['reference'] = $featureMapping[$feature['reference']];
            }

            foreach ($item['questions'] as &$question) {
                $question['reference'] = $questionMapping[$question['reference']];
            }
        }

        unset ($feature, $question);

        $itemMapping = $this->transmit($learnosityContent['items'], 'itembank/items', 'items');

        /* There should be only one activity, but that activity could have a lot of items
         * rewrite its old identifier and send 50 items at a time
         */
        foreach ($learnosityContent['activities'] as $activity) {
            foreach ($activity['data']['items'] as &$item) {
                /* These two fields are the same, due to a bug in learnosity they need to co-exist
                 * since learnosity let you make content in an obsolete format without telling you
                 */
                $item['reference'] = $itemMapping[$item['reference']];
                $item['id'] = $item['reference'];
            }
        }

        unset ($item);

        $activityMapping = $this->transmit($learnosityContent['activities'], 'itembank/activities', 'activity');

        /* Diagnostic
         */
        // new \Alaress_Probe($learnosityContent, $featureMapping, $questionMapping, $itemMapping, $activityMapping);

        /* Maybe a big leap of faith, but ok
         */
        return array_values($activityMapping)[0];
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
