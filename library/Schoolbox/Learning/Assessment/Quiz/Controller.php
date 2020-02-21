<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Alaress_Date_Format;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Quiz\ActionValidator;
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
     * Controller constructor.
     *
     * @param UserMapper             $userMapper
     * @param ErrorMapper            $errorMapper
     * @param DataModel\MapperProxy  $assessmentMapper
     * @param WorkTypeMapper         $workTypeMapper
     * @param Access                 $access
     * @param Transactor             $transactor
     * @param BreadcrumbFactory      $bakery
     * @param QuizMapper             $quizMapper
     * @param QuizSectionMapper      $quizSectionMapper
     * @param QuizAttemptMapper      $quizAttemptMapper
     * @param Term\Mapper            $termMapper
     * @param Participant\DataSource $participantDataSource
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
        Participant\DataSource $participantDataSource
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $workTypeMapper, $access, $transactor, $bakery, $termMapper, $participantDataSource);
        $this->quizMapper        = $quizMapper;
        $this->quizSectionMapper = $quizSectionMapper;
        $this->quizAttemptMapper = $quizAttemptMapper;
    }


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param IAssessable $assessment
     */
    public function getStatus(IAssessable $assessment)
    {
        $baseUrl = $this->getRouteIfAuthorized('learningAssessment.getStatus', ['assessmentId' => $assessment->getId(), 'assessment' => $assessment]);
        $returnUrlSuffix = Redirect::queryString($baseUrl);

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment' => $assessment,

            /* Need this suffix so we can return to the status screen after
             * finishing the modification of le quiz
             */
            'returnUrl' => Redirect::encode($baseUrl),
            'returnUrlSuffix' => $returnUrlSuffix
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/quiz/status.twig",
                $twigData, "Status of {$assessment->getTitle()}", 'submission'
            )
        );
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 165
     *
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws \Aura\Di\Exception\ServiceNotFound
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findOneByColumn('assessment_id', $assessment->getId());
        $quiz = $this->quizMapper->loadSectionsAndQuestions($quiz);
        $quiz = $this->quizMapper->loadAttemptsAndAnswers($quiz, $student);

        // Close any expired attempts before checking for in-progress
        $now = Alaress_Date_Format::now();
        $this->quizMapper->closeExpiredAttempts($quiz, $now);

        // Is an attempt in progress? Show a CTA to resume that if there is one
        $attemptInProgress = null;
        foreach ($quiz->getAttempts() as $attempt) {
            if (!$attempt->isFinished()) {
                $attemptInProgress = $attempt;
                break;
            }
        }

        // Can I attempt this?
        // TODO this should eventually be route permission based
        $actionValidator = new ActionValidator($now, $this->getAccess());

        // TODO this will produce a false-positive if there is an attempt unfinished but the time limit on it has expired
        $canAttempt = $student->getId() === $this->getUser()->getId()
            && $actionValidator->canAttemptQuiz($this->getUser(), $assessment);

        $twigData = [
            'breadcrumbs'       => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'        => $assessment,
            'student'           => $student,
            'participant'       => $assessment->getParticipantIfExtant($student),
            'quiz'              => $quiz,
            'attemptInProgress' => $attemptInProgress,
            'returnUrl'         => $this->getRequest()->query->get(Redirect::RETURN_URL),
            // User may only attempt quiz from here if viewing own summary
            'canAttempt'        => $canAttempt,
            'canReview'         => $actionValidator->canReviewAttempts($this->getUser(), $student, $assessment),
            'canStudentReview'  => $actionValidator->canReviewAttemptsReadonly(
                $this->getUser(),
                $student,
                $assessment,
                $this->getConfigLoader()->get('restrict_student_submissions')
            ),
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(

                "@learningAssessment/quiz/summary/student.twig",
                $twigData,
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string|null $explicitReturnUrl
     * @param string      $defaultReturnUrl
     * @return string
     */
    public function determineRedirect(IAssessable $assessment, $explicitReturnUrl = null, $defaultReturnUrl = '')
    {
        /**
         * Always go to the screen when a quiz has been created,
         * but send the return url with it
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());
        $redirect = "/assess/section/create/{$quiz->getId()}";

        if ($explicitReturnUrl) {
            $redirect .= '?' . Redirect::RETURN_URL . "=$explicitReturnUrl";
        }
        return $redirect;
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
    protected function getAssessmentTwigData(IAssessable $assessment)
    {
        $data['acceptingResponses']= true;
        return $data;
    }

    /**
     * Converts an assessment_id to a Quiz
     *
     * @param int $assessmentId
     * @return Quiz
     */
    protected function getQuizForAssessmentId($assessmentId)
    {
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId, ['deleted' => true])->first();

        if (Quiz::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        return $quiz;
    }
}
