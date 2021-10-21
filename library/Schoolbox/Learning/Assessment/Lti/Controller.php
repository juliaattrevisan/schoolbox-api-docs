<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

use Alaress_Date_Format;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\LTI\Assessment as LtiAssessment;
use Schoolbox\LTI\Assessment\Score as LtiAssessmentScore;
use Schoolbox\LTI\Provider as LtiTool;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Assessment\Controller
{
    /**
     * Schoolbox's registry of LTI providers (a.k.a. tools).
     */
    private LtiTool\Mapper $ltiToolMapper;

    /**
     * Schoolbox's LTI assessment mapper, for the purpose of testing the
     * creation of scores directly within Schoolbox (instead of listening for
     * the score publish service).
     */
    private LtiAssessment\Mapper $ltiAssessmentMapper;

    /**
     * Schoolbox's LTI assessment score mapper, for the purpose of testing the
     * creation of scores directly within Schoolbox (instead of listening for
     * the score publish service).
     */
    private LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper;

    /**
     * Constructor
     *
     * @param UserMapper                    $userMapper
     * @param Error\Mapper                  $errorMapper
     * @param MapperProxy                   $assessmentMapper
     * @param WorkTypeMapper                $workTypeMapper
     * @param Access                        $access
     * @param Transactor                    $transactor
     * @param Assessment\Breadcrumb\Factory $bakery
     * @param Term\Mapper                   $termMapper
     * @param Participant\DataSource        $participantDataSource
     * @param CourseSpecificationMapper     $courseSpecificationMapper
     * @param UnitSpecificationMapper       $unitSpecificationMapper
     * @param Replicator                    $replicator
     * @param LtiTool\Mapper                $ltiToolMapper
     * @param LtiAssessment\Mapper          $ltiAssessmentMapper
     * @param LtiAssessmentScore\Mapper     $ltiAssessmentScoreMapper
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        Term\Mapper $termMapper,
        Participant\DataSource $participantDataSource,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        Replicator $replicator,
        LtiTool\Mapper $ltiToolMapper,
        LtiAssessment\Mapper $ltiAssessmentMapper,
        LtiAssessmentScore\Mapper $ltiAssessmentScoreMapper
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
            $replicator
        );
        $this->ltiToolMapper            = $ltiToolMapper;
        $this->ltiAssessmentMapper      = $ltiAssessmentMapper;
        $this->ltiAssessmentScoreMapper = $ltiAssessmentScoreMapper;
    }

    /**
     * Fill in Twig data required for rendering the create LTI assessment form.
     *
     * @param IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment): array
    {
        return [
            'acceptingResponses' => true,
            'ltiTools'           => $this->ltiToolMapper->findByColumn(
                'lti_version',
                LtiTool\Model::LTI_VERSION_1_3,
            )->filter(function (LtiTool\Model $model) {
                // Only LTI tools with a deep linking or launch URL are supported,
                // otherwise there's no way to select content for them.
                return $model->getDeepLinkingUrl() || $model->getLaunchUrl();
            })->sortNaturally('name'),
        ];
    }

    /**
     * Placeholder for progress summary for all students on an LTI assessment.
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @since 21.1.0
     *
     * @param IAssessable|Model $assessment
     */
    public function getStatus(IAssessable $assessment)
    {
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/status.twig',
                [
                    'type'        => Assessment\AssessmentType::objectToString($assessment),
                    'assessment'  => $assessment,
                    'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                                                       ->add([ $assessment->getTitle(), '' ]),
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }

    /**
     * Display a summary of a student's progress on an LTI assessment.
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 21.1.0
     *
     * @param IAssessable|Model $assessment
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        // Check SEB requirement
        $sebDownload = $this->getRouteIfAuthorized(
            'learningAssessment.getSebDownload',
            [
                'assessment'   => $assessment,
                'assessmentId' => $assessment->getId(),
            ]
        );
        if ($sebDownload && !$this->isSebCheckPasses($assessment)) {
            $this->response->redirect->to($sebDownload);
            return;
        }

        // Display a timeline for the student's participation
        $participant = $assessment->getParticipantIfExtant($student);
        $timeline    = null;
        if ($participant) {
            $timeline = (new Assessment\Timeline\Generic($participant))->getForUser($this->getUser());
        }

        // Assume that a student may only attempt LTI assessments for themselves
        $canAttempt = false;
        if ($student->getId() === $this->getUser()->getId()) {
            $canAttempt = $assessment->isOpen($student, Alaress_Date_Format::now());
        }
        $this->response->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/summary.twig',
                [
                    'breadcrumbs' => $this->bakery->produceFromAssessment($assessment, $this->getUser())
                                                  ->add([ $assessment->getTitle(), '' ]),
                    'assessment'  => $assessment,
                    'participant' => $participant,
                    'student'     => $student,
                    'timeline'    => $timeline,
                    'returnUrl'   => $this->request->query->get(Assessment\Redirect::RETURN_URL),
                    'canAttempt'  => $canAttempt,
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }

    /**
     * Student's won't create responses to LTI assessments directly: they will
     * be created via the LTI tool publishing a score to Schoolbox.
     *
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function getCreateResponseForm(IAssessable $assessment, User $student): void
    {
        throw new NotFound();
    }

    /**
     * Student's won't create responses to LTI assessments directly: they will
     * be created via the LTI tool publishing a score to Schoolbox.
     *
     * However: for testing purposes only it is possible to create either a
     * response or feedback from the status page.
     *
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        $scoreModel = $this->ltiAssessmentScoreMapper->createModel()
            ->setStudent($student)
            ->setIsResponse($this->request->post->getBool('isResponse', false))
            ->setIsFeedback($this->request->post->getBool('isFeedback', false))
            ->setScoreGiven(
                filter_var($this->request->post->get('scoreGiven'), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            )
            ->setScoreMaximum(
                filter_var($this->request->post->get('scoreMaximum'), FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE)
            )
            ->setComment($this->request->post->get('comment'))
            ->setSubmittedAt(Alaress_Date_Format::now());

        // Set activity/grading progress dependent on if response/feedback
        $scoreModel = $scoreModel
            ->setActivityProgress(
                $scoreModel->getIsResponse()
                    ? LtiAssessmentScore\Model::ACTIVITY_PROGRESS_SUBMITTED
                    : LtiAssessmentScore\Model::ACTIVITY_PROGRESS_INITIALIZED
            )
            ->setGradingProgress(
                $scoreModel->getIsFeedback()
                    ? LtiAssessmentScore\Model::GRADING_PROGRESS_FULLY_GRADED
                    : LtiAssessmentScore\Model::GRADING_PROGRESS_NOT_READY
            );

        // Set LTI assessment: need to get it out of the LTI assessment itself
        $scoreModel = $scoreModel
            ->setLtiAssessment(
                $this->ltiAssessmentMapper->findOneByColumn('assessment_id', $assessment->getId())
            );

        // Set marking user (may be anonymous)
        $markingUserId = $this->request->post->getInt('markingUser');
        $scoreModel->setMarkingUser(
            $markingUserId
                ? (new User())->setId($markingUserId)
                : null
        );

        // Save
        $this->ltiAssessmentScoreMapper->save($scoreModel);

        // Then redir to the return URL, or the assessment's folder
        $encodedReturnUrl = $this->request->query->get(Redirect::RETURN_URL);
        $this->response->redirect->afterPost(
            isset($encodedReturnUrl)
                ? Redirect::decode($encodedReturnUrl)
                : ('/homepage' . $assessment->getBaseContext()->getFolder()->getId()),
        );
    }
}
