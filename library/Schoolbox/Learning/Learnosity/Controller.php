<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Web\Controller\Base;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Utils\Uuid;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../../vendor/learnosity/learnosity-sdk-php/bootstrap.php';

class Controller extends Base
{
    /**
     * Controller constructor.
     */
    public function __construct() {
    }


    /**
     * Build quiz contents
     */
    public function getActivityList(): void
    {
        $payload = [
            'mode' => 'activity_list',
            'user' => [
                /* Corresponds to a user in the Learnosity Console who will be
                 * considered "the author" of stuff created by the school's teachers
                 * Misconfiguring this leads to Error 10000 which has no definition
                 */
                'id' => (string)$this->getConfigLoader()->get('learnosity_author_id'),
                /* These fields are superfluous and are presumably
                 * used for auditing; we attribute an individual user's
                 * name to a collectively-utilised author account
                 * configured in Learnosity itself (that's the id above)
                 */
                'firstname' => $this->getUser()->getFirstname(),
                'lastname' => $this->getUser()->getLastname(),
                'email' => $this->getUser()->getEmail()
            ]
        ];

        $api = new Api(
            $this->getConfigLoader()->get('learnosity_consumer_key'),
            $this->getConfigLoader()->get('learnosity_consumer_secret'),
            $this->getConfigLoader()->get('logs_dir')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/activityList.twig', [
            'initOptions' => $api->initOptions('author', $payload)
        ])->setTitle('Example Activity List'));
    }


    /**
     * Do an attempt
     */
    public function getItems(): void
    {
        /* This should be stored in assess_attempt and/or $_SESSION
         * because this lets you resume your attempt where you left off
         * Generating a new sessionId here causes you spawn a new attempt
         * in Learnosity
         */
        // 381c864b-cb14-45e3-b901-0d5b493ece47 is one of my test attempts
        $sessionId = Uuid::generate();

        $quiz = [
            'id' => '733',
            'title' => 'schoolbox test quiz'
        ];

        $payload = [
            /* Our internal Quiz implementation id, it seems?
             */
            'activity_id' => $quiz['id'],
            "config" => [
                "configuration" => [
                    "idle_timeout" => [
                        "interval" => 300
                    ]
                ],
                "navigation" => [
                    "auto_save" => [
                        "save_interval_duration" => 300
                    ],
                    "show_intro" => true,
                    "show_outro" => true
                ],
                /* https://help.learnosity.com/hc/en-us/articles/360000758337-Customizing-the-Assessment-Player-experience-with-Regions
                 */
                'regions' => 'main',
                /* This is our Quiz title, I guess
                 */
                "title" =>  $quiz['title'],
                "subtitle" => "This is a random subtitle"
            ],
            /* This is our Quiz title, I guess
             */
            'name' => $quiz['title'],
            'rendering_type' => 'assess',
            /* An "attempt identifier", I think
             */
            'session_id' => $sessionId,
            /* This saves attempt results into Learnosity servers, enabling reporting etc.
             * You can use 'local_practice' to preview a quiz.
             * You can use 'feedback' to grade an attempt with feedback etc.
             */
            'type' => 'submit_practice',
            /* A uniquely-identifying string for us, I think
             */
            'user_id' => (string)$this->getUser()->getId(),
            'sections' => [
                [
                    'config' => [
                        "configuration" => [
                            "idle_timeout" => [
                                "interval" => 300
                            ]
                        ],
                        "navigation" => [
                            "auto_save" => [
                                "save_interval_duration" => 300
                            ],
                            "show_intro" => true,
                            "show_outro" => true
                        ],
                        /* Each section can have their own unique
                         * title/subtitle combo
                         */
                        'title' => 'Do this section first',
                        'subtitle' => 'first section subtitle'
                    ],
                    'items' => [
                        '7d6b64f9-88af-4e40-b74f-7895cccbff00',
                        '0a64ef3b-eaff-43bc-a2ed-081305c2ee12'
                    ]
                ],
                [
                    'config' => [
                        "configuration" => [
                            "idle_timeout" => [
                                "interval" => 300
                            ]
                        ],
                        "navigation" => [
                            "auto_save" => [
                                "save_interval_duration" => 300
                            ],
                            "show_intro" => true,
                            "show_outro" => true
                        ],
                        /* Each section can have their own unique
                         * title/subtitle combo
                         */
                        'title' => 'Do this other section',
                        'subtitle' => 'second section subtitle'
                    ],
                    'items' => [
                        '572287db-3764-43be-b2e7-2af2e3ee9c2f'
                    ]
                ],
            ],
        ];

        $api = new Api(
            $this->getConfigLoader()->get('learnosity_consumer_key'),
            $this->getConfigLoader()->get('learnosity_consumer_secret'),
            $this->getConfigLoader()->get('logs_dir')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/items.twig', [
            'initOptions' => $api->initOptions('items', $payload)
        ])->setTitle('Example Item'));
    }


    /**
     * Do an attempt
     */
    public function getFeedback(): void
    {
        /* This should be stored in assess_attempt and/or $_SESSION
         * because this lets you resume your attempt where you left off
         * Generating a new sessionId here causes you spawn a new attempt
         * in Learnosity
         */
        // 381c864b-cb14-45e3-b901-0d5b493ece47 is one of my test attempts
//        $sessionId = Uuid::generate();
        $sessionId = '381c864b-cb14-45e3-b901-0d5b493ece47';

        $quiz = [
            'id' => '733',
            'title' => 'schoolbox test quiz'
        ];

        $payload = [
            /* Our internal Quiz implementation id, it seems?
             */
            'activity_id' => $quiz['id'],
            "config" => [
                "configuration" => [
                    "idle_timeout" => [
                        "interval" => 300
                    ]
                ],
                "navigation" => [
                    "auto_save" => [
                        "save_interval_duration" => 300
                    ],
                    "show_intro" => true,
                    "show_outro" => true
                ],
                /* https://help.learnosity.com/hc/en-us/articles/360000758337-Customizing-the-Assessment-Player-experience-with-Regions
                 */
                'regions' => 'main',
                /* This is our Quiz title, I guess
                 */
                "title" =>  $quiz['title'],
                "subtitle" => "This is a random subtitle"
            ],
            /* This is our Quiz title, I guess
             */
            'name' => $quiz['title'],
            'rendering_type' => 'assess',
            /* An "attempt identifier", I think
             */
            'session_id' => $sessionId,
            /* This saves attempt results into Learnosity servers, enabling reporting etc.
             * You can use 'local_practice' to preview a quiz.
             * You can use 'feedback' to grade an attempt with feedback etc.
             */
            'type' => 'feedback',
            /* A uniquely-identifying string for us, I think
             */
            'user_id' => (string)$this->getUser()->getId(),
            'items' => [
                [
                    'id' => '7d6b64f9-88af-4e40-b74f-7895cccbff00',
                    'reference' => '7d6b64f9-88af-4e40-b74f-7895cccbff00',
                ],
                [
                    'id' => '0a64ef3b-eaff-43bc-a2ed-081305c2ee12',
                    'reference' => '0a64ef3b-eaff-43bc-a2ed-081305c2ee12',
                ],
                [
                    'id' => '572287db-3764-43be-b2e7-2af2e3ee9c2f',
                    'reference' => '572287db-3764-43be-b2e7-2af2e3ee9c2f',
                ]
            ],
        ];

        $api = new Api(
            $this->getConfigLoader()->get('learnosity_consumer_key'),
            $this->getConfigLoader()->get('learnosity_consumer_secret'),
            $this->getConfigLoader()->get('logs_dir')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/items.twig', [
            'initOptions' => $api->initOptions('items', $payload)
        ])->setTitle('Example Item'));
    }


    /**
     * Get an overview for all the sessions conducted
     *
     * This gives teachers a strategic idea of how many questions were
     * attempted correctly
     *
     * @return void
     * @throws \JsonException
     * @throws ValidationException
     */
    public function getSessionListReport(): void
    {
        $api = new Api(
            $this->getConfigLoader()->get('learnosity_consumer_key'),
            $this->getConfigLoader()->get('learnosity_consumer_secret'),
            $this->getConfigLoader()->get('logs_dir')
        );

        $payload = [
            'user_id' => (string)$this->getUser()->getId(),
            'reports' => [
                [
                    /* DOM element id, where this goes in
                     *
                     * It's configurable by us, unlike the other anchors for
                     * Author and Items api
                     */
                    'id' => 'schoolbox-result',
                    'type' => 'sessions-list',
                    'limit' => 100,
                    'ui' => 'table',
                    'activities' => [
                        [
                            'id' => '733',
                            'name' => 'the thing'
                        ]
                    ]
                ]
            ],
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/sessionList.twig', [
            'initOptions' => $api->initOptions('reports', $payload)
        ])->setTitle('Example Session List'));
    }
}
