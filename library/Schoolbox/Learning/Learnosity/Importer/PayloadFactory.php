<?php
/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer;

use Ramsey\Uuid\Uuid;
use Schoolbox\Learning\Learnosity\Helper;

/**
 * Spawns a Learnosity-friendly payload structure for Activities,
 * Items, etc. based on the data provided.
 */
class PayloadFactory
{
    public const LEARNOSITY_PAYLOAD_ACTIVITY = 'activity';
    public const LEARNOSITY_PAYLOAD_ITEM = 'item';


    /**
     * Spawns a Learnosity-friendly payload structure for Activities,
     * Items, etc. based on the data provided.
     *
     * @param string $type
     * @param array $data
     * @param array $tags
     * @return array
     */
    public function produce(string $type, array $data, array $tags): array
    {
        return match ($type) {
            self::LEARNOSITY_PAYLOAD_ACTIVITY => $this->setupActivityPayload($data, $tags),
            self::LEARNOSITY_PAYLOAD_ITEM => $this->setupItemPayload($data, $tags),
            default => [],
        };
    }


    /**
     * Set up a payload for a single activity
     *
     * @param array $legacyQuiz
     * @param array $tags
     * @return array
     */
    private function setupActivityPayload(array $legacyQuiz, array $tags): array
    {
        $activity = [
            'data' => [
                'config' => [
                    /* TODO dedupe this with the other implementation, I suppose
                     *  Not sure where it would go though
                     */
                    'regions' => [
                        'top-left' => [
                            ['type' => 'title_element'],
                        ],
                        'top-right' => [
                            ['type' => 'itemcount_element'],
                            ['type' => 'pause_button'],
                            ['type' => 'reading_timer_element'],
                            ['type' => 'timer_element'],
                        ],
                        'items' => [
                            ['type' => 'progress_element'],
                            ['type' => 'slider_element']
                        ],
                        'right' => [
                            ['type' => 'save_button'],
                            ['type' => 'reviewscreen_button'],
                            ['type' => 'resource_button'],
                            ['type' => 'accessibility_button'],
                            ['type' => 'flagitem_button'],
                            ['type' => 'masking_button'],
                            ['type' => 'verticaltoc_element'],
                        ],
                        'bottom-left' => [
                            [
                                'type' => 'previous_button',
                                'show_label_option' => true
                            ],
                        ],
                        'bottom-right' => [
                            ['type' => 'next_button'],
                        ]
                    ],
                    /* Time (duration)
                     */
                    'time' => [
                        /* Force submission when time expires
                         * TODO may be controversial but seems desired, since it minimises
                         *  the quantity of "unfinalised attempts"
                         */
                        'limit_type' => 'hard',
                        /* Show countdown timer
                         * TODO may be controversial (stressful)
                         */
                        'countdown_option' => true
                    ]
                ],
                /* Fill in after the fact
                 */
                'items' => [],
                'rendering_type' => 'assess'
            ],
            /* A unique uuid in learnosity land that we can arbitrarily assign here
             */
            'reference' => (string)Uuid::uuid4(),
            /* Always spawn in PUBLISHED state
             */
            'status' => Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED,
            /* Tags
             * TODO Fill in with some derived values about courses and units and classcodes
             */
            'tags' => $tags,
            /* The name of the quiz
             */
            'title' => $legacyQuiz['title'],
        ];

        if ((int)$legacyQuiz['duration']) {
            /* If a duration was set, configure it similarly
             */
            $activity['data']['config']['time']['max_time'] = (int)$legacyQuiz['duration'];

            /* Warn users when 60 seconds remains
             */
            $activity['data']['config']['time']['warning_time'] = 60;
        }

        return $activity;
    }


    /**
     * Set up a payload for a single item
     * (this will be vaguely synonymous with our legacy Section idea)
     *
     * @param array $section
     * @param array $tags
     * @return array
     */
    private function setupItemPayload(array $section, array $tags): array
    {
        $item = [
            'questions' => [],
            /* A unique uuid in learnosity land that we can arbitrarily assign here
             */
            'reference' => (string)Uuid::uuid4(),
            /* Always spawn in PUBLISHED state
             */
            'status' => Helper::LEARNOSITY_ITEM_STATUS_PUBLISHED,
            /* Tags
             * TODO Fill in with some derived values about courses and units and classcodes
             */
            'tags' => $tags,
            /* The name of the quiz
             */
            'title' => $section['title'],
        ];

        /* Section Description
         * If it was more than an empty string, make a placeholder Passage thing
         * Otherwise skip it; there's nothing to say
         */

        return $item;
    }
}