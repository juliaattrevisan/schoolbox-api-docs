<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Alaress\Lumberjack\Logger;
use Alaress_Date_Format;
use Aura\Router\Exception\RouteNotFound;
use CFPropertyList\IOException;
use CFPropertyList\PListException;
use DomainException;
use External\Canvas\Importer\CanvasDb;
use LearnositySdk\Exceptions\ValidationException;
use PDOException;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term\Mapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\Learning\Assessment\DataModel\LessonPlan\Mapper as LessonPlanDataModelMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\Views\Mapper as LatestViewMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\ApiException;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\L10n\RosettaStone;
use Twig\Error\Error;
use UnexpectedValueException;

/**
 * Class Controller
 *
 * TODO
 * This class and Schoolbox\Learning\Quiz\Controller (note the absence of 'Assessment' in the middle)
 * need to be merged into a single class...
 *
 * @package Schoolbox\Learning\Assessment\Quiz
 */
class Controller extends Assessment\Controller
{
    /**
     * Controller constructor.
     *
     * @param UserMapper $userMapper
     * @param ErrorMapper $errorMapper
     * @param MapperProxy $assessmentMapper
     * @param WorkTypeMapper $workTypeMapper
     * @param Access $access
     * @param Transactor $transactor
     * @param BreadcrumbFactory $bakery
     * @param QuizMapper $quizMapper
     * @param QuizSectionMapper $quizSectionMapper
     * @param QuizAttemptMapper $quizAttemptMapper
     * @param Mapper $termMapper
     * @param DataSource $participantDataSource
     * @param CourseSpecificationMapper $courseSpecificationMapper
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @param RosettaStone $rosettaStone
     * @param Replicator $replicator
     * @param \Schoolbox\Resource\Folder\Mapper $folderMapper
     * @param LatestViewMapper $latestViewMapper
     * @param Assessment\Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper $lessonPlanDataModelMapper
     * @param QuizAttemptMapper $attemptMapper
     * @param StateMapper $stateMapper
     * @param \Schoolbox\Url\Factory $urlFactory
     * @param string $learnosityVersion
     * @param string $learnosityConsumerKey
     * @param string $learnosityConsumerSecret
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        private QuizMapper $quizMapper,
        private QuizSectionMapper $quizSectionMapper,
        private QuizAttemptMapper $quizAttemptMapper,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        RosettaStone $rosettaStone,
        Replicator $replicator,
        private \Schoolbox\Resource\Folder\Mapper $folderMapper,
        LatestViewMapper $latestViewMapper,
        protected Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        protected LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        protected LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
        private StateMapper $stateMapper,
        private \Schoolbox\Url\Factory $urlFactory,
        private string $learnosityVersion,
        #[\SensitiveParameter] private string $learnosityConsumerKey,
        #[\SensitiveParameter] private string $learnosityConsumerSecret,
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
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     *
     * @param IAssessable $assessment
     * @throws ApiException
     * @throws DefinitionException
     * @throws Deleted
     * @throws DomainException
     * @throws Error
     * @throws Exception
     * @throws MapperException
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws ValidationException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws \LogicException
     */
    public function getStatus(IAssessable $assessment): void
    {
        $baseUrl = $this->getRouteIfAuthorized('learningAssessment.getStatus', ['assessmentId' => $assessment->getId(), 'assessment' => $assessment]);
        $returnUrlSuffix = Redirect::queryString($baseUrl);

        /* Auto-complete naturally expired quizzes
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());
        $now = Alaress_Date_Format::now();

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
            $assessment = $this->getAssessmentMapper()->findByIdForReview($assessment->getId());
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

            $available = (new Helper($api, $logger))->findAvailableMarks($assessment->getLearnosityReference());

            /* Now assign it to them
             */
            foreach ($maxMarks as $userId => $value) {
                if (null === $value) {
                    $maxMarks[$userId] = $available;
                }
            }
        }

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'assessment' => $assessment,

            /* Quizzes now support Submission States
             * Annoyingly, Submission States are in the $errors array...
             */
            'errors' => $this->stateMapper->listDisplayValues(),
            /* Each guy's maximum available marks
             */
            'maxMarks' => $maxMarks,
            /* Need this suffix so we can return to the status screen after
             * finishing the modification of le quiz
             */
            'returnUrl' => Redirect::encode($baseUrl),
            'returnUrlSuffix' => $returnUrlSuffix
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/quiz/status.twig',
            $twigData,
            "Status of {$assessment->getTitle()}",
            'submission'
        );
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User $student
     * @return void
     * @throws DefinitionException
     * @throws Deleted
     * @throws DomainException
     * @throws Error
     * @throws Exception
     * @throws MapperException
     * @throws PDOException
     * @throws RouteNotFound
     * @throws UnexpectedValueException
     * @throws \Alaress\Database\Exception
     * @throws IOException
     * @throws PListException
     * @throws \DOMException
     * @throws \InvalidArgumentException
     * @throws \JsonException
     * @throws ValidationException
     * @throws \LogicException
     * @throws ApiException
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
            [
                'assessment' => $assessment,
                'assessmentId' => $assessment->getId()
            ]
        );

        if ($sebDownload && !$this->isSebCheckPasses($assessment)) {
            $this->response = $this->redirectFound($sebDownload);
            return;
        }

        /* Copypasta'd rom the Learnosity controller
         */

        /* The intro also has a list of attempts from which you can resume.
         * We can do this by using the Learnosity session id in getAttempt()
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        /* Apparently this is a thing
         */
        $quiz = $this->quizMapper->loadAttemptsAndAnswers($quiz, $student);

        /* Close any expired attempts before checking for in-progress / unfinished attempts
         * Note that this requires $quiz to have its attempts already loaded via loadAttemptsAndAnswers()
         */
        $now = Alaress_Date_Format::now();
        $this->quizMapper->closeExpiredAttempts($assessment, $quiz, $now);

        $attempts = $this->attemptMapper->findByColumnList([
            'assess_id' => $quiz->getId(),
            'user_id' => $student->getId()
        ], [
            'order' => [
                'start' => 'desc'
            ]
        ]);

        /* Decide if there was an existing attempt, to rename the call to action
         */
        $existingAttempt = $this->attemptMapper->findExistingUnfinished($assessment->getId(), $student->getId());

        /* Special case: participation isn't guaranteed at this point
         * so we can't get a Plan in all cases e.g. visiting from Course Import as an instructor
         */
        $plan = [];

        // Does session user have a participation? If so, use CAP details.
        $participant         = $assessment->getParticipantIfExtant($student);
        $plan['description'] = trim($participant ? $participant->getDescription() : $assessment->getDescription());
        $plan['start']       = $participant ? $participant->getOpenDate() : $assessment->getOpenAt();
        $plan['finish']      = $participant ? $participant->getDueDate() : $assessment->getDueAt();
        $plan['close']       = $participant ? $participant->getCloseDate() : $assessment->getCloseAt();

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

        /* Payload for twig
         */
        $twigData = [
            /* Learnosity-era vars
             */
            'assessment' => $assessment,
            'quiz' => $quiz,
            'student' => $student,
            'attempts' => $attempts,
            'existingAttempt' => $existingAttempt,
            'plan' => $plan,
            /* All the older summary screen stuff is below
             */
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            /* What is this andyland stuff?
             */
            // User may only attempt quiz from here if viewing own summary
            /* Also see if $student can load the learnosity getAttempt() screen
             * If they can, then they can attempt it; provide the button
             */
            'canAttempt' => (bool)$this->getRouteIfAuthorized('learnosity.getAttempt', [
                'assessment' => $assessment,
                'assessmentId' => $assessment->getId(),
                'student' => $student,
                'studentId' => $student->getId(),
            ]),
            /* External Url for third party apps which might have generated this assessment
             */
            'externalRedirectUrl' => $externalRedirectUrl,
            'lastViews' => $lastView->getTwigViews($assessment, $student, $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder())),
            'participant' => $participant,
            'returnUrlName' => Redirect::RETURN_URL,
            'returnUrlEncoded' => $this->requestQuery->get(Redirect::RETURN_URL), // The returnUrl URL parameter (to be used again as a URL parameter).  (Already encoded, so encoding/decoding necessary).
        ];

        $this->response = $this->responseFromTemplate(
            '@learningAssessment/quiz/summary/student.twig',
            $twigData,
            "Status of {$assessment->getTitle()}",
            'submission'
        );
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @return void
     * @throws NotFound
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        // this page does not exist
        throw new NotFound();
    }


    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @since 165
     *
     * @param IAssessable $assessment
     * @param User        $student
     * @throws NotFound
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        // this page does not exist
        throw new NotFound();
    }


    /**
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        $data['acceptingResponses'] = true;
        $data['allMarkTypes'] = $this->getAllMarkTypes($assessment);
        $data['allGradeSchemes'] = $this->getAllGradeSchemes();

        return $data;
    }


    /**
     * Converts an assessment_id to a Quiz
     *
     * @param int $assessmentId
     * @return Quiz
     * @throws Exception
     */
    protected function getQuizForAssessmentId($assessmentId): Quiz
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        return $quiz;
    }

    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $postReturnUrl
     * @param string      $defaultReturnUrl
     * @param bool        $relocated
     * @param bool        $isCreate
     * @return string
     * @throws RouteNotFound
     */
    public function determineRedirect(IAssessable $assessment, $postReturnUrl = null, $defaultReturnUrl = '', bool $relocated = false, bool $isCreate = false): string
    {
        /**
         * Always go to the modify contents screen when a quiz has been created,
         * but send the return url with it
         *
         * If the quiz was only updated, use the parent logic to determine the redirect instead
         */
        if ($isCreate) {
            $redirect = $this->getRouteIfAuthorized('learnosity.getContent', [
                'assessment' => $assessment,
                'assessmentId' => $assessment->getId()
            ]);

            if ($postReturnUrl) {
                $redirect .= '?' . Redirect::RETURN_URL . "=$postReturnUrl";
            }

            return $redirect;
        }

        return parent::determineRedirect($assessment, $postReturnUrl, $defaultReturnUrl, $relocated, $isCreate);
    }
}
