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
use Schoolbox\UserMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Model as QuizSection;
use Schoolbox\Learning\Quiz\Section\Mapper as QuizSectionMapper;

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
     * @var Participant\DataSource
     */
    private $participantMapper;

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
     * @param Participant\DataSource $participantMapper
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
        Participant\DataSource $participantMapper,
        QuizMapper $quizMapper,
        QuizSectionMapper $quizSectionMapper,
        QuizAttemptMapper $quizAttemptMapper
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $bakery);
        $this->participantMapper = $participantMapper;
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
        /* TODO: Quickmark screen
         */
        $twigData = [
            'markScheme'  => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment),
            'assessment' => $assessment,
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
            // this is the special case: redirect to intro
            $this->getResponse()->redirect->afterPost(
                $this->getRouteIfAuthorized('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ])
            );
            return;
        } else {
            // Get latest attempt's finish date.
            $latestResponse = $participant->findMostRecentResponse();
            $latestAttempt = $this->quizAttemptMapper->findById($latestResponse->getId());
            $finishDate = $latestAttempt->getFinish();

            // Is the attempt in progress ?
            if(is_null($finishDate) || $finishDate instanceof \Alaress_Date_NullDate){
                $quiz = $this->getQuizForAssessmentId($assessment->getId());
                $sections = $this->quizSectionMapper->findByColumn('assess_id', $quiz->getId());
                /** @var QuizSection $firstSection */
                $firstSection = null;
                foreach($sections as $section){
                    if(is_null($firstSection) || $section->getSequence() < $firstSection->getSequence()){
                        $firstSection = $section;
                    }
                }

                $this->getResponse()->redirect->to("/assess/attempt/{$quiz->getId()}/{$firstSection->getId()}");
                return;
                // Not in progress
            } else {
                $this->getResponse()->redirect->afterPost(
                    $this->getRouteIfAuthorized('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ])
                );
                return;
            }
        }
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
        $quiz = $this->getQuizForAssessmentId($assessment->getId());
        return '/assess/section/create/'. $quiz->getId();
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
     * @return Participant\DataSource
     */
    protected function getParticipantMapper()
    {
        return $this->participantMapper;
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
        $quiz = $this->quizMapper->findByColumn('assessment_id', $assessmentId)->first();

        if (Quiz::AVAILABILITY_ASSESS != $quiz->getAvailability()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
        }
        return $quiz;
    }

}
