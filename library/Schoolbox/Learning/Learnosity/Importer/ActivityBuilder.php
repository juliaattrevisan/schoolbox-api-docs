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
class ActivityBuilder
{
    /**
     * Constructor
     *
     * @param string $schoolCode
     * @param PayloadFactory $payloadFactory
     * @param TagFactory $tagFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private string $schoolCode,
        private PayloadFactory $payloadFactory,
        private TagFactory $tagFactory,
        private LoggerInterface $logger
    ) {
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
     */
    public function buildFlattened(array $legacyQuiz): array
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

        foreach ($legacyQuiz['_sections'] as $section) {
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
                $itemStruct = $this->payloadFactory->produce(
                    PayloadFactory::LEARNOSITY_PAYLOAD_ITEM,
                    $section,
                    /* TODO Need the MapperProxy to decide its tag set, I suppose
                     */
                    $this->tagFactory->produce(null, null, null)
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
                    /* TODO Need the MapperProxy to decide its tag set, I suppose
                     */
                    $this->tagFactory->produce(null, null, null)
                );

                $itemStruct['definition']['widgets'][] = [
                    'reference' => $questionStruct['reference']
                ];

                $itemStruct['questions'][] = [
                    'reference' => $questionStruct['reference']
                ];

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
            'payloadSequence' => $payloadSequence,
        ];
    }


    /**
     * Build a payload "plan" for $legacyQuiz
     *
     * This maintains our legacy idea of "sections" which are interpreted
     * as analogous to Learnosity Items
     *
     * @param array $legacyQuiz
     * @return array|array[]
     * @throws \DomainException
     */
    public function buildClassically(array $legacyQuiz): array
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
}
