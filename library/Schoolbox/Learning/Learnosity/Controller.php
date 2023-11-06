<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Lumberjack\Logger;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Utils\Uuid;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Project\ResultCache\Mapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Learnosity\Feedback\SanitiseScores;
use Schoolbox\Learning\Quiz\Attempt\Model;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * A prefix for each report id attr in the front end
     */
    private const REPORT_HORIZONTAL_MARKING_PREFIX = 'schoolbox-student-';


    /**
     * Users are prompted to do something after this many seconds of inactivity
     *
     */
    private const LEARNOSITY_ATTEMPT_IDLE_TIMEOUT = 300;


    /**
     * Saves intermittently every 300 seconds by default
     */
    private const LEARNOSITY_SAVE_INTERVAL = 300;


    /**
     * Alert students 60 seconds before it kicks you out
     */
    private const LEARNOSITY_WARNING_TIME = 60;


    /**
     * Some interactivities/questions/features are only available in certain plans
     * we don't blacklist things we want to hide; we whitelist things we want to show
     * because that's just...the way learnosity built it
     *
     * This means you have a bunch of random template_reference strings here which
     * correspond to stuff that you can see on screen.
     */
    private const LEARNOSITY_QUESTION_GROUP_PLANS = [
        'elite' => [
            'question_type_groups' => [
                ['reference' => 'mcq'],
                ['reference' => 'cloze'],
                ['reference' => 'match'],
                ['reference' => 'writespeak'],
                ['reference' => 'highlight'],
                ['reference' => 'graph'],
                ['reference' => 'math'],
                ['reference' => 'chart'],
                ['reference' => 'chemistry'],
                [
                    'reference' => 'other',
                    'template_references' => [
                        '8ff43fa9-804b-42e7-9b3e-3cdd7e3a882b',
                    ]
                ],
            ]
        ],
        'default' => [
            'question_type_groups' => [
                ['reference' => 'mcq'],
                ['reference' => 'cloze'],
                ['reference' => 'match'],
                [
                    'reference' => 'writespeak',
                    'template_references' => [
                        '1e6039f8-0676-495d-aca9-108710a51ce5',
                        '0f559437-5072-475e-830d-1fa9d2f6bb0d',
                        '4fe6c726-419c-4aff-b189-4a9756a0ffda',
                    ]
                ],
                ['reference' => 'highlight'],
                ['reference' => 'graph'],
                [
                    'reference' => 'math',
                    'template_references' => [
                        'ef0ab0ba-235f-459d-9380-a4a106280c9a',
                        '57b0690f-4e99-4bbc-9c8f-aa3074baa495',
                        '11c9093a-dfd3-4852-b1ee-d5e5c3f032df',
                        '7b810c2c-9100-4ff1-96de-cda23bf34445',
                    ]
                ],
                ['reference' => 'chart'],
                ['reference' => 'chemistry'],
                [
                    'reference' => 'other',
                    'template_references' => [
                        '8ff43fa9-804b-42e7-9b3e-3cdd7e3a882b',
                    ]
                ],
            ]
        ]
    ];


    /**
     * Learnosity API instance is the same for everything afaict
     *
     * @var Api
     */
    private Api $api;


    /**
     * Controller constructor.
     * @param Factory $urlFactory
     * @param string $learnosityConsumerKey
     * @param string $learnosityConsumerSecret
     * @param ErrorMapper $errorMapper
     * @param AssessmentMapperProxy $assessmentDataSource
     * @param Mapper $resultCacheMapper
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     * @param \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper
     * @param Feedback\Mapper $feedbackMapper
     * @param UserMapper $userMapper
     * @throws \DomainException
     */
    public function __construct(
        \Schoolbox\Url\Factory $urlFactory,
        #[\SensitiveParameter] private string $learnosityConsumerKey,
        #[\SensitiveParameter] private string $learnosityConsumerSecret,
        private \Schoolbox\Error\Mapper $errorMapper,
        private AssessmentMapperProxy $assessmentDataSource,
        private \Schoolbox\Learning\Assessment\Project\ResultCache\Mapper $resultCacheMapper,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
        private \Schoolbox\Learning\Learnosity\Feedback\Mapper $feedbackMapper,
        private \Schoolbox\UserMapper $userMapper
    ) {
        /* Probably inject this one day, but for now,
         * I want to avoid dumping Yet Another Thing into the kernel
         * I don't have a convenient means to access SERVER_NAME except like this, sadly
         */
        $this->api = new Api(
            $urlFactory->buildFullyQualified()->getHost(),
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
     * @throws Error
     * @throws ValidationException
     * @throws \DomainException Thrown when site_school_code is empty string
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getContent(IAssessable $assessment): void
    {
        /* Tags for presetting without requiring the additional
         * authorApp.saveActivity() upon spawn. "create" (such as
         * in "tags_on_create") is when you first hit the Save button,
         * not when authorApp initialises for the first time
         */
        $tagFactory = new TagFactory($this->getConfigLoader()->get('site_school_code'));

        if ('' === (string)$assessment->getLearnosityReference()) {
            throw new \RuntimeException(sprintf(
                'Assessment %s is not in learnosity; try importing it with the cli tool',
                $assessment->getId()
            ));
        }

        $tags = $tagFactory->produceFromAssessment($assessment);

        /* The plan that we're currently on
         */
        $sitePlan = strtolower($this->getConfigLoader()->get('site_plan'));
        $questionGroups = self::LEARNOSITY_QUESTION_GROUP_PLANS[$sitePlan] ?? self::LEARNOSITY_QUESTION_GROUP_PLANS['default'];

        $payload = [
            'config' => [
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
                    ],
                    /* Hide the "Source" tab
                     */
                    'source' => false,
                    /* Tags are only spawned upon clicking Save, not before
                     */
                    'tags_on_create' => $tags
                ],
                'dependencies' => [
                    'question_editor_api' => [
                        'init_options' => $questionGroups
                    ]
                ],
                /* Editing a particular item
                 */
                'item_edit' => [
                    'item' => [
                        'title' => [
                            'show' => true
                        ]
                    ],
                    /* Tags are only spawned upon clicking Save, not before
                     */
                    'tags_on_create' => $tags
                ],
                /* Show a list of Items, which you can see by clicking Find Items
                 */
                'item_list' => [
                    'filter' => [
                        'restricted' => [
                            'tags' => [
                                /* Show all content belonging to your school, for now, I guess
                                 * TODO What is the limit of scope for Find Item like this?
                                 */
                                'all' => [
                                    $tagFactory->produceForSchoolCode()
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'mode' => 'activity_edit',
            /* A Learnosity uuid that we arbitrarily prescribed
             */
            'reference' => $assessment->getLearnosityReference(),
            /* Corresponds to a user in the Learnosity Console who will be
             * considered "the author" of stuff created by the school's teachers
             * Misconfiguring this leads to Error 10000 which has no definition
             */
            'user' => [
                'id' => (string)$this->getUser()->getId(),
                /* These fields are superfluous and are presumably used for auditing
                 * (we don't have a screen for this, it's in Learnosity somewhere else)
                 */
                'firstname' => 'Schoolbox',
                'lastname'  => 'Schoolbox',
                'email'     => 'schoolbox@schoolbox.com.au'
            ]
        ];

        $this->response = $this->responseFromTemplate('@learnosity/content.twig', [
            'assessment'  => $assessment,
            'initOptions' => $this->api->initOptions('author', $payload),
            'tags' => json_encode($tags, JSON_THROW_ON_ERROR),
            'returnUrl'   => Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL) ?? ''),
        ], sprintf(
            'Modify contents of %s',
            $assessment->getTitle()
        ));
    }


    /**
     * Let a student do an attempt in a Learnosity quiz
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \Alaress\Database\Exception
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
            'activity_id' => (string)$assessment->getId(),
            /* The quiz and its questions
             */
            'activity_template_id' => $quiz->getLearnosityReference(),
            "config" => [
                "configuration" => [
                    "idle_timeout" => [
                        "interval" => self::LEARNOSITY_ATTEMPT_IDLE_TIMEOUT
                    ],
                    'onsubmit_redirect_url' => false,
                    /* You were idle too long. Go back to the intro if Learnosity
                     * saves your session ahd kicks you out, I guess? At least it
                     * can prompt you to Resume Quiz from there.
                     */
                    'onsave_redirect_url' => $this->router->getGenerator()->generate('learningAssessment.getSummary', [
                        'assessmentId' => $assessment->getId(),
                        'studentId' => $student->getId(),
                    ]),
                    /* Reading Time
                     */
                    'reading_mode' => [
                        'reading_time' => $quiz->getReadingTimeDuration()
                    ],
                ],
                "navigation" => [
                    "auto_save" => [
                        "save_interval_duration" => self::LEARNOSITY_SAVE_INTERVAL
                    ],
                    "show_intro" => false,
                    "show_outro" => false
                ],
                /* https://help.learnosity.com/hc/en-us/articles/360000758337-Customizing-the-Assessment-Player-experience-with-Regions
                 *
                 * Perhaps more useful: https://reference.learnosity.com/items-api/initialization/config.regions
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
                /* Allowed Time
                 * Force hard time limits, kicking you out at the expiry
                 */
                'time' => [
                    'max_time' => $quiz->getDuration(),
                    'limit_type' => 'hard',
                    'warning_time' => self::LEARNOSITY_WARNING_TIME
                ],
                /* This is our Quiz title, I guess
                 */
                "title" => $assessment->getTitle(),
                "subtitle" => ''
            ],
            /* Enable livestreaming lol
             */
            'events' => false,
            /* This is our Quiz title, I guess
             */
            'name' => $assessment->getTitle(),
            'rendering_type' => 'assess',
            /* An "attempt identifier", I think
             */
            'session_id' => $attempt->getLearnosityReference(),
            /* This saves attempt results into Learnosity servers, enabling reporting etc.
             * You can use 'local_practice' to preview a quiz.
             * You can use 'feedback' to grade an attempt with feedback etc.
             */
            'type' => 'submit_practice',
            /* A uniquely-identifying string for us, I think
             */
            'user_id' => (string)$this->getUser()->getId(),
        ];

        /* Prompts And Feedback mode settings
         */
        if (!$quiz->getPromptsMode()) {
            /* Hide stuff that might normally appear client-side
             */
            $payload['config']['ignore_question_attributes'] = [
                'instant_feedback',
                'metadata.sample_answer',
                'validation'
            ];
        }

        /* Shuffle Items
         * Use the current microtime() as a seed so everyone gets a random ordering
         * https://reference.learnosity.com/items-api/initialization/config.configuration.shuffle_items
         */
        if ($quiz->getShuffleItems()) {
            $payload['config']['configuration']['shuffle_items'] = sprintf(
                '%s-%s',
                microtime(true),
                random_int(0, 1000000)
            );
        }

        $this->response = $this->responseFromTemplate('@learnosity/attempt.twig', [
            'initOptions' => $this->api->initOptions('items', $payload),
            'assessment' => $assessment,
            'attemptId' => $attempt->getId(),
            /* Save results locally
             */
            'completionUrl' => $this->router->getGenerator()->generate('learnosity.postCompleteAttempt', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
            ]),
            /* Redirect to here when we are ready
             */
            'outroUrl' => $this->router->getGenerator()->generate('learnosity.getCompleteAttempt', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
                'learnositySessionReference' => $attempt->getLearnosityReference()
            ]),
            'learnositySessionReference' => $attempt->getLearnosityReference(),
        ], "Attempt {$assessment->getTitle()}");
    }


    /**
     * Complete an attempt by setting a finish date.
     *
     * Uses the (learnosityReference, attemptId) tuple so you can't
     * randomly shut down some other dude's attempt; knowing their
     * learnosityReference should not be easily guessed
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws Deleted
     * @throws NotFound
     * @throws ValidationException
     * @throws \JsonException
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

        /* Arbitrarily max out $mark if it somehow exceeds the maximum limit
         * ($mark must never exceed $maxMark) and/or otherwise sanitise data
         */
        $sanitisedScore = (new SanitiseScores())->sanitise($result['data'][0]['score'], $result['data'][0]['max_score']);

        $mark = $sanitisedScore['score'];
        $maxMark = $sanitisedScore['max_score'];

        /* Record it locally for our own usages
         */
        $existingAttempt = $this->attemptMapper->findOneByColumnList([
            'attempt_id'           => $this->requestPost->get('attemptId'),
            'learnosity_reference' => $this->requestPost->get('learnosityReference'),
            'user_id'              => $student->getId()
        ]);

        $existingAttempt->setMark($mark)
            ->setMaxMark($maxMark)
            ->refreshNormalisedMark();

        /* The finish time might already have been set if their timer elapsed;
         * If it did, just use that datetime. Otherwise, you'll be recording
         * the datetime of when the user clicked past the modal at the end of
         * every attempt, which can record a later datetime than intended.
         */
        if (!$existingAttempt->getFinish()) {
            $existingAttempt->setFinish(\Alaress_Date_Format::now());
        }

        $this->attemptMapper->save($existingAttempt);
    }


    /**
     * The outro screen for completing an attempt;
     * show some basic stats about your attempt
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws Error
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
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws Error
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
     * @param User $student
     * @param Model $attempt
     * @throws Error
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
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \JsonException
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

        /* Instead of asking learnosity how it tallied everything, we can
         * just...do it ourselves, I guess, and sort-of delete this problem
         * We knew the max_mark from the attempt table, so it could potentially
         * desync from what Learnosity knows.
         *
         * Arbitrarily max out $mark if it somehow exceeds the maximum limit
         * ($mark must never exceed $maxMark) and/or otherwise sanitise data
         */
        $sanitisedScore = (new SanitiseScores())->sanitise(array_sum(array_values($postData['scores'])), $attempt->getMaxMark());

        $mark = $sanitisedScore['score'];
        $maxMark = $sanitisedScore['max_score'];

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

            /* If $updatedResponse['max_score'] is null...I guess this is unscored/practice
             * Learnosity fails with http 400 if you try to score an unscorable question
             */
            if (null === $updatedResponse['max_score']) {
                continue;
            }

            /* Arbitrarily max out $mark if it somehow exceeds the maximum limit
             * ($mark must never exceed $maxMark) and/or otherwise sanitise data
             */
            $sanitisedScore = (new SanitiseScores())->sanitise($postData['scores'][$responseId], $updatedResponse['max_score']);

            $mark = $sanitisedScore['score'];
            $maxMark = $sanitisedScore['max_score'];

            /* If the new score is also null, skip it
             *
             * This would happen if frontend validation failed and allowed users
             * to pass a value that equated to null, such as the string "cat"
             * Consult unit tests for other reasons
             */
            if (null === $maxMark) {
                continue;
            }

            /* Get ready to send it back to Learnosity land
             */
            $updatedResponse['score'] = $mark;
            $updatedResponse['max_score'] = $maxMark;

            /* "When setting a non-zero score, the response must also be attempted."
             *    -- https://reference.learnosity.com/data-api/endpoints/sessions_endpoints#updateResponseScores
             *
             * This includes responses that cannot be automagically calculated
             */
            $updatedResponse['attempted'] = true;

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
        if (\count($responseData)) {
            $this->api->dataUpdateRequest('/sessions/responses/scores', [
                'sessions' => [
                    [
                        'session_id' => $attempt->getLearnosityReference(),
                        'user_id' => (string)$attempt->getUser()->getId(),
                        'responses' => $responseData
                    ]
                ]
            ]);
        }

        /* Get the new result back from learnosity
         *
         * FIXME Add Polling Mechanism
         *  This occurs too quickly. We refetch the session score before
         *  Learnosity had a chance to finish updating it, so we always get
         *  the old value back.
         *
            $result = $this->api->dataGetRequest('/sessions/scores', [
                'session_id' => [
                    $attempt->getLearnosityReference()
                ]
            ]);
         */

        /* Redirect back to summary screen
         */
        $redirect = $this->router->getGenerator()->generate('learningAssessment.getSummary', [
            'assessmentId' => $assessment->getId(),
        ]);

        $this->response = $this->redirectFound($redirect);
    }


    /**
     * Start horizontal marking for a given quiz
     *
     * This is grossly inefficient but there aren't many options available to us
     *
     * @param IAssessable $assessment
     * @return void
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws InvalidColumnException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getHorizontalMarking(IAssessable $assessment): void
    {
        /* Get the quiz identifier
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* Get a list of attempts sorted by finish date
         * This gives us the most recent attempt for a quiz, if a student did multiple attempts
         *
         * Horizontal marking always users the most recent attempt.
         */
        $pb = $this->attemptMapper->getPredicateBuilder()
            ->equals('assess_id', $quiz->getId())
            ->notNull('learnosity_reference');

        $attempts = $this->attemptMapper->findByPredicates($pb, [
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
        $attemptComments = [];
        $urls = [];

        foreach ($attempts as $attempt) {
            $key = self::REPORT_HORIZONTAL_MARKING_PREFIX . $attempt->getUser()->getId();

            if (!isset($users[$key]) && $attempt->isFinished()) {
                $sessions[$attempt->getUser()->getId()] = $attempt->getLearnosityReference();

                /* Find all the comments for this attempt (need it later)
                 */
                $attemptComments[$attempt->getUser()->getId()] = [
                    'learnosityReference' => $attempt->getLearnosityReference(),
                    'studentId' => $attempt->getUser()->getId(),
                    'comment' => $attempt->getComment()
                ];

                /* ...because Learnosity wants it in this format
                 */
                $users[$key] = [
                    'id' => $attempt->getUser()->getId(),
                    'name' => $attempt->getUser()->getFullName(),
                    'firstname' => $attempt->getUser()->getFirstname(),
                    'lastname' => $attempt->getUser()->getLastname(),
                    'elementId' => $key
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
                            'id' => $key,
                            'type' => 'session-detail-by-item',
                            'session_id' => $attempt->getLearnosityReference(),
                            'questions_api_init_options' => [
                                'showCorrectAnswers' => true
                            ]
                        ]
                    ],
                ]);

                /* Pregenerate all the urls for each guy, because you can't manufacture them
                 * in javascript without risking desync with backend
                 */
                $args = [
                    'assessmentId' => $assessment->getId(),
                    'studentId' => $attempt->getUser()->getId(),
                    'learnositySessionReference' => $attempt->getLearnosityReference()
                ];

                $urls[$attempt->getUser()->getId()] = [
                    'studentId' => $attempt->getUser()->getId(),
                    'attemptComment' => $this->router->getGenerator()->generate('learnosity.postFeedbackAttemptComment', $args),
                    'comment' => $this->router->getGenerator()->generate('learnosity.postFeedbackComment', $args),
                    'score' => $this->router->getGenerator()->generate('learnosity.postFeedbackScore', $args),
                    'teacherFeedback' => $this->router->getGenerator()->generate('learnosity.getFeedback', $args),
                ];
            }
        }

        /* Sort alphabetically.
         * Apparently this has been copypasted a bunch of times all over
         * the place so I might as well add my two rupees to the pile
         */
        uasort($users, static function (array $a, array $b) {
            $last = strnatcasecmp($a['lastname'] ?? '', $b['lastname'] ?? '');
            if ($last === 0) {
                return strnatcasecmp($a['firstname'], $b['firstname']);
            }
            return $last;
        });

        /* Find a list of Learnosity Items (questions) available in this quiz
         * These are their references only
         */
        $itemReferences = $this->api->dataGetRequest('itembank/activities', [
            'references' => [
                $quiz->getLearnosityReference()
            ]
        ])['data'][0]['data']['items'] ?? null;

        /* $itemReferences is null when you try to horizontal mark a non-learnosity quiz.
         * This shouldn't happen except during dev, since our importer would have shanked
         * everything into learnosity's db
         */
        if (null !== $itemReferences) {
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
                /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
                 * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
                 */
                if (!is_array($itemReference)) {
                    $itemReference = [
                        'reference' => $itemReference,
                        'id' => $itemReference
                    ];
                }

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
        }

        /* Obtain their scores from Learnosity
         */
        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
            'session_id' => array_values(array_filter($sessions))
        ])['data'];

        /* Reforge it into a generic set of tuples
         * because we need to shank it in via frontend
         */
        $scores = [];

        foreach ($responses as $session) {
            foreach ($session['responses'] as $response) {
                $learnosityReference = $response['response_id'];

                $scores[$learnosityReference] = [
                    'learnosityReference' => $learnosityReference,
                    'score' => $response['score'],
                    'maxScore' => $response['max_score'],
                    'itemReference' => $response['item_reference']
                ];
            }
        }

        /* Find all the comments for these responses
         */
        $responseComments = $this->feedbackMapper->findByLearnosityReferences(array_keys($scores));

        /* New donny thing - combine, restructure and rename vars
         */
        $students = array_values($users);
        $itemResponses = array_values(array_merge_recursive($scores, $responseComments));
        $attemptComments = array_values($attemptComments);
        $urls = array_values($urls);

        $this->response = $this->responseFromTemplate('@learnosity/horizontal.twig', [
            'assessment' => $assessment,
            'reportPayloads' => json_encode($reportPayloads, JSON_THROW_ON_ERROR),
            'itemReferences' => $itemReferences,
            'students' => $students,
            'itemResponses' => $itemResponses,
            'attemptComments' => $attemptComments,
            'urls' => $urls
        ], sprintf(
            'Horizontal Marking for %s',
            $assessment->getTitle()
        ));
    }

    /**
     * Persists a new score back to Learnosity for it to re-tally
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws ValidationException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function postFeedbackScore(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* Fetch the response id first, because you need a bunch of stuff
         * The response id (learnosity response reference) is in POST
         */
        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
            'session_id' => [
                $attempt->getLearnosityReference()
            ]
        ])['data'][0]['responses'];

        /* Traverse to find the one you want to change, because you don't get
         * a response_id filter in this endponit...
         */
        $updatedResponse = null;

        foreach ($responses as $response) {
            if ($this->requestPost->get('learnosityReference') === $response['response_id']) {
                /* Update the returned payload with the new score
                 * This is really similar to the implementation in postFeedback()
                 */
                $updatedResponse = $response;
                $updatedResponse['score'] = $this->requestPost->get('score');

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
            }
        }

        /* TODO Couldn't find the response id requested;
         *  This shouldn't happen, but what do we do now?
         */
        if (null === $updatedResponse) {
            throw new \DomainException('Learnosity response identifier was not found.');
        }

        /* Use the learnosity Data API to send the revised payload which
         * should now contain all the parts Learnosity expected
         */
        $this->api->dataUpdateRequest('/sessions/responses/scores', [
            'sessions' => [
                [
                    'session_id' => $attempt->getLearnosityReference(),
                    'user_id' => (string)$attempt->getUser()->getId(),
                    'responses' => [
                        $updatedResponse
                    ]
                ]
            ]
        ]);
    }


    /**
     * Persists a new comment into quiz_feedback, associated with an attempt_id and a
     * Learnosity Reference (it's a response id, but there's no official name for this)
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postFeedbackComment(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* The response id (learnosity response reference) is in POST.
         *
         * For the given attempt, see if there's already a row in quiz_feedback
         * corresponding to the (attempt id, response id) tuple
         *
         * If there is, update it; otherwise create it
         */
        try {
            $feedback = $this->feedbackMapper->findOneByColumnList([
                'attempt_id' => $attempt->getId(),
                'learnosity_reference' => $this->requestPost->get('learnosityReference')
            ]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $feedback = $this->feedbackMapper->createModel()
                ->setAttempt($attempt)
                ->setLearnosityReference($this->requestPost->get('learnosityReference'));
        }

        /* Put the new/updated comment in
         */
        $feedback->setComment($this->requestPost->get('comment'));

        /* Save it
         */
        $this->feedbackMapper->save($feedback);
    }


    /**
     * Post a comment about a particular Attempt into assess_attempt.comment
     * Predominantly used in horizontal marking's final screen
     *
     * @param IAssessable $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postFeedbackAttemptComment(
        IAssessable $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* Put the new/updated comment in
         */
        $attempt->setComment($this->requestPost->get('comment'));

        /* Save it
         */
        $this->attemptMapper->save($attempt);
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
                    ],
                ]
            ],
            'user' => [
                'id' => (string)$this->getUser()->getId(),
                /* These fields are superfluous and are presumably used for auditing
                 * (we don't have a screen for this, it's in Learnosity somewhere else)
                 */
                'firstname' => 'Schoolbox',
                'lastname'  => 'Schoolbox',
                'email'     => 'schoolbox@schoolbox.com.au'
            ]
        ];

        $this->response = $this->responseFromTemplate('@learnosity/activityList.twig', [
            'initOptions' => $this->api->initOptions('author', $payload)
        ], 'Example Activity List');
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


    /**
     * Soft deletes all the attempt ids in $attemptIds
     *
     * @param IAssessable $assessment
     * @return void
     * @throws Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws DefinitionException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postDeleteAttempts(IAssessable $assessment): void
    {
        $attemptIds = $this->requestPost->get('attemptIds');

        /* Find the users associated with each attempt id;
         * we need it to update any project caches that exist
         */
        $attempts = $this->attemptMapper->findByColumn('attempt_id', $attemptIds);

        /* Delete them
         */
        $this->attemptMapper->deleteByColumn('attempt_id', $attemptIds);

        /* Update project result caches for each person who's going to be affected
         * by what we just did...
         */
        foreach ($attempts as $attempt) {
            /* Update project result cache
             */
            (new ResultCache\ResultCache($this->resultCacheMapper))->refresh($this->assessmentDataSource, $assessment, $attempt->getUser());
        }
    }


    /**
     * Soft deletes all unreviewed attempts for the given $assessment
     * and set of $userIds; fortunately this has no effect on project
     * caches since none of these attempts should have been contributing
     * any marks
     *
     * @param IAssessable $assessment
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \UnderflowException
     */
    public function postDeleteUnreviewedAttempts(IAssessable $assessment): void
    {
        $userIds = $this->requestPost->get('userIds');

        /* We need this lol for the predicate builder
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* Find all the unreviewed attempts that haven't already been deleted
         * for the set of $userIds provided
         */
        $pb = $this->attemptMapper->getPredicateBuilder()
            ->equals('assess_id', $quiz->getId())
            ->in('user_id', $userIds)
            ->isNull('deleted_at')
            ->isNull('reviewed_at');

        /* Delete them
         */
        $this->attemptMapper->deleteByPredicates($pb);
    }


    /**
     * Sets reviewed_at for all the attempt ids in $attemptIds
     *
     * @param IAssessable $assessment
     * @return void
     * @throws DefinitionException
     * @throws Deleted
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postMassReviewAttempts(IAssessable $assessment): void
    {
        $attemptIds = $this->requestPost->get('attemptIds');

        /* Find the users associated with each attempt id;
         * we need it to update any project caches that exist
         */
        $attempts = $this->attemptMapper->findByColumn('attempt_id', $attemptIds);

        /* You can't mass-save or mass-update via predicates, afaict?
         * Traverse individually
         *
         * Note that sometimes we'll skip processing attempts:
         * attempt authors might have been deleted/excluded via another tab via multiplayer
         * schoolbox, but it's not really apparent anymore why we cared enough to do this
         * only for this workflow and not others...or why we cared enough to do it at all?
         * Were we trying to service 0.01% of the edge cases or what?
         */
        foreach ($attempts as $attempt) {
            /* Author nonexistent? skip 'em
             */
            try {
                $user = $this->userMapper->findById($attempt->getUser()->getId());
            } catch (\Schoolbox\Mapper\Exception $e) {
                continue;
            }

            /* Author excluded? skip 'em
             * Shanked out of that random andyland ActionValidator class, because we need
             * to know all the participants - not just the quiz itself
             */
            $quiz ??= $this->assessmentDataSource->findByQuizId($attempt->getQuiz()->getId());

            if (!$quiz->isParticipant($attempt->getUser())) {
                continue;
            }

            /* Anyway, set the reviewer and timestamp as usual
             */
            $attempt->setReviewer($this->getUser())
                ->setReviewed(\Alaress_Date_Format::now());

            $this->attemptMapper->save($attempt);

            /* Update project result cache
             */
            (new ResultCache\ResultCache($this->resultCacheMapper))->refresh($this->assessmentDataSource, $assessment, $attempt->getUser());
        }
    }
}
