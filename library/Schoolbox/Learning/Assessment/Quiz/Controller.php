<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
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
        $this->quizMapper = $quizMapper;
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
     * In cases where no Participant is available a User will get an intro of a quiz with no Begin Quiz button
     *
     * @api GET /learning/assessment/:assessmentId/preview
     * @level 165
     * @param IAssessable $assessment
     */
    public function getPreview(IAssessable $assessment)
    {
        $returnUrlSuffix = $this->getRequest()->query->get('returnUrl') ? "?returnUrl=". $this->getRequest()->query->get('returnUrl') : '';
        // TODO: remove buttons from intro.html if the user is not participant
        $this->getResponse()->redirect->afterPost(
            $this->getRouteIfAuthorized('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ]) . $returnUrlSuffix
        );
    }


    /**
     * Redirect method
     *
     * Sends you to a page depending on the user's role and progress in a Quiz
     *
     *     if(user has no attempts || latest attempt is finished)
     *         Display Quiz Intro
     *     else(user's latest attempt is unfinished)
     *         Display latest attempt, first section
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     * @param IAssessable $assessment
     * @param User        $student
     * @throws \Schoolbox\Application\Exception\Exception
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $participant = $assessment->getParticipantIfExtant($student);

        // Has user not yet attempted the quiz
        if (!$participant || !$participant->getAllResponses()->count()) {
            $this->redirectToSummaryOrIntro($this->getUser(), $participant->getUser(), $assessment);
            return;
        }

        // Get latest attempt's finish date.
        $latestResponse = $participant ? $participant->findMostRecentResponse() : null;
        $latestAttempt  = $latestResponse ? $this->quizAttemptMapper->findById($latestResponse->getId()) : null;
        $finishDate     = $latestAttempt ? $latestAttempt->getFinish() : null;

        // Is the attempt in progress ?
        if (is_null($finishDate) || $finishDate instanceof \Alaress_Date_NullDate) {

            // Is the current session user the specified participant?
            if ($this->getUser()->getId() == $student->getId()){
                // redirect to a place where they can continue the attempt
                $quiz     = $this->getQuizForAssessmentId($assessment->getId());
                $sections = $this->quizSectionMapper->findByColumn('assess_id', $quiz->getId());

                /** @var QuizSection $firstSection */
                $firstSection = null;

                foreach ($sections as $section) {
                    if (is_null($firstSection) || $section->getSequence() < $firstSection->getSequence()) {
                        $firstSection = $section;
                    }
                }

                $this->getResponse()->redirect->to("/assess/attempt/{$quiz->getId()}/{$firstSection->getId()}");
                return;
            }
        }

        // Not in progress (or you're a teacher - this redirect eventually
        // shunts you off to the Attempts List for a particular student)
        $this->redirectToSummaryOrIntro($this->getUser(), $student, $assessment);
    }


    /**
     * @param User        $sessionUser
     * @param User        $participantUser
     * @param IAssessable $assessment
     */
    protected function redirectToSummaryOrIntro(User $sessionUser, User $participantUser, IAssessable $assessment)
    {
        $returnUrlSuffix = $this->getRequest()->query->get('returnUrl') ? "?returnUrl=". $this->getRequest()->query->get('returnUrl') : '';

        /* Attempts List for this user
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());
        $attemptsListUrl = "/assess/attempts/{$quiz->getId()}/{$participantUser->getId()}" . $returnUrlSuffix;

        /* If you're a teacher viewing the summary screen for your own attempts
         * you should end up at the attempts list rather than be magically prompted
         * to attempt the quiz
         */
        $access = $this->getAccess()->getFolderAccess($sessionUser, $assessment->getContext()->getFolder()->getId());

        if ($access >= UserAccess::ACCESS_WRITE) {
            $this->getResponse()->redirect->to($attemptsListUrl);
        }
        elseif ($sessionUser->getId() == $participantUser->getId() && $assessment->isOpen()) {

            /* If you're a participant, and the quiz is available for attemptation
             * go to the intro screen so you can begin
             */

            // this is the special case: redirect to intro
            $this->getResponse()->redirect->afterPost(
                $this->getRouteIfAuthorized('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ]) . $returnUrlSuffix
            );
            return;
        }

        $this->getResponse()->redirect->to($attemptsListUrl);
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
     * @level 165
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
     * @level 165
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
