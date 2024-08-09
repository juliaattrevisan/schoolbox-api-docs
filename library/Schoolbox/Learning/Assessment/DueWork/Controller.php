<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use CFPropertyList\IOException;
use CFPropertyList\PListException;
use DomainException;
use DOMException;
use Eportfolio\ShowcaseMapper;
use Eportfolio_Authenticator;
use External\Canvas\Importer\CanvasDb;
use ICollection;
use Interop\Queue\Exception\InvalidDestinationException;
use Interop\Queue\Exception\InvalidMessageException;
use InvalidArgumentException;
use LogicException;
use PDOException;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use RangeException;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DueWork\Download\MessageFactory;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\DataModel\Descriptor\Mapper as RubricDescriptorMapper;
use Schoolbox\Assessment\Rubric\DataModel\IndicatorMark\Mapper as RubricIndicatorMarkMapper;
use Schoolbox\Assessment\Rubric\DataModel\Level\Mapper as RubricLevelMapper;
use Schoolbox\Assessment\Rubric\DataModel\Result\Mapper as RubricResultSetMapper;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Level;
use Schoolbox\Assessment\Term;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfGradableWithResponse;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfReflectionWithResponse;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\LockedChecker;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantMapper;
use Schoolbox\Learning\Assessment\Timeline\Full as FullTimeline;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\InlineGrader\Kami;
use Schoolbox\Learning\InlineGrader\Kami\Connector;
use Schoolbox\Learning\InlineGrader\StudentCarousel;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Api;
use Schoolbox\Learning\Turnitin\Helper as TurnitinHelper;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Storage\FilesystemProxy;
use Schoolbox\TaskDaemon\Producer;
use Schoolbox\User;
use Schoolbox\UserMapper;
use UnexpectedValueException;
use Url;

use function count;

/**
 * Class Controller
 *
 * @package Schoolbox\Learning\Assessment\DueWork
 */
class Controller extends Assessment\Controller
{
    /**
     * Draft and Published marks are stored in two different
     * (but adjacent) areas of the $marks array
     */
    private const MARK_DRAFT_KEY = 'latest';
    private const MARK_PUBLISHED_KEY = 'published';


    /**
     * This is a suffix appended after every Draft mark
     * so users know conclusively and obviously.
     */
    private const MARK_DRAFT_SUFFIX = ' (draft)';


    /**
     * The querystring param for Show Drafts from the ui
     * Note that jquery.marksheet.js hardcodes this value
     * and it needs to match this
     */
    private const SHOW_DRAFTS_KEY = 'showDrafts';


    /**
     * @param UserMapper                            $userMapper
     * @param Mapper                                $errorMapper
     * @param AssessmentDataSource                  $assessmentMapper
     * @param WorkTypeMapper                        $workTypeMapper
     * @param Access                                $access
     * @param Transactor                            $transactor
     * @param Factory                               $bakery
     * @param RubricDataSource                      $rubricMapper
     * @param RubricResultSetMapper                 $resultSetMapper
     * @param DueWorkResponseMapper                 $dueWorkResponseMapper
     * @param ParticipantMapper                     $participantMapper
     * @param Term\Mapper                           $termMapper
     * @param SingleFile\Mapper                     $submissionFileMapper
     * @param Connector                             $kamiConnector
     * @param Kami\Mapper                           $kamiMapper
     * @param ParticipantMapper                     $participantDataSource
     * @param CourseSpecificationMapper             $courseSpecificationMapper
     * @param UnitSpecificationMapper               $unitSpecificationMapper
     * @param Replicator                            $replicator
     * @param Producer                              $producer           for sending the message created by `$messageFactory` to
     *                                                                  taskd
     * @param Turnitin\File\Mapper                  $turnitinFileMapper - for generating the Similarity Report.
     * @param IFeedbackDataSource                   $feedbackDataSource
     * @param \Schoolbox\Resource\Folder\Mapper     $folderMapper
     * @param LatestViewMapper                      $latestViewMapper
     * @param FilesystemProxy                       $filesystemProxy
     * @param StateMapper                           $stateMapper
     * @param RosettaStone                          $rosettaStone
     * @param MessageFactory                        $dueWorkDownloadMessageFactory
     * @param Assessment\Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper              $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper             $lessonPlanDataModelMapper
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        AssessmentDataSource $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        private RubricDataSource $rubricMapper,
        private RubricResultSetMapper $resultSetMapper,
        private DueWorkResponseMapper $dueWorkResponseMapper,
        private ParticipantMapper $participantMapper,
        Term\Mapper $termMapper,
        private SingleFile\Mapper $submissionFileMapper,
        private Kami\Connector $kamiConnector,
        private Kami\Mapper $kamiMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator,
        private Producer $producer,
        private Turnitin\File\Mapper $turnitinFileMapper,
        private Assessment\Feedback\IFeedbackDataSource $feedbackDataSource,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        LatestViewMapper $latestViewMapper,
        private FilesystemProxy $filesystemProxy,
        private StateMapper $stateMapper,
        private RosettaStone $rosettaStone,
        private Download\MessageFactory $dueWorkDownloadMessageFactory,
        protected Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        protected LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        protected LessonPlanDataModelMapper $lessonPlanDataModelMapper,
    ) {
        parent::__construct(
            $userMapper,
            $errorMapper,
            $assessmentMapper,
            $workTypeMapper,
            $access,
            $transactor,
            $bakery,
            $termMapper,
            $participantDataSource,
            $courseSpecificationMapper,
            $unitSpecificationMapper,
            $rosettaStone,
            $replicator,
            $latestViewMapper,
            $this->projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper
        );
    }


    /**
     * Determine if there are any submission files or feedback available for download on the assessment
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
                if ($feedback instanceof Feedback\Model && $feedback->isPublished()) {
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
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @param Model $assessment
     * @throws InvalidArgumentException If assessment due date is null.
     * @throws PDOException
     * @throws UnexpectedValueException
     * @api GET /learning/assessments/:assessmentId/status
     */
    public function getStatus(IAssessable $assessment): void
    {
        /* Find out which users have a draft submission
         * MAYBE you can do this purely in twig...but I'm not enamourd by frontend making
         * those sort of data collecting maneuvers mainly because they don't know when their
         * calls access a potentially expensive method.
         */
        $draftResponses = [];

        foreach ($assessment->getAllParticipants() as $participant) {
            $draftResponse = $participant->findMostRecentDraftResponse();

            if ($draftResponse) {
                $draftResponses[$participant->getUser()->getId()] = $draftResponse->getId();
            }
        }

        $twigData = [
            'errors'            => $this->stateMapper->listDisplayValues(),
            'type'              => Assessment\AssessmentType::objectToString($assessment),
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    $assessment->getTitle(),
                    '',
                ]
            ),
            'assessment'        => $assessment,
            // Only display download link if there are actually files to download
            'download'          => $this->hasDownloadableSubmissionFiles($assessment),
            'rubricMarks'       => RubricIndicatorMarkMapper::getInstance()->findMarkedByAssessment($assessment->getId()),
            'interactionLocked' => (new Assessment\LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment),
            'selfReflect'       => false,
            'selfAssess'        => false,
            /* Find the first valid Blind Review url
             */
            'blindReviewUrl'    => (new StudentCarousel\Blind(
                $this->routeGenerator,
                null,
                $assessment
            ))->getFirstUrl(),
            /* Draft Responses made by each guy
             */
            'draftResponses' => $draftResponses
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dueWork/status.twig',
            $twigData,
            "Status of {$assessment->getTitle()}",
            'submission'
        );
    }


    /**
     * @param Model $assessment
     * @param User  $student
     * @throws MapperException
     * @throws RouteNotFound
     * @throws IOException
     * @throws PListException
     * @throws DOMException
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 165
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        /* Redirect to some kind of third party who spawned this assessment
         */
        $externalRedirectUrl = null;

        /**
         * Canvas custom redirect
         *
         * Ensure Canvas is enabled
         * ensure canvas api creds (url, client id and url) are setup NOTE: we only need CD2 now
         * and in this case is the schoolcode NEWIC
         */
        $canvasUrl = $this->getConfigLoader()->get(ConfigMapper::_CANVAS_URL);
        if (
            !empty($canvasUrl) &&
            !empty($this->getConfigLoader()->get(ConfigMapper::_CANVAS_DATA2_CLIENT_ID)) &&
            !empty($this->getConfigLoader()->get(ConfigMapper::_CANVAS_DATA2_SECRET)) &&
            \Custom\Newic\Redirect::SITE_SCHOOL_CODE === strtolower($this->getConfigLoader()->get('site_school_code'))
        ) {
            try {
                $externalRedirectUrl = (new \Custom\Newic\Redirect(
                    $canvasUrl,
                    new CanvasDb($this->getConfigLoader()),
                    $this->folderMapper,
                ))->generateAssessmentRedirect(
                    $this->getUser(),
                    $assessment
                );
            } catch (\Alaress\Database\Exception $e) {
                // Don't know where to go: just go to Canvas
                $this->captureException($e);
                $externalRedirectUrl = $canvasUrl;
            }
        }

        /* Check if this assessment has a SEB requirement
         * If it does, send you to the download screen
         */
        $sebDownload = $this->getRouteIfAuthorized(
            'learningAssessment.getSebDownload',
            ['assessment' => $assessment,
            'assessmentId' => $assessment->getId()]
        );

        if ($sebDownload && !$this->isSebCheckPasses($assessment)) {
            $this->response = $this->redirectFound($sebDownload);
            return;
        }

        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        $participant = $assessment->getParticipantIfExtant($student);

        // Generate rubric marked grids for every feedback being displayed
        if ($assessment->getUseRubric()) {
            $assessment->setRubricGrid($this->rubricMapper->retrieveGrid($assessment, false));
            if ($participant) {
                foreach ($participant->getAllFeedbacks() as $feedback) {
                    // All feedbacks ought to be due work feedbacks,
                    // and thus have a rubric grid
                    // TODO this returns false if I use the alias, but works correctly if I use the fully qualified name - why?
                    if ($feedback instanceof Feedback\Model) {
                        $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
                    }
                }
            }
        }
        /**
         * record view
         */
        $lastView = new Assessment\Views\LatestView(
            $this->getAccess(),
            $this->latestViewMapper,
            new \Schoolbox\External\User(
                $this->getConfigLoader()->get('external_type'),
                new \External_Class(),
                new \External_User(),
                $this->getUserMapper()
            )
        );
        $lastView->addNewView($assessment, $student, $this->getUser());

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */
        $twigData = [
            'lastViews'    => $lastView->getTwigViews($assessment, $student, $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder())),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    $assessment->getTitle(),
                    '',
                ]
            ),
            'assessment'             => $assessment,
            'student'                => $student,
            'participant'            => $participant,
            'returnUrl'              => $returnUrl,                   // Unencoded URL for use in direct redirection (eg. <a href='{{ returnUrl }}'>)
            'returnUrlEncoded'       => Redirect::encode($returnUrl), // Encoded URL For use as a URL parameter (eg. '?returnUrl={{ returnUrlEncoded }}')
            'timeline'               => $participant ? (new FullTimeline($participant))->getForUser($this->getUser()) : null,
            'authoritativeFeedback'  => $participant ? $participant->findAuthoritativeVisibleFeedback($this->getUser()) : null,
            'selfAssessmentFeedback' => $participant ? $participant->findMostRecentSelfAssessedPublishedFeedback() : null,
            'selfReflect'            => $participant ? (new ValidSelfReflectionWithResponse())->isSatisfiedBy($this->getUser(), $participant) : null,
            'selfAssess'             => $participant ? (new ValidSelfGradableWithResponse())->isSatisfiedBy($this->getUser(), $participant) : null,

            /* Similarity Check status
             */
            'turnitinApiEnabled'     => (new TurnitinHelper($this->getConfigLoader()))->isEnabled($this->getUser()),

            /* Eportfolio Showcase - whether to show the "send to showcase" trophy button
             */
            'canShowcase'            => $assessment->isImplementation() && (new Eportfolio_Authenticator($this->getUser(), $student))->canModify(),
            /* Whether this assessment is showcased on the user's eportfolio
             */
            'isShowcased'            => ShowcaseMapper::getInstance()->isShowcased((int)$assessment->getId(), (int)$student->getId()),

            /* External Url for third party apps which might have generated this assessment
             */
            'externalRedirectUrl' => $externalRedirectUrl
        ];

        $routeArgs = [
            'assessment'   => $assessment,
            'assessmentId' => $assessment->getId(),
            'student'      => $student,
            'studentId'    => $student->getId(),
        ];

        if ($assessment->isSelfAssessable()) {
            $twigData['acknowledgementBlocked'] = false === $this->getRouteIfAuthorized('learningAssessment.postSelfAcknowledge', $routeArgs);
        } else {
            // if not self-assessable, the ack routes are always blocked
            $twigData['acknowledgementBlocked'] = true;
        }

        try {
            $assessment->assertAcceptSubmission($participant);
            $twigData['submissionsBlocked']    = false;
            $twigData['acknowledgementBlocked'] = false;
        } catch (DomainException $e) {
            $twigData['submissionsBlocked'] = $this->getErrorMapper()->generateException($e->getCode())->getMessage();

            // detect the combination of "accept none + self-assess" and allow ACK in this case
            $twigData['acknowledgementBlocked'] = false === ($assessment->isSelfAssessable() && $assessment->isAcceptNone());
        }

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dueWork/summary/student.twig',
            $twigData,
            "{$assessment->getTitle()} - {$student->getFullName()}",
            'submission'
        );
    }


    /**
     * Download all student submissions for a given assessable
     * @param IAssessable $assessment
     * @throws RouteNotFound
     * @throws \Interop\Queue\Exception
     * @throws InvalidDestinationException
     * @throws InvalidMessageException
     * @api   GET /learning/assessments/:assessmentId/submissions/download
     * @since 165
     */
    public function downloadSubmissions(IAssessable $assessment): void
    {
        // Not a due work somehow? Raise a 404 if so
        if (!$assessment instanceof Model) {
            throw new NotFound();
        }

        $userIds = $this->requestQuery->get('userIds');
        $userIds = array_map(
            // Convert strings to integers to avoid problems later.
            static function ($v) {
                return (int)$v;
            },
            $userIds
        );

        // Generate message and send to taskd
        $message = $this->dueWorkDownloadMessageFactory->buildForAssessment(
            $assessment,
            $userIds,
            $this->getUser(),
        );
        $this->producer->send($message);

        // Return whence we came,
        // or to the assessment's status page if not specified
        $encodedReturnUrl = $this->requestQuery->get(Redirect::RETURN_URL);
        $returnUrl        = isset($encodedReturnUrl)
            ? Redirect::decode($encodedReturnUrl)
            : $this->routeGenerator->generate(
                'learningAssessment.getStatus',
                ['assessmentId' => $assessment->getId()],
            );
        $this->response = $this->redirectFound($returnUrl);
    }


    /**
     * Shows a rubric overview
     *
     * @param Model $assessment
     * @throws DomainException
     * @throws MapperException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws DefinitionException
     * @api   GET /learning/assessments/:assessmentId/overview
     * @since 165
     */
    public function getOverview(IAssessable $assessment): void
    {
        // Sort participant by lastname, firstname, and id
        $participants = $assessment->getParticipantUsers()->sortByCallback(
            static function (User $participantA, User $participantB) {
                return strnatcasecmp($participantA->getLastname() ?? '', $participantB->getLastname() ?? '')
                    ?: strnatcasecmp($participantA->getFirstname(), $participantB->getFirstname())
                        ?: strnatcasecmp($participantA->getId(), $participantB->getId());
            }
        );

        $data = [
            'breadcrumbs'  => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                                   ->add(
                                       [
                                           $assessment->getTitle(),
                                           $this->routeGenerator->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]),
                                       ]
                                   )
                                   ->add(['Rubric Report', '']),
            'dueWork'      => $assessment,
            'participants' => $participants,
        ];

        if ($assessment->getUseRubric()) {
            $rubricGrid = $this->rubricMapper->retrieveGrid($assessment, false);
            $assessment = $this->participantMapper->loadParticipationIntoAssessment($assessment);

            $data = array_merge(
                $data,
                [
                'headers' => $this->getRubricHeaders($rubricGrid),
                'marks'   => $this->getMarks($assessment->getAllParticipants()),
                ]
            );
        }

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dueWork/overview.twig',
            $data,
            'Rubric Report'
        );
    }


    /**
     * Downloads a rubric overview
     *
     * @param Model $assessment
     * @throws DefinitionException
     * @throws DomainException
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws UnexpectedValueException
     * @throws Exception
     * @throws \Exception
     * @since 165
     * @api   GET /learning/assessments/:assessmentId/overview/export?format=csv
     */
    public function downloadOverview(IAssessable $assessment): void
    {
        if (!$assessment->getUseRubric()) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_PAGE_NOT_FOUND);
        }

        $rubricGrid = $this->rubricMapper->retrieveGrid($assessment, false);
        $assessment = $this->participantMapper->loadParticipationIntoAssessment($assessment);

        $basename = (new Url($assessment->getTitle()))->getSlug();

        /** @var Model $assessment */
        $content  = $this->array2csv($this->collateResultSquare($assessment->isMarkPerCapability(), $rubricGrid, $assessment->getAllParticipants()));

        /* Maybe we set the Show Drafts option
         */
        $showDraft = (bool)$this->requestQuery->get(self::SHOW_DRAFTS_KEY, false);

        $prefix = '';

        if ($showDraft) {
            $prefix = 'Draft - ';
        }

        $this->response = $this->responseFromContent($content)
                               ->withHeader('Pragma', ' ')
                               ->withHeader('Cache-Control', ' ')
                               ->withHeader('Content-Type', 'text/csv')
                               ->withHeader('Content-Length', strlen($content))
                               ->withHeader('Content-Disposition', "attachment; filename=\"{$prefix}{$basename}.csv\"")
        ;
    }


    /**
     * Get headers for the rubric report or CSV download.
     *
     * @param Grid $grid
     * @return array Grouped by capability name, then indicator ID
     */
    private function getRubricHeaders(Grid $grid): array
    {
        $headers = [];
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            $capability = $capabilityUsage->getCapability();

            /* Index capability identifiers along with their indicator stuff
             */
            if (!isset($headers['capabilities'][$capabilityUsage->getCapability()->getId()])) {
                $headers['capabilities'][$capability->getId()] = [
                    'name' => $capability->getName(),
                    'indicators' => []
                ];
            }

            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicator = $indicatorUsage->getIndicator();

                $headers['capabilities'][$capability->getId()]['indicators'][$indicator->getId()] = $indicator->getName();
            }
        }
        return $headers;
    }


    /**
     * Get rubric marks given for each indicator.
     *
     * @param ICollection|Participant[] $participants
     * @return array capability id, published marks and indicator data grouped in sets indexed by user.id
     */
    private function getMarks(ICollection $participants): array
    {
        $marks = [];

        foreach ($participants as $participant) {
            $latestFeedback = $participant->findMostRecentInstructorGivenFeedback();
            $publishedFeedback = $latestFeedback;
            if (!$latestFeedback) {
                continue;
            }
            // if this feedback is a draft, we need to check if there is published feedback...
            if ($latestFeedback->isDraft()) {
                $publishedFeedback = $participant->findMostRecentInstructorGivenPublishedFeedback();
            } else {
                // ignore latest feedbacks that are published... we only care about draft latest
                $latestFeedback = null;
            }

            try {
                $markedGridLatest = $latestFeedback ? $this->rubricMapper->retrieveMarkedGrid($latestFeedback) : null;
                // if no published feedback default to empty
                $markedGridPublished = $publishedFeedback ? $this->rubricMapper->retrieveMarkedGrid($publishedFeedback) : null;
            } catch (MapperException) {
                // can't really do anything
                continue;
            }
            $studentId = $participant->getUser()->getId();
            if (null !== $markedGridPublished) {
                foreach ($markedGridPublished->getCapabilityMarks() as $capabilityMark) {
                    /* Index cap marks by capability id, per person
                     */
                    $capabilityId = $capabilityMark->getCapabilityUsage()->getCapability()->getId();
                    $marks[$studentId]['capabilityMark'][$capabilityId]['published'] = $capabilityMark->getHumanReadableValue();

                    /* Remember the Capability to which this Indicator belongs
                     * (The Indicators in the csv output don't know their parent Capability)
                     * Arguably you could remember the full capability instance itself, but meh
                     */
                    foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                        $marks[$studentId][$indicatorMark->getIndicatorUsage()->getIndicator()->getId()]['capabilityId'] = $capabilityMark->getCapabilityUsage()->getCapability()->getId();
                        $marks[$studentId][$indicatorMark->getIndicatorUsage()->getIndicator()->getId()][self::MARK_PUBLISHED_KEY] = $indicatorMark;
                    }
                }
            }
            if (null !== $markedGridLatest) {
                foreach ($markedGridLatest->getCapabilityMarks() as $capabilityMark) {
                    /* Index cap marks by capability id, per person
                     */
                    $capabilityId = $capabilityMark->getCapabilityUsage()->getCapability()->getId();
                    $marks[$studentId]['capabilityMark'][$capabilityId][self::MARK_DRAFT_KEY] = $capabilityMark->getHumanReadableValue();

                    /* Remember the Capability to which this Indicator belongs
                     * (The Indicators in the csv output don't know their parent Capability)
                     * Arguably you could remember the full capability instance itself, but meh
                     */
                    foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                        $marks[$studentId][$indicatorMark->getIndicatorUsage()->getIndicator()->getId()]['capabilityId'] = $capabilityMark->getCapabilityUsage()->getCapability()->getId();
                        $marks[$studentId][$indicatorMark->getIndicatorUsage()->getIndicator()->getId()][self::MARK_DRAFT_KEY] = $indicatorMark;
                    }
                }
            }
        }

        return $marks;
    }


    /**
     * Creates a CSV structure for this rubric grid and its participants.
     *
     * @param bool $isMarkPerCapability
     * @param Grid $grid
     * @param Collection $participants
     * @return array
     */
    private function collateResultSquare(bool $isMarkPerCapability, Grid $grid, Collection $participants): array
    {
        // array inside an array that's *nearly* empty...
        $data = [['Name', 'External ID']];

        // put header row in array
        foreach ($this->getRubricHeaders($grid)['capabilities'] as $capabilityData) {
            $indicators = $capabilityData['indicators'];
            $capabilityName = $capabilityData['name'];

            foreach ($indicators as $indicatorId => $indicator) {
                $data[0][] = "{$capabilityName} - {$indicator}";
                $data[0][] = '';
            }

            /* If Capability Mark Type is something other than None,
             * prepare an additional column
             */
            if ($isMarkPerCapability) {
                $data[0][] = 'Overall';
            }
        }

        $data[0][] = 'Total';

        /**
         * Collate by id, each sub-collection will contain only 1 User element
         *
         * @var Collection[] $participantsGrouped
         */
        $participantsGrouped = $participants->groupByCallback(
            function (Participant $participant) {
                return $participant->getUser()->getId();
            }
        )->getAll();

        /* Maybe we set the Show Drafts option
         */
        $showDraft = (bool)$this->requestQuery->get(self::SHOW_DRAFTS_KEY, false);

        /* For each student,
         * try to build a csv row based on capability and indicator usage
         */
        foreach ($this->getMarks($participants) as $studentId => $marks) {
            /** @var Participant $participant */
            $participant = $participantsGrouped[$studentId]->first();

            /* Total marks for this person
             */
            $sumDraft = null;
            $sumPublished = null;

            $row = [
                'name' => $participant->getUser()->formatName('{lastname}, {firstname}'),
                'externalId' => $participant->getUser()->getExternalId(),
            ];

            foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
                $capabilityUsageId = $capabilityUsage->getCapability()->getId();

                foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                    $indicatorUsageId = $indicatorUsage->getIndicator()->getId();

                    /* Indexing to preserve structure and order for students without marks selected
                     * via the rubric grid (ie. descriptor stayed unclicked)
                     */
                    $descriptorIndex = "{$capabilityUsageId}-{$indicatorUsageId}-descriptor";
                    $valueIndex = "{$capabilityUsageId}-{$indicatorUsageId}-value";

                    /* Initialise nodes even if they will contain nothing because the student
                     * doesn't have any marks for these indicator/descriptor combinations
                     */
                    $row[$descriptorIndex] = '';
                    $row[$valueIndex] = '';

                    /* If the student has marks, populate with their values
                     */
                    if (\count($marks)) {
                        // array of all marked indicator ids of the participant
                        $markedIndicatorIds = array_keys($marks);

                        /* If the current descriptor has a draft,
                         * and we enabled drafts,
                         * show its draft value and add a suffix (even if it's null/empty)
                         */
                        if ($showDraft && array_key_exists(self::MARK_DRAFT_KEY, $marks[$indicatorUsageId] ?? [])) {
                            if (in_array($indicatorUsageId, $markedIndicatorIds, true)) {
                                $descriptor = $marks[$indicatorUsageId][self::MARK_DRAFT_KEY]->getDescriptor();
                                $row[$descriptorIndex] = ($descriptor ? $descriptor->getDescription() : '') . self::MARK_DRAFT_SUFFIX;
                                $row[$valueIndex] = $marks[$indicatorUsageId][self::MARK_DRAFT_KEY]->getValue() . self::MARK_DRAFT_SUFFIX;
                            } else {
                                $row[$descriptorIndex] = self::MARK_DRAFT_SUFFIX;
                                $row[$valueIndex] = self::MARK_DRAFT_SUFFIX;
                            }

                            /* Accumulate total marks for this person
                             */
                            $sumDraft += $marks[$indicatorUsageId][self::MARK_DRAFT_KEY]->getValue();
                        } elseif (array_key_exists(self::MARK_PUBLISHED_KEY, $marks[$indicatorUsageId] ?? []) && in_array($indicatorUsageId, $markedIndicatorIds, true)) {
                            $descriptor = $marks[$indicatorUsageId][self::MARK_PUBLISHED_KEY]->getDescriptor();
                            $row[$descriptorIndex] = $descriptor ? $descriptor->getDescription() : '';
                            $row[$valueIndex] = $marks[$indicatorUsageId][self::MARK_PUBLISHED_KEY]->getValue();

                            /* Accumulate total marks for this person
                             */
                            $sumPublished += $marks[$indicatorUsageId][self::MARK_PUBLISHED_KEY]->getValue();
                        }
                    }
                }

                /* Prepare an additional column for Capability Marks
                 */
                if ($isMarkPerCapability) {
                    $capabilityMarkIndex = "{$capabilityUsageId}-mark";

                    /* Decide which kind to show: draft or published
                     *
                     * Note: the student might not have a draft available,
                     * and/or the student might not have a published mark available
                     */
                    $capabilityMark = '';

                    if ($showDraft && isset($marks['capabilityMark'][$capabilityUsageId][self::MARK_DRAFT_KEY])) {
                        $capabilityMark = $marks['capabilityMark'][$capabilityUsageId][self::MARK_DRAFT_KEY];
                        $capabilityMark .= self::MARK_DRAFT_SUFFIX;
                    } elseif (isset($marks['capabilityMark'][$capabilityUsageId][self::MARK_PUBLISHED_KEY])) {
                        $capabilityMark = $marks['capabilityMark'][$capabilityUsageId][self::MARK_PUBLISHED_KEY];
                    }

                    $row[$capabilityMarkIndex] = $capabilityMark;
                }
            }

            /* Total marks for this person
             */
            if (null !== $sumDraft) {
                $row['total'] = $sumDraft . self::MARK_DRAFT_SUFFIX;
            } elseif (null !== $sumPublished) {
                $row['total'] = $sumPublished;
            } else {
                $row['total'] = '';
            }

            /* Trim everything
             */
            $data[] = array_values(array_map(fn ($x) => trim($x ?? ''), $row));
        }

        return $data;
    }


    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array): string
    {
        $fp = fopen('php://temp', 'w');
        foreach ($array as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);
        return $contents;
    }


    /**
     * Displays a form enabling a student to create Submission for a Due Work
     *
     * @param Model $assessment
     * @param User  $student
     * @throws MapperException
     * @throws \League\Flysystem\FilesystemException
     * @since 165
     * @api   GET /learning/assessments/:assessmentId/:participantId/submit
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        $acceptsOnlineSubmissions = $assessment->getAcceptOnline();
        /** @var null|Participant $participant */
        $participant = $assessment->getParticipantIfExtant($student);

        $reviewRoute = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        if ($assessment->isSelfReflectableOnly()) {
            $reviewRoute = ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION;
        } elseif ($assessment->isSelfAssessable()) {
            $reviewRoute = ReturnedWork::REVIEW_ROUTE_SELF_GRADING;
        }

        // Show the feedback for the student, if:
        // * a self-assessment
        // * the most recent feedback is a draft
        //   (if it has been published, they need to re-create the self-assess)
        $feedback = null;
        if ($reviewRoute !== ReturnedWork::REVIEW_ROUTE_INSTRUCTOR && $participant) {
            $feedback = $participant->findMostRecentSelfAssessedFeedback();
            if ($feedback && $feedback->isPublished()) {
                $feedback = null;
            }
        }

        if ($feedback instanceof Feedback\Model) {
            $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
        }

        // Attach rubrics data
        $rubricMapper = RubricDataSource::getInstance();
        if ($assessment->getUseRubric()) {
            $assessment->setRubricGrid($rubricMapper->retrieveGrid($assessment, false));
        }

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        $allResponses = $participant ? $participant->getAllResponses() : new Collection();

        /* Otherwise continue
         */
        $latestResponse = null;
        $editorContents = '';
        $kamiFile = null;
        $kamiSession = null;

        $attachZoneFactory = new Assessment\Attachzone\Factory();

        /* Assume the most recent record is the latest submission
         */
        if ($allResponses->count()) {
            /** @var Submission|null $latestResponse */
            $latestResponse = $participant?->findMostRecentResponse();

            /* If the latest response is a draft, collect all its contents for editing
             */
            if ($latestResponse->isDraft()) {
                /* Files attached to the draft submission
                 * Attach files if any exist
                 */
                $dangerZone = $attachZoneFactory->buildForDueWorkResponse($latestResponse);

                /* Load in the wysiwyg instead of a file attachment,
                 * if the $file uses the wysiwyg editor
                 */
                foreach ($latestResponse->getAllFiles() as $file) {
                    if ($file->getEditableInline()) {
                        $editorContents = $this->filesystemProxy->read($file);
                    }
                }
                // Kami Interactive Worksheet
                // show the existing kami file
            } else {
                /* Otherwise, spawn an entirely new draft
                 * I would not expect it to recycle files from my previous Submitted submission
                 */
                $latestResponse = null;
                $dangerZone     = $attachZoneFactory->buildForDueWorkResponse(new Submission());
            }
        } else {
            /* Init a dangerZone that is ready to accept files for
             * the first ever draft
             */
            $dangerZone = $attachZoneFactory->buildForDueWorkResponse(new Submission());
        }

        // Is the page being displayed in readonly mode?
        $isInteractionLocked = (new LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment);


        // Kami Interactive Worksheet
        if ($this->kamiConnector->isOnline() && $assessment->getKamiWorksheetTemplate() && $assessment->getKamiWorksheetTemplate()->getKamiDocumentId() && $this->getConfigLoader()->get(ConfigMapper::NAME_KAMI_WORKSHEETS_ENABLED)) {
            // kami string if needed.
            $worksheetName = sprintf(Kami\Document::WORKSHEET_TITLE, $assessment->getTitle(), $student->getFullName());
            $kamiGroup = sprintf(Kami\Document::WORKSHEET_GROUP, INSTANCE_ID, $assessment->getBaseContext()->getFolder()->getId(), $assessment->getId());

            //if this is an existing attempt, then fetch the kami file
            if ($latestResponse) {
                // Kami Worksheet - fetch Kami Doc
                foreach ($latestResponse->getAllFiles() as $file) {
                    if ($file->getIsWorksheet()) {
                        $kamiFile = $file->getKami()?->getKamiDocumentId();
                    }
                }
            }
            if (!$kamiFile) {
                // the latest response has no kami worksheet, or there was no previous response.

                // try and use the last attempt (published)
                /** @var Submission|null $lastResponse */
                $lastResponse = $participant?->findMostRecentResponse();
                if ($lastResponse) {
                    foreach ($lastResponse->getAllFiles() as $file) {
                        if ($file->getIsWorksheet()) {
                            $kamiFile = $file->getKami()?->getKamiDocumentId();
                        }
                    }
                }

                // if still no kami file from a previous attempt, just spawn a new one
                if (!$kamiFile) {
                    $kamiDoc = $this->kamiConnector->cloneWorksheet($worksheetName, $assessment->getKamiWorksheetTemplate()->getKamiDocumentId(), $kamiGroup);
                    $kamiFile = $kamiDoc->getId();
                } else {
                    /**
                     * kami file exists. 2 options
                     * 1. when the student starts a new attempt we save a PDF export of the annotated file onto the old attempt, then move the kami hash to the new attempt
                     * 2. we download the kami file of the previous attempt and pipe that to the kami upload endpoint which according to kami will work, and can transfer the annotations
                     * option 2 has the issue, that the cloned worksheet will appear in the same group (student may have duplicate entries)
                     * 3. we move the kami file to the new attempt, and clear the file from the old attempt.
                     *
                     * I will go with option 3.
                     * we will cache the kami ID for the student. (which we already have as $kamiFile)
                     * (then in the save function) clear the student submission from the old attempt, and move along
                     */
                }
            }

            // Kami Worksheet - display the student view of the student worksheet
            // with perms to edit the kami if we are allowing editing of this attempt
            $kamiSession = $this->kamiConnector->viewSession(
                $kamiFile,
                $this->getUser(),
                !$isInteractionLocked,
            );
        }

        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);

        // Are we self assessing or only self reflecting?
        $selfReflect = $reviewRoute === ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION;
        $selfAssess = $reviewRoute === ReturnedWork::REVIEW_ROUTE_SELF_GRADING;

        if ($this->requestQuery->get(Redirect::RETURN_URL)) {
            $returnUrl = Redirect::decode($this->requestQuery->get(Redirect::RETURN_URL));
        } else {
            $returnUrl = '/homepage/' . $assessment->getBaseContext()->getFolder()->getId();
        }

        $data = [
            'breadcrumbs'    => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(
                [
                    'Create Response for ' . $assessment->getTitle(),
                    '',
                ]
            ),
            'assessment' => $assessment,

            /* I need this, otherwise Combobox dies catastrophically
             */
            'errors' => [],

            'reviewRoute'    => $reviewRoute,
            'studentView'    => true,
            'student'        => $student,
            'participant'    => $participant,
            'feedback'       => $feedback,
            'attachzone'     => $dangerZone ? $dangerZone->getTwigContext() : null,
            'response'       => $latestResponse,
            'editorContents' => $editorContents,
            'policy'         => $assessment->getEnablePolicy() ? $this->getConfigLoader()->get('lms_student_policy') : '', // only use the policy, if this assessment is showing the policy
            'errorMessage'   => $errorMessage,
            'readonly'       => $isInteractionLocked,
            'selfReflect'    => $selfReflect,
            'selfAssess'     => $selfAssess,
            'acceptsOnline'  => $acceptsOnlineSubmissions,
            'returnUrl'      => $returnUrl,
            'kamiSession'    => $kamiSession,
            'kamiFileId'     => $kamiFile,

            /* Only show these things if the assessment is self-gradable */
            'showDescriptorValue' => $selfAssess,
            'showGroupTotal'      => $selfAssess && ($assessment->getCapabilityMarkType() !== SubmissionBox\MarkType::SLUG_NONE),
            'showRubricTotal'     => $selfAssess
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dueWork/createSubmission.twig',
            $data
        );
    }


    /**
     * Try to use a pseudo-standardised json payload return structure (I think)
     *
     * @param string $errorMessage
     * @param string $redirectTo
     * @param int    $errorCode
     */
    private function configureResponseWithError(string $errorMessage, string $redirectTo, int $errorCode = 403): void
    {
        if ($this->isXhrRequest()) {
            $result['success'] = false;
            $result['message'] = $errorMessage;
            $this->response = $this->responseAsJson($result)->withStatus($errorCode);
        } else {
            // Show some error message?
            $this->getSession()->getSegment(static::class)->set('errorMessage', $errorMessage);
            $this->response = $this->redirectFound($redirectTo);
        }
    }


    /**
     * Save a Response, on behalf of a User, for a particular Assessment.
     * If the assessment has self-reflection/assessment, this will also save any feedback the student gave themselves.
     *
     * @param Model $assessment
     * @param User  $student
     * @return void
     * @throws MapperException If unable to create new Response object
     * @throws Deleted If specified Response is deleted
     * @throws RouteNotFound If unable to generate a specified Route
     * @throws \Schoolbox\File\Exception
     * @throws Exception
     * @api POST /learning/assessments/:assessmentId/:studentId/submit
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        $requestPost = $this->requestPost;

        /* Set in javascript when automatically saving a draft
         * Careful the negation
         */
        $isFinal = !$requestPost->get('saveDraft');
        $body    = $requestPost->get('body');

        /* Determine whether a finalised submission could be initialised
         * Check that you don't exceed the specified rate limit
         * Return error message if you attempted to resubmit too soon
         */
        if ($isFinal) {
            try {
                (new ValidFinalisedSubmission())->isSatisfiedBy($assessment, $student, $this->getConfigLoader()->get('submission_window'));
            } catch (DomainException $e) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_SUBMISSION_WINDOW_EXCEEDED);
            }
        }

        if ($requestPost->get('responseId')) {
            // Load an existing response to update
            $response = $this->dueWorkResponseMapper->findById($requestPost->get('responseId'));

            /* @var Response\Model $response */
            //check if $response has already been finalised, if so error
            if (!$response->isDraft()) {
                //cannot update
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_WORK_SUBMITTED);
            }
        } else {
            // Create a new response
            $response = $this->dueWorkResponseMapper->createModel()
                                                    ->setSubmittedBy($this->getUser())
                                                    ->setSubmittedOnBehalfOf($student)
                                                    ->setCreatedAt(Alaress_Date_Format::now())
                                                    ->setUpdatedAt(Alaress_Date_Format::now());
        }
        $response->setAssessable($assessment);

        // Replace files with what was actually submitted
        $response->setAllFiles(new Collection());

        /* Add the online submission to this response's list of files
         * only if there is content in it.
         */
        if (trim(strip_tags($body ?? '')) !== '') {
            $response->attachOnlineSubmissionContent($body);
        }

        // Make one of those factories that builds storages from hashes
        $factory = new CollectorFactory();
        if (!empty($requestPost->get('attachment'))) {
            // Validate attachment list can actually be built
            try {
                $attachments = $factory->build($requestPost->get('attachment'))->getAll();
            } catch (InvalidArgumentException) {
                $errorMessage = 'One or more files were not able to be attached';
                $redirect     = $this->routeGenerator->generate(
                    'learningAssessment.getCreateResponseForm',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId'    => $student->getId(),
                    ]
                );

                $this->configureResponseWithError($errorMessage, $redirect);
                return;
            }

            foreach ($attachments as $attachment) {
                /* Don't stack the online-submission.html file here, it should never
                 * appear in the attached files list at all (it should always be
                 * loaded into the wysiwyg)
                 */
                $attachedFile = $this->submissionFileMapper->createModel()
                                                           ->setStorage($attachment)
                                                           ->setFilename($attachment->getFilename())
                                                           ->setEditableInline(false);

                /* Stack it up
                 */
                $response->getAllFiles()->add($attachedFile);
            }
        }

        // Kami worksheet - save / update
        if ($requestPost->get('kamiFileId') && $this->getConfigLoader()->get(ConfigMapper::NAME_KAMI_WORKSHEETS_ENABLED)) {
            $kamiFileId = $requestPost->get('kamiFileId');
            $worksheetName = sprintf(Kami\Document::WORKSHEET_TITLE, $assessment->getTitle(), $student->getFullName());

            try {
                /** @var Kami\Model $kamiModel */
                $kamiModel = $this->kamiMapper->findOneByColumn('kami_document_id', $kamiFileId);
                // remove the worksheet flag from the submission file object
                $oldSubFile = clone $kamiModel->getStudentSubmissionFile();
                $this->submissionFileMapper->save($oldSubFile->setIsWorksheet(false)->setKami(null));
            } catch (Deleted $e) {
                /** @var Kami\Model $kamiModel */
                $kamiModel = $e->getDeletedObject();
                $kamiModel->setDeletedAt(null);
            } catch (MapperException $e) {
                $kamiModel = $this->kamiMapper->createModel()
                    ->setType(Kami\Model::TYPE_STUDENT_SUBMISSION_FILE_ANNOTATION)
                    ->setKamiDocumentId($kamiFileId)
                    ->setStatusDone();
                $this->kamiMapper->save($kamiModel);
            }

            // apparently we need to always create a new student submission object...
            /** @var SubmissionFile $kamiFile */
            $kamiFile = $this->submissionFileMapper->createModel()
                ->setFilename($worksheetName)
                ->setKami($kamiModel)
                ->setIsWorksheet(true)
                ->setStorage($assessment->getWorksheetTemplate())
                ->setSubmission($response);
            $response->getAllFiles()->add($kamiFile);
        }

        // If we're saving the final version of the submission (i.e. not a draft) ram the current datetime in
        if ($isFinal) {
            $acceptsOnline = $assessment->getAcceptOnline();
            $notSubmittedInPerson = !$requestPost->getBool('submitted_in_person');
            $notHasKamiFile = !$requestPost->get('kamiFileId', false);

            // should there be files attached? fail if we didn't get any
            if ($acceptsOnline && $notSubmittedInPerson && $response->getAllFiles()->count() === 0 && $notHasKamiFile) {
                $errorMessage = 'One or more files failed to attach to your submission; please try again.';
                $redirect     = $this->routeGenerator->generate(
                    'learningAssessment.getCreateResponseForm',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId'    => $student->getId(),
                    ]
                );

                $this->configureResponseWithError($errorMessage, $redirect);
                return;
            }

            $response->setSubmittedAt(Alaress_Date_Format::now());

            // Kami worksheet
            if (isset($kamiModel)) {
                // export students document to pdf, store this to this attempt
                $this->kamiConnector->initiatePollingForKamiDownload(
                    $kamiModel,
                    'submission',
                    $this->getUser(),
                );
            }
        }

        // Save the response before attempting to save any feedback: we need a response ID to be able to attach the feedback
        $this->dueWorkResponseMapper->save($response);

        // re save the kami file and submission file with all things attached
        if (isset($kamiModel, $kamiFile)) {
            // force the worksheet to be true (we are a kami worksheet file), and this was being dropped, somehow..
            $kamiFile->setIsWorksheet(true);
            $this->submissionFileMapper->save($kamiFile);
            $this->kamiMapper->save($kamiModel->setStudentSubmissionFile($kamiFile));
        }

        // Check whether we are self-assessing, or only self-reflecting; default is the instructor route, which is neither
        $reviewRoute = ReturnedWork::REVIEW_ROUTE_INSTRUCTOR;
        if ($assessment->isSelfReflectableOnly()) {
            $reviewRoute = ReturnedWork::REVIEW_ROUTE_SELF_REFLECTION;
        } elseif ($assessment->isSelfAssessable()) {
            $reviewRoute = ReturnedWork::REVIEW_ROUTE_SELF_GRADING;
        }

        // Students can't mark themselves as requiring resubmission
        $resubmissionRequired = false;
        // Self-reflectors can't attach files to feedback, so this will always be null
        $attachment = null;
        // And we only need to know if the feedback was submitted from the inline grader in order to determine if
        // we need to process attachments, so this is a nope too
        $fromInlineGrader = false;

        // Only try to create a feedback if this due work uses self reflect
        if ($reviewRoute != ReturnedWork::REVIEW_ROUTE_INSTRUCTOR) {
            // Reload assessment's participation.
            // There's a rare case where if a teacher submits feedback without
            // being a participant: their participation is added but not
            // recognised yet.
            $assessment  = $this->participantMapper->loadParticipationIntoAssessment($assessment);
            $participant = $assessment->getParticipant($student);
            $comment     = $requestPost->get('comment');
            // There might not be a mark: due works may have mark type none, in which case no mark will be posted
            $mark = $requestPost->get('mark') ?? null;
            // Awful hack because feedback factory expects a string (which it immediately casts to a bool) >.<
            $publish = $isFinal ? '1' : '0';
            // This gets used to decide whether to create a new feedback, or update an existing one
            $mostRecentFeedback = $participant->findMostRecentSelfAssessedFeedback();
            // Check whether the latest feedback has rubric data set.
            $hasRubric = isset($requestPost['rubric']);
            if (
                $mostRecentFeedback !== null
                && $mostRecentFeedback->getAssessable() instanceof Model
                && $mostRecentFeedback->getAssessable()->getUseRubric()
            ) {
                $hasRubric = true;
            }

            $hasFeedbackContent = $mark || $comment || $hasRubric;

            // only create a feedback if there's feedback content
            if ($hasFeedbackContent) {
                $dueWorkFeedbackFactory = new Feedback\Factory(
                    $this->feedbackDataSource,
                    $assessment->getParticipant($student),
                    $this->getUser(),
                    $mark,
                    $resubmissionRequired,
                    $comment,
                    $publish,
                    $attachment,
                    $fromInlineGrader,
                    $reviewRoute,
                    $mostRecentFeedback,
                    $response
                );

                $feedback = $dueWorkFeedbackFactory->produce();

                // Save the feedback, or delete it if the latest feedback is an...un-feedback
                // TODO do we want to allow students self reflecting to undo their feedback?
                // The only way this could happen is if they gave themselves feedback in a draft, then came back and deleted the draft feedback completely and submitted
                $isNewFeedback = $feedback->getId() === null;
                if (
                    !$hasRubric
                    && !$isNewFeedback
                    && $feedback->isEmpty()
                ) {
                    $this->feedbackDataSource->delete($feedback);
                } else {
                    try {
                        $this->feedbackDataSource->save($feedback);
                    } catch (RangeException $e) {
                        throw new Exception(sprintf('Invalid mark provided: %s', $e->getMessage()));
                    }
                }

                // Process the rubric
                $factory = new \Schoolbox\Assessment\Rubric\MarkedGrid\Factory();

                // Does this assessment use rubrics?
                // Save the rubric if so, even if nothing is there.
                if ($hasRubric) {
                    $markedGrid = $factory->buildFromPostData(
                        $this->rubricMapper->retrieveMarkedGrid($feedback),
                        $requestPost['rubric'] ?? [],
                    );
                    $this->rubricMapper->persistMarkedGrid($markedGrid);

                    // Update the feedback with the rubric marks
                    $feedback->setRubricMarkedGrid($markedGrid);
                    $this->feedbackDataSource->save($feedback);
                    $response->setAllFeedback($response->getAllFeedback()->add($feedback));
                }
            }
        }

        // Save the response
        $this->dueWorkResponseMapper->save($response);

        // If not a draft, check if any files should be uploaded to Kami
        if ($isFinal) {
            $this->enqueueKamiUploads($response->getAllFiles());
        }

        /* Similarity Report endpoints should only spawn upon saving a Submitted IResponse instance
         * This screen is shared between self-reflection and non-self-reflection steps
         * Self-reflection's Similarity Check stubs saving .......happens elsewhere
         *
         * Similarity Check must be enabled to use plag or turnitin
         * This is processed in a detached function, so it doesn't hang the ui
         * while it's executing, since accessing third party api can be slow
         */
        $detachedFunction = null;

        /** @var Model $assessment */
        if ($isFinal && $response->getId() && $assessment->getSimilarityCheckEnabled()) {
            /* Only one of these can be active at a time, but both universes can co-exist
             * Specifically enable turnitin if its api key was set
             */
            if ((new TurnitinHelper($this->getConfigLoader()))->isConfigured()) {
                $turnitinMapper = Turnitin\File\Mapper::getInstance();

                /* Set up a turnitin stub record
                 * This allows it to be picked up and processed by an async script later
                 * if it doesn't start processing via the $detachedFunction
                 */
                $models = [];

                foreach ($response->getAllFiles() as $currentFile) {
                    $model = $turnitinMapper->saveStub($currentFile);

                    if ($model) {
                        $models[] = $model;
                    }
                }

                /* We need this api
                 */
                $api = new Api(
                    $this->getConfigLoader()->get('site_version'),
                    $this->getConfigLoader()->get('turnitin_api_host'),
                    $this->getConfigLoader()->get('turnitin_api_key')
                );

                /* Set up detached function so turnitin can continue in the background
                 */
                $instanceIdentifier = $this->getConfigLoader()->get('site_type');
                $assessmentMapper   = $this->getAssessmentMapper();
                $detachedFunction   = static function () use ($api, $models, $instanceIdentifier, $assessmentMapper) {
                    /* Set up a record processor
                     */
                    $processor = (new Turnitin\File\Process\Transmit(
                        $instanceIdentifier,
                        $api,
                        Turnitin\File\Mapper::getInstance(),
                        Turnitin\Group\Mapper::getInstance(),
                        UserMapper::getInstance(),
                        SubmissionBoxMapper::getInstance(),
                        SingleFile\Mapper::getInstance(),
                        $assessmentMapper,
                    ));

                    /* Shank them all up
                     */
                    foreach ($models as $model) {
                        $processor->process($model);
                    }
                };
            }
        }

        if (null !== $detachedFunction) {
            $this->postOutputCallbacks[] = $detachedFunction;
        }

        /* Asynchronously shove it into plagscan, so we don't make the user wait ages
         * for it to read 1 byte at a time >_<
         */

        /* Saving drafts from the wysiwyg
         */
        if ($this->isXhrRequest()) {
            $result['success'] = true;
            $result['message'] = 'OK';
            $result['id']      = $response->getId();

            $this->response = $this->responseAsJson($result)->withStatus(200);
        } else {
            // Redirect after saving to Due Work Summary for particular student.
            $this->response = $this->redirectFound(
                $this->routeGenerator->generate(
                    'learningAssessment.getSummary',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId'    => $student->getId()
                    ]
                ) . (
                    ($requestPost->get('returnUrl') === null) ? '' :
                    '?returnUrl=' .  Redirect::encode($requestPost->get('returnUrl'))
                )
            );
        }
    }


    /**
     * This takes a combined asynchronous payload:
     *
     * let payload = {
     *     'draft': {
     *         'ids': [],
     *         'overdue': false
     *     },
     *     'offline': {
     *         'ids': [],
     *         'overdue': false
     *     }
     * }
     *
     * ...and forcibly submits the latest draft for the ids specified in payload.draft.ids
     * and creates a receipt of submission for the ids specified in payload.offline.ids
     *
     * In the case of assessments that are overdue:
     * If the teacher elects, they can decide if these submissions will use the current time
     * (penalising students for being late) or use teacher discretion to set the submission
     * time to be just before the formal due date.
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function postManageSubmissions(IAssessable $assessment): void
    {
        $postData = $this->requestPost->getArrayCopy();
        print_r($postData);
        $code = 200;

        $data = [
            'success' => true,
            'message' => null
        ];

        $this->response = $this->responseAsJson($data)->withStatus($code);
    }


    /**
     * Acknowledge receipt of (potentially offline) submission from dueWork status page
     *
     * Creates an empty SOBO. Needs a different route to postCreateResponse()
     * to cater for different auth rules.
     *
     * @param Model $assessment
     * @param User              $student
     * @api   POST /learning/assessments/:assessmentId/:studentId/received
     * @since 17.0
     */
    public function postStatusAcknowledge(IAssessable $assessment, User $student): void
    {
        try {
            if ($assessment->isOverdue() || $assessment->isPastClose()) {
                // If the 'submissionWasOnTime' parameter is true, then this is a late acknowledgement of an event that occurred earlier.
                // Otherwise, set submission time to 'now' to align with the behaviour before parameter 'submissionWasOnTime' was introduced.
                $submittedLate  = !$this->requestPost->getBool('submissionWasOnTime', false);
                $submissionTime = ($submittedLate) ? Alaress_Date_Format::now() : $assessment->getDueAt()->addSeconds(-1); // 1 second before due time.
            } else {
                $submittedLate  = false;
                $submissionTime = Alaress_Date_Format::now();
            }

            $response = $this->persistAcknowledgementResponse($assessment, $student, $submissionTime);
            $data     = [
                'success'       => true,
                'id'            => $response->getId(),
                'submittedLate' => $submittedLate
            ];
            $code     = 200;
        } catch (PDOException $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $code = 403;
        }
        $this->response = $this->responseAsJson($data)->withStatus($code);
    }


    /**
     * Acknowledge receipt of (potentially offline) submission
     *
     * Creates an empty SOBO. Needs a different route to postCreateResponse()
     * to cater for different auth rules.
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @api   POST /learning/assessments/:assessmentId/:studentId/ack
     * @since 17.0
     */
    public function postPendingAcknowledge(IAssessable $assessment, User $student): void
    {
        try {
            if ($assessment->isOverdue() || $assessment->isPastClose()) {
                // If the 'submissionWasOnTime' parameter is true, then this is a late acknowledgement of an event that occurred earlier.
                // Otherwise, set submission time to 'now' to align with the behaviour before parameter 'submissionWasOnTime' was introduced.
                $submittedLate  = !$this->requestPost->getBool('submissionWasOnTime', false);
                $submissionTime = ($submittedLate) ? Alaress_Date_Format::now() : $assessment->getDueAt()->addSeconds(-1); // 1 second before due time.
            } else {
                $submissionTime = Alaress_Date_Format::now();
            }

            $this->persistAcknowledgementResponse($assessment, $student, $submissionTime);
            $participant = $assessment->getParticipant($student);

            $this->response = $this->responseFromTemplateUnthemed(
                '@learning/fragments/toMarkParticipant.twig',
                [
                    'participant' => $participant,
                    'assessment'  => $participant->getAssessable(),
                ]
            );
            $code = 200;
        } catch (PDOException $e) {
            $this->response = $this->responseAsJson(
                [
                    'success' => false,
                    'message' => $e->getMessage(),
                ]
            );
            $code = 403;
        }
        $this->response = $this->response->withStatus($code);
    }


    /**
     * Action for a due work participant to create an empty acknowledgement
     * response.
     *
     * If the assessment doesn't allow self-reflect or self-assessment, this
     * will simply create the acknowledgement response.
     *
     * If it does, it will:
     * * Redirect to the self-reflect screen for the most recent draft response
     *   so the participant may reflect on their work, and publish from there
     * * If there is no draft; a new draft acknowledgement response will be
     *   created, and then will redirect to the self-reflect screen for that
     *   response
     *
     * TODO merge common code w/ postPendingAcknowledge, postStatusAcknowledge
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws RouteNotFound
     * @api   POST /learning/assessments/:assessmentId/:studentId/ackDraft
     * @since 18.0
     */
    public function postSelfAcknowledge(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);
        $selfReview  = $assessment instanceof Model && $assessment->isSelfAssessable();

        // Route and arguments for location to redirect to once acknowledged.
        $route  = 'learningAssessment.getSummary';
        $params = [
            'assessment'   => $assessment,
            'assessmentId' => $assessment->getId(),
            'student'      => $student,
            'studentId'    => $student->getId(),
        ];

        try {
            if ($selfReview) {
                $response = $participant ? $participant->findMostRecentDraftResponse() : null;
                if (!$response) {
                    $response = $this->persistAcknowledgementResponse($assessment, $student, null);
                }

                $route                = 'learningAssessment.getCreateResponseForm';
                $params['response']   = $response;
                $params['responseId'] = $response->getId();
            } else {
                $response = $this->persistAcknowledgementResponse($assessment, $student, Alaress_Date_Format::now());
            }

            $data = [
                'success'       => true,
                'id'            => $response->getId(),
                'submittedLate' => $assessment->isOverdue() || $assessment->isPastClose(),
                'location'      => $this->getRouteIfAuthorized($route, $params, $this->getUser()),
            ];
            $code = 200;
        } catch (PDOException $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $code = 403;
        }
        $this->response = $this->responseAsJson($data)->withStatus($code);
    }


    /**
     * Common logic for 'dueWork status' and 'pending assessments' pages 'acknowledgement of submission receipt'.
     *
     * @param Model                    $dueWork
     * @param User                     $student
     * @param Alaress_Date_Format|null $submittedDate Date this was submitted at, null if draft
     * @return Submission
     */
    private function persistAcknowledgementResponse(Model $dueWork, User $student, ?Alaress_Date_Format $submittedDate): Response\Model
    {
        $response = $this->dueWorkResponseMapper->createModel()
                                                ->setAssessable($dueWork)
                                                ->setSubmittedBy($this->getUser())
                                                ->setSubmittedOnBehalfOf($student)
                                                ->setSubmittedAt($submittedDate)
                                                ->setCreatedAt(Alaress_Date_Format::now())
                                                ->setUpdatedAt(Alaress_Date_Format::now());

        $this->dueWorkResponseMapper->save($response);

        // Ensure we update our domain graph so callers to this method can rely on the graph being accurate
        $participant = $dueWork->getParticipantIfExtant($student);
        if (!$participant) {
            $dynamicPlan = $dueWork->getDynamicPlan();
            $participant = new Participant($student, $dynamicPlan, $this->projectResultCacheMapper);
            /** @noinspection NullPointerExceptionInspection (because we can't have no participant without a dynamic plan) */
            $dynamicPlan->getParticipants()[$student->getId()] = $participant;
        }
        $participant->getAllResponses()[$response->getId()] = $response;

        return $response;
    }


    /**
     * @param IAssessable $assessment
     * @return array
     * @throws MapperException
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        // setup some data...
        $data = [
            'acceptingResponses' => true,
            'attachzone'         => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
            'allMarkTypes'       => $this->getAllMarkTypes($assessment),
            'allGradeSchemes'    => $this->getAllGradeSchemes(),
            'selfReviewTypes'    => [
                'none'    => [
                    'value' => SubmissionBox::SELF_REVIEWABILITY_NONE,
                    'label' => 'None',
                ],
                'reflect' => [
                    'value' => SubmissionBox::SELF_REVIEWABILITY_REFLECT_ONLY,
                    'label' => $this->rosettaStone->translate('selfReflection', false, true)
                ],
                'full' => [
                    'value' => SubmissionBox::SELF_REVIEWABILITY_FULL,
                    'label' => sprintf(
                        '%s + %s',
                        $this->rosettaStone->translate('selfReflection', false, true),
                        $this->rosettaStone->translate('selfAssessment', false, true)
                    )
                ],
            ],
        ];

        if ($assessment instanceof Model) {
            // ...append rubric-related data...
            $data = array_merge($data, $this->generateRubricTwigData($assessment));
        }

        // ...and scene.
        return $data;
    }


    /**
     * Common logic when generating Rubrics
     *
     * @param Model $dueWork
     * @return array
     * @throws MapperException
     */
    private function generateRubricTwigData(Model $dueWork): array
    {
        $data = [];

        $rubricZone = new Zone('rubric');
        // JS file.type isn't set reliably enough to use text/csv mime filter
        $rubricZone
            ->setFileVar('rb_attach[]')
            ->setAttachPrompt('Import a rubric')
            ->useImportExpiryDate();
        //->setMimeFilter('text/csv');
        $data['rubricAttachZone'] = $rubricZone->getTwigContext();

        $resultSets = $this->resultSetMapper->findAll();

        $rs = new Collection();

        foreach ($resultSets as $result) {
            if (!$rs[$result->getResultSetId()] instanceof ICollection) {
                $rs[$result->getResultSetId()] = $this->resultSetMapper->createCollection();
            }
            $rs[$result->getResultSetId()][$result->getValue()] = $result;
        }

        $data['rubricResultSets'] = $rs;

        // Do any students have marks for this rubric?
        $levelIds      = RubricLevelMapper::getInstance()->findIdsByColumn('b.assessment_id', $dueWork->getId());
        $descriptorIds = RubricDescriptorMapper::getInstance()->findIdsByColumn('level_id', $levelIds);
        $countMarkIds  = RubricIndicatorMarkMapper::getInstance()->countByColumnList(
            [
                'descriptor_id' => $descriptorIds,
                'valid'         => true,
            ]
        );

        $data['rubricHasMarks'] = $countMarkIds > 0;

        // The rubric grid can be used as is without Twig-wrangling
        $rubricGrid = $this->rubricMapper->retrieveGrid($dueWork, true);
        if (count($rubricGrid->getLevels()) === 0) {
            // Two fake levels
            $rubricGrid->getLevels()
                       ->add(new Level())
                       ->add(new Level());
        }
        $data['rubricGrid'] = $rubricGrid;

        return $data;
    }


    /**
     * Update particular fields of an existing IAssessable
     *
     * @param Model $assessment
     * @return void
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    public function patchUpdateAssessment(IAssessable $assessment): void
    {
        /* TODO: Make this more generalised
         * For now the only utility here is to update Skip Unsubmitted setting
         */
        $showSubmittedOnly = $this->request->getParsedBody()['showSubmittedOnly'] ?? 0;
        if ((int)$showSubmittedOnly) {
            $assessment->showOnlySubmittedStudents();
            $skipUnsubmitted = true;
        } else {
            $assessment->showAllSubmissibleStudents();
            $skipUnsubmitted = false;
        }

        /* Persist checkbox setting
         */
        try {
            $submitBox = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $assessment->getId());
            $submitBox->setSkipUnsubmitted($skipUnsubmitted);
            SubmissionBoxMapper::getInstance()->save($submitBox);
        } catch (MapperException) {
            // noop - submission box doesn't exist
        }

        $this->response = $this->responseAsJson(null)->withStatus(204);
    }


    /**
     * Enqueue any eligible files for upload to Kami.
     *
     * Must be called *after* the files are saved to ensure their IDs exist
     *
     * @param Collection|SubmissionFile[] $files
     * @return GenericCollection|Kami\Model[]
     * @throws MapperException
     */
    private function enqueueKamiUploads(Collection $files): GenericCollection
    {
        $kamiModels = $this->kamiMapper->createCollection();

        // Short-circuit if Kami is not enabled
        if (!$this->kamiConnector->isEnabled()) {
            return $kamiModels;
        }

        // Check files have been saved so Kami docs can point to them
        $fileIds = $files->map(
            function (SubmissionFile $file) {
                if (empty($file->getId())) {
                    throw new LogicException("File '{$file->getBasename()}' has not been saved, cannot process");
                }
                return $file->getId();
            }
        );

        // Check if any files have already been queued for kami upload
        $existingKamiModels = $this->kamiMapper->findByColumn('student_submission_file_id', $fileIds);
        $existingFileIds    = $existingKamiModels->map(
            function (Kami\Model $kamiModel) {
                return $kamiModel->getStudentSubmissionFile()->getId();
            }
        )->getAll();
        $existingFileIds    = array_combine($existingFileIds, $existingFileIds);

        foreach ($files as $file) {
            if ($file->getEditableInline()) {
                // For now, we ignore the WYSIWYG editor contents.
                continue;
            }
            if (array_key_exists($file->getId(), $existingFileIds)) {
                // Don't re-enqueue if already queued
                continue;
            }
            if (!array_key_exists(strtolower($file->getExtension()), Kami\Model::ALLOWED_FILE_TYPES)) {
                // Don't enqueue if extension not eligible for Kami
                continue;
            }
            // File is eligible, and not yet queued.

            /* Try to find out if this file already existed in kami
             *
             * If it did, just skip past it - there's been no consideration
             * for what happens to dupes, plus, mapper cache messes with you
             */
            try {
                $kamiModel = $this->kamiMapper->findOneByColumn('student_submission_file_id', $file->getId(), ['cache' => false]);
            } catch (MapperException) {
                /* Create a new one if it didn't already exist
                 */
                $kamiModel = $this->kamiMapper->createModel()
                    ->setType(Kami\Model::TYPE_STUDENT_SUBMISSION_FILE_ANNOTATION)
                    ->setStudentSubmissionFile($file)
                    ->setStatusQueued();

                $this->kamiMapper->save($kamiModel);
            }

            /* Pile it into whatever lookup this is
             */
            $kamiModels[$kamiModel->getId()] = $kamiModel;
        }

        return $kamiModels;
    }


    /**
     * Index screen
     *
     * @param IAssessable $assessment
     * @throws RouteNotFound
     */
    public function getSimilarityReport(IAssessable $assessment): void
    {
        $this->response = $this->responseFromTemplate('@learningAssessment/dueWork/similarityReport.twig', [
            'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())->add([
                $assessment->getTitle(),
                $this->getRouteIfAuthorized('learningAssessment.getStatus', [
                    'assessment' => $assessment,
                    'assessmentId' => $assessment->getId()
                ])
            ])->add([
                'Similarity Report',
                ''
            ]),
            'assessment' => $assessment,
            'payloadUrl' => $this->routeGenerator->generate('learningAssessment.getSimilarityReportPayload', [
                'assessment' => $assessment,
                'assessmentId' => $assessment->getId()
            ])
        ], "{$assessment->getTitle()} Similarity Report");
    }


    /**
     * Get a list of all the students' most recent submissions, their files,
     * and corresponding turnitin reports and assorted metadata. This is used
     * as the payload endpoint for the Similarity Report.
     */
    public function getSimilarityReportPayload(IAssessable $assessment): void
    {
        $payload = (new Turnitin\SimilarityReportPayload($this->turnitinFileMapper, $this->getUserMapper(), $this->routeGenerator))
            ->generate($assessment);

        $this->response = $this->responseAsJson($payload);
    }


    /**
     * Kami interactive worksheet teacher view
     *
     * @api   GET /learning/assessments/:assessmentId/worksheet
     * @since 24.0
     *
     * @param IAssessable $assessment
     * @return void
     * @throws MapperException\NotFound
     */
    public function getWorksheet(IAssessable $assessment): void
    {
        if (!($assessment instanceof Model && $assessment->getKamiWorksheetTemplate())) {
            throw new MapperException\NotFound();
        }
        $kamiGroup = sprintf(Kami\Document::WORKSHEET_GROUP, INSTANCE_ID, $assessment->getBaseContext()->getFolder()->getId(), $assessment->getId());
        $session = null;
        try {
            $session = $this->kamiConnector->viewSessionForGroup(
                $kamiGroup,
                $this->getUser(),
                true,
            );
        } catch (Kami\Exception\NotFound $e) {
            // No kami group file yet, You will have to wait...
            // noop
        }
        $this->response = $this->responseFromTemplate(
            '@learningAssessment/dueWork/worksheet.twig',
            [
                'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())->add([
                    $assessment->getTitle(),
                    $this->getRouteIfAuthorized('learningAssessment.getStatus', [
                        'assessment' => $assessment,
                        'assessmentId' => $assessment->getId()
                    ])
                ])->add([
                    'Class View',
                    ''
                ]),
                'assessment' => $assessment,
                'session'    => $session,
            ]
        );
    }
}
