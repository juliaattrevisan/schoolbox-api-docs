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
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
        private \Schoolbox\Learning\Learnosity\Feedback\Mapper $feedbackMapper
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
                    'onsubmit_redirect_url' => false,
                    'onsave_redirect_url' => false,
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
            /* Save results locally
             */
            'completionUrl'       => $this->router->getGenerator()->generate('learnosity.postCompleteAttempt', [
                'assessmentId' => $assessment->getId(),
                'studentId'    => $student->getId(),
            ]),
            /* Redirect to here when we are ready
             */
            'outroUrl' => $this->router->getGenerator()->generate('learnosity.getCompleteAttempt', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
                'learnosityReference' => $attempt->getLearnosityReference()
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
     * @throws ValidationException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postCompleteAttempt(IAssessable $assessment, \Schoolbox\User $student): void
    {
        /* Find out what they scored by the automated system
         */
        $result = $this->api->dataGetRequest('/sessions/scores', [
            'session_id' => [
                $this->request->post['learnosityReference']
            ]
        ]);

        $mark = $result['data'][0]['score'];
        $maxMark = $result['data'][0]['max_score'];

        /* Record it locally for our own usages
         */
        $existingAttempt = $this->attemptMapper->findOneByColumnList([
            'attempt_id'           => $this->requestPost->get('attemptId'),
            'learnosity_reference' => $this->requestPost->get('learnosityReference'),
            'user_id'              => $student->getId()
        ]);

        $existingAttempt->setMark($mark)
            ->setMaxMark($maxMark)
            ->refreshNormalisedMark()
            ->setFinish(\Alaress_Date_Format::now());

        $this->attemptMapper->save($existingAttempt);
    }


    /**
     * The outro screen for completing an attempt;
     * show some basic stats about your attempt
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @param Model       $attempt
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getCompleteAttempt(
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
            ],
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput('@learnosity/outro.twig', [
            'initOptions' => $this->api->initOptions('reports', $payload),
            'assessment' => $assessment,
            'attempt' => $attempt,
            'returnUrl' => "/homepage/{$assessment->getContext()->getId()}"
        ])->setTitle("Result for {$assessment->getTitle()}"));
    }


    /**
     * From a student's perspective,
     * show some in-depth stats about your attempt
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @param Model       $attempt
     * @return void
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getReview(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        if (null !== $attempt->getReviewed()) {
            $payload = [
                'user_id' => (string)$attempt->getUser()->getId(),
                'reports' => [
                    [
                        /* DOM element id, where this goes in
                         *
                         * It's configurable by us, unlike the other anchors for
                         * Author and Items api
                         */
                        'id' => 'schoolbox-student',
                        'type' => 'session-detail-by-item',
                        'session_id' => $attempt->getLearnosityReference(),
                        'questions_api_init_options' => [
                            'showCorrectAnswers' => false
                        ]
                    ]
                ],
            ];

            /* If there's a feedback for this attempt, get it
             * and show the student. At the moment it's just
             * commentary.
             */
            $comments = $this->feedbackMapper->findForAttempt($attempt->getId());
        } else {
            $payload = [
                'user_id' => (string)$attempt->getUser()->getId(),
                'reports' => [
                    [
                        /* DOM element id, where this goes in
                         *
                         * It's configurable by us, unlike the other anchors for
                         * Author and Items api
                         */
                        'id' => 'schoolbox-student',
                        'type' => 'session-detail-by-question',
                        'session_id' => $attempt->getLearnosityReference(),
                        'questions_api_init_options' => [
                            'showCorrectAnswers' => false
                        ]
                    ]
                ],
            ];

            $comments = [];
        }

        $this->response = $this->responseFromTemplate('@learnosity/review.twig', [
            'initOptions' => $this->api->initOptions('reports', $payload),
            'assessment' => $assessment,
            'attempt' => $attempt,
            'comments' => $comments ? json_encode($comments, JSON_THROW_ON_ERROR) : null,
            'returnUrl' => "/homepage/{$assessment->getContext()->getId()}"
        ], "Result for {$assessment->getTitle()}");
    }


    /**
     * Provide feedback for an attempt
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @param Model       $attempt
     * @throws Exception
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getFeedback(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* This is what the student did
         */
        $reportPayload = [
            'user_id' => (string)$attempt->getUser()->getId(),
            'reports' => [
                [
                    /* DOM element id, where this goes in
                     *
                     * It's configurable by us, unlike the other anchors for
                     * Author and Items api
                     */
                    'id' => 'schoolbox-student',
                    'type' => 'session-detail-by-item',
                    'session_id' => $attempt->getLearnosityReference(),
                    'questions_api_init_options' => [
                        'showCorrectAnswers' => false
                    ]
                ]
            ],
        ];

        /* These are the scores they obtained for the one session we chose
         * (It'll still come to us as an array of sessions though)
         */
        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
            'session_id' => [
                $attempt->getLearnosityReference()
            ]
        ])['data'][0]['responses'];

        /* Reforge it into a generic set of tuples
         * because we need to shank it in via frontend
         */
        $scores = [];

        foreach ($responses as $response) {
            $learnosityReference = $response['response_id'];

            $scores[$learnosityReference] = [
                'score' => $response['score'],
                'max' => $response['max_score']
            ];
        }

        /* Find all the comments for this attempt
         */
        $comments = $this->feedbackMapper->findForAttempt($attempt->getId());

        $this->response = $this->responseFromTemplate('@learnosity/feedback.twig', [
            'reportInitOptions' => $this->api->initOptions('reports', $reportPayload),
            'assessment' => $assessment,
            'attempt' => $attempt,
            'scores' => json_encode($scores, JSON_THROW_ON_ERROR),
            'comments' => json_encode($comments, JSON_THROW_ON_ERROR),
        ], sprintf(
            'Feedback for %s in %s',
            $student->getFullName(),
            $assessment->getTitle()
        ));
    }


    /**
     * Tell Learnosity about what our teachers marked each question
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @param Model       $attempt
     * @return void
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postFeedback(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* Updated marks given by our teacher,
         * including attempt-based commentary
         */
        $postData = $this->request->post->getArrayCopy();

        /* $postData is a structured array containing all the feedback info
         * Some of it goes back into learnosity; some is saved locally
         *
         * $postData = [
         *     'scores' => [
                   '62ff8e77-cfec-444c-ad82-2dc7657aee91_d864fd514aeab7448acdb271fc81164a' => 2
         *     ],
         *     'comments' => [
         *         '62ff8e77-cfec-444c-ad82-2dc7657aee91_d864fd514aeab7448acdb271fc81164a' => 'my comment'
         *     ],
         *     'attempt-comment' => 'you did a good job, now back to sleep'
         * ]
         */

        /* FIXME Instead of asking learnosity how it tallied everything, we can
         *  just...do it ourselves, I guess, and sort-of delete this problem
         *  We knew the max_mark from the attempt table, so it could potentially
         *  desync from what Learnosity knows.
         */
        $mark = array_sum(array_values($postData['scores']));
        $maxMark = $attempt->getMaxMark();

        /* Save the comment and new normalised mark
         */
        $attempt->setComment(trim($postData['attempt-comment']))
            ->setMark($mark)
            ->setMaxMark($maxMark)
            ->setReviewer($this->getUser())
            ->setReviewed(\Alaress_Date_Format::now())
            ->refreshNormalisedMark();

        $this->attemptMapper->save($attempt);

        /* For every learnosity question, see if we have a db record for its feedback
         * If we do, recycle it and update its contents
         * Otherwise spawn a new one
         *
         * TODO make this algorithm cheaper
         */
        foreach ($postData['comments'] as $learnosityReference => $comment) {
            try {
                $feedback = $this->feedbackMapper->findOneByColumnList([
                    'attempt_id' => $attempt->getId(),
                    'learnosity_reference' => $learnosityReference
                ]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $feedback = $this->feedbackMapper->createModel()
                    ->setAttempt($attempt)
                    ->setLearnosityReference($learnosityReference);
            }

            /* Update comment
             *
             * TODO optionally update score, if we want to store that locally
             *  at the risk of desyncing with learnosity
             */
            $feedback->setComment($comment);

            $this->feedbackMapper->save($feedback);
        }

        /* Start preparing data payload for updating Learnosity
         * This is so the updated scores appear in their interfaces
         *
         * These are the scores they obtained for the one session we chose
         * (It'll still come to us as an array of sessions though)
         *
         * Learnosity requires multiple fields to update values so you
         * might as well refetch it all at this point so we can send it
         * to them in the format it wants
         */
        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
            'session_id' => [
                $attempt->getLearnosityReference()
            ]
        ])['data'][0]['responses'];

        /* Re-collate updated response data
         */
        $responseData = [];

        foreach ($responses as $response) {
            $responseId = $response['response_id'];

            $updatedResponse = $response;
            $updatedResponse['score'] = $postData['scores'][$responseId];

            $responseData[] = $updatedResponse;
        }

        /* Post back to learnosity
         * When the learnosity ui reloads again, it'll still be remembering
         * all the old scores. You have to wait for it to reload, then hit
         * F5 to get the new updated values to appear. That's what happens
         * if you just rely on window.location.reload() in frontend.
         *
         * Instead, redirect people to a whole other screen afterwards to
         * artificially force this behaviour
         */
        $this->api->dataUpdateRequest('/sessions/responses/scores', [
            'sessions' => [
                [
                    'session_id' => $attempt->getLearnosityReference(),
                    'user_id' => (string)$attempt->getUser()->getId(),
                    'responses' => $responseData
                ]
            ]
        ]);

        /* Get the new result back from learnosity
         *
         * FIXME This occurs too quickly. We refetch the session score before
         *  Learnosity had a chance to finish updating it, so we always get
         *  the old value back.
         *
            $result = $this->api->dataGetRequest('/sessions/scores', [
                'session_id' => [
                    $attempt->getLearnosityReference()
                ]
            ]);
         */

        /* TODO Redirect back to the index screen for now
         *  but probably need a returnUrl
         */
        $redirect = $this->router->getGenerator()->generate('learnosity.getIntro', [
            'assessmentId' => $assessment->getId(),
            'studentId' => $student->getId()
        ]);

        $this->response->redirect->to($redirect);
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
     * A list of all the Learnosity Quizzes
     *
     * This was an experimental first step, but I ended up using it
     * to micromanage all our test content (mostly for mass deleting)
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
