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
     * @param TagFactory $tagFactory
     * @param LoggerInterface $logger
     */
    public function __construct(private TagFactory $tagFactory, private LoggerInterface $logger)
    {
    }


    /**
     * Build a payload "plan" for $legacyQuiz
     *
     * @param array $legacyQuiz
     * @return array|array[]
     * @throws \DomainException
     */
    public function build(array $legacyQuiz): array
    {
        $payloadSequence = [];

        /* Produces Learnosity payloads
         *
         * TODO this author identifier should be a (schoolcode, user id) tuple for
         *  auditing reasons, to deconflict with other schools who used the same
         *  user id. It's mandatory as part of Question creation. We can assign
         *  it here in the blueprint, or in the actual api payload itself while
         *  performing
         */
        $payloadFactory = new PayloadFactory((string)$legacyQuiz['user_id'], new Strategy\Factory());

        /* Set up a Learnosity Activity payload
         * https://reference.learnosity.com/data-api/endpoints/itembank_endpoints#setActivities
         */
        $activityStruct = $payloadFactory->produce(
            PayloadFactory::LEARNOSITY_PAYLOAD_ACTIVITY,
            $legacyQuiz,
            /* TODO Need the MapperProxy to decide its tag set, I suppose
             */
            $this->tagFactory->produce(null, null, null)
        );

        /* Set up a Learnosity Item which will be vaguely equivalent to a Section
         * purely for the purposes of this importer
         */
        foreach ($legacyQuiz['_sections'] as $section) {
            $itemStruct = $payloadFactory->produce(
                PayloadFactory::LEARNOSITY_PAYLOAD_ITEM,
                $section,
                /* TODO Need the MapperProxy to decide its tag set, I suppose
                 */
                $this->tagFactory->produce(null, null, null)
            );

            /* Optional Passage feature to emulate section descriptions
             */
            $feature = $payloadFactory->produce(
                PayloadFactory::LEARNOSITY_PAYLOAD_SECTION_FEATURE,
                $section,
                /* TODO Need the MapperProxy to decide its tag set, I suppose
                 */
                $this->tagFactory->produce(null, null, null)
            );

            if (!empty($feature)) {
                $payloadSequence[] = [
                    'endpoint' => '/itembank/features',
                    'payload' => [
                        'features' => [$feature]
                    ]
                ];

                $this->logger->debug(sprintf(
                    'Built blueprint for feature "%s" (section_id: %s) with learnosity_reference %s',
                    \TextFormatter::cutSentence($section['title'], 64),
                    $section['section_id'],
                    $feature['reference']
                ));

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
                /* TODO temporary shim while constructing Strategies
                 */
                try {
                    $questionStruct = $payloadFactory->produce(
                        PayloadFactory::LEARNOSITY_PAYLOAD_QUESTION,
                        $question,
                        null
                    );
                }
                catch (\DomainException $e) {
                    $this->logger->warning($e->getMessage());
                    continue;
                }


                /* Stack it
                 */
                $payloadSequence[] = [
                    'endpoint' => '/itembank/questions',
                    'payload' => [
                        'meta' => [
                            /* TODO this should have a schoolcode prefix
                             */
                            'user' => [
                                'id' => (string)$legacyQuiz['user_id']
                            ]
                        ],
                        'questions' => [$questionStruct]
                    ]
                ];

                $this->logger->debug(sprintf(
                    'Built blueprint for question "%s" (question_id: %s, type: %s) with learnosity_reference %s',
                    \TextFormatter::cutSentence($question['question'], 64),
                    $question['question_id'],
                    $question['type_id'],
                    $questionStruct['reference']
                ));

                /* Item payload needs to know this reference
                 */
                $itemStruct['definition']['widgets'][] = [
                    'reference' => $questionStruct['reference']
                ];

                $itemStruct['questions'][] = [
                    'reference' => $questionStruct['reference']
                ];
            }

            /* Stack it
             */
            $payloadSequence[] = [
                'endpoint' => '/itembank/items',
                'payload' => [
                    'items' => [$itemStruct]
                ]
            ];

            $this->logger->debug(sprintf(
                'Built blueprint for item "%s" (section_id: %s) with learnosity_reference %s',
                $section['title'],
                $section['section_id'],
                $itemStruct['reference']
            ));

            /* Activity payload needs to know this reference
             */
            $activityStruct['data']['items'][] = $itemStruct['reference'];

            /* Stack it
             */
            $payloadSequence[] = [
                'endpoint' => '/itembank/activities',
                'payload' => [
                    'activities' => [$activityStruct]
                ]
            ];

            $this->logger->debug(sprintf(
                'Built blueprint for quiz "%s" (assessmentId: %s, assess_id: %s) with assess.learnosity_reference %s',
                $legacyQuiz['title'],
                $legacyQuiz['assessment_id'],
                $legacyQuiz['assess_id'],
                $activityStruct['reference']
            ));
        }

        return $payloadSequence;
    }
}
