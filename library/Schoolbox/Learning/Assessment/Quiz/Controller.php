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
     * Controller constructor.
     *
     * @param UserMapper             $userMapper
     * @param ErrorMapper            $errorMapper
     * @param DataModel\MapperProxy  $assessmentMapper
     * @param Access                 $access
     * @param Transactor             $transactor
     * @param BreadcrumbFactory      $bakery
     * @param Participant\DataSource $participantMapper
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery,
        Participant\DataSource $participantMapper
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $bakery);
        $this->participantMapper = $participantMapper;
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
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment),
            'assessment' => $assessment,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/quiz/status/staff.twig",
                $twigData
            )
        );
    }


    /**
     * Display appropriate landing page based on role
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     * @param Participant $participant
     */
    public function getSummary(Participant $participant)
    {
        // TODO redirect to quiz student review... I think... maybe intro??
        throw new NotFound();
    }


    /**
     * Redirect method
     *
     * Sends you to a status or summary screen depending on who is
     * currently logged in and what they are viewing
     *
     * For quizzes, this is slightly more complicated than for other types.
     * If you are a participant of this quiz, but you have not made any attempts,
     * redirect straight to the intro page.
     *
     * @api GET /learning/assessment/:assessmentId
     * @param IAssessable $assessment
     */
    public function getAssessment(IAssessable $assessment)
    {
        try {
            $participation = $this->getParticipantMapper()->findForAssessmentAndUser($assessment, $this->getUser());

            if ($participation->getAllResponses()->count() == 0) {
                // this is the special case: redirect to intro
                $this->getResponse()->redirect->afterPost(
                    $this->getRouteIfAuthorized('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ])
                );
                return;
            }

        } catch (\OutOfBoundsException $e ) {
            // not a participant; fall through to default behaviour
        }

        parent::getAssessment($assessment);
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
        // no additional data required: everything we need is already on the Quiz object
        return [];
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
        return $defaultReturnUrl;
    }


    /**
     * @return Participant\DataSource
     */
    protected function getParticipantMapper()
    {
        return $this->participantMapper;
    }

}
