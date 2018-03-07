<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz;

use Alaress\Date\Interval;
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
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Quiz\Model as Quiz;
use Schoolbox\Learning\Quiz\Mapper as QuizMapper;
use Schoolbox\Learning\Quiz\Attempt\Mapper as QuizAttemptMapper;
use Schoolbox\Learning\Quiz\Section\Question\Mapper as QuizQuestionMapper;
use Schoolbox\Learning\Quiz\Section\Question\Model as QuizQuestionModel;
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
     * @var QuizQuestionMapper
     */
    private $quizQuestionMapper;

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
     * @param QuizQuestionMapper     $quizQuestionMapper
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
        QuizQuestionMapper $quizQuestionMapper,
        QuizAttemptMapper $quizAttemptMapper,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $workTypeMapper, $access, $transactor, $bakery, $termMapper, $participantDataSource);
        $this->quizMapper         = $quizMapper;
        $this->quizSectionMapper  = $quizSectionMapper;
        $this->quizQuestionMapper = $quizQuestionMapper;
        $this->quizAttemptMapper  = $quizAttemptMapper;
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
     * Returns the URL that a user should be redirected to when visiting a Quiz summary page.
     * Options include:
     * - Continue in-progress attempt                    /assess/attempt/{quizId}/{firstSectionId}
     * - View list of attempts                           /assess/attempts/{quizId}/{userId}
     * - View quiz details. Optional 'Begin Quiz' button /assess/intro/{quizId}
     *
     * @param Model $assessment
     * @param User  $student
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \Schoolbox\Application\Exception\Exception
     */
    private function determineSummaryRedirect(Model $assessment, User $student): string
    {
        // Is this a spec?
        if ($assessment->getBaseContext()->isSpecificationContext()) {
            // Go to intro page (where you can see details of the quiz, but cannot 'Begin Quiz')
            return $this->getRouter()->generate('learningQuiz.getQuizIntro', ['assessmentId' => $assessment->getId()]);
        }

        $access = $this->getAccess()->getFolderAccess($this->getUser(), $assessment->getContext()->getFolder()->getId());
        $participant = $assessment->getParticipantIfExtant($student);
        $now = \Alaress_Date_Format::now();

        // A participant, but not staff or instructor, and we're past the close date?
        if ($participant && $access <= UserAccess::ACCESS_WRITE && !$student->getRole()->isStaff() && $now->gt($participant->getCloseDate())) {
            $quiz = $this->getQuizForAssessmentId($assessment->getId());
            return "/assess/attempts/{$quiz->getId()}/{$student->getId()}";
        }

        // Not a participant? But you still had access to this page?
        if (!$participant || $participant->isExcluded()) {
            // Go to intro page (you may or may not see a 'Begin Quiz' button
            return $this->getRouter()->generate('learningQuiz.getQuizIntro', ['assessmentId' => $assessment->getId()]);
        }

        // User is a participant and quiz is not a spec. Have they attempted the quiz yet?
        $latestResponse = $participant->findMostRecentResponse();
        $latestAttempt  = $latestResponse ? $this->quizAttemptMapper->findById($latestResponse->getId()) : null;

        // Is there an attempt in progress? And user is looking at themself?
        if ($latestAttempt && !$latestAttempt->isFinished() && $this->getUser()->getId() == $student->getId()) {
            // redirect to a place where they can continue the attempt
            $quiz         = $latestAttempt->getQuiz();
            $firstSection = $this->findFirstQuizSection($quiz);
            return "/assess/attempt/{$quiz->getId()}/{$firstSection->getId()}";
        }

        // Is session user an instructor?

        if ($latestAttempt && $access >= UserAccess::ACCESS_WRITE) {
            // Note that if an instructor is looking at their own summary, and has attempt/s, and wishes to re-attempt,
            // that action is available from attempts-list page.
            $quiz = $latestAttempt ? $latestAttempt->getQuiz() : $this->getQuizForAssessmentId($assessment->getId());
            return "/assess/attempts/{$quiz->getId()}/{$student->getId()}";
        }

        return $this->getRouter()->generate('learningQuiz.getQuizIntro', [ 'assessmentId' => $assessment->getId() ]);
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
     * @param IAssessable|Model $assessment
     * @param User              $student
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        // $assessment doesn't contain quiz questions, get something that does.
        /** @var Quiz $quiz */
        $quiz = $this->quizMapper->findOneByColumn('assessment_id', $assessment->getId());
        $quiz->setSections($this->quizSectionMapper->findByColumn(
            'assess_id',
            $quiz->getId(),
            ['order' => ['sequence' => 'asc']]
        ));

        $quizQuestions = $this->quizQuestionMapper
            ->findByColumn(
                'section_id',
                $quiz->getSections()->getPropertyValues('id')
            )
            ->groupByCallback(function(QuizQuestionModel $q) {
                return $q->getSection()->getId();
            });
        foreach ($quiz->getSections() as $section) {
            if (isset($quizQuestions[$section->getId()])) {
                $section->setQuestions($quizQuestions[$section->getId()]);
            }
        }


        // Load attempts
        $quiz->setAttempts(
            $this->quizAttemptMapper->findByColumnList(
                ['assess_id' => $quiz->getId(), 'user_id'   => $student->getId(),],
                ['order' => ['start' => 'desc']]
            )
        );


        // Can I attempt this?
        // TODO this should eventually be route permission based
        $actionValidator = new ActionValidator(Alaress_Date_Format::now(), $this->getAccess());

        // load all of the questions here too
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'  => $assessment,
            'student'     => $student,
            'participant' => $assessment->getParticipantIfExtant($student),
            'quiz'        => $quiz,
            'returnUrl'   => $this->getRequest()->query->get(Redirect::RETURN_URL),
            'canAttempt'  => $student->getId() === $this->getUser()->getId()
                && $actionValidator->canAttemptQuiz($this->getUser(), $assessment),
            'duration'    => $assessment->isDurationUnlimited() ? null : new Interval($assessment->getDuration()),
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


    /**
     * Returns a quiz's first section
     *
     * @param Quiz $quiz
     * @return QuizSection
     */
    private function findFirstQuizSection(Quiz $quiz): QuizSection
    {
        $sections = $this->quizSectionMapper->findByColumn('assess_id', $quiz->getId());

        /** @var QuizSection $firstSection */
        $firstSection = null;
        foreach ($sections as $section) {
            if (null === $firstSection || $section->getSequence() < $firstSection->getSequence()) {
                $firstSection = $section;
            }
        }
        return $firstSection;
    }
}
