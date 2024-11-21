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
use DebugBar\DebugBarException;
use Http\Discovery\Psr17Factory;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use LearnositySdk\Exceptions\ValidationException;
use LearnositySdk\Utils\Uuid;
use Random\RandomException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Project\ResultCache;
use Schoolbox\Learning\Assessment\Project\ResultCache\Mapper;
use Schoolbox\Learning\Assessment\Quiz\Model as QuizDomainObject;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\Learnosity\Api\Duplicator;
use Schoolbox\Learning\Learnosity\Cli\RescoreProcessor;
use Schoolbox\Learning\Learnosity\Codec\UserIdentifier;
use Schoolbox\Learning\Learnosity\Export\Csv\Processor;
use Schoolbox\Learning\Learnosity\Feedback\SanitiseScores;
use Schoolbox\Learning\Learnosity\Importer\Cli\SourceAssociationException;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\Quiz\Attempt\Model;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\TaskDaemon\Request\HttpRequest\MessageFactory;
use Schoolbox\TaskDaemon\Request\DownloadPages\MessageFactory as DownloadPagesMessageFactory;
use Schoolbox\TokenReplacer;
use Schoolbox\Url\Factory;
use Schoolbox\User;
use Schoolbox\User\Translator\Author\ModelToArray;
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
     * It is an offset from config.session_length whose default is usually 3600
     * (means that if you are idle for 3600-60 = 3540 seconds, you get alerted...
     * 60 more seconds of idle time means your schoolbox session will log you out)
     */
    private const LEARNOSITY_ATTEMPT_IDLE_TIMEOUT_OFFSET = 60;


    /**
     * Saves intermittently every 300 seconds by default
     */
    private const LEARNOSITY_SAVE_INTERVAL = 15;


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
     * A default text for items without titles
     */
    private const ITEM_TITLE_DEFAULT = 'Untitled';


    /**
     * Learnosity API instance is the same for everything afaict
     *
     * @var Api
     */
    private Api $api;


    /**
     * Learnosity Helper, which contains multiple convenience methods
     *
     * @var Helper
     */
    private Helper $helper;


    /**
     * Logger: used for... logging
     */
    private Logger $logger;


    /**
     * @var QuizFetcher
     */
    private QuizFetcher $quizFetcher;


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
                        'reference' => [
                            'show' => false,
                        ],
                        'title' => [
                            'show' => true,
                        ]
                    ],
                    /* Tags are only spawned upon clicking Save, not before
                     */
                    'tags_on_create' => $tags
                ],
                /* Show a list of Items, which you can see by clicking Find Items
                 */
                'item_list' => [
                    'item' => [
                        'title' => [
                            'show' => false,
                            'show_reference' => false
                        ]
                    ],
                    'filter' => [
                        'restricted' => [
                            'tags' => [
                                /* Show all content belonging to your school, for now, I guess
                                 * TODO What is the limit of scope for Find Item like this?
                                 */
                                'all' => [
                                    $tagFactory->produceForSchoolCode()
                                ],
                                /* Due to an absolutely disgusting series of issues, it turns out
                                 * that the item list filter also affects your ability to edit the
                                 * items that were filtered out.
                                 *
                                 * https://help.learnosity.com/hc/requests/24009
                                 *
                                 * Therefore: temporarily disabled until they fix this somehow.
                                 * I can't circumvent their implementation.
                                 */
                                //                                 'none' => [
                                //                                    $tagFactory->produceImplFlag()
                                //                                ]
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
     * @throws ApiException
     * @throws DefinitionException
     * @throws Deleted
     * @throws Error
     * @throws Exception
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \Alaress\Database\Exception
     * @throws \DomainException
     * @throws \JsonException
     * @throws \PDOException
     * @throws RandomException
     * @throws \RuntimeException
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
        $this->quizMapper->closeExpiredAttempts($assessment, $quiz, $now); // Close any expired attempts before checking for in-progress.

        /* Also see if $student can load the learnosity getAttempt() screen
         * If they can, then they can attempt it; provide the button
         */
        $canAttempt = (bool)$this->getRouteIfAuthorized('learnosity.getAttempt', [
            'assessment' => $assessment,
            'assessmentId' => $assessment->getId(),
            'student' => $student,
            'studentId' => $student->getId(),
        ]);

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
                        "interval" => max((int)$this->getConfigLoader()->get('session_length') - self::LEARNOSITY_ATTEMPT_IDLE_TIMEOUT_OFFSET, 0)
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
                'navigation' => [
                    'auto_save' => [
                        'save_interval_duration' => self::LEARNOSITY_SAVE_INTERVAL,
                        'ui' => true
                    ],
                    "show_intro" => false,
                    "show_outro" => false
                ],
                'regions' => Helper::LEARNOSITY_CONFIG_PLAYER_REGIONS,
                /* Allowed Time
                 * Force hard time limits, kicking you out at the expiry
                 */
                'time' => [
                    /* Learnosity didn't code for their default value; this allows is to
                     * forcibly dodge around a misimplementation on their end.
                     * https://schoolbox.atlassian.net/browse/SA-17209
                     */
                    'countdown_option' => !(\Schoolbox\Learning\Quiz\Model::DURATION_UNLIMITED === $quiz->getDuration()),
                    'max_time' => $quiz->getDuration(),
                    'limit_type' => 'hard',
                    'warning_time' => self::LEARNOSITY_WARNING_TIME,
                    "start_time" => [
                        /* The "soft" start time type tells Learnosity to store the start time on their servers
                         * and use that to calculate the time, this prevents a user from reloading the
                         * page and causing their timer to be inaccurate or reset
                         */

                        /* FIXME Temporarily disabled due to SA-17145 where learnosity relied on frontend
                         *  as a source of truth instead of their server...lol. Re-enable this once learnosity
                         *  figures out how to accurately control attempt commencement
                         */
                        //                        'type' => 'soft',
                        /* Note: Whilst looking into this, I also experimented with the "hard" type which requires a "value" prop.
                         * Learnosity wants this value given in UTC with an unusual format, which can be achieved with "Ymd-His"
                         * The Learnosity docs do not include seconds as a valid attribute, however, I found that this both works and is
                         * required to ensure that the start time is not off by a few seconds when starting the quiz
                         */]
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

        /* Log payload data, because we're dropping attempts randomly and nobody knows why.
         * Hopefully this init payload will helps hed light on things in SA-17302
         */
        (new Feedback\SuspiciousLogger())->logPayload(
            'getAttempt() init payload',
            $payload,
            [],
            $attempt
        );

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
            /* Monitor attempt preparation
             */
            'attemptMonitorUrl' => $this->router->getGenerator()->generate('learnosity.postAttemptMonitor', [
                'assessmentId' => $assessment->getId(),
                'studentId' => $student->getId(),
                'learnositySessionReference' => $attempt->getLearnosityReference()
            ]),
        ], "Attempt {$assessment->getTitle()}");
    }


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
     * @param StateMapper $stateMapper
     * @param Psr17Factory $httpFactory for creating HTTP requests
     * @param MessageFactory $messageFactory for creating messages to send to taskd
     * @param Producer $messageProducer for sending messages to taskd
     * @param DownloadPagesMessageFactory $downloadMessageFactory for creating download page messages to send to taskd
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
        private StateMapper $stateMapper,
        private Psr17Factory $httpFactory,
        private MessageFactory $messageFactory,
        private Producer $messageProducer,
        private DownloadPagesMessageFactory $downloadMessageFactory,
    ) {
        /* Probably inject this one day, but for now,
         * I want to avoid dumping Yet Another Thing into the kernel
         * I don't have a convenient means to access SERVER_NAME except like this, sadly
         */
        $this->logger = new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity');

        $this->api = new Api(
            $urlFactory->buildFullyQualified()->getHost(),
            $this->learnosityVersion,
            $this->learnosityConsumerKey,
            $this->learnosityConsumerSecret,
            $this->logger
        );

        $this->helper = new Helper($this->api, $this->logger);

        /* A convenient thing to have lying around
         */
        $this->quizFetcher = new QuizFetcher($this->quizMapper);
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
     * @throws ApiException
     * @throws Deleted
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws NotFound
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \Interop\Queue\Exception
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postCompleteAttempt(QuizDomainObject $assessment, \Schoolbox\User $student): void
    {
        /* Find out what they scored by the automated system
         * Record it locally for our own usages
         */
        $existingAttempt = $this->attemptMapper->findOneByColumnList([
            'attempt_id'           => $this->requestPost->get('attemptId'),
            'learnosity_reference' => $this->requestPost->get('learnosityReference'),
            'user_id'              => $student->getId()
        ]);

        $this->helper->processAttemptCompletion($this->attemptMapper, $assessment, $existingAttempt);

        if ($existingAttempt->getLearnosityStatus() === Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING) {
            $attemptEvaluator = $this->buildAttemptEvaluator();
            $attemptEvaluator->poll(
                $this->getUser(),
                $assessment,
                $existingAttempt,
            );
        }
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
        $hasMarkVisibility = $assessment->isFeedbackVisibleToUser($student);
        $initOptions = null;

        /* Only load a report if:
         * * mark visibility is set to include students
         * * the attempt has been scored (either by a teacher or automatically)
         */
        $isAttemptScored = $attempt->getMark() !== null;
        if ($hasMarkVisibility && $isAttemptScored) {
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

            $initOptions = $this->api->initOptions('reports', $payload);
        }

        /* Breadcrumbs
         */
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())
            ->add([
                $assessment->getTitle(),
                $this->routeGenerator->generate(
                    'learningAssessment.getSummary',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId' => $this->getUser()->getId()
                    ]
                )
            ])
            ->add(['Result Summary']);

        $returnUrlEncoded = $this->requestQuery->get(Redirect::RETURN_URL);

        $this->response = $this->responseFromTemplate('@learnosity/outro.twig', [
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'initOptions' => $initOptions,
            'attempt' => $attempt,
            'isMarkVisible' => $hasMarkVisibility,
            'returnUrl' => (
                empty($returnUrlEncoded) ?
                "/homepage/{$assessment->getBaseContext()->getFolder()->getId()}" :
                Redirect::decode($returnUrlEncoded)
            ),
        ], "Result for {$assessment->getTitle()}");
    }


    /**
     * The student who did the attempt
     *
     * @param Model $attempt
     * @return array
     */
    private function getStudentData(\Schoolbox\Learning\Quiz\Attempt\Model $attempt): array
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
     *
     * @param Model $attempt
     * @return array
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     */
    private function getItemResponses(\Schoolbox\Learning\Quiz\Attempt\Model $attempt): array
    {
        /* These are the scores they obtained for the one session we chose
         * (It'll still come to us as an array of sessions though)
         */
        $responses = $this->api->dataGetRequest('sessions/responses/scores', [
            'session_id' => [
                $attempt->getLearnosityReference()
            ]
        ])['data'][0]['responses'] ?? [];

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
        $responseComments = [];

        if (\count($scores)) {
            $responseComments = $this->feedbackMapper->findByLearnosityReferences(array_keys($scores));
        }

        return array_values(array_merge_recursive($scores, $responseComments));
    }


    /**
     * Retrieve the response for this attempt
     *
     * @param Model $attempt
     * @param IFeedback|null $feedback
     * @return array
     */
    private function getAttemptResponse(\Schoolbox\Learning\Quiz\Attempt\Model $attempt, ?IFeedback $feedback): array
    {
        return  [
            'learnosityReference' => $attempt->getLearnosityReference(),
            'studentId' => $attempt->getUser()->getId(),
            'comment' => $attempt->getComment(),
            'responseId' => $feedback?->getId(),
            'mark' => $attempt->getMark(),
            'maxMarks' => $attempt->getMaxMark(),
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
     * @throws ApiException
     * @throws Error
     * @throws RouteNotFound
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

        $loggedInUser = $this->getUser();
        $hasMarkVisibility = $assessment->isFeedbackVisibleToUser($loggedInUser); // This looks up assess.mark_visible.

        /* If a teacher reviewed the attempt, show their feedback information
         * Otherwise just show what the student did.
         */
        $reportPayload['reports'][] = [
            /* DOM element id, where this goes in
             *
             * It's configurable by us, unlike the other anchors for
             * Author and Items api
             *
             * @see https://reference.learnosity.com/reports-api/reporttypes#sessionsDetailByItemReport
             */
            'id' => 'schoolbox-student',
            'type' => 'session-detail-by-item',
            'session_id' => $attempt->getLearnosityReference(),
            'questions_api_init_options' => [
                'showCorrectAnswers' => ($attempt->getReviewed() !== null && $hasMarkVisibility),
            ],
            'display_scores' => $hasMarkVisibility
        ];

        /* Conditional reports
         *     - If an attempt is finished, provide the completion summary (unsure how people get here, but ok)
         */
        if ($hasMarkVisibility && $attempt->isFinished()) {
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
                $assessment->isImplementation()
                    ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()])
                    : null
            ];
        } else {
            $assessmentNode = [
                $assessment->getTitle(),
                $assessment->isImplementation()
                    ? $this->routeGenerator->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId(), 'studentId' => $student->getId()])
                    : null
            ];
        }

        $breadcrumbs->add($assessmentNode)
            ->add([
                'Review', null
            ]);

        /* Find this attempt's MapperProxy equivalent from something we already obtained
         */
        $feedback = $assessment->getParticipant($student)
            ->getAllFeedbacks()
            ->filter(static function ($model) use ($attempt) {
                return $model->getResponse()->getLearnosityReference() === $attempt->getLearnosityReference();
            })
            ->first();

        $this->response = $this->responseFromTemplate('@learnosity/review.twig', [
            'student' => $this->getStudentData($attempt),
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'reportInitOptions' => $this->api->initOptions('reports', $reportPayload),
            'attempt' => $attempt,
            'itemResponses' => $this->getItemResponses($attempt),
            'attemptResponse' => $this->getAttemptResponse($attempt, $feedback),
            'returnUrl' => "/homepage/{$assessment->getContext()->getId()}",
            'hasMarkVisibility' => $hasMarkVisibility,
            /* For submission states
             */
            'errors' => $this->stateMapper->listDisplayValues(),
            'markData' => (new MarkSchemePayload(
                $this->stateMapper->findAll(),
                $assessment->getMarkType(),
                $assessment->getGradeGroup()
            ))->convert()
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
        $breadcrumbs = $this->bakery->produceFromAssessment($assessment, $this->getUser())->add([
            $assessment->getTitle(),
            $assessment->isImplementation() ? $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]) : null
        ])->add(['Feedback']);

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
            'postFeedbackAttemptScore' => $this->router->getGenerator()->generate('learnosity.postFeedbackAttemptScore', $args),
            'postFeedbackAttemptComment' => $this->router->getGenerator()->generate('learnosity.postFeedbackAttemptComment', $args),
            'postFeedbackComment' => $this->router->getGenerator()->generate('learnosity.postFeedbackComment', $args),
            'postFeedbackScore' => $this->router->getGenerator()->generate('learnosity.postFeedbackScore', $args),
            'getFeedback' => $this->router->getGenerator()->generate('learnosity.getFeedback', $args),
            /* This is a special route name that doesn't actually exist: it's used to POST data to the
             * Quiz Quickmark endpoint that will set the reviewed_at value, making feedback published.
             * In the corresponding aura module/controller, it's called postModify: I've used postFinalisedAttempt
             * to try and elucidate what we're trying to do here
             */
            'postFinaliseAttempt' => $this->router->getGenerator()->generate('learningQuizAttempt.postModify', $args),
        ];

        /* Find this attempt's MapperProxy equivalent from something we already obtained
         */
        $feedback = $assessment->getParticipant($student)
            ->getAllFeedbacks()
            ->filter(static function ($model) use ($attempt) {
                return $model->getResponse()->getLearnosityReference() === $attempt->getLearnosityReference();
            })
            ->first();

        $this->response = $this->responseFromTemplate('@learnosity/feedback.twig', [
            'student' => $this->getStudentData($attempt),
            'reportInitOptions' => $this->api->initOptions('reports', $reportPayload),
            'assessment' => $assessment,
            'breadcrumbs' => $breadcrumbs,
            'attempt' => $attempt,
            'itemResponses' => $this->getItemResponses($attempt),
            'attemptResponse' => $this->getAttemptResponse($attempt, $feedback),
            'urls' => $urls,
            /* For Submission States
             */
            'participant' => $assessment->getParticipant($student),
            'feedback' => $feedback,
            'maxMarks' => $attempt->getMaxMark(),
            'errors' => $this->stateMapper->listDisplayValues(),
            'submissionStates' => $this->stateMapper->findAll(),
            'markData' => (new MarkSchemePayload(
                $this->stateMapper->findAll(),
                $assessment->getMarkType(),
                $assessment->getGradeGroup()
            ))->convert()
        ], sprintf(
            'Feedback for %s in %s',
            $student->getFullName(),
            $assessment->getTitle()
        ));
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
        $attemptResponses = [];
        $urls = [];
        $grading = [];

        /** @var Model $attempt */
        foreach ($attempts as $attempt) {
            $key = self::REPORT_HORIZONTAL_MARKING_PREFIX . $attempt->getUser()->getId();

            if (!isset($users[$key]) && $attempt->isFinished()) {
                $sessions[$attempt->getUser()->getId()] = $attempt->getLearnosityReference();

                /* Find this attempt's MapperProxy equivalent from something we already obtained
                */
                $feedback = $assessment->getParticipant($attempt->getUser())
                    ->getAllFeedbacks()
                    ->filter(static function ($model) use ($attempt) {
                        return $model->getResponse()->getLearnosityReference() === $attempt->getLearnosityReference();
                    })
                    ->first();

                /* Find all the responses for this attempt (need it later)
                 */
                $attemptResponses[$attempt->getUser()->getId()] = $this->getAttemptResponse($attempt, $feedback);

                /* ...because Learnosity wants it in this format
                 */
                $users[$key] = [
                    'id' => $attempt->getUser()->getId(),
                    'name' => $attempt->getUser()->getFullName(),
                    'firstname' => $attempt->getUser()->getFirstname(),
                    'lastname' => $attempt->getUser()->getLastname(),
                    'elementId' => $key,
                    'isReviewed' => $attempt->getReviewed() !== null
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
                    'postFeedbackAttemptScore' => $this->router->getGenerator()->generate('learnosity.postFeedbackAttemptScore', $args),
                    'postFeedbackAttemptComment' => $this->router->getGenerator()->generate('learnosity.postFeedbackAttemptComment', $args),
                    'postFeedbackComment' => $this->router->getGenerator()->generate('learnosity.postFeedbackComment', $args),
                    'postFeedbackScore' => $this->router->getGenerator()->generate('learnosity.postFeedbackScore', $args),
                    'getFeedback' => $this->router->getGenerator()->generate('learnosity.getFeedback', $args),
                    /* This is a special route name that doesn't actually exist: it's used to POST data to the
                     * Quiz Quickmark endpoint that will set the reviewed_at value, making feedback published.
                     * In the corresponding aura module/controller, it's called postModify: I've used postFinalisedAttempt
                     * to try and elucidate what we're trying to do here
                     */
                    'postFinaliseAttempt' => $this->router->getGenerator()->generate('learningQuizAttempt.postModify', $args),
                ];

                /* Set up the grading ingredients for the submission states and comboboxes
                 */
                $participant = $assessment->getParticipant($attempt->getUser());

                $feedback = $participant
                    ->getAllFeedbacks()
                    ->filter(static function ($model) use ($attempt) {
                        return $model->getResponse()->getLearnosityReference() === $attempt->getLearnosityReference();
                    })
                    ->first();

                $grading[$attempt->getUser()->getId()] = [
                    'participant' => $participant,
                    'feedback' => $feedback,
                    'maxMarks' => $attempt->getMaxMark(),
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
            /* Due to learnosity behaviour https://help.learnosity.com/hc/en-us/requests/23258
             * we have to accommodate $itemReference being a string (lol) as well as an array (lol)
             */
            if (is_array($itemReferences[0])) {
                /* Forcibly mutate this, because learnosity insanity
                 */
                $itemReferenceList = array_column($itemReferences, 'id');
            } else {
                /* Presume it was a list of reference strings already
                 */
                $itemReferenceList = $itemReferences;
            }

            /* Find a list of Learnosity Items (questions) available in this quiz
             * This includes more info about each item
             */
            $items = $this->api->dataGetRequest('itembank/items', [
                'references' => $itemReferenceList
            ])['data'];

            /* Index them by reference, to remove quadratic complexity...
             * ...because some schools have 900+ items in their quizzes!
             * 900x900 iterations is far too many for what it accomplishes.
             */
            $indexedItems = [];

            foreach ($items as $item) {
                $indexedItems[$item['reference']] = $item;
            }

            /* Consolidate reference and titles into an in-order lookup
             * (because $items is in some non-sequenced order)
             *
             * Forcibly mutate $itemReference yet again >.<
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

                /* Set a default title - this should always be overwritten, but I can't assert
                 * what will happen in the wild with people changing quiz contents before/during/after
                 * attempts are being made
                 */
                $itemReference['title'] = self::ITEM_TITLE_DEFAULT;

                /* Find it in the set of $items
                 */
                $item = $indexedItems[$itemReference['reference']] ?? null;

                if ($item) {
                    $title = '' === trim((string)$item['title']) ? self::ITEM_TITLE_DEFAULT : $item['title'];
                    $itemReference['title'] = $title;
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
        $attemptResponses = array_values($attemptResponses);
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
            'attemptResponses' => $attemptResponses,
            'urls' => $urls,
            'grading' => $grading,
            /* A list of submission states
             */
            'errors' => $this->stateMapper->listDisplayValues(),
            'markData' => (new MarkSchemePayload(
                $this->stateMapper->findAll(),
                $assessment->getMarkType(),
                $assessment->getGradeGroup()
            ))->convert(),
            /* This list of learnosity references is used for the finalisation step
             */
            'sessions' => json_encode($learnositySessions, JSON_THROW_ON_ERROR),
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
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
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
            $existingResponse = $this->helper->extractExistingResponse($assessment, $attempt->getUser(), $attempt);
            if ($existingResponse) {
                $result = $groupedResults[$attempt->getLearnosityReference()] ?? null;
                if ($result) {
                    $this->helper->assignMarksToAttempt(
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
        $payload = [
            'session_id' => [
                $attempt->getLearnosityReference()
            ]
        ];

        $responses = $this->api->dataGetRequest('sessions/responses/scores', $payload)['data'][0]['responses'];

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
            (new Feedback\SuspiciousLogger())->logPayload(
                'postFeedbackScore() $result payload after processing learnosity /sessions/responses/scores',
                $payload,
                $responses,
                $attempt
            );

            throw new \DomainException('Learnosity response identifier was not found.');
        }

        /* Use the learnosity Data API to send the revised payload which
         * should now contain all the parts Learnosity expected
         */
        $payload = [
            'sessions' => [
                [
                    'session_id' => $attempt->getLearnosityReference(),
                    'user_id' => (new UserIdentifier())->generate($this->getConfigLoader()->get('site_school_code'), $attempt->getUser()->getId()),
                    'responses' => [
                        $updatedResponse
                    ]
                ]
            ]
        ];

        $result = $this->api->dataUpdateRequest('/sessions/responses/scores', $payload);

        (new Feedback\SuspiciousLogger())->logPayload(
            'postFeedbackScore() final update $result payload after processing learnosity /sessions/responses/scores',
            $payload,
            $result,
            $attempt
        );
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
        $comment = $this->replaceTokens($this->requestPost->get('comment'), $student);

        $feedback->setComment($comment);

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
        $comment = $this->replaceTokens($this->requestPost->get('comment'), $student);

        $attempt->setComment($comment);

        /* Save it
         */
        $this->attemptMapper->save($attempt);
    }


    /**
     * Post the human-readable overall mark for an Attempt into assess_attempt.mark
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws DebugBarException
     * @throws Deleted
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postFeedbackAttemptScore(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        $postData = $this->requestPost->getArrayCopy();

        /* If this is an actual grade, try to interpret the pieces:
         *     - Human-readable mark, from the combobox
         *     - Normalised mark derived from the human-readable mark
         */
        $mark = trim((string)$postData['mark']);

        if ($assessment->getMarkScheme() instanceof TotalMarkFractional) {
            try {
                $assessment->getMarkScheme()->setTotalMark((float)($attempt->getMaxMark()));
            } catch (\RangeException $e) {
                // This is thrown if you haven't assigned question scores in learnosity
            }
        }

        /* Default values
         * Note that $mark might be empty string at this point:
         * this occurs when you add a comment before the grade, in the Quiz Quickmark screen
         */
        $normalisedMark = null;
        $humanReadableMark = null;

        /* Apparently this is how you detect a Submission State: an exception is thrown
         * trying to interpret it as one of the Mark Schemes. No wonder it was classified
         * as the $errors var. Wild!
         *
         * Shanked from Learning/Assessment/DueWork/Feedback/Factory.php::210
         */
        try {
            $normalisedMark = $assessment->getMarkScheme()->getNormalisedMark($mark);
            $humanReadableMark = $assessment->getMarkScheme()->getHumanReadableMark($normalisedMark);
        } catch (\InvalidArgumentException $e) {
            if ('' !== $mark) {
                /* If it's a Submission State, save the text as the human readable mark
                 * and derive its normalised mark from the uh Lists in admin
                 */
                $code = $this->stateMapper->findOneByColumn('display_as', $mark);

                $normalisedMark = $code->getNormaliseAs();
                $humanReadableMark = $mark;
            }
        }

        /* Update the deets and save it
         */
        $attempt->setMark($humanReadableMark)->setNormalisedMark($normalisedMark);

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
     * @throws \JsonException
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
     * @throws Deleted
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws SourceAssociationException
     * @throws ValidationException
     * @throws \DomainException
     * @throws \Interop\Queue\Exception
     * @throws \LogicException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postCopyQuizConstituents(QuizDomainObject $source, QuizDomainObject $destination): void
    {
        // Start duplicating and polling status of duplication
        $duplicator = $this->buildDuplicator();
        try {
            $jobId = $duplicator->copyQuizConstituents($source, $destination);
        } catch (SourceAssociationException $e) {
            // Not able to be associated?
            // Set destination as unprocessable, save and rethrow exception
            $destination->setLearnosityJobStatus(Helper::LEARNOSITY_PROCESS_STATUS_UNPROCESSABLE);
            $this->assessmentDataSource->save($destination, $this->getUser());
            throw $e;
        }
        $duplicator->initiatePollingForQuizStatus($this->getUser(), $destination);
        $this->response = $this->responseAsJson(['jobId' => $jobId]);
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
     * @throws Exception
     * @throws \DomainException
     * @throws \InvalidArgumentException
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
        if (!$assessment->getLearnosityJobReference()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }

        $this->buildDuplicator()->pollQuizStatus($assessment);

        // What's in the response: just a "status" callback,
        // possibly an API problem?
        $jobStatus    = $assessment->getLearnosityJobStatus();
        $responseCode = match ($jobStatus) {
            Helper::LEARNOSITY_PROCESS_STATUS_COMPLETED => 200,
            Helper::LEARNOSITY_PROCESS_STATUS_PENDING, Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING => 202,
                // including failure
            default => 400,
        };
        $this->response = $this
            ->responseAsJson(['jobStatus' => $jobStatus])
            ->withStatus($responseCode);
    }

    /**
     * Callback for polling the status of a Learnosity attempt.
     *
     * @api   POST /learning/learnosity/{assessmentId}/attempt/{studentId}/{learnositySessionReference}/poll
     * @since 23.1
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws ApiException
     * @throws ValidationException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postPollQuizAttemptStatus(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        $this->helper->processAttemptCompletion($this->attemptMapper, $assessment, $attempt);

        // Respond with code:
        // * 200 if complete
        // * 202 if still processing (this tells polling process to try again)
        // * 400 if failed
        $status       = $attempt->getLearnosityStatus();
        $responseCode = match ($status) {
            Helper::LEARNOSITY_PROCESS_STATUS_COMPLETED => 200,
            Helper::LEARNOSITY_PROCESS_STATUS_PENDING, Helper::LEARNOSITY_PROCESS_STATUS_PROCESSING => 202,
                // including failure
            default => 400,
        };
        $this->response = $this
            ->responseAsJson(['status' => $status])
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
     * Use this arbitrary endpoint to try and monitor attempt saving in Learnosity,
     * to try and figure out why they *sometimes* don't work.
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws \JsonException
     */
    public function postAttemptMonitor(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* Get the post data that the frontend asynchronously sent to us
         */
        $postData = $this->requestPost->getArrayCopy();

        /* Push it into the log file so we can track when things happened,
         * (if they happened...) along with whatever outcomes might be helpful
         */
        $logger = new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity_attempt_monitor');

        /* If data is empty, we probably resumed a quiz (that's what happens when you resume a quiz)
         * Otherwise, it should contain all the response ids in the quiz, when you start a whole new attempt
         */
        if ('' !== $postData['data']) {
            $logger->debug(sprintf(
                'Assessment %s (%s) new attempt started at %s using learnosity reference %s',
                $assessment->getTitle(),
                $assessment->getId(),
                \Alaress_Date_Format::now()->getDatabaseDate(),
                $attempt->getLearnosityReference()
            ));
        } else {
            $logger->debug(sprintf(
                'Assessment %s (%s) attempt resumed at %s using learnosity reference %s',
                $assessment->getTitle(),
                $assessment->getId(),
                \Alaress_Date_Format::now()->getDatabaseDate(),
                $attempt->getLearnosityReference()
            ));
        }

        $logger->debug(json_encode($postData, JSON_THROW_ON_ERROR));
    }


    /**
     * Replace tokens in $originalComment for values pertaining to $student
     * Note that $comment could be anything...
     *
     * @param mixed $comment
     * @param User $student
     * @return string
     */
    private function replaceTokens(mixed $comment, User $student): string
    {
        return (new TokenReplacer\Student(new TokenReplacer\Simple()))
            ->replace(trim((string)($comment ?? '')), $student);
    }


    /**
     * Build assessment duplicator upon request, because it cannot be built
     * in the constructor.
     *
     * @return Duplicator
     * @throws \DomainException
     */
    private function buildDuplicator(): Api\Duplicator
    {
        return new Api\Duplicator(
            $this->api,
            $this->quizMapper->getDatabase(),
            (new Factory($this->getConfigLoader()))->buildFullyQualified(),
            $this->httpFactory,
            $this->routeGenerator,
            $this->messageFactory,
            $this->messageProducer,
            new TagFactory($this->getConfigLoader()->get('site_school_code')),
            $this->logger,
        );
    }

    /**
     * Build assessment attempt evaluator upon request, because it cannot be
     * built in the constructor.
     *
     * @return Api\AttemptEvaluator
     */
    private function buildAttemptEvaluator(): Api\AttemptEvaluator
    {
        return new Api\AttemptEvaluator(
            (new Factory($this->getConfigLoader()))->buildFullyQualified(),
            $this->httpFactory,
            $this->routeGenerator,
            $this->messageFactory,
            $this->messageProducer,
        );
    }


    /**
     * Rescore all the attempts for a given $assessment
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws ApiException
     * @throws Exception
     * @throws InvalidColumnException
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @throws RouteNotFound
     * @throws ValidationException
     * @throws \Interop\Queue\Exception
     * @throws \InvalidArgumentException
     * @throws \JsonException
     */
    public function postRescoreAttempts(QuizDomainObject $assessment): void
    {
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* Find all the finished attempts for this quiz
         * (Don't try to rescore attempts currently in progress)
         */
        $pb = $this->attemptMapper->getPredicateBuilder()
            ->equals('assess_id', $quiz->getId())
            ->notNull('finish');

        $attempts = $this->attemptMapper->findByPredicates($pb);

        (new RescoreProcessor($this->api, true, null))->process($attempts);

        $attemptEvaluator = $this->buildAttemptEvaluator();

        foreach ($attempts as $attempt) {
            $attemptEvaluator->poll(
                $this->getUser(),
                $assessment,
                $attempt,
            );
        }

        $this->getSession()->getSegment(static::class)->set('hasRescore', false);
    }


    /**
     * taskd POSTs callbackParams to this url
     *
     * @param QuizDomainObject $assessment
     * @param User $student
     * @param Model $attempt
     * @return void
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function postRescoreAttemptsCallback(
        QuizDomainObject $assessment,
        \Schoolbox\User $student,
        \Schoolbox\Learning\Quiz\Attempt\Model $attempt
    ): void {
        /* Ask learnosity about $attempt's new score and ram that back in
         */
        $payload = [
            'session_id' => [
                $attempt->getLearnosityReference()
            ]
        ];

        $result = $this->api->dataGetRequest('/sessions/scores', $payload);

        // As with posting a feedback:
        // need the existing response to determine a human-readable mark.
        $existingResponse = $this->helper->extractExistingResponse($assessment, $student, $attempt);

        // TODO as with posting a feedback:
        //  what kind of error should we present to users? It's asynchronous
        if (!$existingResponse) {
            return;
        }

        /* Instead of asking learnosity how it tallied everything, we can
         * just...do it ourselves, I guess, and sort-of delete this problem
         * We knew the max_mark from the attempt table, so it could potentially
         * desync from what Learnosity knows.
         *
         * Arbitrarily max out $mark if it somehow exceeds the maximum limit
         * ($mark must never exceed $maxMark) and/or otherwise sanitise data
         */
        $sanitisedScore = (new SanitiseScores())->sanitise($result['data'][0]['score'], $result['data'][0]['max_score']);

        $mark = $sanitisedScore['score'];
        $maxMark = $sanitisedScore['max_score'];

        $this->helper->assignMarksToAttempt($attempt, $existingResponse, $assessment, $mark, $maxMark);

        $this->attemptMapper->save($attempt);
    }


    /**
     * Export each student's latest response for a given quiz $assessment
     * in csv format. Each question type needs its own way of find and rendering
     * what should appear in the csv.
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws ApiException
     * @throws ValidationException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    public function getExportAttempts(QuizDomainObject $assessment): void
    {
        (new Processor($this->api, $this->quizFetcher, $this->attemptMapper))->export($assessment);
    }

    /**
     * Hook to getExportAttempts route via taskd, so it backgrounds the job
     *
     * @param QuizDomainObject $assessment
     * @return void
     * @throws Error
     */
    public function getExportDownload(QuizDomainObject $assessment): void
    {
        $urlBase = (new \Schoolbox\Url\Factory($this->getConfigLoader()))->buildFullyQualified();

        $filename = sprintf(
            'exported_responses_%s.csv',
            $assessment->getId()
        );

        $userSerializer = new ModelToArray();

        $messageFactory = $this->downloadMessageFactory;
        $messageFactory = $messageFactory->addFileContent(
            $filename,
            (string)$urlBase
                ->withPath($this->getRouteIfAuthorized('learnosity.getExportAttempts', ['assessmentId' => $assessment->getId()]))
        );

        /* This $message instance is the payload that we send to taskd
         * which it then unpacks, configures its Gotenberg client, and uses
         * to generate the pdfs. This is the bridge between the systems
         */
        $message = $messageFactory
            ->setUser($this->getUser())
            ->setCallbackUrl(
                (string)$urlBase
                    ->withPath(
                        $this->routeGenerator->generate(
                            'apiusernotify.apiUserNotify',
                            ['userId' => $this->getUser()->getId()]
                        )
                    )
            )
            ->setCallbackParams([
                'message' => sprintf(
                    'Attempts export for "%s" is ready to download',
                    $assessment->getTitle(),
                ),
                'from'    => call_user_func($userSerializer, $this->getUser()),
                'to'      => call_user_func($userSerializer, $this->getUser()),
            ])
            ->setOutfile(sprintf(
                'exported_responses_%s.zip',
                $assessment->getId()
            ))
            ->build();

        /* Send it to task for async processing
         */
        $this->messageProducer->send($message);
    }
}
