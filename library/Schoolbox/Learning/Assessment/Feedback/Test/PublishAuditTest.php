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
use Alaress\Specification\ISpecification;
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
use Schoolbox\Learning\Assessment\AbstractFeedback;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\Reporting;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Mapper as ReportingFeedbackMapper;
use Schoolbox\Learning\Assessment\DataModel\Reporting\Feedback\Model as ReportingFeedback;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\Feedback\IFeedbackDataSource;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantDataSource;
use Schoolbox\Learning\Assessment\Project\ResultCache\Mapper as ProjectResultCacheMapper;
use Schoolbox\Learning\Assessment\Reporting\Model;
use Schoolbox\Learning\Assessment\Response\DataSource as ResponseDataSource;
use Schoolbox\Learning\DueWork\StateMapper;
use Schoolbox\Learning\InlineGrader\Kami\Connector as KamiConnector;
use Schoolbox\Learning\Markbook\FeedbackFolders;
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
    private const int REPORTING_INSTRUCTOR_ID = 401;
    private const int ORIGINAL_REPORTING_PUBLISHER_ID = 501;
    private const int CURRENT_REPORTING_PUBLISHER_ID = 601;

    private const int DUE_WORK_ASSESSMENT_ID = 84;
    private const int DUE_WORK_STUDENT_ID = 92;
    private const int DUE_WORK_DRAFT_RETURNED_WORK_ID = 2001;
    private const int DUE_WORK_PUBLISHED_RETURNED_WORK_ID = 2002;
    private const int DUE_WORK_DRAFT_FEEDBACK_ID = 3001;
    private const int DUE_WORK_PUBLISHED_FEEDBACK_ID = 3002;
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
     * Verifies reporting bulk publish records the bulk publisher when a draft feedback item is published.
     */
    public function testReportingBulkPublishSetsCurrentUserAsPublisherWhenDraftPublisherIsUnset(): void
    {
        $assessmentModel = new AssessmentModel()->setId(self::REPORTING_ASSESSMENT_ID);
        $assessment = new Model()
            ->setId(self::REPORTING_ASSESSMENT_ID)
            ->setContext(new Folder()->setId(self::REPORTING_CONTEXT_FOLDER_ID));
        $reporting = new Reporting\Model()->setAssessmentModel($assessmentModel);
        $feedback = new ReportingFeedback()
            ->setId(self::REPORTING_FEEDBACK_ID)
            ->setReporting($reporting)
            ->setInstructor(new User()->setId(self::REPORTING_INSTRUCTOR_ID));

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
                    $savedFeedback->getPublishedAt() instanceof Alaress_Date_Format
                    && $savedFeedback->getPublishedBy() === self::CURRENT_REPORTING_PUBLISHER_ID
            ));

        $controller = $this->buildReportingController(
            feedbackMapper: $feedbackMapper,
            projectResultCacheMapper: $this->createStub(ProjectResultCacheMapper::class),
        );
        $controller->setRequestPost(new Values(['drafts' => [self::REPORTING_FEEDBACK_ID]]));
        $controller->setUser(new User()->setId(self::CURRENT_REPORTING_PUBLISHER_ID));

        $controller->postParticipantFeedbacks($assessment);
    }

    /**
     * Verifies reporting bulk publish skips already published feedback.
     */
    public function testReportingBulkPublishSkipsAlreadyPublishedFeedback(): void
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
            ->expects($this->never())
            ->method('save');

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

        self::assertSame($originalPublishedAt, $feedback->getPublishedAt());
        self::assertSame(self::ORIGINAL_REPORTING_PUBLISHER_ID, $feedback->getPublishedBy());
        self::assertSame(
            [
                self::REPORTING_FEEDBACK_ID => [
                    'publishSuccess' => false,
                    'error' => 'Feedback already published',
                ],
            ],
            $this->getJsonResponsePayload($controller),
        );
    }

    /**
     * Verifies due work bulk publish preserves the existing draft publisher.
     */
    public function testDueWorkBulkPublishPreservesDraftPublisherWhenDraftIsPublished(): void
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
            ->setSubmissionBox($submissionBox)
            ->setPublishedBy(self::ORIGINAL_DUE_WORK_PUBLISHER_ID);

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
                    && $savedReturn->getPublishedBy() === self::ORIGINAL_DUE_WORK_PUBLISHER_ID
            ));

        $controller = $this->buildDueWorkController(
            $returnedWorkMapper,
            $this->createStub(IFeedbackDataSource::class),
        );
        $controller->setRequestPost(new Values(['drafts' => [self::DUE_WORK_DRAFT_RETURNED_WORK_ID]]));
        $controller->setUser(new User()->setId(self::CURRENT_DUE_WORK_PUBLISHER_ID));

        $controller->postParticipantFeedbacks($assessment);
    }

    /**
     * Verifies due work bulk publish skips already published feedback.
     */
    public function testDueWorkBulkPublishSkipsAlreadyPublishedFeedback(): void
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
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildDueWorkController(
            $returnedWorkMapper,
            $this->createStub(IFeedbackDataSource::class),
        );
        $controller->setRequestPost(new Values(['drafts' => [self::DUE_WORK_PUBLISHED_RETURNED_WORK_ID]]));
        $controller->setUser(new User()->setId(self::CURRENT_DUE_WORK_PUBLISHER_ID));

        $controller->postParticipantFeedbacks($assessment);

        self::assertSame($originalPublishedAt, $returnedWork->getPublishedAt());
        self::assertSame(self::ORIGINAL_DUE_WORK_PUBLISHER_ID, $returnedWork->getPublishedBy());
        self::assertSame(
            [
                self::DUE_WORK_PUBLISHED_RETURNED_WORK_ID => [
                    'publishSuccess' => false,
                    'error' => 'Feedback already published',
                ],
            ],
            $this->getJsonResponsePayload($controller),
        );
    }

    /**
     * Verifies due work single publish records who published a draft feedback item.
     */
    public function testDueWorkSinglePublishSetsPublishedAuditFieldsWhenDraftIsPublished(): void
    {
        $student = new User()->setId(self::DUE_WORK_STUDENT_ID);
        $feedback = new DueWorkFeedback()
            ->setId(self::DUE_WORK_DRAFT_FEEDBACK_ID)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);

        $assessment = $this->createDueWorkAssessmentWithFeedback($student, $feedback);
        $feedbackDataSource = $this->createMock(IFeedbackDataSource::class);
        $feedbackDataSource
            ->expects($this->once())
            ->method('save')
            ->with(self::callback(
                static fn(DueWorkFeedback $savedFeedback): bool =>
                    $savedFeedback->getPublishedAt() instanceof Alaress_Date_Format
                    && $savedFeedback->getPublishedBy() === self::CURRENT_DUE_WORK_PUBLISHER_ID
            ));

        $controller = $this->buildDueWorkController(
            $this->createStub(ReturnedWorkMapper::class),
            $feedbackDataSource,
        );
        $controller->setUser(new User()->setId(self::CURRENT_DUE_WORK_PUBLISHER_ID));

        $controller->postPublishFeedback($assessment, $student);
    }

    /**
     * Verifies due work single publish skips already published feedback.
     */
    public function testDueWorkSinglePublishSkipsAlreadyPublishedFeedback(): void
    {
        $student = new User()->setId(self::DUE_WORK_STUDENT_ID);
        $originalPublishedAt = Alaress_Date_Format::now()->modify('-1 day');
        $feedback = new DueWorkFeedback()
            ->setId(self::DUE_WORK_PUBLISHED_FEEDBACK_ID)
            ->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR)
            ->setPublishedAt($originalPublishedAt)
            ->setPublishedBy(self::ORIGINAL_DUE_WORK_PUBLISHER_ID);

        $assessment = $this->createDueWorkAssessmentWithFeedback($student, $feedback);
        $feedbackDataSource = $this->createMock(IFeedbackDataSource::class);
        $feedbackDataSource
            ->expects($this->never())
            ->method('save');

        $controller = $this->buildDueWorkController(
            $this->createStub(ReturnedWorkMapper::class),
            $feedbackDataSource,
        );
        $controller->setUser(new User()->setId(self::CURRENT_DUE_WORK_PUBLISHER_ID));

        $controller->postPublishFeedback($assessment, $student);

        self::assertSame($originalPublishedAt, $feedback->getPublishedAt());
        self::assertSame(self::ORIGINAL_DUE_WORK_PUBLISHER_ID, $feedback->getPublishedBy());
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
            $this->createStub(FeedbackFolders::class),
        );

        $this->configureControllerResponseFactory($controller);

        return $controller;
    }

    /**
     * Builds a due work feedback controller with the supplied returned work mapper.
     *
     * @param ReturnedWorkMapper $returnedWorkMapper
     * @param IFeedbackDataSource $feedbackDataSource
     * @return DueWork\Feedback\Controller
     */
    private function buildDueWorkController(
        ReturnedWorkMapper $returnedWorkMapper,
        IFeedbackDataSource $feedbackDataSource,
    ): DueWork\Feedback\Controller {
        $controller = new DueWork\Feedback\Controller(
            $feedbackDataSource,
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
     * Builds a due work assessment stub that returns a participant with the supplied feedback.
     *
     * @param User $student
     * @param AbstractFeedback $feedback
     * @return DueWork\Model
     */
    private function createDueWorkAssessmentWithFeedback(User $student, AbstractFeedback $feedback): DueWork\Model
    {
        $participant = $this->createMock(Participant::class);
        $participant
            ->expects($this->once())
            ->method('findMostRecentInstructorGivenFeedback')
            ->willReturn($feedback);

        $assessment = $this->createMock(DueWork\Model::class);
        $assessment
            ->expects($this->once())
            ->method('getParticipant')
            ->with($student)
            ->willReturn($participant);

        return $assessment;
    }

    /**
     * Configures the response factory required by JSON controller responses.
     *
     * @param Base $controller
     */
    private function configureControllerResponseFactory(Base $controller): void
    {
        $httpFactory = new HttpFactory();
        $isJsonSpec = $this->createStub(ISpecification::class);
        $isJsonSpec
            ->method('isSatisfiedBy')
            ->willReturn(true);
        $isXhrSpec = $this->createStub(ISpecification::class);
        $isXhrSpec
            ->method('isSatisfiedBy')
            ->willReturn(false);

        $controller
            ->setRequest($httpFactory->createServerRequest('POST', '/'))
            ->setIsJsonSpec($isJsonSpec)
            ->setIsXhrSpec($isXhrSpec);

        $controller->setResponseFactory(new ResponseFactory(
            $httpFactory,
            $httpFactory,
            $this->createStub(Environment::class),
        ));
    }


    /**
     * Reads the decoded JSON response payload from a controller.
     *
     * @param Base $controller
     * @return array<mixed>
     */
    private function getJsonResponsePayload(Base $controller): array
    {
        $response = $controller->getResponse();
        self::assertNotNull($response);

        return json_decode((string) $response->getBody(), true);
    }
}
