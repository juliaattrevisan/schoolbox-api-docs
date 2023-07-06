<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Web\Controller\Base;
use LearnositySdk\Utils\Uuid;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../../vendor/learnosity/learnosity-sdk-php/bootstrap.php';

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param UserMapper  $userMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private UserMapper $userMapper
    ) {
    }


    /**
     * Provide form
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
     * Provide form
     */
    public function getItem(): void
    {
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
                "regions" => "horizontal-fixed",
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

            'items' => [
                '7d6b64f9-88af-4e40-b74f-7895cccbff00',
                '0a64ef3b-eaff-43bc-a2ed-081305c2ee12',
                '572287db-3764-43be-b2e7-2af2e3ee9c2f'
            ]


        ];

        $api = new Api(
            $this->getConfigLoader()->get('learnosity_consumer_key'),
            $this->getConfigLoader()->get('learnosity_consumer_secret'),
            $this->getConfigLoader()->get('logs_dir')
        );

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/item.twig', [
            'initOptions' => $api->initOptions('author', $payload)
        ])->setTitle('Example Item'));
    }
}
