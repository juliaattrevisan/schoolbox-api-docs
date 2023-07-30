<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Lumberjack\Logger;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use DebugBar\DebugBarException;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Utils\Uuid;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Quiz\Attempt\Model;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\User;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * Learnosity API instance is the same for everything afaict
     *
     * @var Api
     */
    private Api $api;


    /**
     * Controller constructor.
     */
    public function __construct(
        #[\SensitiveParameter] private string $learnosityConsumerKey,
        #[\SensitiveParameter] private string $learnosityConsumerSecret,
        private \Schoolbox\Error\Mapper $errorMapper,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper
    ) {
        /* Probably inject this one day, but for now,
         * I want to avoid dumping Yet Another Thing into the kernel
         * I don't have a convenient means to access SERVER_NAME except like this, sadly
         */
        $this->api = new Api(
            $_SERVER['SERVER_NAME'],
            $this->learnosityConsumerKey,
            $this->learnosityConsumerSecret,
            new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity')
        );
    }


    /**
     * Edit the contents of $assessment using the Learnosity system
     *
     * @param IAssessable $assessment
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getContent(IAssessable $assessment): void
    {
        $payload = [
            'config'    => [
                'activity_edit' => [
                    'back' => false,
                    'item' => [
                        'title' => [
                            'show' => true
                        ]
                    ],
                    'item_search' => [
                        'title' => [
                            'show' => true,
                        ],
                    ],
                    'reference' => [
                        'show' => false,
                    ]
                ],
                'item_edit' => [
                    'item' => [
                        'title' => [
                            'show' => true
                        ]
                    ]
                ]
            ],
            'mode'      => 'activity_edit',
            /* A Learnosity uuid that we arbitrarily prescribed
             */
            'reference' => $assessment->getLearnosityReference(),
            /* Corresponds to a user in the Learnosity Console who will be
             * considered "the author" of stuff created by the school's teachers
             * Misconfiguring this leads to Error 10000 which has no definition
             */
            'user'      => [
                'id'        => (string)$this->getConfigLoader()->get('learnosity_author_id'),
                /* These fields are superfluous and are presumably
                 * used for auditing; we attribute an individual user's
                 * name to a collectively-utilised author account
                 * configured in Learnosity itself (that's the id above)
                 */
                'firstname' => $this->getUser()->getFirstname(),
                'lastname'  => $this->getUser()->getLastname(),
                'email'     => $this->getUser()->getEmail()
            ]
        ];

        $this->response = $this->responseFromTemplate('@learnosity/content.twig', [
            'assessment'  => $assessment,
            'initOptions' => $this->api->initOptions('author', $payload),
            'returnUrl'   => Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL) ?? ''),
        ], sprintf(
            'Modify contents of %s',
            $assessment->getTitle()
        ));
    }


    /**
     * Just use our Welcome screen for now
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws \Alaress\Database\Exception
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getIntro(IAssessable $assessment, \Schoolbox\User $student): void
    {
        /* The intro also has a list of attempts from which you can resume.
         * We can do this by using the Learnosity session id in getAttempt()
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        $attempts = $this->attemptMapper->findByColumn('assess_id', $quiz->getId(), [
            'order' => [
                'start' => 'desc'
            ]
        ]);

        /* Decide if there was an existing attempt, to rename the call to action
         */
        $existingAttempt = $this->attemptMapper->findExistingUnfinished($assessment->getId(), $student->getId());

        $this->response = $this->responseFromTemplate('@learnosity/intro.twig', [
            'assessment' => $assessment,
            'student' => $student,
            'attempts' => $attempts,
            'existingAttempt' => $existingAttempt
        ], "Start {$assessment->getTitle()}");
    }


    /**
     * Let a student do an attempt in a Learnosity quiz
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws Deleted
     * @throws Exception
     * @throws ValidationException
     * @throws \Alaress\Database\Exception
     * @throws RouteNotFound
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function getAttempt(IAssessable $assessment, \Schoolbox\User $student): void
    {
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* Generate a session (attempt) identifier
         *
         * First, see if an incomplete attempt exists; if it does,
         * just reuse it
         */
        $attempt = $this->attemptMapper->findExistingUnfinished($assessment->getId(), $student->getId());

        if (null === $attempt) {
            /* Assign that Learnosity identifier to an assess_attempt
             */
            $learnosityReference = Uuid::generate();

            $attempt = $this->attemptMapper->createModel()
                ->setStart(\Alaress_Date_Format::now())
                ->setQuiz($quiz)
                ->setUser($student)
                ->setLearnosityReference($learnosityReference);

            $this->attemptMapper->save($attempt);
        }

        /* Initialise Learnosity Quiz player
         *
         * You need all the Learnosity items associated with the
         * $assessment's Learnosity reference, apparently
         * (how do we do this more cheapishly?)
         */
        $payload = [
            /* Our internal Quiz implementation id, it seems?
             */
            'activity_id'          => (string)$assessment->getId(),
            /* The quiz and its questions
             */
            'activity_template_id' => $quiz->getLearnosityReference(),
            "config"               => [
                "configuration" => [
                    "idle_timeout"          => [
                        "interval" => 300
                    ],
                    /* Go to a nice report screen when you're done, for now
                     */
                    'onsubmit_redirect_url' => $this->router->getGenerator()->generate('learnosity.getReport', [
                        'assessmentId' => $assessment->getId(),
                        'studentId' => $student->getId(),
                        'learnosityReference' => $attempt->getLearnosityReference()
                    ])
                ],
                "navigation"    => [
                    "auto_save"  => [
                        "save_interval_duration" => 300
                    ],
                    "show_intro" => false,
                    "show_outro" => false
                ],
                /* https://help.learnosity.com/hc/en-us/articles/360000758337-Customizing-the-Assessment-Player-experience-with-Regions
                 */
                'regions'       => 'main',
                /* This is our Quiz title, I guess
                 */
                "title"         => $assessment->getTitle(),
                "subtitle"      => ''
            ],
            /* This is our Quiz title, I guess
             */
            'name'                 => $assessment->getTitle(),
            'rendering_type'       => 'assess',
            /* An "attempt identifier", I think
             */
            'session_id'           => $attempt->getLearnosityReference(),
            /* This saves attempt results into Learnosity servers, enabling reporting etc.
             * You can use 'local_practice' to preview a quiz.
             * You can use 'feedback' to grade an attempt with feedback etc.
             */
            'type'                 => 'submit_practice',
            /* A uniquely-identifying string for us, I think
             */
            'user_id'              => (string)$this->getUser()->getId(),
        ];

        $this->response = $this->responseFromTemplate('@learnosity/attempt.twig', [
            'initOptions'         => $this->api->initOptions('items', $payload),
            'assessment'          => $assessment,
            'attemptId'           => $attempt->getId(),
            'completionUrl'       => $this->router->getGenerator()->generate('learnosity.postCompleteAttempt', [
                'assessmentId' => $assessment->getId(),
                'studentId'    => $student->getId(),
            ]),
            'learnosityReference' => $attempt->getLearnosityReference(),
        ], "Attempt {$assessment->getTitle()}");

        /* TODO Record the automated result somewhere (idk where)
         */
    }


    /**
     * Complete an attempt by setting a finish date.
     *
     * Uses the (learnosityReference, attemptId) tuple so you can't
     * randomly shut down some other dude's attempt; knowing their
     * learnosityReference should not be easily guessed
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws Deleted
     * @throws NotFound
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postCompleteAttempt(IAssessable $assessment, \Schoolbox\User $student): void
    {
        $existingAttempt = $this->attemptMapper->findOneByColumnList([
            'attempt_id'           => $this->requestPost->get('attemptId'),
            'learnosity_reference' => $this->requestPost->get('learnosityReference'),
            'user_id'              => $student->getId()
        ]);

        $existingAttempt->setFinish(\Alaress_Date_Format::now());

        $this->attemptMapper->save($existingAttempt);
    }


    /**
     * Show some basic stats about your attempt
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @param Model       $attempt
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getReport(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        $payload = [
            'user_id' => (string)$attempt->getUser()->getId(),
            'reports' => [
                [
                    /* DOM element id, where this goes in
                     *
                     * It's configurable by us, unlike the other anchors for
                     * Author and Items api
                     */
                    'id' => 'schoolbox-result-1',
                    'type' => 'sessions-summary-by-question',
                    'session_ids' => [
                        $attempt->getLearnosityReference()
                    ]
                ],
                [
                    /* DOM element id, where this goes in
                     *
                     * It's configurable by us, unlike the other anchors for
                     * Author and Items api
                     */
                    'id' => 'schoolbox-result-2',
                    'type' => 'session-detail-by-question',
                    'session_id' => $attempt->getLearnosityReference(),
                    'questions_api_init_options' => [
                        'showCorrectAnswers' => false
                    ]
                ]
            ],
        ];

        $this->response = $this->responseFromTemplate('@learnosity/report.twig', [
            'initOptions' => $this->api->initOptions('reports', $payload),
            'assessment' => $assessment,
            'returnUrl' => "/homepage/{$assessment->getContext()->getId()}"
        ], "Result for {$assessment->getTitle()}");
    }


    /**
     * Archive (soft-delete) multiple Learnosity Activities
     *
     * @return void
     * @throws ValidationException
     * @throws DebugBarException
     */
    public function getArchiveReferences(): void
    {
        $activityReferences = [
            'aaabbbbbbcccccddddd',
        ];

        $messages = [];

        foreach ($activityReferences as $activityReference) {
            try {
                $this->getArchiveReference($activityReference);
            } catch (\RuntimeException $e) {
                $messages[] = $e->getMessage();
            }
        }

        new \Alaress_Probe($messages);
    }


    /**
     * Archive (soft-delete) a particular Learnosity Activity
     * Activities must have at least one published item before an activity can be archived
     *
     * If you can't delete an activity because it tells you that you need a published item,
     * also check any Welcome/Exit screens - those are Items too
     *
     * @param string $learnosityReference
     * @return void
     * @throws ValidationException
     * @throws \RuntimeException
     */
    public function getArchiveReference(string $learnosityReference): void
    {
        (new Helper($this->api))->archive($learnosityReference);
    }


    /**
     * Build quiz contents
     */
    public function getActivityList(): void
    {
        $payload = [
            'mode' => 'activity_list',
            'config' => [
                'activity_list' => [
                    'title' => [
                        'show' => true,
                        'show_reference' => true
                    ]
                ]
            ],
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

        $this->response = $this->responseFromTemplate('@learnosity/activityList.twig', [
            'initOptions' => $this->api->initOptions('author', $payload)
        ], 'Example Activity List');
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

        $this->response = $this->responseFromTemplate('@learnosity/items.twig', [
            'initOptions' => $this->api->initOptions('items', $payload)
        ], 'Example Item');
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

        $this->response = $this->responseFromTemplate('@learnosity/items.twig', [
            'initOptions' => $this->api->initOptions('items', $payload)
        ], 'Example Item');
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

        $this->response = $this->responseFromTemplate('@learnosity/sessionList.twig', [
            'initOptions' => $this->api->initOptions('reports', $payload)
        ], 'Example Session List');
    }

    /**
     * TODO Literally copypasta'd
     *
     * Converts an assessment_id to a Quiz
     *
     * @param int $assessmentId
     * @return Quiz
     * @throws Exception
     */
    protected function getQuizForAssessmentId(int $assessmentId): Quiz
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS !== $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        return $quiz;
    }
}
