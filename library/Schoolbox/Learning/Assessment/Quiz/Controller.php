<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Alaress_Date_Format;
use Alaress\Lumberjack\Logger;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use External\Canvas\Importer\CanvasDb;
use PDOException;
use Schoolbox\Admin\LessonPlan\Template\Mapper as LessonPlanTemplateMapper;
use Schoolbox\Application\Exception\Exception;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term\Mapper;
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
use Schoolbox\Learning\Learnosity\Api;
use Schoolbox\Learning\Learnosity\Helper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Quiz\ActionValidator;
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
     * @var QuizMapper
     */
    private $quizMapper;


    /**
     * @var QuizSectionMapper
     */
    private $quizSectionMapper;


    /**
     * @var QuizAttemptMapper
     */
    private $quizAttemptMapper;


    /**
     * @var \Schoolbox\Resource\Folder\Mapper
     */
    private \Schoolbox\Resource\Folder\Mapper $folderMapper;


    /**
     * Controller constructor.
     *
     * @param UserMapper                            $userMapper
     * @param ErrorMapper                           $errorMapper
     * @param MapperProxy                           $assessmentMapper
     * @param WorkTypeMapper                        $workTypeMapper
     * @param Access                                $access
     * @param Transactor                            $transactor
     * @param BreadcrumbFactory                     $bakery
     * @param QuizMapper                            $quizMapper
     * @param QuizSectionMapper                     $quizSectionMapper
     * @param QuizAttemptMapper                     $quizAttemptMapper
     * @param Mapper                                $termMapper
     * @param DataSource                            $participantDataSource
     * @param CourseSpecificationMapper             $courseSpecificationMapper
     * @param UnitSpecificationMapper               $unitSpecificationMapper
     * @param Replicator                            $replicator
     * @param \Schoolbox\Resource\Folder\Mapper     $folderMapper
     * @param LatestViewMapper                      $latestViewMapper
     * @param Assessment\Project\ResultCache\Mapper $projectResultCacheMapper
     * @param LessonPlanTemplateMapper              $lessonPlanTemplateMapper
     * @param LessonPlanDataModelMapper             $lessonPlanDataModelMapper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        QuizMapper $quizMapper,
        QuizSectionMapper $quizSectionMapper,
        QuizAttemptMapper $quizAttemptMapper,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator,
        \Schoolbox\Resource\Folder\Mapper $folderMapper,
        LatestViewMapper $latestViewMapper,
        protected Assessment\Project\ResultCache\Mapper $projectResultCacheMapper,
        protected LessonPlanTemplateMapper $lessonPlanTemplateMapper,
        protected LessonPlanDataModelMapper $lessonPlanDataModelMapper,
        private \Schoolbox\Learning\Quiz\Attempt\Mapper $attemptMapper,
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
            $replicator,
            $latestViewMapper,
            $this->projectResultCacheMapper,
            $lessonPlanTemplateMapper,
            $lessonPlanDataModelMapper
        );
        $this->quizMapper        = $quizMapper;
        $this->quizSectionMapper = $quizSectionMapper;
        $this->quizAttemptMapper = $quizAttemptMapper;
        $this->folderMapper      = $folderMapper;
    }


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param IAssessable $assessment
     * @throws RouteNotFound
     */
    public function getStatus(IAssessable $assessment): void
    {
        $baseUrl = $this->getRouteIfAuthorized('learningAssessment.getStatus', ['assessmentId' => $assessment->getId(), 'assessment' => $assessment]);
        $returnUrlSuffix = Redirect::queryString($baseUrl);

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'assessment' => $assessment,

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
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws Deleted
     * @throws DomainException
     * @throws PDOException
     * @throws DefinitionException
     * @throws MapperException
     * @throws UnexpectedValueException
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        /* Redirect to some kind of third party who spawned this assessment
         */
        $externalRedirectUrl = null;

        if (\Custom\Newic\Redirect::SITE_SCHOOL_CODE === strtolower($this->getConfigLoader()->get('site_school_code'))) {
            $externalRedirectUrl = (new \Custom\Newic\Redirect(
                $this->getConfigLoader()->get('canvas_url'),
                new CanvasDb($this->getConfigLoader()),
                $this->folderMapper,
            ))->generateAssessmentRedirect(
                $this->getUser(),
                $assessment
            );
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

        // Note on need for consistency when dealing with permissions
        // ----------------------------------------------------------
        // It is important that the permission checks that determine whether the 'start quiz' button is displayed (in this
        // function) are the same as those used on the quiz page itself (Learnosity/Controller->getAttempt()).  Otherwise,
        // either an enabled 'start quiz' button will lead to a permission error on the quiz page, or directly accessing
        // the quiz page will enable users to bypass permission checks that prevent the 'start quiz' button from displaying.
        // See Learnosity/Controller->getAttempt() (where the same permission checks are used).
        $now = Alaress_Date_Format::now();
        $this->quizMapper->closeExpiredAttempts($quiz, $now); // Close any expired attempts before checking for in-progress
        // TODO this should eventually be route permission based
        // TODO this will produce a false-positive if there is an attempt unfinished but the time limit on it has expired
        $actionValidator = new ActionValidator($now, $this->getAccess());
        $canAttempt = (
            $this->getUser()->getId() === $student->getId() && // A user may not attempt a quiz on behalf of another user.
            $actionValidator->canAttemptQuiz($this->getUser(), $assessment, $this->getConfigLoader())
        );

        /**
         * record view
         */
        $lastView = new Assessment\Views\LatestView(
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
            'canAttempt' => $canAttempt,
            'canReview' => $actionValidator->canReviewAttempts($this->getUser(), $student, $assessment),
            'canStudentReview' => $actionValidator->canReviewAttemptsReadonly(
                $this->getUser(),
                $student,
                $assessment,
                $this->getConfigLoader()->get('restrict_student_submissions')
            ),
            /* External Url for third party apps which might have generated this assessment
             */
            'externalRedirectUrl' => $externalRedirectUrl,
            'lastViews' => $lastView->getTwigViews($assessment, $student, $this->getAccess()->isInstructor($this->getUser(), $assessment->getBaseContext()->getFolder())),
            'participant' => $participant,
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
}
