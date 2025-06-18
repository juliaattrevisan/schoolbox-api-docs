<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Json;

use Alaress\Database\Connector\IConnector;
use LearnositySdk\Exceptions\ValidationException;
use Ramsey\Uuid\Uuid;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Learning\Assessment\WorkType\Mapper;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Export\Json\Payload;
use Schoolbox\Learning\Learnosity\Importer\Legacy\Xml\ContextLookup;
use Schoolbox\Learning\Learnosity\TagFactory;
use Schoolbox\Learning\Quiz\Model;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\User;

class Importer
{
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
        private \Schoolbox\Resource\Storage\Mapper $storageMapper,
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

        /* Generates sets of tags for Activities' and Items' assignations
         */
        $tagFactory = new TagFactory($this->schoolCode);

        /* A context lookup builder thing for conveniently deriving tags
         *
         *  Replaced with usage of AssessmentContext since we have no
         *  idea where this assessment's context details are (it comes from
         *  an xml where there's no record of it in the db)
         */
        $contextLookup = new ContextLookup($tagFactory, $this->context);

        /* Find some taggies
         */
        $tags = $contextLookup->find((int)$instances['assessment']->getId());

        /* Start manufacturing quiz payloads for learnosity in this order because learnosity build upwards
         * Features, Questions, Items, Activity
         */
        $learnosityReference = $this->assemble($learnosityContent, $tags);

        $this->associate([
            'activityLearnosityReference' => $learnosityReference,
            'quizId' => $instances['quiz']->getId()
        ]);

        /* TODO Add error handling next, as I try to snap this
         */
        return [
            'assessment' => $instances['assessment'],
            'quiz' => $instances['quiz'],
            'errors' => []
        ];
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
     * @return array
     * @throws ValidationException
     * @throws \JsonException
     */
    private function transmit(array $data, string $endpoint, string $key): array
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

            /* Sometimes this is here
             */
            if (isset($object['id'])) {
                $object['id'] = $object['reference'];
            }

            /* Stick it into the payload
             */
            $payload[$key][] = $object;

            /* Assign this random meta node to questions specifically
             */
            if ('questions' === $key) {
                $payload['meta'] = [
                    'user' => [
                        /* This author identifier should be a (schoolcode, user id) tuple for
                         * auditing reasons, to deconflict with other schools who used the same
                         * user id. It's mandatory as part of Question creation. We can assign
                         * it here in the blueprint, or in the actual api payload itself while
                         * performing. This is because user 123 in one school means a whole
                         * different dude in another school.
                         */
                        'id' => sprintf(
                            '%s__%s',
                            $this->schoolCode,
                            $this->user->getId()
                        )
                    ]
                ];
            }

            /* Maximum size reached. Send it and start another.
             */
            if (\count($payload[$key]) === self::LEARNOSITY_MAX_PAYLOAD_SIZE) {
                $this->api->dataSetRequestHub($endpoint, $payload);

                new \Alaress_Probe($payload);

                $payload = [
                    $key => []
                ];
            }
        }

        /* Send any remainders
         */
        if (\count($payload[$key])) {
            new \Alaress_Probe($payload);

            $this->api->dataSetRequestHub($endpoint, $payload);
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
    private function assemble(array $learnosityContent, array $tags): string
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
         *
         * Slap on this random meta node that for some reason exists here and nowhere else,
         * for learnosity reasons I suppose
         */
        $questionMapping = $this->transmit($learnosityContent['questions'], 'itembank/questions', 'questions');

        /* For each item
         * rewrite its old identifier and store the data struct in a temporary payload.
         * If the payload contains 50 items, send it and start a new one until all are uploaded.
         *
         * While you're doing that, remember all the temporary sb- references
         * and how they related to new ones you generated en route
         */
        foreach ($learnosityContent['items'] as &$item) {
            foreach ($item['features'] as &$feature) {
                $feature['reference'] = $featureMapping[$feature['reference']];
            }

            foreach ($item['questions'] as &$question) {
                $question['reference'] = $questionMapping[$question['reference']];
            }

            /* Update widgets too
             */
            foreach ($item['definition']['widgets'] as &$widget) {
                if (Payload::WIDGET_FEATURE === $widget['widget_type']) {
                    $widget['reference'] = $featureMapping[$widget['reference']];
                } else {
                    $widget['reference'] = $questionMapping[$widget['reference']];
                }
            }

            /* Tag it and bag it
             */
            $item['tags'] = $tags;
        }

        unset($feature, $question, $item, $widget);

        $itemMapping = $this->transmit($learnosityContent['items'], 'itembank/items', 'items');

        /* There should be only one activity, but that activity could have a lot of items
         * rewrite its old identifier and send 50 items at a time
         */
        foreach ($learnosityContent['activities'] as &$activity) {
            foreach ($activity['data']['items'] as &$item) {
                /* These two fields are the same, due to a bug in learnosity they need to co-exist
                 * since learnosity let you make content in an obsolete format without telling you
                 */
                $item['reference'] = $itemMapping[$item['reference']];

                $item['id'] = $item['reference'];
            }

            /* Rewrite identifiers
             */
            $activity['reference'] = (string)UUid::uuid4();

            /* Tag it and bag it
             */
            $activity['tags'] = $tags;
        }

        unset($item, $activity);

        /* There's only ever one activity at a time: no need to micromanage large payloads
         */
        $payload = [
            'activities' => array_values($learnosityContent['activities'])
        ];

        $this->api->dataSetRequestHub('itembank/activities', $payload);

        /* Maybe a big leap of faith, but ok
         */
        return $payload['activities'][0]['reference'];
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
