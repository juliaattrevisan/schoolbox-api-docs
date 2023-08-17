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
            'mode'  => 'activity_edit',
            /* A Learnosity uuid that we arbitrarily prescribed
             */
            'reference' => $assessment->getLearnosityReference(),
            /* Corresponds to a user in the Learnosity Console who will be
             * considered "the author" of stuff created by the school's teachers
             * Misconfiguring this leads to Error 10000 which has no definition
             *
             * TODO Waiting for learnosity to clarify
             */
            'user' => [
                'id' => (string)$this->getUser()->getId(),
                /* These fields are superfluous and are presumably
                 * used for auditing; we attribute an individual user's
                 * name to a collectively-utilised author account
                 * configured in Learnosity itself (that's the id above)
                 */
                'firstname' => 'Schoolbox',
                'lastname'  => 'Schoolbox',
                'email'     => 'schoolbox@schoolbox.com.au'
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
                 *
                 * Perhaps more useful: https://reference.learnosity.com/items-api/initialization/config.regions
                 */
                'regions'       => [
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
                /* This is our Quiz title, I guess
                 */
                "title"         => $assessment->getTitle(),
                "subtitle"      => ''
            ],
            /* Enable livestreaming lol
             */
            'events' => false,
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
                $this->requestPost->get('learnosityReference')
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

        $this->response = $this->responseFromTemplate('@learnosity/outro.twig', [
            'initOptions' => $this->api->initOptions('reports', $payload),
            'assessment' => $assessment,
            'attempt' => $attempt,
            'returnUrl' => "/homepage/{$assessment->getContext()->getId()}"
        ], "Result for {$assessment->getTitle()}");
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
            'comments' => $comments ? base64_encode(json_encode($comments, JSON_THROW_ON_ERROR)) : null,
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
            'comments' => base64_encode(json_encode($comments, JSON_THROW_ON_ERROR)),
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
        $postData = $this->requestPost->getArrayCopy();

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

            /* "When setting a non-zero score, the response must also be attempted."
             *    -- https://reference.learnosity.com/data-api/endpoints/sessions_endpoints#updateResponseScores
             *
             * This includes responses that cannot be automagically calculated
             */
            $updatedResponse['attempted'] = true;

            /* If there's no max score, but the teacher gave a score, I guess
             * we just assume that to be the max score too...basically bonus
             * marks in this scenario
             */
            if (null === $updatedResponse['max_score']) {
                $updatedResponse['max_score'] = $updatedResponse['score'];
            }

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

        $this->response = $this->redirectFound($redirect);
    }


    /**
     * Start horizontal marking for a given quiz
     *
     * This is grossly inefficient but there aren't many options available to us
     *
     * @param IAssessable $assessment
     * @param string|null $learnosityReference  An item reference identifier in learnosity land
     * @return void
     */
    public function getHorizontalMarking(IAssessable $assessment, ?string $learnosityReference): void
    {
        $reportIdentifierPrefix = 'schoolbox-student-';

        /* Get the quiz identifier
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* Get a list of attempts sorted by finish date
         * This gives us the most recent attempt for a quiz, if a student did multiple attempts
         *
         * TODO What was supposed to happen for multiple attempts in a horizontal marking context?
         */
        $attempts = $this->attemptMapper->findByColumn('assess_id', $quiz->getId(), [
            'order' => [
                'finish' => 'desc'
            ]
        ]);

        /* Fetch all the assess_attempt.learnosity_references for $assessment
         * for a student's most recent completed attempt and store then in a hashtable
         *
         * Remove this deduping if you wanted to show all attempts regardless of completion status.
         */
        $sessions = [];
        $users = [];
        $reportPayloads = [];

        foreach ($attempts as $attempt) {
            $key = $reportIdentifierPrefix . $attempt->getUser()->getId();

            if (!isset($users[$key]) && $attempt->isFinished()) {
                $sessions[$attempt->getUser()->getId()] = $attempt->getLearnosityReference();

                /* ...because Learnosity wants it in this format
                 */
                $users[$key] = [
                    'id' => (string)$attempt->getUser()->getId(),
                    'name' => $attempt->getUser()->getFullName()
                ];

                /* Collate a report payload for this attempt
                 * We're going to get them all and display them all at the same time
                 * using css to hide all the questions except for the item_id
                 * ($learnosityReference) currently determined.
                 */
                $reportPayloads[] = $this->api->initOptions('reports', [
                    'user_id' => (string)$attempt->getUser()->getId(),
                    'reports' => [
                        [
                            /* DOM element id, where this goes in
                             *
                             * It's configurable by us, unlike the other anchors for
                             * Author and Items api
                             */
                            'id' => 'schoolbox-student-' . $attempt->getUser()->getId(),
                            'type' => 'session-detail-by-item',
                            'session_id' => $attempt->getLearnosityReference(),
                            'questions_api_init_options' => [
                                'showCorrectAnswers' => true
                            ]
                        ]
                    ],
                ]);
            }
        }

        /* Find a list of Learnosity Items (questions) available in this quiz
         * These are their references only
         */
        $itemReferences = $this->api->dataGetRequest('itembank/activities', [
            'references' => [
                $quiz->getLearnosityReference()
            ]
        ])['data'][0]['data']['items'];

        /* Find a list of Learnosity Items (questions) available in this quiz
         * This includes more info about each item
         */
        $items = $this->api->dataGetRequest('itembank/items', [
            'references' => array_column($itemReferences, 'id')
        ])['data'];

        /* Consolidate reference and titles into an in-order lookup
         * (because $items is in some non-sequenced order)
         */
        foreach ($itemReferences as &$itemReference) {
            $reference = $itemReference['reference'];

            /* Find it in the set of $items
             */
            foreach ($items as $item) {
                if ($reference === $item['reference']) {
                    $title = '' === trim((string)$item['title']) ? 'Untitled' : $item['title'];
                    $itemReference['title'] = $title;
                }
            }
        }

        unset($itemReference);

        $this->response = $this->responseFromTemplate('@learnosity/horizontal.twig', [
            'reportPayloads' => json_encode($reportPayloads, JSON_THROW_ON_ERROR),
            'users' => $users,
            'usersEncoded' => json_encode($users, JSON_THROW_ON_ERROR),
            'assessment' => $assessment,
            'itemReferences' => $itemReferences
        ], sprintf(
            'Horizontal Marking for %s',
            $assessment->getTitle()
        ));

//        /* Obtain their scores from Learnosity
//         */
//        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
//            'session_id' => array_values($sessions)
//        ]);
//

//
//        /* If $learnosityReference was specified, load everyone's responses
//         * for that particular item; otherwise just load the first one
//         */
//        $currentItemReference = $learnosityReference;
//
//        /* The responses that we want to show at the moment
//         */
//        $currentResponses = [];
//
//        if (null === $learnosityReference) {
//            $currentItemReference = $itemReferences[0]['reference'];
//        }
//
//        new \Alaress_Probe($sessions, $users, $responses, $itemReferences);

        /* Provide "Previous Question" and "Next Question" urls
         */

        return;
    }



//
//    /**
//     * Start horizontal marking for a given quiz
//     *
//     * @param IAssessable $assessment
//     * @return void
//     * @throws Error
//     * @throws ValidationException
//     * @throws \JsonException
//     * @throws \RuntimeException
//     */
//    public function getHorizontalMarkingV1(IAssessable $assessment): void
//    {
//        $quiz = $this->getQuizForAssessmentId($assessment->getId());
//
//        /* Get a list of attempts sorted by finish date
//         * This gives us the most recent attempt for a quiz, if a student did multiple attempts
//         *
//         * TODO What was supposed to happen for multiple attempts in a horizontal marking context?
//         */
//        $attempts = $this->attemptMapper->findByColumn('assess_id', $quiz->getId(), [
//            'order' => [
//                'finish' => 'desc'
//            ]
//        ]);
//
//        /* Fetch all the assess_attempt.learnosity_references for $assessment
//         * for a student's most recent completed attempt and store then in a hashtable
//         *
//         * Remove this deduping if you wanted to show all attempts regardless of completion status.
//         */
//        $sessions = [];
//        $users = [];
//
//        foreach ($attempts as $attempt) {
//            if ($attempt->isFinished() && !isset($stack[$attempt->getUser()->getId()])) {
//                $sessions[$attempt->getUser()->getId()] = $attempt->getLearnosityReference();
//
//                /* ...because Learnosity wants it in this format
//                 */
//                $users[$attempt->getUser()->getId()] = [
//                    'id' => (string)$attempt->getUser()->getId(),
//                    'name' => $attempt->getUser()->getFullName()
//                ];
//            }
//        }
//
//        $reportPayload = [
//            'reports' => [
//                [
//                    'id' => 'horizontal-marking',
//                    'type' => 'response-analysis-by-item',
//                    'session_ids' => array_values($sessions),
//                    'users' => array_values($users),
//                    'render' => true
//                ],
//            ]
//        ];
//
//        /* These are the scores they obtained for the one session we chose
//         * (It'll still come to us as an array of sessions though)
//         */
//        $responseData = $this->api->dataGetRequest('sessions/responses/scores', [
//            'session_id' => array_values($sessions)
//        ]);
//
//        /* Reforge it into a generic set of tuples
//         * because we need to shank it in via frontend
//         */
//        $scores = [];
//        $learnosityReferences = [];
//
//        foreach ($responseData['data'] as $userData) {
//            $userId = $userData['user_id'];
//
//            /* Init their data struct if it didn't already exist
//             * We'll pile in (learnosity reference, scores) tuples here
//             */
//            if (!isset($scores[$userId])) {
//                $scores[$userId] = [];
//            }
//
//            /* Shank each score for each question for this user
//             */
//            foreach ($userData['responses'] as $response) {
//                $responseId = $response['response_id'];
//
//                /* We need to fetch our locally-stored comment for this reference
//                 */
//                $learnosityReferences[] = $responseId;
//
//                $scores[$userId][$responseId] = [
//                    'score' => $response['score'],
//                    'max' => $response['max_score']
//                ];
//            }
//        }
//
//        /* Find all the comments for this attempt
//         */
//        $comments = $this->feedbackMapper->findByLearnosityReferences($learnosityReferences);
//
//        $this->response = $this->responseFromTemplate('@learnosity/horizontal-v1.twig', [
//            'initOptions' => $this->api->initOptions('reports', $reportPayload),
//            'assessment' => $assessment,
//            'scores' => $scores, // json_encode($scores, JSON_THROW_ON_ERROR),
//            'comments' => $comments // base64_encode(json_encode($comments, JSON_THROW_ON_ERROR)),
//        ], sprintf(
//            'Horizontal Marking for %s',
//            $assessment->getTitle()
//        ));
//    }


//    /**
//     * Live Progress monitor thing for a quiz
//     *
//     * Be sure to hit like and subscribe
//     * It really helps us out a lot
//     *
//     * @param IAssessable $assessment
//     * @return void
//     * @throws ValidationException
//     * @throws \JsonException
//     */
//    public function getLiveProgress(IAssessable $assessment): void
//    {
//        $users = [];
//
//        foreach ($assessment->getParticipantUsers() as $user) {
//            $users[] = [
//                'id' => $user->getId(),
//                'name' => $user->getFullName(),
//                'hash' => hash('sha256', $user->getId() . $this->learnosityConsumerSecret)
//            ];
//        }
//
//        $payload = [
//            'reports' => [
//                [
//                    'id' => 'live-progress',
//                    'type' => 'live-activitystatus-by-user',
//                    'activity' => [
//                        'id' => $assessment->getLearnosityReference(),
//                        'name' => $assessment->getTitle(),
//                    ],
//                    'show_user_selectors' => true,
//                    'show_action_controls' => true,
//                    'display_activity' => true,
//                    'users' => $users
//                ]
//            ]
//        ];
//
//        new \Alaress_Probe($payload);
//
//        $this->response = $this->responseFromTemplate('@learnosity/stream.twig', [
//            'initOptions' => $this->api->initOptions('reports', $payload),
//            'assessment' => $assessment,
//        ], sprintf(
//            'Livestream for %s',
//            $assessment->getTitle()
//        ));
//    }


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
            'f0b0a793-7f52-45eb-baba-071e5f12d60b',
            '9120323f-672f-4e68-8533-94ba8c9abfca',
            'd1276ea8-5be7-449a-8bea-87ed1a0f7be3',
            'aed231de-5909-4628-a7e0-c4d5c772d5a8',
            '8b43fe13-79a1-4ae4-8c08-43e858d9f6d7',
            'ad45b26f-f3aa-4127-ba59-c61901d9990f',
        ];

        $messages = [];

        foreach ($activityReferences as $activityReference) {
            try {
                $this->getArchiveReference($activityReference);

                $messages[] = "Archived {$activityReference}";
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
                ],
                'activity_edit' => [
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
