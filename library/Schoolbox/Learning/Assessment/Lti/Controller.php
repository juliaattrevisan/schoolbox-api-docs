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
use Schoolbox\Learning\Assessment\Replicator;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
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
        LtiTool\Mapper $ltiToolMapper
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
        $this->ltiToolMapper = $ltiToolMapper;
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
     * @param IAssessable $assessment
     * @param User        $student
     */
    public function postCreateResponse(IAssessable $assessment, User $student): void
    {
        throw new NotFound();
    }
}
