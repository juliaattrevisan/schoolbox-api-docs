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
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;
use Schoolbox\Learning\Assessment\Redirect;

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
     * @param Access                 $access
     * @param Transactor             $transactor
     * @param BreadcrumbFactory      $bakery
     * @param QuizMapper             $quizMapper
     * @param QuizSectionMapper      $quizSectionMapper
     * @param QuizAttemptMapper      $quizAttemptMapper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        QuizMapper $quizMapper,
        QuizSectionMapper $quizSectionMapper,
        QuizAttemptMapper $quizAttemptMapper
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $bakery);
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
        $returnUrlSuffix = (new Redirect())->queryString($baseUrl);

        $twigData = [
            'markScheme'  => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment' => $assessment,

            /* Need this suffix so we can return to the status screen after
             * finishing the modification of le quiz
             */
            'returnUrlSuffix' => $returnUrlSuffix,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/quiz/status.twig",
                $twigData, "Status of {$assessment->getTitle()}", 'submission'
            )
        );
    }


    /**
     * Redirect method
     *
     * Sends you to a page depending on the user's role and progress in a Quiz
     *
     * if(user is a participant of this quiz)
     *     if(user has no attempts || latest attempt is finished)
     *         Display Quiz Intro
     *     else(user's latest attempt is unfinished)
     *         Display latest attempt, first section
     * else(user is not participant)
     *    if(user is teacher)
     *        Display Summary page
     *    else(user not teacher)
     *        Display permission denied
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     * @param Participant $participant
     */
    public function getSummary(Participant $participant)
    {
        $assessment = $participant->getAssessable();

        // Has user not yet attempted the quiz
        if ($participant->getAllResponses()->count() == 0) {

            $this->redirectToSummaryOrIntro($this->getUser(), $participant->getUser(), $assessment);
            return;
        }

        // Get latest attempt's finish date.
        $latestResponse = $participant->findMostRecentResponse();
        $latestAttempt  = $this->quizAttemptMapper->findById($latestResponse->getId());
        $finishDate     = $latestAttempt->getFinish();

        /* Is the attempt in progress ?
         *
         * If the current user is a student, then redirect to a place
         * where they can continue the attempt
         */
        if ($this->getUser()->getRole()->isStudent() && (is_null($finishDate) || $finishDate instanceof \Alaress_Date_NullDate)) {
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

        // Not in progress (or you're a teacher - this redirect eventually
        // shunts you off to the Attempts List for a particular student)
        $this->redirectToSummaryOrIntro($this->getUser(), $participant->getUser(), $assessment);
    }


    /**
     * @param User        $sessionUser
     * @param User        $participantUser
     * @param IAssessable $assessment
     */
    protected function redirectToSummaryOrIntro(User $sessionUser, User $participantUser, IAssessable $assessment)
    {
        if ($sessionUser->getId() == $participantUser->getId()) {
            // this is the special case: redirect to intro
            $this->getResponse()->redirect->afterPost(
                $this->getRouteIfAuthorized('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ])
            );
            return;
        }

        $quiz = $this->getQuizForAssessmentId($assessment->getId());

        $this->getResponse()->redirect->to("/assess/attempts/{$quiz->getId()}/{$participantUser->getId()}");
    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string      $defaultReturnUrl
     * @return string
     */
    protected function determineRedirect(IAssessable $assessment, $defaultReturnUrl = '')
    {
        /**
         * Always go to the screen when a quiz has been created,
         * but send the return url with it
         */
        $quiz = $this->getQuizForAssessmentId($assessment->getId());
        $redirect = $this->getRouter()->generate('learningQuiz.getQuizSectionCreate', ['assessmentId' => $assessment->getId()]);

        if ($explicitReturnUrl) {
            $redirect .= '?' . Redirect::RETURN_URL . "=$explicitReturnUrl";
        }

        return $redirect;
    }


    /**
     * @api   GET /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function getCreateResponseForm(Participant $participant)
    {
        // this page does not exist
        throw new NotFound();
    }


    /**
     * @api   POST /learning/assessments/:assessmentId/:studentId/submit
     * @level 165
     *
     * @param Participant $participant
     * @return void
     */
    public function postCreateResponse(Participant $participant)
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
