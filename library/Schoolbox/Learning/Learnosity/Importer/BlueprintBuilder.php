<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Learnosity\TagFactory;

/**
 * Build an array of payloads that we intend to send to Learnosity
 * to import our quiz contents.
 *
 * This approach lets you witness the payload BEFORE you send anything
 * so you can debug and build unit tests around it if you wanted.
 *
 * It also means you can look at the payloads before you whump
 * them into Learnosity where you must micromanage them; you have
 * to manually archive all your "testing" data in the third party
 * using cleanse.php or the ui itself. There is no mass-archive or
 * mass-soft-delete; you have to do it programmatically.
 */
class BlueprintBuilder
{
    /**
     * This isn't an actual Learnosity endpoint but it's used to indicate
     * when we need to associate a Learnosity quiz with our legacy ones.
     */
    public const ENDPOINT_ASSOCIATIONS = 'associations';


    /**
     * Learnosity endpoint index
     */
    public const ENDPOINT_FEATURES = '/itembank/features';
    public const ENDPOINT_QUESTIONS = '/itembank/questions';
    public const ENDPOINT_ITEMS = '/itembank/items';
    public const ENDPOINT_ACTIVITIES = '/itembank/activities';

    /**
     * Maximum number of Activities per payload
     * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities
     */
    private const LEARNOSITY_MAX_ACTIVITIES = 50;


    /**
     * Maximum number of Items per payload
     * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setItems
     */
    private const LEARNOSITY_MAX_ITEMS = 50;


    /**
     * Maximum number of Questions per payload
     * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setQuestions
     */
    private const LEARNOSITY_MAX_QUESTIONS = 50;


    /**
     * Maximum number of Features per payload
     * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setFeatures
     */
    private const LEARNOSITY_MAX_FEATURES = 50;


    /**
     * Combined strata of features, questions, items, activities, associations
     */
    private array $initialConsolidation;


    /**
     * TODO What is this supposed to be, then
     *
     * A generic author identifier attributed to each and every question
     * since we're aligning the strata of all questions from every quiz.
     *
     * Learnosity wants you to give a particular author to a set of questions,
     * not to an individual question, in the itembank/questions endpoint.
     * This means we lose the original authorship identifiers, although this
     * only appears in an audit trail that our users won't ever see afaict
     */
    private string $defaultAuthor;


    /**
     * Constructor
     *
     * @param string $schoolCode
     * @param PayloadFactory $payloadFactory
     * @param TagFactory $tagFactory
     * @param ContextLookup $contextLookup
     * @param LoggerInterface $logger
     */
    public function __construct(
        private string $schoolCode,
        private PayloadFactory $payloadFactory,
        private TagFactory $tagFactory,
        private ContextLookup $contextLookup,
        private LoggerInterface $logger
    ) {
        $this->defaultAuthor = sprintf('%s_v23_migration', $this->schoolCode);

        /* Contains a dynamic property, so it can't be a class const anymore
         */
        $this->initialConsolidation = [
            self::ENDPOINT_FEATURES => [
                'endpoint' => self::ENDPOINT_FEATURES,
                'payload' => [
                    'features' => []
                ],
                'count' => 0,
            ],
            self::ENDPOINT_QUESTIONS => [
                'endpoint' => self::ENDPOINT_QUESTIONS,
                'payload' => [
                    'meta' => [
                        'user' => [
                            /* FIXME replace with actual author somehow, or just assume a system user
                             *  I'm not really sure this is ever shown to an end user
                             */
                            'id' => $this->defaultAuthor
                        ]
                    ],
                    'questions' => []
                ],
                'count' => 0,
            ],
            self::ENDPOINT_ITEMS => [
                'endpoint' => self::ENDPOINT_ITEMS,
                'payload' => [
                    'items' => []
                ],
                'count' => 0,
            ],
            self::ENDPOINT_ACTIVITIES => [
                'endpoint' => self::ENDPOINT_ACTIVITIES,
                'payload' => [
                    'activities' => []
                ],
                'count' => 0,
            ],
            self::ENDPOINT_ASSOCIATIONS => [
                'endpoint' => self::ENDPOINT_ASSOCIATIONS,
                'payload' => [
                    'associations' => []
                ],
                'count' => 0,
            ]
        ];
    }


    /**
     * Build a payload "plan" for $legacyQuiz
     *
     * This preserves legacy Section content which is interpreted as a
     * Learnosity Feature, removing the spectre of Sections entirely
     *
     * @param array $legacyQuiz
     * @return array|array[]
     * @throws \DomainException
     * @throws \PDOException
     */
    public function buildFlattenSections(array $legacyQuiz): array
    {
        $payloadSequence = [];

        /* Find some taggies
         */
        $tags = $this->contextLookup->find((int)$legacyQuiz['assessment_id']);

        $this->logger->debug(sprintf(
            'Assigning %s tags to this quiz: [%s]',
            \count($tags),
            implode(', ', array_column($tags, 'name'))
        ));

        /* Set up a Learnosity Activity payload
         * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities
         */
        $activityStruct = $this->payloadFactory->produce(
            PayloadFactory::LEARNOSITY_PAYLOAD_ACTIVITY,
            $legacyQuiz,
            $tags
        );

        /* To optimise further, maybe, we can collect all the QuestionStructs
         * and pump them into one mega payload instead of lots of little ones to
         * save on the transmission time (each api request to Learnosity took around
         * ~2sec in a vagrant)
         */
        $batchFeaturePayload = [];
        $batchItemPayload = [];
        $batchQuestionPayload = [];

        foreach ($legacyQuiz['_sections'] as $section) {
            /* Optional Passage feature to emulate section descriptions
             */
            $feature = $this->payloadFactory->produce(
                PayloadFactory::LEARNOSITY_PAYLOAD_SECTION_FEATURE,
                $section,
                $tags
            );

            if (!empty($feature)) {
                $this->logger->debug(sprintf(
                    'Built blueprint for feature "%s" (section_id: %s) with learnosity_reference %s',
                    trim(\TextFormatter::cutSentence($section['title'], 64)),
                    $section['section_id'],
                    $feature['reference']
                ));

                /* Stack it
                 */
                $batchFeaturePayload[] = $feature;

                /* Item payload needs to know this reference
                 */
                $itemStruct = $this->payloadFactory->produce(
                    PayloadFactory::LEARNOSITY_PAYLOAD_ITEM,
                    $section,
                    $tags
                );

                $itemStruct['features'][] = $feature['reference'];

                $itemStruct['definition']['widgets'][] = [
                    'reference' => $feature['reference']
                ];

                /* Activity payload needs to know this reference
                 */
                $activityStruct['data']['items'][] = $itemStruct['reference'];

                /* Stack it
                 */
                $batchItemPayload[] = $itemStruct;
            }

            /* Accumulate all the questions we found in $section and
             * pile them up here
             */
            foreach ($section['_questions'] as $question) {
                /* FIXME temporary shim while constructing Strategies
                 */
                try {
                    $questionStruct = $this->payloadFactory->produce(
                        PayloadFactory::LEARNOSITY_PAYLOAD_QUESTION,
                        $question,
                        null
                    );
                } catch (\DomainException $e) {
                    $this->logger->warning($e->getMessage());
                    continue;
                }

                /* Put this question into the set
                 */
                $this->logger->debug(sprintf(
                    'Built blueprint for question "%s" (question_id: %s, type: %s) with learnosity_reference %s',
                    \TextFormatter::cutSentence($question['question'], 64),
                    $question['question_id'],
                    $question['type_id'],
                    $questionStruct['reference']
                ));

                $batchQuestionPayload[] = $questionStruct;

                /* Item payload needs to know this reference
                 */
                $itemStruct = $this->payloadFactory->produce(
                    PayloadFactory::LEARNOSITY_PAYLOAD_ITEM,
                    $section,
                    $tags
                );

                $itemStruct['definition']['widgets'][] = [
                    'reference' => $questionStruct['reference']
                ];

                $itemStruct['questions'][] = [
                    'reference' => $questionStruct['reference']
                ];

                /* In a flattened structure, name the item after the question
                 * for lack of any other value; remove any richtext bidness
                 * from the question text we offered (good luck to those who
                 * only had an image as their question, though.)
                 */
                $itemStruct['title'] = strip_tags($question['question']);

                /* Activity payload needs to know this reference
                 */
                $activityStruct['data']['items'][] = $itemStruct['reference'];

                /* Put this item into the set
                 */
                $this->logger->debug(sprintf(
                    'Built blueprint for item "%s" (section_id: %s) with learnosity_reference %s',
                    $section['title'],
                    $section['section_id'],
                    $itemStruct['reference']
                ));

                $batchItemPayload[] = $itemStruct;
            }
        }

        /* Stack it
         * Send all the features together
         */
        if (\count($batchFeaturePayload)) {
            $payloadSequence[] = [
                'endpoint' => '/itembank/features',
                'payload' => [
                    'features' => $batchFeaturePayload
                ],
                'count' => \count($batchFeaturePayload)
            ];
        }

        /* Stack it
         * Send all the questions together
         */
        $payloadSequence[] = [
            'endpoint' => '/itembank/questions',
            'payload' => [
                'meta' => [
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
                            $legacyQuiz['user_id']
                        )
                    ]
                ],
                'questions' => $batchQuestionPayload
            ],
            'count' => \count($batchQuestionPayload)
        ];

        /* Stack it
         * Send all the items together
         */
        $payloadSequence[] = [
            'endpoint' => '/itembank/items',
            'payload' => [
                'items' => $batchItemPayload
            ],
            'count' => \count($batchItemPayload)
        ];

        /* Stack it
         */
        $payloadSequence[] = [
            'endpoint' => '/itembank/activities',
            'payload' => [
                'activities' => [$activityStruct]
            ],
            /* Always one quiz at a time for now
             * (Might be more if you expand cli arg to allow multiple ids)
             */
            'count' => 1
        ];

        $this->logger->debug(sprintf(
            'Built blueprint for quiz "%s" (assessmentId: %s, assess_id: %s) with assess.learnosity_reference %s',
            $legacyQuiz['title'],
            $legacyQuiz['assessment_id'],
            $legacyQuiz['assess_id'],
            $activityStruct['reference']
        ));

        return [
            'activityLearnosityReference' => $activityStruct['reference'],
            'quizId' => $legacyQuiz['assess_id'],
            'assessmentId' => $legacyQuiz['assessment_id'],
            'payloadSequence' => $payloadSequence,
        ];
    }


    /**
     * Build a payload "plan" for $legacyQuiz
     *
     * This maintains our legacy idea of "sections" which are interpreted
     * as analogous to Learnosity Items
     *
     * @deprecated
     *
     * @param array $legacyQuiz
     * @return array|array[]
     * @throws \DomainException
     */
    public function buildPreserveSections(array $legacyQuiz): array
    {
        $payloadSequence = [];

        /* Set up a Learnosity Activity payload
         * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities
         */
        $activityStruct = $this->payloadFactory->produce(
            PayloadFactory::LEARNOSITY_PAYLOAD_ACTIVITY,
            $legacyQuiz,
            /* TODO Need the MapperProxy to decide its tag set, I suppose
             */
            $this->tagFactory->produce(null, null, null)
        );

        /* To optimise further, maybe, we can collect all the QuestionStructs
         * and pump them into one mega payload instead of lots of little ones to
         * save on the transmission time (each api request to Learnosity took around
         * ~2sec in a vagrant)
         */
        $batchFeaturePayload = [];
        $batchItemPayload = [];
        $batchQuestionPayload = [];

        /* Set up a Learnosity Item which will be vaguely equivalent to a Section
         * purely for the purposes of this importer
         */
        foreach ($legacyQuiz['_sections'] as $section) {
            $itemStruct = $this->payloadFactory->produce(
                PayloadFactory::LEARNOSITY_PAYLOAD_ITEM,
                $section,
                /* TODO Need the MapperProxy to decide its tag set, I suppose
                 */
                $this->tagFactory->produce(null, null, null)
            );

            /* Optional Passage feature to emulate section descriptions
             */
            $feature = $this->payloadFactory->produce(
                PayloadFactory::LEARNOSITY_PAYLOAD_SECTION_FEATURE,
                $section,
                /* TODO Need the MapperProxy to decide its tag set, I suppose
                 */
                $this->tagFactory->produce(null, null, null)
            );

            if (!empty($feature)) {
                $this->logger->debug(sprintf(
                    'Built blueprint for feature "%s" (section_id: %s) with learnosity_reference %s',
                    \TextFormatter::cutSentence($section['title'], 64),
                    $section['section_id'],
                    $feature['reference']
                ));

                /* Stack it
                 */
                $batchFeaturePayload[] = $feature;

                /* Item payload needs to know this reference
                 */
                $itemStruct['features'][] = $feature['reference'];

                $itemStruct['definition']['widgets'][] = [
                    'reference' => $feature['reference']
                ];
            }

            /* Accumulate all the questions we found in $section and
             * pile them up here
             */
            foreach ($section['_questions'] as $question) {
                /* FIXME temporary shim while constructing Strategies
                 */
                try {
                    $questionStruct = $this->payloadFactory->produce(
                        PayloadFactory::LEARNOSITY_PAYLOAD_QUESTION,
                        $question,
                        null
                    );
                } catch (\DomainException $e) {
                    $this->logger->warning($e->getMessage());
                    continue;
                }

                /* Put this question into the set
                 */
                $this->logger->debug(sprintf(
                    'Built blueprint for question "%s" (question_id: %s, type: %s) with learnosity_reference %s',
                    \TextFormatter::cutSentence($question['question'], 64),
                    $question['question_id'],
                    $question['type_id'],
                    $questionStruct['reference']
                ));

                $batchQuestionPayload[] = $questionStruct;

                /* Item payload needs to know this reference
                 */
                $itemStruct['definition']['widgets'][] = [
                    'reference' => $questionStruct['reference']
                ];

                $itemStruct['questions'][] = [
                    'reference' => $questionStruct['reference']
                ];
            }

            /* Activity payload needs to know this reference
             */
            $activityStruct['data']['items'][] = $itemStruct['reference'];

            /* Put this item into the set
             */
            $this->logger->debug(sprintf(
                'Built blueprint for item "%s" (section_id: %s) with learnosity_reference %s',
                $section['title'],
                $section['section_id'],
                $itemStruct['reference']
            ));

            $batchItemPayload[] = $itemStruct;
        }

        /* Stack it
         * Send all the features together
         */
        if (\count($batchFeaturePayload)) {
            $payloadSequence[] = [
                'endpoint' => '/itembank/features',
                'payload' => [
                    'features' => $batchFeaturePayload
                ],
                'count' => \count($batchFeaturePayload)
            ];
        }

        /* Stack it
         * Send all the questions together
         */
        $payloadSequence[] = [
            'endpoint' => '/itembank/questions',
            'payload' => [
                'meta' => [
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
                            $legacyQuiz['user_id']
                        )
                    ]
                ],
                'questions' => $batchQuestionPayload
            ],
            'count' => \count($batchQuestionPayload)
        ];

        /* Stack it
         */
        $payloadSequence[] = [
            'endpoint' => '/itembank/items',
            'payload' => [
                'items' => $batchItemPayload
            ],
            'count' => \count($batchItemPayload)
        ];

        /* Stack it
         */
        $payloadSequence[] = [
            'endpoint' => '/itembank/activities',
            'payload' => [
                'activities' => [$activityStruct]
            ],
            /* Always one quiz at a time for now
             * (Might be more if you expand cli arg to allow multiple ids)
             */
            'count' => 1
        ];

        $this->logger->debug(sprintf(
            'Built blueprint for quiz "%s" (assessmentId: %s, assess_id: %s) with assess.learnosity_reference %s',
            $legacyQuiz['title'],
            $legacyQuiz['assessment_id'],
            $legacyQuiz['assess_id'],
            $activityStruct['reference']
        ));

        return [
            'activityLearnosityReference' => $activityStruct['reference'],
            'payloadSequence' => $payloadSequence,
        ];
    }


    /**
     * Create a new consolidation of blueprints.
     *
     * Alias/shortcut, since the initial consolidation payload contains a derived
     * value (author identifier) which cannot be declared in a class const
     *
     * @param array $blueprint
     * @return array
     */

    public function consolidateNew(array $blueprint): array
    {
        return $this->consolidate($this->initialConsolidation, $blueprint);
    }


    /**
     * Combine the various strata (features, questions, items, activities) of each
     * $blueprint into one existing mega-blueprint grouped by their corresponding kind.
     *
     * @param array $previousConsolidation
     * @param array $blueprint
     * @return array
     */
    public function consolidate(array $previousConsolidation, array $blueprint): array
    {
        /* Add to this thing
         */
        $consolidated = $previousConsolidation;

        /* Combined strata of features, questions, items, activities, associations
         */
        foreach ($blueprint['payloadSequence'] as $payloadSequence) {
            $endpoint = $payloadSequence['endpoint'];

            /* Update payloads and counts
             */
            if (self::ENDPOINT_FEATURES === $endpoint) {
                $consolidated[$endpoint]['payload']['features'] = array_merge($consolidated[$endpoint]['payload']['features'], $payloadSequence['payload']['features']);
                $consolidated[$endpoint]['count'] += \count($payloadSequence['payload']['features']);
            } elseif (self::ENDPOINT_QUESTIONS === $endpoint) {
                $consolidated[$endpoint]['payload']['questions'] = array_merge($consolidated[$endpoint]['payload']['questions'], $payloadSequence['payload']['questions']);
                $consolidated[$endpoint]['count'] += \count($payloadSequence['payload']['questions']);
            } elseif (self::ENDPOINT_ITEMS === $endpoint) {
                $consolidated[$endpoint]['payload']['items'] = array_merge($consolidated[$endpoint]['payload']['items'], $payloadSequence['payload']['items']);
                $consolidated[$endpoint]['count'] += \count($payloadSequence['payload']['items']);
            } elseif (self::ENDPOINT_ACTIVITIES === $endpoint) {
                $consolidated[$endpoint]['payload']['activities'] = array_merge($consolidated[$endpoint]['payload']['activities'], $payloadSequence['payload']['activities']);
                $consolidated[$endpoint]['count'] += \count($payloadSequence['payload']['activities']);
            }
        }

        /* We need to do this in schoolbox land after the fact
         */
        $consolidated[self::ENDPOINT_ASSOCIATIONS]['payload']['associations'][] = [
            'quizId' => $blueprint['quizId'],
            'assessmentId' => $blueprint['assessmentId'],
            'activityLearnosityReference' => $blueprint['activityLearnosityReference']
        ];

        $consolidated[self::ENDPOINT_ASSOCIATIONS]['count']++;

        return $consolidated;
    }


    /**
     * Decide if $blueprint can fit onto the already-consolidated stack $consolidated
     *
     * @param array $consolidated
     * @param array $blueprint
     * @return bool
     */
    public function canConsolidate(array $consolidated, array $blueprint): bool
    {
        /* Inspect $legacyQuiz to see what is inside, aiming to cram as many
         * Features, Questions and Items per payload, as Learnosity allows.
         *
         * This is all about minimising the number of Learnosity API calls
         * dynamically based on the content we're trying to cram in to each
         * payload; see their API docs for limits (also indexed as consts at
         * the top of this file)
         */
        foreach ($blueprint['payloadSequence'] as $payloadSequence) {
            if (self::ENDPOINT_FEATURES === $payloadSequence['endpoint']) {
                $sum = ($consolidated[self::ENDPOINT_FEATURES]['count'] ?? 0) + $payloadSequence['count'];

                if ((int)$sum >= self::LEARNOSITY_MAX_FEATURES) {
                    return false;
                }
            }

            if (self::ENDPOINT_QUESTIONS === $payloadSequence['endpoint']) {
                $sum = ($consolidated[self::ENDPOINT_QUESTIONS]['count'] ?? 0) + $payloadSequence['count'];

                if ((int)$sum >= self::LEARNOSITY_MAX_QUESTIONS) {
                    return false;
                }
            }

            if (self::ENDPOINT_ITEMS === $payloadSequence['endpoint']) {
                $sum = ($consolidated[self::ENDPOINT_ITEMS]['count'] ?? 0) + $payloadSequence['count'];

                if ((int)$sum >= self::LEARNOSITY_MAX_ITEMS) {
                    return false;
                }
            }

            if (self::ENDPOINT_ACTIVITIES === $payloadSequence['endpoint']) {
                $sum = ($consolidated[self::ENDPOINT_ACTIVITIES]['count'] ?? 0) + $payloadSequence['count'];

                if ((int)$sum >= self::LEARNOSITY_MAX_ACTIVITIES) {
                    return false;
                }
            }
        }

        /* I guess we can keep consolidating
         */
        return true;
    }
}
