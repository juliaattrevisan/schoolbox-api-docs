<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Status;

use Alaress\Lumberjack\Logger;
use Alaress_Date_Format;
use PDOException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Mapper as RubricIndicatorMarkMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter\Filters\Assessment\IdFinder;
use Schoolbox\Filter\IFactoryFactory;
use Schoolbox\Filter\Mapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\IFeedback;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\Assessment\Timeline\Full;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\MarkScheme\IMarkScheme;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Alaress\Web\Controller as WebController;
use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\Codec;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImpl;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Mapper\Exception\Deleted;

use Schoolbox\User;
use function array_combine;

class Controller extends WebController\Base
{
    /**
     * @param ErrorMapper $errorMapper
     * @param IdFinder $assessmentIdFinder
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param Factory $bakery
     * @param \Schoolbox\Url\Factory $urlFactory
     * @param string $learnosityVersion
     * @param string $learnosityConsumerKey
     * @param string $learnosityConsumerSecret
     * @param QuizMapper $quizMapper
     * @param IFactoryFactory $filterFactoryFactory
     * @param Mapper $filterMapper
     * @param Filter\Selection\Mapper $filterSelectionMapper
     * @param CourseImpl\Mapper $courseImplMapper
     * @param UnitImpl\Mapper $unitImplMapper
     * @param AssessmentMapperProxy $assessmentDataSource
     * @param ResponseDataSource $responseDataSource
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @param Project\ResultCache\Mapper $projectResultCacheMapper
     * @param Connector $kamiConnector
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private Filter\Filters\Assessment\IdFinder $assessmentIdFinder,
        private AssessmentMapperProxy $assessmentMapperProxy,
        private Breadcrumb\Factory $bakery,
        private \Schoolbox\Url\Factory $urlFactory,
        private string $learnosityVersion,
        #[\SensitiveParameter] private string $learnosityConsumerKey,
        #[\SensitiveParameter] private string $learnosityConsumerSecret,
        private QuizMapper $quizMapper,
        private Filter\IFactoryFactory $filterFactoryFactory,
        private Filter\Mapper $filterMapper,
        private Filter\Selection\Mapper $filterSelectionMapper,
        private CourseImpl\Mapper $courseImplMapper,
        private UnitImpl\Mapper $unitImplMapper,
        private AssessmentDataSource $assessmentDataSource,
        private ResponseDataSource $responseDataSource,
        private ReturnedWorkMapper $returnedWorkMapper,
        private Project\ResultCache\Mapper $projectResultCacheMapper,
        private Connector $kamiConnector,
    ) {
    }

    /**
     * View status page for a course level assessment filtered by markbook filters
     * this will take a courseSpec and an assessmentId, then filter from url query args mostly the following filters:
     * * folders
     * * due in academic period
     * * activity status
     * (the rest of the filters are not relevant for this page)
     *
     * @param Model $courseSpec
     * @param IAssessable $assessmentSpec
     * @return void
     * @throws Exception
     * @api GET /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/status
     */
    public function getStatus(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        /**
         * we are assuming the links to this page have been appended with
         * 'queryArgs'               => $codec->convertSelectionsToQueryString($selectionsPerFilter),
         */

        $queryArgs = $this->requestQuery->getArrayCopy();
        [$filters,] = $this->convertRequestToFilters($courseSpec, $queryArgs, false);

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        // filter to only the assessment we are looking for
        $assessments = $assessments->filter(static function (IAssessable $assessment) use ($assessmentSpec) {
            return $assessment->getSpecificationAssessment()?->getId() === $assessmentSpec->getId();
        });

        // sort the assessments in a standard order (class page name naturally) which appears to be the same as the main markbook screen
        $assessments = $assessments->sortByCallback(function (IAssessable $a, IAssessable $b) {
            return strnatcasecmp($a->getBaseContext()->getFolder()->getTitle(), $b->getBaseContext()->getFolder()->getTitle());
        });

        // hydrate participants
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        $baseUrl = $this->getRouteIfAuthorized('courseStatus.getStatus', ['assessmentId' => $assessmentSpec->getId(), 'assessmentSpec' => $assessmentSpec, 'courseSpecId' => $courseSpec->getId(), 'courseSpec' => $courseSpec]);
        $returnUrlSuffix = Redirect::queryString($baseUrl);

        // Injectables...
        $rubricIndicatorMarkMapper = RubricIndicatorMarkMapper::getInstance();
        $termMapper = Term\Mapper::getInstance();
        $stateMapper = StateMapper::getInstance();

        // init the twig data
        $twigData = [
            'type' => AssessmentType::objectToString($assessmentSpec),
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessmentSpec, $this->getUser())->add([ $assessmentSpec->getTitle(), '' ]),
            'assessments' => $assessments,
            'assessmentSpec'  => $assessmentSpec,
            'courseSpec' => $courseSpec,
            /* Need this suffix so we can return to the status screen after
             * finishing the modification of le quiz or project
             */
            'returnUrl' => Redirect::encode($baseUrl),
            'returnUrlSuffix' => $returnUrlSuffix,
        ];

        // switch based on which assessment type
        if ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\DueWork\Model) {
            // duework specific data
            $twigData['errors'] = $stateMapper->listDisplayValues();

            /* Draft Responses made by each guy
             */
            $twigData['draftResponses'] = [];

            // class assessment specific data
            /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
            foreach ($assessments as $assessment) {
                // Only display download link if there are actually files to download in any of the classes
                $twigData['download'] = ($twigData['download'] ?? false) ?: $this->hasDownloadableSubmissionFiles($assessment);

                $twigData['rubricMarks'][$assessment->getId()] = $rubricIndicatorMarkMapper->findMarkedByAssessment($assessment->getId());
                $twigData['interactionLocked'][$assessment->getId()] = (new Assessment\LockedChecker($termMapper))->isLockedForInteractions($assessment);
                /* Find the first valid Blind Review url
                */
                $twigData['blindReviewUrl'][$assessment->getId()] = (new StudentCarousel\Blind(
                    $this->routeGenerator,
                    null,
                    $assessment
                ))->getFirstUrl();

                /* Find out which users have a draft submission
                * MAYBE you can do this purely in twig...but I'm not enamourd by frontend making
                * those sort of data collecting maneuvers mainly because they don't know when their
                * calls access a potentially expensive method.
                */
                foreach ($assessment->getAllParticipants() as $participant) {
                    $draftResponse = $participant->findMostRecentDraftResponse();

                    if ($draftResponse) {
                        $twigData['draftResponses'][$assessment->getId()][$participant->getUser()->getId()] = $draftResponse->getId();
                    }
                }
            }

            $twigData['selfReflect'] = false;
            $twigData['selfAssess'] = false;

            $template = '@courseStatus/duework.twig';
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Lti\Model) {
            $twigData['message'] = strip_tags($this->requestQuery->get('lti_msg', ''));
            $twigData['errorMessage'] = strip_tags($this->requestQuery->get('lti_errormsg', ''));
            $template = '@courseStatus/lti.twig';
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Task\Model) {
            $template = '@courseStatus/task.twig';
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
            $now = Alaress_Date_Format::now();

            /* Probably inject this one day, but for now,
             * I want to avoid dumping Yet Another Thing into the kernel
             * I don't have a convenient means to access SERVER_NAME except like this, sadly
             */
            $logger = new Logger(STDOUT, STDOUT, \Monolog\Logger::DEBUG, [], true, 'learnosity');

            /* TODO Derive from learnosity atm
             *   Consider caching locally the standing maximum available marks for the assessment in the absence of an attempt.
             *   This only matters when it's a Total Mark markscheme: everything else is out of 100
             */
            $api = new Api(
                $this->urlFactory->buildFullyQualified()->getHost(),
                $this->learnosityVersion,
                $this->learnosityConsumerKey,
                $this->learnosityConsumerSecret,
                $logger
            );
            $learnosityHelper = new Helper($api, $logger);

            // class assessment specific data
            /** @var \Schoolbox\Learning\Assessment\Quiz\Model $assessment */
            foreach ($assessments as $assessment) {
                /* Auto-complete naturally expired quizzes
                 */
                $quiz = $this->getQuizForAssessmentId($assessment->getId());

                /* Apparently this is a thing
                 */
                $quiz = $this->quizMapper->loadAttemptsAndAnswers($quiz);

                /* Close any expired attempts before checking for in-progress / unfinished attempts
                 * Note that this requires $quiz to have its attempts already loaded via loadAttemptsAndAnswers()
                 */
                $numChanged = $this->quizMapper->closeExpiredAttempts($assessment, $quiz, $now);

                /* Attempts list is hydrated as part of $assessment, but we might have changed that
                 * If anyone got their attempt automatically closed, reload with the latest data.
                 */
                if ($numChanged) {
                    $assessment = $this->assessmentMapperProxy->findByIdForReview($assessment->getId());
                }

                /* Each user's attempt could have its own different max mark value; it's not uniform across
                 * every attempt. Each one could be unique. Build a hash lookup of what the maximums are,
                 * and for those who never completed an attempt: ask learnosity how much is available
                 */
                $maxMarks = [];

                foreach ($assessment->getAllParticipants() as $participant) {
                    if ($assessment->getMarkScheme() instanceof TotalMarkFractional) {
                        /* Ask learnosity to find out how much their attempt *would* be worth
                         * based on the current set of questions if any of the following occurs:
                         *
                         *     - this participant never made an attempt.
                         *     - this participant's attempt is still in progress (db row won't exist yet).
                         *     - most recent response's marksAvailable is zero.
                         *         this occurs when you spawn an arbitrary attempt with a Letter Grade,
                         *         then changed it to Total Mark; we never asked or recorded what the
                         *         quantitative total actually was. Deeper in the andyland part of the
                         *         swamp is a series of floatval() calls that cast nulls to zero.
                         *         TODO maybe find out the breadth of this idea and undo it to support null
                         */
                        /** @var null|\Schoolbox\Learning\Assessment\Quiz\Attempt\Model $mostRecentResponse */
                        $mostRecentResponse = $participant->findMostRecentResponse();

                        if (!$mostRecentResponse) {
                            $maxMarks[$participant->getUser()->getId()] = null;
                        } elseif (!$mostRecentResponse->isSubmitted()) {
                            $maxMarks[$participant->getUser()->getId()] = null;
                        } else {
                            $maxMarks[$participant->getUser()->getId()] = $mostRecentResponse->getMarksAvailable();
                        }
                    }
                }

                /* If the quiz is a Total Mark markscheme and if there was someone without a response,
                 * ask learnosity what they could potentially get; otherwise skip the api penalty
                 */
                if (\count($maxMarks) && $assessment->getMarkScheme() instanceof TotalMarkFractional) {
                    /** @var \Schoolbox\Learning\Assessment\Quiz\Model $assessment */
                    $available = $learnosityHelper->findAvailableMarks($assessment->getLearnosityReference());

                    /* Now assign it to them
                     */
                    foreach ($maxMarks as $userId => $value) {
                        if (null === $value) {
                            $maxMarks[$userId] = $available;
                        }
                    }
                }

                /* Each guy's maximum available marks
                */
                $twigData[$assessment->getId()]['maxMarks'] = $maxMarks;
            }

            $twigData['errors'] = $stateMapper->listDisplayValues();

            $template = '@courseStatus/quiz.twig';
            // project doesn't have a status page yet
        } elseif ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Project) {
            $twigData['assessmentStats'] = [];
            $twigData['participantStats'] = [];
            $twigData['baseContext'] = $assessmentSpec->getBaseContext();
            // class assessment specific data
            /** @var \Schoolbox\Learning\Assessment\Project $assessment */
            foreach ($assessments as $assessment) {
                // Calculate summary totals for display purposes
                $participants = $assessment->getAllParticipants();

                $assessmentStats = new GenericCollection(Assessment\Statistics::class);
                $assessmentStats[$assessment->getId()] = new Assessment\Statistics($assessment);
                foreach ($assessment->getAssessables() as $subAssessable) {
                    $assessmentStats[$subAssessable->getId()] = new Assessment\Statistics($subAssessable);
                }

                $participantStats = new GenericCollection(Assessment\ParticipantStatistics::class);
                foreach ($participants as $participant) {
                    $participantStats[$participant->getUser()->getId()] = new Assessment\ParticipantStatistics($participant);
                }

                $twigData['assessmentStats'][$assessment->getId()] = $assessmentStats;
                $twigData['participantStats'][$assessment->getId()] = $participantStats;
            }
            $template = '@courseStatus/project.twig';
        } else {
            throw new NotFound('Unsupported assessment type: ' . AssessmentType::objectToString($assessmentSpec));
        }

        $this->response = $this->responseFromTemplate(
            $template,
            $twigData,
            "Status of {$assessmentSpec->getTitle()}",
            'submission'
        );
    }

    /**
     * publish feedback for all assessments imported from a course level assessment (whilst matching filters in the post)
     *
     * @param Model $courseSpec
     * @param IAssessable $assessmentSpec
     * @return void
     * @throws Exception
     * @throws Deleted
     * @api POST /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/publish
     */
    public function postPublishFeedback(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // This report may take a while
        $this->extendExecutionTime();

        /** @var int[] $draftFeedbackIds */
        $draftFeedbackIds = $this->requestPost->get('drafts', []);
        $now              = Alaress_Date_Format::now();
        $results = [];
        $projectCache = (new Project\ResultCache\ResultCache($this->projectResultCacheMapper));

//        $queryArgs = $this->requestQuery->getArrayCopy();
//        [$filters,] = $this->convertRequestToFilters($courseSpec, $queryArgs, false);

        $request = $this->requestPost['filter_selection'] ?: [];

        /**
         * @var null[]|Collection[]        $filters
         * @var Filter\Codec               $codec
         * @var Filter\Selection\Model[][] $selectionsPerFilter
         */
        // TODO add some intelligence around which filter settings to save (eg AssessmentType but not units)
        [$filters, $codec, $selectionsPerFilter] = $this->convertRequestToFilters($courseSpec, $request, false);

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        // filter to only the assessment we are looking for
        $assessments = $assessments->filter(static function (IAssessable $assessment) use ($assessmentSpec) {
            return $assessment->getSpecificationAssessment()?->getId() === $assessmentSpec->getId();
        });
        // hydrate participants
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        foreach ($draftFeedbackIds as $draftFeedbackId) {
            // Verify that this return is for this assessment
            $submissionReturn = $this->returnedWorkMapper->findById($draftFeedbackId);
            $publishSuccess = false;

            // grab the actual assessment
            /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
            $assessment = $assessments->getBy('id', $submissionReturn->getSubmissionBox()->getAssessmentModel()->getId());

            if (!$assessment) {
                $results[$draftFeedbackId] = ['publishSuccess' => false, 'error' => 'Assessment not found'];
                continue;
            }

            $submissionReturn->setPublishedAt($now);

            try {
                $this->returnedWorkMapper->save($submissionReturn);
                $publishSuccess = true;

                // Find the Kami worksheet (if it exists)
                if ($submissionReturn->getSubmission()) {
                    $responses = $this->responseDataSource->findForAssessment($assessment, $submissionReturn->getSubmission()->getOwner());
                    if (0 !== $responses->count()) {
                        $response = null;
                        foreach ($responses as $responseCurrent) {
                            if ($responseCurrent->getId() == $submissionReturn->getSubmission()->getId()) {
                                $response = $responseCurrent;
                                break;
                            }
                        }

                        // attempt to clean as we go
                        unset($responses);

                        /** @var ?DueWork\Response\Model $response */
                        $kamiModel = $response?->getAllFiles()->filter(function (SubmissionFile $file) {
                            return $file->getIsWorksheet();
                        })->first()?->getKami();
                        if ($kamiModel) {
                            // Save the kami doc to the feedback object
                            $this->kamiConnector->initiatePollingForKamiDownload(
                                $kamiModel,
                                'feedback',
                                $this->getUser(),
                            );
                        }
                    }
                }

                /* Update project result cache when Publish All is used
                 */
                $projectCache->refresh($this->assessmentDataSource, $assessment, $submissionReturn->getOwner());
            } catch (PDOException | \DomainException | \InvalidArgumentException | \LogicException | \UnexpectedValueException $e) {
                $publishSuccess = false;
            }

            $results[$draftFeedbackId] = ['publishSuccess' => $publishSuccess];
        }

        $this->response = $this->responseAsJson($results);
    }

    /**
     * for each assessment we need to fetch the max mark (from learnosity or fallback to the assessment spec)
     * same as status page
     *
     * @param Model $courseSpec
     * @param $assessmentId
     * @return void
     * @api GET /learning/courseMarkbook/{courseSpecId:\d+}/assessment/{assessmentId:\d+}/maxMarks
     */
    public function getMaxMarks(CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // this only exists for learnosity / quiz
        if (!$assessmentSpec instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
            throw new NotFound('This endpoint is only available for quizzes');
        }
        // This report may take a while
        $this->extendExecutionTime();

        $queryArgs = $this->requestQuery->getArrayCopy();
        [$filters,] = $this->convertRequestToFilters($courseSpec, $queryArgs, false);

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        // filter to only the assessment we are looking for
        $assessments = $assessments->filter(static function (IAssessable $assessment) use ($assessmentSpec) {
            return $assessment->getSpecificationAssessment()?->getId() === $assessmentSpec->getId();
        });
        // hydrate participants
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, null, true);

        $maxMarks = [];

        /* Probably inject this one day, but for now,
             * I want to avoid dumping Yet Another Thing into the kernel
             * I don't have a convenient means to access SERVER_NAME except like this, sadly
             */
        $logger = new Logger(STDOUT, STDOUT, \Monolog\Level::Debug, [], true, 'learnosity');

        /* TODO Derive from learnosity atm
         *   Consider caching locally the standing maximum available marks for the assessment in the absence of an attempt.
         *   This only matters when it's a Total Mark markscheme: everything else is out of 100
         */
        $api = new Api(
            $this->urlFactory->buildFullyQualified()->getHost(),
            $this->learnosityVersion,
            $this->learnosityConsumerKey,
            $this->learnosityConsumerSecret,
            $logger
        );
        $helper = new Helper($api, $logger);
        /** @var \Schoolbox\Learning\Assessment\Quiz\Model $assessment */
        foreach ($assessments as $assessment) {
            $maxMarks[$assessment->getId()] = $helper->findAvailableMarks($assessment->getLearnosityReference());
        }

        $this->response = $this->responseAsJson([
            'maxMarks' => $maxMarks,
        ]);
    }


    /**
     * get assessment info for participant (for markbook popout feedback)
     * we need all the info, draft, rubric, etc
     *
     * @param User $student
     * @param Model $courseSpec
     * @param IAssessable $assessmentSpec
     * @return void
     * @api GET /learning/markbook/assessment/{assessmentId}/{studentId}/info
     */
    public function getInfo(User $student, CourseSpec\Model $courseSpec, IAssessable $assessmentSpec): void
    {
        // fetch the assessment with the student's responses and feedback

        // This report may take a while
        $this->extendExecutionTime();

        $queryArgs = $this->requestQuery->getArrayCopy();
        [$filters,] = $this->convertRequestToFilters($courseSpec, $queryArgs, false);

        $assessmentIds  = $this->assessmentIdFinder->findIdsByFilters($filters, $this->getUser());

        // Fetch assessments
        $assessments = $this->assessmentMapperProxy->findAllByIds($assessmentIds)->indexBy('id');
        // filter to only the assessment we are looking for
        $assessments = $assessments->filter(static function (IAssessable $assessment) use ($assessmentSpec) {
            return $assessment->getSpecificationAssessment()?->getId() === $assessmentSpec->getId();
        });
        // hydrate participant
        $this->assessmentMapperProxy->loadParticipantsIntoAssessmentCollection($assessments, $student, true);

        if ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
            /* Probably inject this one day, but for now,
                 * I want to avoid dumping Yet Another Thing into the kernel
                 * I don't have a convenient means to access SERVER_NAME except like this, sadly
                 */
            $logger = new Logger(STDOUT, STDOUT, \Monolog\Level::Debug, [], true, 'learnosity');

            /* TODO Derive from learnosity atm
             *   Consider caching locally the standing maximum available marks for the assessment in the absence of an attempt.
             *   This only matters when it's a Total Mark markscheme: everything else is out of 100
             */
            $api = new Api(
                $this->urlFactory->buildFullyQualified()->getHost(),
                $this->learnosityVersion,
                $this->learnosityConsumerKey,
                $this->learnosityConsumerSecret,
                $logger
            );
            $helper = new Helper($api, $logger);
        }

        $assessmentInfo = [];

        foreach ($assessments as $assessment) {
            $maxMarks = null;
            if ($assessmentSpec instanceof \Schoolbox\Learning\Assessment\Quiz\Model) {
                /** @var \Schoolbox\Learning\Assessment\Quiz\Model $assessment */
                $maxMarks = $helper->findAvailableMarks($assessment->getLearnosityReference());
            }

            $participant = $assessment->getParticipantIfExtant($student);
            /* A list of Responses and Floating Feedbacks, as per the Submission History
            */
            if ($participant) {
                $latestResponse = $participant->findMostRecentResponse();
                $latestFeedback = $participant->findMostRecentInstructorGivenFeedback();

                // all things have this, even if there are no submissions...
                $response = [
                    'id'  => null,
                    'isSubmitted' => null,
                    'isDraft' => null,
                    'isSubmittedOffline' => null,
                    'hasFiles' => null,
                    'isSubmittedOnTime' => null,
                    'getLearnosityReference' => null,
                    'comment' => null,
                    'duration' => null,
                    'getHumanReadableMark' => null,
                    'getNormalisedMark' => null,
                    'getMarksAvailable' => null,
                    'isSelfAssessed' => null,
                    'getCompletionStatus' => null,
                    'getScoreGiven' => null,
                    'getScoreMaximum' => null,
                ];
                if ($latestResponse) {
                    $response['id'] = $latestResponse->getId();
                    $response['isSubmitted'] = (bool)$latestResponse->getId();
                    $response['isSubmittedOnTime'] = $latestResponse->isSubmittedOnTime();

                    // now for assessment type specific data
                    if ($latestResponse instanceof DueWork\Response\Model) {
                        $response['isSubmitted'] = $latestResponse->isSubmitted();
                        $response['isDraft'] = $latestResponse->isDraft();
                        $response['isSubmittedOffline'] = $latestResponse->isSubmittedOffline();
                        $response['hasFiles'] = $latestResponse->getAllFiles()->count() > 0;
                    } elseif ($latestResponse instanceof Assessment\Task\Response\Model) {
                        $response['isSubmitted'] = $latestResponse->isSubmitted();
                        $response['getCompletionStatus'] = $latestResponse->getCompletionStatus();
                        $response['isSelfAssessed'] = $latestResponse->isSelfAssessed();
                    } elseif ($latestResponse instanceof Assessment\Lti\Response\Model) {
                        $response['getScoreGiven'] = $latestResponse->getScoreGiven();
                        $response['getScoreMaximum'] = $latestResponse->getScoreMaximum();
                        $response['comment'] = $latestResponse->getComment();
                    } elseif ($latestResponse instanceof Assessment\Quiz\Attempt\Model) {
                        $response['getLearnosityReference'] = $latestResponse->getLearnosityReference();
                        $response['getMarksAvailable'] = $latestResponse->getMarksAvailable();
                        $response['duration'] = $latestResponse->getDuration();
                        $response['comment'] = $latestResponse->getComment();
                        $response['getHumanReadableMark'] = $latestResponse->getHumanReadableMark();
                        $response['getNormalisedMark'] = $latestResponse->getNormalisedMark();
                    }
                }

                $feedback = [
                    'id' => null,
                    'comment' => null,
                    'published' => null,
                    'normalisedMark' => null,
                    'humanReadable' => null,
                    'rubric' => null,
                    'resubmissionRequired' => null,
                    'rubricMarkedGrid' => null,
                    'hasResponseWithKamiDocs' => null,
                    'hasFiles' => null,
                    'getHumanReadableInstructorMark' => null,
                    'getCompletionStatus' => null,
                    'getScoreGiven' => null,
                    'getScoreMaximum' => null,
                ];

                if ($latestFeedback) {
                    $feedback['id'] = $latestFeedback->getId();
                    $feedback['comment'] = $latestFeedback->getComment();
                    $feedback['published'] = $latestFeedback->isPublished();
                    $feedback['normalisedMark'] = $latestFeedback->getNormalisedMark();
                    $feedback['humanReadable'] = $latestFeedback->getHumanReadableMark();

                    // now for assessment type specific data
                    if ($latestFeedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model) {
                        /** @var \Schoolbox\Learning\Assessment\DueWork\Feedback\Model $latestFeedback  */
                        $feedback['rubric'] = $assessment->getUseRubric();
                        $feedback['resubmissionRequired'] = $latestFeedback->getResubmissionRequired();
                        $feedback['rubricMarkedGrid'] = $latestFeedback->getRubricMarkedGrid();
                        $feedback['hasResponseWithKamiDocs'] = $latestFeedback->hasResponseWithKamiDocs();
                        $feedback['hasFiles'] = $latestFeedback->getAllFiles()->count() > 0;
                        $feedback['getHumanReadableInstructorMark'] = $latestFeedback->getHumanReadableInstructorMark();
                    } elseif ($latestFeedback instanceof \Schoolbox\Learning\Assessment\Task\Feedback\Model) {
                        $feedback['getCompletionStatus'] = $latestFeedback->getCompletionStatus();
                    } elseif ($latestFeedback instanceof \Schoolbox\Learning\Assessment\Lti\Feedback\Model) {
                        $feedback['getScoreGiven'] = $latestFeedback->getScoreGiven();
                        $feedback['getScoreMaximum'] = $latestFeedback->getScoreMaximum();
                    }
                }

                $assessmentInfo[$assessment->getId()] = [
                    'assessmentId' => $assessment->getId(),
                    'assessmentTitle' => $assessment->getTitle(),
                    'assessmentType' => AssessmentType::objectToString($assessment),
                    'workType' => $assessment->getWorkType()->getName(),
                    'maxMarks' => $maxMarks,
                    'response' => $response,
                    'feedback' => $feedback,
                ];
            }
        }

        $this->response = $this->responseAsJson($assessmentInfo);
    }


    /**
     * Determine if there are any submission files or feedback available for download on the assessment
     *
     * Direct clone from \Schoolbox\Learning\Assessment\DueWork\Controller::hasDownloadableSubmissionFiles
     * @see \Schoolbox\Learning\Assessment\DueWork\Controller::hasDownloadableSubmissionFiles
     * @param IAssessable $assessment
     * @return bool
     */
    private function hasDownloadableSubmissionFiles(IAssessable $assessment): bool
    {
        // Grab all participants in the assessment

        /** @var Participant $participant */
        foreach ($assessment->getAllParticipants() as $participant) {
            // Iterate all feedbacks
            foreach ($participant->getAllFeedbacks() as $feedback) {
                if ($feedback instanceof \Schoolbox\Learning\Assessment\DueWork\Feedback\Model && $feedback->isPublished()) {
                    return true;
                }
            }

            // Iterate all their responses
            /** @var Submission $response */
            foreach ($participant->getAllResponses() as $response) {
                // If the response is a submission...

                if ($response instanceof Submission && !$response->isDraft()) {
                    if ($response->getAllFiles()->count() > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }


    /**
     * Converts an assessment_id to a Quiz
     *
     * @see \Schoolbox\Learning\Assessment\Quiz\Controller::getQuizForAssessmentId
     * @param int $assessmentId
     * @return Quiz
     * @throws \Schoolbox\Application\Exception\Exception
     */
    protected function getQuizForAssessmentId($assessmentId): Quiz
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->errorMapper->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        return $quiz;
    }


    /**
     * @see \Schoolbox\Assessment\Course\Markbook\Controller::convertRequestToFilters
     * @param CourseSpec\Model $courseSpec
     * @param array            $request  Indexed by filter.id, option.key
     * @param bool             $saveToDB Should we persist these selections to the DB ?
     * @return array
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \DomainException If this class has not defined what entity it is filtering
     * @throws \InvalidArgumentException If unable to find the filter mapper class
     * @throws \Schoolbox\Mapper\DefinitionException If join $name isn't on this mapper
     * @throws \Schoolbox\Mapper\Exception If an alias is incorrect
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    private function convertRequestToFilters(CourseSpec\Model $courseSpec, array $request, bool $saveToDB): array
    {
        $page            = Filter\Page\Model::PAGE_COURSE_MARK_BOOK;
        $codec           = new Codec($this->filterMapper, $this->filterSelectionMapper);

        // Convert Request to Selection models
        $selectionsPerFilter = $codec->convertRequestToSelections($this->getUser(), $page, $request);
        if ($saveToDB) {
            $this->filterSelectionMapper->updateSelections($this->getUser(), $page, $selectionsPerFilter);
        }

        $filters = $this->filterFactoryFactory->convertSelectionsToFilters($selectionsPerFilter, $this->getUser(), $page);

        // Ensure that assessments are:
        // * not Assessment Specs
        // * Are inside unitImpls that belong to courseImpls that were imported from the courseSpec
        $courseImpls = $this->courseImplMapper->findAllByCourseSpecificationId($courseSpec->getId());
        if ($courseImpls->count()) {
            $pb          = $this->unitImplMapper->getPredicateBuilder()->in('course_implementation_id', $courseImpls->getPropertyValues('id'));
            $unitImplIds = $this->unitImplMapper->findIdsByPredicates($pb);
        } else {
            $unitImplIds = [];
        }
        $filters = array_merge($filters, [
            Filter\Filters\Assessment\NullableContext\UnitSpec\Definition::class => [],
            Filter\Filters\Assessment\NullableContext\UnitImpl\Definition::class => array_combine($unitImplIds, $unitImplIds),
        ]);

        return [$filters, $codec, $selectionsPerFilter];
    }
}
