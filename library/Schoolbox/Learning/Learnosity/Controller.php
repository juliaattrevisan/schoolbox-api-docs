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
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use Http\Discovery\Psr17Factory;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Utils\Uuid;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Project\ResultCache\Mapper;
use Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizResponse;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Learnosity\Codec\UserIdentifier;
use Schoolbox\Learning\Learnosity\Feedback\SanitiseScores;
use Schoolbox\Learning\Quiz\ActionValidator;
use Schoolbox\Learning\Quiz\Attempt\Model;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\Access;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
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
     *
     * @param Factory $urlFactory
     * @param BreadcrumbFactory $bakery
     * @param string $learnosityVersion
     * @param string $learnosityConsumerKey
     * @param string $learnosityConsumerSecret
     * @param ErrorMapper $errorMapper
     * @param AssessmentMapperProxy $assessmentDataSource
     * @param Mapper $resultCacheMapper
     * @param \Schoolbox\Learning\Quiz\Mapper $quizMapper
     * @param \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper
     * @param Feedback\Mapper $feedbackMapper
     * @param UserMapper $userMapper
     * @param Psr17Factory $httpFactory for creating HTTP requests
     * @param MessageFactory $messageFactory for creating messages to send to taskd
     * @param Producer $messageProducer for sending messages to taskd
     * @throws \DomainException
     */
    public function __construct(
        \Schoolbox\Url\Factory $urlFactory,
        protected BreadcrumbFactory $bakery,
        private string $learnosityVersion,
        #[\SensitiveParameter] private string $learnosityConsumerKey,
        #[\SensitiveParameter] private string $learnosityConsumerSecret,
        private \Schoolbox\Error\Mapper $errorMapper,
        private AssessmentMapperProxy $assessmentDataSource,
        private \Schoolbox\Learning\Assessment\Project\ResultCache\Mapper $resultCacheMapper,
        private \Schoolbox\Learning\Quiz\Mapper $quizMapper,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
        private \Schoolbox\Learning\Learnosity\Feedback\Mapper $feedbackMapper,
        private \Schoolbox\UserMapper $userMapper,
        private Psr17Factory $httpFactory,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
    ) {
        /* Probably inject this one day, but for now,
         * I want to avoid dumping Yet Another Thing into the kernel
         * I don't have a convenient means to access SERVER_NAME except like this, sadly
         */
        $this->api = new Api(
            $urlFactory->buildFullyQualified()->getHost(),
            $this->learnosityVersion,
            $this->learnosityConsumerKey,
            $this->learnosityConsumerSecret,
            new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity')
        );
    }


    /**
     * Edit the contents of $assessment using the Learnosity system
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws Error
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \DomainException Thrown when site_school_code is empty string
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getContent(QuizDomainObject $assessment): void
    {
        $assessment->assertUsable();

        /* Tags for presetting without requiring the additional
         * authorApp.saveActivity() upon spawn. "create" (such as
         * in "tags_on_create") is when you first hit the Save button,
         * not when authorApp initialises for the first time
         */
        $tagFactory = new TagFactory($this->getConfigLoader()->get('site_school_code'));

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
                    'tags_on_create' => $tags,
                    /* Set the default player template to match our own template
                     * NOTE: Updating this will not update existing quizzes, only new ones...
                     */
                    'enabled_player_templates' => [
                        'schoolbox'
                    ],
                    'default_player_template' => 'schoolbox',
                    'player_templates' => [
                        [
                            'reference' => 'schoolbox',
                            'name' => 'Schoolbox',
                            'regions' => Helper::LEARNOSITY_CONFIG_PLAYER_REGIONS,
                            "navigation" => [
                                "show_intro" => false,
                                "show_outro" => false
                            ],
                        ]
                    ]
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
                'id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $this->getUser()->getId()),
                /* These fields are superfluous and are presumably used for auditing
                 * (we don't have a screen for this, it's in Learnosity somewhere else)
                 */
                'firstname' => 'Schoolbox',
                'lastname'  => 'Schoolbox',
                'email'     => 'schoolbox@schoolbox.com.au'
            ]
        ];

        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())
            ->add([
                $assessment->getTitle(),
                $assessment->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) : null
            ])
            ->add(['Modify Contents', null]);

        $this->response = $this->responseFromTemplate('@learnosity/content.twig', [
            'assessment'  => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'initOptions' => $this->api->initOptions('author', $payload),
            'tags' => json_encode($tags, JSON_THROW_ON_ERROR),
            'returnUrl'   => Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL) ?? ''),
        ], sprintf(
            'Modify Contents of %s',
            $assessment->getTitle()
        ));
    }


    /**
     * Let a student do an attempt in a Learnosity quiz
     *
     * @param QuizDomainObject $assessment
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
    public function getAttempt(QuizDomainObject $assessment, \Schoolbox\User $student): void
    {
        $assessment->assertUsable();

        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        // Note on need for consistency when dealing with permissions
        // ----------------------------------------------------------
        // It is important that the permission checks on the quiz page itself (here in this function) are the same as
        // those that determine whether the 'start quiz' button is displayed (on the quiz summary page).  Otherwise,
        // either an enabled 'start quiz' button will lead to a permission error on the quiz page, or directly accessing
        // the quiz page will enable users to bypass permission checks that prevent the 'start quiz' button from displaying.
        // See library/Schoolbox/Learning/Assessment/Quiz/Controller.php (where the same permission checks are used).
        $now = Alaress_Date_Format::now();
        $this->quizMapper->closeExpiredAttempts($quiz, $now); // Close any expired attempts before checking for in-progress.
        $actionValidator = new ActionValidator($now, Access::getInstance());
        $canAttempt = (
            $this->getUser()->getId() === $student->getId() && // A user may not attempt a quiz on behalf of another user.
            $actionValidator->canAttemptQuiz($this->getUser(), $assessment, $this->getConfigLoader())
        );

        if (!$canAttempt) {
            /* Redirect back to summary screen
             */
            $redirect = $this->router->getGenerator()->generate('learningAssessment.getSummary', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
            ]);

            $this->response = $this->redirectTemporary($redirect);
            return;
        }

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
                     * saves your session and kicks you out, I guess? At least it
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
                'regions' => Helper::LEARNOSITY_CONFIG_PLAYER_REGIONS,
                /* Allowed Time
                 * Force hard time limits, kicking you out at the expiry
                 */
                'time' => [
                    'max_time' => $quiz->getDuration(),
                    'limit_type' => 'hard',
                    'warning_time' => self::LEARNOSITY_WARNING_TIME,
                    "start_time" => [
                        /* The "soft" start time type tells Learnosity to store the start time on their servers
                         * and use that to calculate the time, this prevents a user from reloading the
                         * page and causing their timer to be inaccurate or reset
                         */
                        'type' => 'soft',
                        /* Note: Whilst looking into this, I also experimented with the "hard" type which requires a "value" prop.
                        * Learnosity wants this value given in UTC with an unusual format, which can be achieved with "Ymd-His"
                        * The Learnosity docs do not include seconds as a valid attribute, however, I found that this both works and is
                        * requried to ensure that the start time is not off by a few seconds when starting the quiz
                        */
                    ]
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
            'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $this->getUser()->getId()),
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

        /* Breadcrumbs
         * Legacy quiz never had breadcrumbs for attempt screen; I can only presume
         * we meant to minimise the ways students could get out of a quiz. Given that
         * we have a whole slew of buttons at the top, I don't see how omitting the
         * breadcrumbs reduces that risk.
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle()]);

        $returnUrlEncoded = $this->requestQuery->get(Redirect::RETURN_URL);

        $this->response = $this->responseFromTemplate('@learnosity/attempt.twig', [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'initOptions' => $this->api->initOptions('items', $payload),
            'attemptId' => $attempt->getId(),
            /* Save results locally
             */
            'completionUrl' => $this->router->getGenerator()->generate('learnosity.postCompleteAttempt', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
            ]),
            /* Redirect to here when we are ready
             */
            'outroUrl' => (
                $this->router->getGenerator()->generate('learnosity.getCompleteAttempt', [
                    'assessmentId' => $assessment->getId(),
                    'studentId' => $student->getId(),
                    'learnositySessionReference' => $attempt->getLearnosityReference()
                ]) . ($returnUrlEncoded === null ? '' : '/?' . Redirect::RETURN_URL . "=$returnUrlEncoded")
            ),
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
     * @param QuizDomainObject $assessment
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
    public function postCompleteAttempt(QuizDomainObject $assessment, \Schoolbox\User $student): void
    {
        /* Find out what they scored by the automated system
         */
        $result = $this->api->dataGetRequest('/sessions/scores', [
            'session_id' => [
                $this->requestPost->get('learnosityReference')
            ]
        ]);

        /* The learnosityReference posted here doesn't exist
         * TODO What kind of error should we present to users? It's asynchronous
         */
        if (!\count($result['data'])) {
            throw new \UnexpectedValueException('Empty result payload for attempt.');
        }

        /* Find out if this attempt was in Incomplete status
         * Users don't always officially conclude their attempts
         */
//        if (Helper::LEARNOSITY_ATTEMPT_STATUS_INCOMPLETE === $result['data'][0]['status']) {
//            /* FIXME Need to load their Session, then complete it on their behalf,
//             *  then reload their $result like we originally tried...but how?
//             *  There's no endpoint to update a Session's status...
//             */
//        }

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

        /* Fabricate an IResponse instance for existing mark scheme
         *
         * Note that $existingResponse might well be null for broken data somehow,
         * but we have no means to recover from that...and it shouldn't really
         * occur naturally
         */
        $existingResponse = $this->extractExistingResponse($assessment, $student, $existingAttempt);

        /* TODO What kind of error should we present to users? It's asynchronous
         */
        if (null === $existingResponse) {
            return;
        }

        $this->assignMarksToAttempt($existingAttempt, $existingResponse, $assessment, $mark, $maxMark);

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
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws Error
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getCompleteAttempt(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        $payload = [
            'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
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

        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())
            ->add(
                [
                    $assessment->getTitle(),
                    $this->routeGenerator->generate(
                        'learningAssessment.getSummary',
                        [
                            'assessmentId' => $assessment->getId(),
                            'studentId' => $this->getUser()->getId()
                        ]
                    )
                ]
            )
            ->add(['Result Summary']);

        $returnUrlEncoded = $this->requestQuery->get(Redirect::RETURN_URL);

        $this->response = $this->responseFromTemplate('@learnosity/outro.twig', [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'initOptions' => $this->api->initOptions('reports', $payload),
            'attempt' => $attempt,
            'returnUrl' => (
                empty($returnUrlEncoded) ?
                "/homepage/{$assessment->getBaseContext()->getFolder()->getId()}" :
                Redirect::decode($returnUrlEncoded)
            ),
        ], "Result for {$assessment->getTitle()}");
    }

    /**
     * The student who did the attempt
     * @param Model $attempt
     * @return array
     */
    private function getStudentData(\Schoolbox\Learning\Quiz\Attempt\Model $attempt)
    {
        return [
            'id' => $attempt->getUser()->getId(),
            'name' => $attempt->getUser()->getFullName(),
            'firstname' => $attempt->getUser()->getFirstname(),
            'lastname' => $attempt->getUser()->getLastname(),
            'elementId' => 'schoolbox-student'
        ];
    }

    /**
     * The student who did the attempt
     * @param Model $attempt
     * @return array
     */
    private function getItemResponses(\Schoolbox\Learning\Quiz\Attempt\Model $attempt)
    {
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
                'learnosityReference' => $learnosityReference,
                'score' => $response['score'],
                'maxScore' => $response['max_score'],
                'itemReference' => $response['item_reference']
            ];
        }

        /* Find all the comments for this attempt
         */
        $responseComments = $this->feedbackMapper->findByLearnosityReferences(array_keys($scores));

        return array_values(array_merge_recursive($scores, $responseComments));
    }

    /**
     * Retrieve the comment for this attempt
     * @param Model $attempt
     * @return array
     */
    private function getAttemptComment(\Schoolbox\Learning\Quiz\Attempt\Model $attempt)
    {
        return  [
            'learnosityReference' => $attempt->getLearnosityReference(),
            'studentId' => $attempt->getUser()->getId(),
            'comment' => $attempt->getComment()
        ];
    }

    /**
     * From a student's perspective,
     * show some in-depth stats about your attempt
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws Error
     * @throws ValidationException
     * @throws \JsonException
     */
    public function getReview(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* Common elements to both code paths
         * Completion summary always displays
         */
        $reportPayload = [
            'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
            'reports' => []
        ];

        /* If a teacher reviewed the attempt, show their feedback information
         * Otherwise just show what the student did.
         */
        $reportPayload['reports'][] = [
            /* DOM element id, where this goes in
             *
             * It's configurable by us, unlike the other anchors for
             * Author and Items api
             */
            'id' => 'schoolbox-student',
            'type' => 'session-detail-by-item',
            'session_id' => $attempt->getLearnosityReference(),
            'questions_api_init_options' => [
                'showCorrectAnswers' => $attempt->getReviewed() !== null ? true : false,
            ]
        ];

        /* Conditional reports
         *     - If an attempt is finished, provide the completion summary (unsure how people get here, but ok)
         */
        if ($attempt->isFinished()) {
            $reportPayload['reports'][] = [
                /* DOM element id, where this goes in
                 *
                 * It's configurable by us, unlike the other anchors for
                 * Author and Items api
                 */
                'id' => 'schoolbox-completion-summary',
                'type' => 'sessions-summary-by-question',
                'session_ids' => [
                    $attempt->getLearnosityReference()
                ]
            ];
        }

        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser());

        /* Assessment summary/status node
         * This depends on the current user's permissions/role to figure out where you should go
         * Note that Specifications don't get status/summary screens; idk how you'd get there
         * from an attempt review screen that couldn't possibly exist, but ok
         */
        if ($this->getUser()->getRole()->isStaff()) {
            /* Not that you should ever get here because staff won't get a link
             * to view their own attempt, but ok
             */
            $assessmentNode = [
                $assessment->getTitle(),
                $assessment->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) : null
            ];
        } else {
            $assessmentNode = [
                $assessment->getTitle(),
                $assessment->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId(), 'studentId' => $this->getUser()->getId()]) : null
            ];
        }

        $breadcrumbs->add($assessmentNode)
            ->add([
                'Review', null
            ]);

        $this->response = $this->responseFromTemplate('@learnosity/review.twig', [
            'student' => $this->getStudentData($attempt),
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'reportInitOptions' => $this->api->initOptions('reports', $reportPayload),
            'attempt' => $attempt,
            'itemResponses' => $this->getItemResponses($attempt),
            'attemptComment' => $this->getAttemptComment($attempt),
            'returnUrl' => "/homepage/{$assessment->getContext()->getId()}"
        ], "Result for {$assessment->getTitle()}");
    }

    /**
     * Provide feedback for an attempt
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @throws Error
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getFeedback(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {

        /* This is what the student did
         */
        $reportPayload = [
            'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
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
                        'showCorrectAnswers' => true
                    ]
                ]
            ],
        ];

        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())->add(
            [
                $assessment->getTitle(),
                $assessment->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) : null

            ]
        )
            ->add(['Feedback']);

        /* Pregenerate all the urls for the user, because you can't manufacture them
        * in javascript without risking desync with backend
        */
        $args = [
            'assessmentId' => $assessment->getId(),
            'studentId' => $attempt->getUser()->getId(),
            'learnositySessionReference' => $attempt->getLearnosityReference()
        ];

        $urls = [
            'studentId' => $attempt->getUser()->getId(),
            'attemptComment' => $this->router->getGenerator()->generate('learnosity.postFeedbackAttemptComment', $args),
            'comment' => $this->router->getGenerator()->generate('learnosity.postFeedbackComment', $args),
            'score' => $this->router->getGenerator()->generate('learnosity.postFeedbackScore', $args),
            'teacherFeedback' => $this->router->getGenerator()->generate('learnosity.getFeedback', $args),
        ];

        $this->response = $this->responseFromTemplate('@learnosity/feedback.twig', [
            'student' => $this->getStudentData($attempt),
            'reportInitOptions' => $this->api->initOptions('reports', $reportPayload),
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'attempt' => $attempt,
            'itemResponses' => $this->getItemResponses($attempt),
            'attemptComment' => $this->getAttemptComment($attempt),
            'urls' => $urls
        ], sprintf(
            'Feedback for %s in %s',
            $student->getFullName(),
            $assessment->getTitle()
        ));
    }


    /**
     * Tell Learnosity about what our teachers marked each question
     *
     * @param QuizDomainObject $assessment
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
        QuizDomainObject $assessment,
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
         * FIXME Add Polling Mechanism
         *  This would otherwise have been processed by the polling thing
         *
         * Arbitrarily max out $mark if it somehow exceeds the maximum limit
         * ($mark must never exceed $maxMark) and/or otherwise sanitise data
         */
        $sanitisedScore = (new SanitiseScores())->sanitise(array_sum(array_values($postData['scores'])), $attempt->getMaxMark());

        $mark = $sanitisedScore['score'];
        $maxMark = $sanitisedScore['max_score'];

        // As with posting a feedback:
        // need the existing response to determine a human-readable mark.
        $existingResponse = $this->extractExistingResponse($assessment, $student, $attempt);

        // TODO as with posting a feedback:
        //      what kind of error should we present to users? It's asynchronous
        if (!$existingResponse) {
            return;
        }
        $this->assignMarksToAttempt($attempt, $existingResponse, $assessment, $mark, $maxMark);

        /* Save the comment and new normalised mark
         */
        $attempt->setComment(trim($postData['attempt-comment']))
            ->setReviewer($this->getUser())
            ->setReviewed(\Alaress_Date_Format::now());

        $this->attemptMapper->save($attempt);

        /* For every learnosity question, see if we have a db record for its feedback
         * If we do, recycle it and update its contents
         * Otherwise spawn a new one
         *
         * TODO make this algorithm cheaper
         */
        foreach ($postData['comment'] as $learnosityReference => $comment) {
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
            $itemReference = $response['item_reference'];

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
            $sanitisedScore = (new SanitiseScores())->sanitise($postData['scores'][$itemReference], $updatedResponse['max_score']);

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
                        'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
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
            'studentId' => $attempt->getUser()->getId(),
        ]);

        $this->response = $this->redirectFound($redirect);
    }


    /**
     * Start horizontal marking for a given quiz
     *
     * This is grossly inefficient but there aren't many options available to us
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws InvalidColumnException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getHorizontalMarking(QuizDomainObject $assessment): void
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
                    'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
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
        $learnositySessions = array_values(array_filter($sessions));

        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
            'session_id' => $learnositySessions
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

        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())
            ->add([
                $assessment->getTitle(),
                $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()])
            ])
            ->add(['Horizontal Marking']);

        $this->response = $this->responseFromTemplate('@learnosity/horizontal.twig', [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'reportPayloads' => json_encode($reportPayloads, JSON_THROW_ON_ERROR),
            'itemReferences' => $itemReferences,
            'students' => $students,
            'itemResponses' => $itemResponses,
            'attemptComments' => $attemptComments,
            'urls' => $urls,
            /* This list of learnosity references is used for the finalisation step
             */
            'sessions' => json_encode($learnositySessions, JSON_THROW_ON_ERROR)
        ], sprintf(
            'Horizontal Marking for %s',
            $assessment->getTitle()
        ));
    }


    /**
     * Finalise the list of attempts whose learnosity_references match those
     * sent in the POST array
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postHorizontalMarking(QuizDomainObject $assessment): void
    {
        $refs = $this->requestPost->get('learnosityReferences');
        $attempts = $this->attemptMapper->findByColumn('learnosity_reference', $refs);

        // Get results from Learnosity, for writing to Schoolbox attempt
        $results = $this->api->dataGetRequest(
            '/sessions/scores',
            ['session_id' => $refs],
        );
        $groupedResults = [];
        foreach ($results['data'] as $result) {
            $groupedResults[$result['session_id']] = $result;
        }

        foreach ($attempts as $attempt) {
            // Assign marks to attempt based on results from Learnosity
            $existingResponse = $this->extractExistingResponse($assessment, $attempt->getUser(), $attempt);
            if ($existingResponse) {
                $result = $groupedResults[$attempt->getLearnosityReference()] ?? null;
                if ($result) {
                    $this->assignMarksToAttempt(
                        $attempt,
                        $existingResponse,
                        $assessment,
                        $result['score'],
                        $result['max_score'],
                    );
                }
            }

            $attempt->setReviewer($this->getUser())
                ->setReviewed(\Alaress_Date_Format::now());

            $this->attemptMapper->save($attempt);
        }
    }


    /**
     * Persists a new score back to Learnosity for it to re-tally
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws ValidationException
     * @throws \DomainException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function postFeedbackScore(
        QuizDomainObject $assessment,
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
                    'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
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
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postFeedbackComment(
        QuizDomainObject $assessment,
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
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postFeedbackAttemptComment(
        QuizDomainObject $assessment,
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
                'id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $this->getUser()->getId()),
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
     * Callback for copying a quiz's contents from one quiz to another.
     *
     * This callback will in turn create another callback for polling the quiz's
     * status, setting it to complete or failed appropriately.
     *
     * @api   POST /learning/learnosity/{sourceId}/copy/{destinationId}
     * @since 23.1
     *
     * @param QuizDomainObject $source
     * @param QuizDomainObject $destination
     * @return void
     * @throws ValidationException
     */
    public function postCopyQuizConstituents(QuizDomainObject $source, QuizDomainObject $destination): void
    {
        $jobId = $this->quizMapper->copyQuizConstituents($source, $destination);
        $this->response = $this->responseAsJson(['jobId' => $jobId]);

        // Start polling status of duplication
        $baseUrl = (new Factory($this->getConfigLoader()))->buildFullyQualified();
        $message = $this->messageFactory
            ->setUser($this->getUser())
            ->setRequest(
                $this->httpFactory
                    ->createRequest(
                        'POST',
                        $baseUrl->withPath(
                            $this->routeGenerator->generate(
                                'learnosity.postPollQuizStatus',
                                ['assessmentId' => $destination->getId()]
                            )
                        )
                    )
                    ->withHeader('accept', 'application/json')
            )
            ->build();
        $this->messageProducer->send($message, delay: 5, maxRequeues: 10);
    }

    /**
     * Callback for polling the status of a Learnosity quiz duplication.
     *
     * This will also update the status of the assessment: hence this is a POST
     * request.
     *
     * @api   POST /learning/learnosity/{assessmentId}/poll
     * @since 23.1
     *
     * @param QuizDomainObject $assessment
     * @return void
     */
    public function postPollQuizStatus(QuizDomainObject $assessment): void
    {
        // Not a quiz? error
        // No job ID? error
        // Job already complete: return 200

        // Otherwise check job:
        // pending: return status 202
        // failed: set and return status 4xx
        // complete: set and return status 200

        // Not a quiz, or no Learnosity job reference? error
        $quiz = $this->getQuizForAssessmentId($assessment->getId());
        if (!$quiz->getLearnosityJobReference()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        // Already complete or failed: don't bother re-checking
        $jobStatus = $quiz->getLearnosityJobStatus();
        if ($jobStatus === Quiz::LEARNOSITY_JOB_STATUS_PROCESSING) {
            // Check the API for this job's status
            $learnosityJobResponse = $this->api->dataGetRequest(
                '/jobs',
                ["references" => [$quiz->getLearnosityJobReference()],],
            );

            // Possible statuses: queued, running, halted, completed
            // https://reference.learnosity.com/data-api/endpoints/jobs_endpoints
            $learnosityJobStatus = $learnosityJobResponse['data'][0]['status'] ?? null;
            if ($learnosityJobStatus === Api::JOB_STATUS_COMPLETED) {
                $jobStatus = Quiz::LEARNOSITY_JOB_STATUS_COMPLETED;
                $quiz->setLearnosityJobStatus($jobStatus);
                $this->quizMapper->save($quiz);
            } elseif ($learnosityJobStatus === Api::JOB_STATUS_HALTED) {
                $jobStatus = Quiz::LEARNOSITY_JOB_STATUS_FAILED;
                $quiz->setLearnosityJobStatus($jobStatus);
                $this->quizMapper->save($quiz);
            }
        }


        // What's in the response: just a "status" callback,
        // possibly a API problem?
        $jobStatus    = $quiz->getLearnosityJobStatus();
        $responseCode = match ($jobStatus) {
            Quiz::LEARNOSITY_JOB_STATUS_COMPLETED => 200,
            Quiz::LEARNOSITY_JOB_STATUS_PENDING, Quiz::LEARNOSITY_JOB_STATUS_PROCESSING => 202,
                // including failure
            default => 400,
        };
        $this->response = $this
            ->responseAsJson(['jobStatus' => $jobStatus])
            ->withStatus($responseCode);
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
     * @param QuizDomainObject $assessment
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
    public function postDeleteAttempts(QuizDomainObject $assessment): void
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
     * @param QuizDomainObject $assessment
     * @return void
     * @throws Exception
     * @throws InvalidColumnException
     * @throws \UnderflowException
     */
    public function postDeleteUnreviewedAttempts(QuizDomainObject $assessment): void
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
     * @param QuizDomainObject $assessment
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
    public function postMassReviewAttempts(QuizDomainObject $assessment): void
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

    /**
     * Extract the response object which matches `$existingAttempt` out of the
     * student's participation record.
     *
     * @param QuizDomainObject $assessment
     * @param User             $student
     * @param Model            $existingAttempt
     * @return QuizResponse|null
     */
    private function extractExistingResponse(
        QuizDomainObject $assessment,
        User $student,
        Model $existingAttempt,
    ): ?QuizResponse {
        $response = $assessment->getParticipant($student)->getAllResponses()->getBy('id', $existingAttempt->getId());
        return $response instanceof QuizResponse ? $response : null;
    }

    /**
     * Assign normalised and human-readable marks to `$attempt`.
     *
     * @param Model             $attempt
     * @param QuizResponse      $responseDomainObject The domain-level object corresponding to `$attempt`: required for
     *                                                deriving a human-readable mark.
     * @param QuizDomainObject  $assessment           The assessment to which `$attempt` belongs: required for deriving
     *                                                a human-readable mark.
     * @param float             $mark
     * @param float             $maxMark
     * @return void
     */
    private function assignMarksToAttempt(
        Model $attempt,
        QuizResponse $responseDomainObject,
        QuizDomainObject $assessment,
        float $mark,
        float $maxMark
    ): void {
        /* Update normalised and human-readable marks
         */
        $normalisedMark = $attempt->calculateNormalisedMark($mark, $maxMark);

        /* Update the domain object synonymously which will desync from $attempt
         * so we need to sync them manually...
         *
         * This is required for getMarkScheme() to calculate human-readable mark
         */
        $responseDomainObject->setNormalisedMark($normalisedMark);
        $responseDomainObject->setMarksAvailable($maxMark);

        $humanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark, $responseDomainObject);

        /* Update the columns with newly-derived values
         */
        $attempt
            ->setMark($humanReadableMark)
            ->setNormalisedMark($normalisedMark)
            ->setMaxMark($maxMark);
    }
}
