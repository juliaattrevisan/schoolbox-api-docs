<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Lti;

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
use Schoolbox\LTI\Provider;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Controller extends Assessment\Controller
{
    /**
     * Schoolbox's registry of LTI providers (a.k.a. tools).
     */
    private Provider\Mapper $ltiProviderMapper;

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
     * @param Provider\Mapper               $ltiProviderMapper
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
        Provider\Mapper $ltiProviderMapper
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
        $this->ltiProviderMapper = $ltiProviderMapper;
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
            'ltiProviders'       => $this->ltiProviderMapper->findByColumn(
                'lti_version',
                Provider\Model::LTI_VERSION_1_3,
            ),
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
                    'assessment' => $assessment,
                ],
                "Status of {$assessment->getTitle()}",
                'submission'
            )
        );
    }

    /**
     * Placeholder for a summary of a student's progress on an LTI assessment.
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @since 21.1.0
     *
     * @param IAssessable|Model $assessment
     */
    public function getSummary(IAssessable $assessment, User $student): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@learningAssessment/lti/summary.twig',
                [
                    'assessment' => $assessment,
                    'student'    => $student,
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
