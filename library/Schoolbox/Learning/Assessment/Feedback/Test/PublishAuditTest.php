<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Feedback\Test;

use Alaress\Web\Controller\Base;
use Alaress\Web\Request\Values;
use Alaress\Web\Response\Factory as ResponseFactory;
use Alaress_Date_Format;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\Instance\Mapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Reporting;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper as ReportingFeedbackMapper;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model as ReportingFeedback;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Project\ResultCache\Mapper as ProjectResultCacheMapper;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\InlineGrader\Kami\Connector as KamiConnector;
use Schoolbox\Learning\Reporting\Feedback\Controller;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Twig\Environment;

/**
 * Tests publish audit fields for reporting and due work feedback bulk publishing.
 */
class PublishAuditTest extends TestCase
{
    /**
     * These IDs are arbitrary identifiers used to link mocked models together.
     * They do not refer to rows that are expected to exist in the database.
     */
    private const int REPORTING_ASSESSMENT_ID = 42;
    private const int REPORTING_CONTEXT_FOLDER_ID = 1;
    private const int REPORTING_FEEDBACK_ID = 1001;
    private const int ORIGINAL_REPORTING_PUBLISHER_ID = 501;
    private const int CURRENT_REPORTING_PUBLISHER_ID = 601;

    private const int DUE_WORK_ASSESSMENT_ID = 84;
    private const int DUE_WORK_DRAFT_RETURNED_WORK_ID = 2001;
    private const int DUE_WORK_PUBLISHED_RETURNED_WORK_ID = 2002;
    private const int ORIGINAL_DUE_WORK_PUBLISHER_ID = 502;
    private const int CURRENT_DUE_WORK_PUBLISHER_ID = 602;

    /**
     * Verifies reporting feedback published state is derived from published_at.
     */
    public function testReportingFeedbackPublishedStateFollowsPublishedAt(): void
    {
        $feedback = new ReportingFeedback();

        self::assertFalse($feedback->isPublished());

        $feedback->setPublishedAt(Alaress_Date_Format::now());

        self::assertTrue($feedback->isPublished());
    }

    /**
     * Verifies reporting bulk publish does not overwrite existing published audit fields.
     */
    public function testReportingBulkPublishPreservesExistingPublishedAuditFields(): void
    {
        $assessmentModel = new AssessmentModel()->setId(self::REPORTING_ASSESSMENT_ID);
        $assessment = new Model()
            ->setId(self::REPORTING_ASSESSMENT_ID)
            ->setContext(new Folder()->setId(self::REPORTING_CONTEXT_FOLDER_ID));
        $reporting = new Reporting\Model()->setAssessmentModel($assessmentModel);
        $originalPublishedAt = Alaress_Date_Format::now()->modify('-1 day');
        $feedback = new ReportingFeedback()
            ->setId(self::REPORTING_FEEDBACK_ID)
            ->setReporting($reporting)
            ->setPublishedAt($originalPublishedAt)
            ->setPublishedBy(self::ORIGINAL_REPORTING_PUBLISHER_ID);

        $feedbackMapper = $this->createMock(ReportingFeedbackMapper::class);
        $feedbackMapper
            ->expects($this->once())
            ->method('findById')
            ->with(self::REPORTING_FEEDBACK_ID)
            ->willReturn($feedback);
        $feedbackMapper
            ->expects($this->once())
            ->method('save')
            ->with(self::callback(
                static fn(ReportingFeedback $savedFeedback): bool =>
                    $savedFeedback->getPublishedAt() === $originalPublishedAt
                    && $savedFeedback->getPublishedBy() === self::ORIGINAL_REPORTING_PUBLISHER_ID
            ));

        $projectResultCacheMapper = $this->createMock(ProjectResultCacheMapper::class);
        $projectResultCacheMapper
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildReportingController(
            feedbackMapper: $feedbackMapper,
            projectResultCacheMapper: $projectResultCacheMapper,
        );
        $controller->setRequestPost(new Values(['drafts' => [self::REPORTING_FEEDBACK_ID]]));
        $controller->setUser(new User()->setId(self::CURRENT_REPORTING_PUBLISHER_ID));

        $controller->postParticipantFeedbacks($assessment);
    }

    /**
     * Verifies due work bulk publish records who published a draft feedback item.
     */
    public function testDueWorkBulkPublishSetsPublishedAuditFieldsWhenDraftIsPublished(): void
    {
        $assessment = $this->createStub(DueWork\Model::class);
        $assessment
            ->method('isSpecification')
            ->willReturn(false);
        $assessment
            ->method('getId')
            ->willReturn(self::DUE_WORK_ASSESSMENT_ID);

        $submissionBox = new SubmissionBox()->setAssessmentModel(
            new AssessmentModel()->setId(self::DUE_WORK_ASSESSMENT_ID)
        );
        $returnedWork = new ReturnedWork()
            ->setId(self::DUE_WORK_DRAFT_RETURNED_WORK_ID)
            ->setSubmissionBox($submissionBox);

        $returnedWorkMapper = $this->createMock(ReturnedWorkMapper::class);
        $returnedWorkMapper
            ->expects($this->once())
            ->method('findById')
            ->with(self::DUE_WORK_DRAFT_RETURNED_WORK_ID)
            ->willReturn($returnedWork);
        $returnedWorkMapper
            ->expects($this->once())
            ->method('save')
            ->with(self::callback(
                static fn(ReturnedWork $savedReturn): bool =>
                    $savedReturn->getPublishedAt() instanceof Alaress_Date_Format
                    && $savedReturn->getPublishedBy() === self::CURRENT_DUE_WORK_PUBLISHER_ID
            ));

        $controller = $this->buildDueWorkController($returnedWorkMapper);
        $controller->setRequestPost(new Values(['drafts' => [self::DUE_WORK_DRAFT_RETURNED_WORK_ID]]));
        $controller->setUser(new User()->setId(self::CURRENT_DUE_WORK_PUBLISHER_ID));

        $controller->postParticipantFeedbacks($assessment);
    }

    /**
     * Verifies due work bulk publish does not overwrite existing published audit fields.
     */
    public function testDueWorkBulkPublishPreservesExistingPublishedAuditFields(): void
    {
        $assessment = $this->createStub(DueWork\Model::class);
        $assessment
            ->method('isSpecification')
            ->willReturn(false);
        $assessment
            ->method('getId')
            ->willReturn(self::DUE_WORK_ASSESSMENT_ID);

        $originalPublishedAt = Alaress_Date_Format::now()->modify('-1 day');
        $submissionBox = new SubmissionBox()->setAssessmentModel(
            new AssessmentModel()->setId(self::DUE_WORK_ASSESSMENT_ID)
        );
        $returnedWork = new ReturnedWork()
            ->setId(self::DUE_WORK_PUBLISHED_RETURNED_WORK_ID)
            ->setSubmissionBox($submissionBox)
            ->setPublishedAt($originalPublishedAt)
            ->setPublishedBy(self::ORIGINAL_DUE_WORK_PUBLISHER_ID);

        $returnedWorkMapper = $this->createMock(ReturnedWorkMapper::class);
        $returnedWorkMapper
            ->expects($this->once())
            ->method('findById')
            ->with(self::DUE_WORK_PUBLISHED_RETURNED_WORK_ID)
            ->willReturn($returnedWork);
        $returnedWorkMapper
            ->expects($this->once())
            ->method('save')
            ->with(self::callback(
                static fn(ReturnedWork $savedReturn): bool =>
                    $savedReturn->getPublishedAt() === $originalPublishedAt
                    && $savedReturn->getPublishedBy() === self::ORIGINAL_DUE_WORK_PUBLISHER_ID
            ));

        $controller = $this->buildDueWorkController($returnedWorkMapper);
        $controller->setRequestPost(new Values(['drafts' => [self::DUE_WORK_PUBLISHED_RETURNED_WORK_ID]]));
        $controller->setUser(new User()->setId(self::CURRENT_DUE_WORK_PUBLISHER_ID));

        $controller->postParticipantFeedbacks($assessment);
    }

    /**
     * Builds a reporting feedback controller with the supplied feedback mapper.
     *
     * @param ReportingFeedbackMapper $feedbackMapper
     * @param ProjectResultCacheMapper $projectResultCacheMapper
     * @return Controller
     */
    private function buildReportingController(
        ReportingFeedbackMapper $feedbackMapper,
        ProjectResultCacheMapper $projectResultCacheMapper,
    ): Controller {
        $controller = new Controller(
            $this->createStub(ErrorMapper::class),
            $this->createStub(BreadcrumbFactory::class),
            $this->createStub(Reporting\Mapper::class),
            $this->createStub(TermMapper::class),
            $this->createStub(StateMapper::class),
            $this->createStub(ParticipantDataSource::class),
            $this->createStub(IFeedbackDataSource::class),
            $feedbackMapper,
            $this->createStub(AssessmentDataSource::class),
            $projectResultCacheMapper,
        );

        $this->configureControllerResponseFactory($controller);

        return $controller;
    }

    /**
     * Builds a due work feedback controller with the supplied returned work mapper.
     *
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @return DueWork\Feedback\Controller
     */
    private function buildDueWorkController(ReturnedWorkMapper $returnedWorkMapper): DueWork\Feedback\Controller
    {
        $controller = new DueWork\Feedback\Controller(
            $this->createStub(IFeedbackDataSource::class),
            $this->createStub(ParticipantDataSource::class),
            $this->createStub(AssessmentDataSource::class),
            $this->createStub(ResponseDataSource::class),
            $this->createStub(UserMapper::class),
            $this->createStub(RubricDataSource::class),
            $returnedWorkMapper,
            $this->createStub(ProjectResultCacheMapper::class),
            $this->createStub(KamiConnector::class),
            $this->createStub(TermMapper::class),
            $this->createStub(Access::class),
            $this->createStub(Mapper::class),
        );

        $this->configureControllerResponseFactory($controller);

        return $controller;
    }

    /**
     * Configures the response factory required by JSON controller responses.
     *
     * @param Base $controller
     */
    private function configureControllerResponseFactory(Base $controller): void
    {
        $httpFactory = new HttpFactory();
        $controller->setResponseFactory(new ResponseFactory(
            $httpFactory,
            $httpFactory,
            $this->createStub(Environment::class),
        ));
    }
}
