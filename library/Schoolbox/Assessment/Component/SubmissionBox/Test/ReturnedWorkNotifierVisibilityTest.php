<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkMapper;
use Schoolbox\Assessment\Component\SubmissionBox\ReturnedWorkNotifier;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Term;
use Schoolbox\External;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DataModel\Model as AssessmentModel;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentDataSource;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Realtime\Message\NS;
use Schoolbox\Realtime\Message\NSMapper;
use Schoolbox\Realtime\Schedule\Event\Event;
use Schoolbox\Realtime\Schedule\Trigger\TriggerBundleInterface;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Tests returned-work notifier behaviour for work-type visibility per audience.
 */
final class ReturnedWorkNotifierVisibilityTest extends TestCase implements TriggerBundleInterface
{
    /** @var array<string,int|callable> */
    public array $attached = [];

    /** @var string[] */
    public array $detached = [];

    private ?Event $event = null;

    private string $method = '';

    private const int WORK_TYPE_SI = 1;
    private const int WORK_TYPE_SIL = 2;
    private const int WORK_TYPE_SIP = 3;
    private const int WORK_TYPE_SILP = 4;

    private const int BOX_ID = 101;
    private const int ASSESSMENT_ID = 201;
    private const int STUDENT_ID = 501;
    private const int EFFECTOR_ID = 601;

    private const string TRIGGER_RETURNED = 'returned';

    public function attach($label, $timestamp)
    {
        $this->attached[$label] = $timestamp;
    }

    public function attachEach($array)
    {
        foreach ($array as $label => $timestamp) {
            $this->attach($label, $timestamp);
        }
    }

    public function detach($label)
    {
        $this->detached[] = $label;
    }

    public function detachEach($array)
    {
        foreach ($array as $label) {
            $this->detach($label);
        }
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getEvent()
    {
        self::assertNotNull($this->event);
        return $this->event;
    }

    public function getTriggers()
    {
        return [];
    }

    /**
     * notifyStudentReturned must not produce a notification for work types whose visibility excludes learners.
     */
    #[DataProvider('provideLearnerHiddenWorkTypes')]
    public function testNotifyStudentReturnedSuppressesNotificationForLearnerHiddenWorkTypes(int $workTypeId): void
    {
        $notifier = $this->notifierForWorkType($this->workType($workTypeId));
        $returnedWork = $this->returnedWorkForWorkType($workTypeId);

        $envelope = $notifier->notifyStudentReturned($returnedWork, $this->effector());

        self::assertSame([], array_keys($envelope->getAll()));
    }

    /**
     * notifyParentReturned must not produce a notification for work types whose visibility excludes parents.
     */
    #[DataProvider('provideParentHiddenWorkTypes')]
    public function testNotifyParentReturnedSuppressesNotificationForParentHiddenWorkTypes(int $workTypeId): void
    {
        $notifier = $this->notifierForWorkType($this->workType($workTypeId));
        $returnedWork = $this->returnedWorkForWorkType($workTypeId);

        $envelope = $notifier->notifyParentReturned($returnedWork, $this->effector());

        self::assertSame([], array_keys($envelope->getAll()));
    }

    /**
     * bundleTriggers must not attach a returned trigger when the work type hides both learners and parents.
     */
    public function testBundleTriggersDoesNotAttachReturnedForStaffOnlyWorkType(): void
    {
        $notifier = $this->notifierForWorkType($this->workType(self::WORK_TYPE_SI));
        $bundle = $this->bundleForReturnedWork(BaseMapper::METHOD_UPDATE, self::WORK_TYPE_SI);

        $notifier->bundleTriggers($bundle);

        self::assertSame([], array_keys($bundle->attached));
    }

    /**
     * bundleTriggers must attach the returned trigger for work types visible to at least one audience.
     */
    #[DataProvider('provideAtLeastOneVisibleWorkTypes')]
    public function testBundleTriggersAttachesReturnedForWorkTypesVisibleToAnyAudience(int $workTypeId): void
    {
        $notifier = $this->notifierForWorkType($this->workType($workTypeId));
        $bundle = $this->bundleForReturnedWork(BaseMapper::METHOD_UPDATE, $workTypeId);

        $notifier->bundleTriggers($bundle);

        self::assertSame([self::TRIGGER_RETURNED], array_keys($bundle->attached));
    }

    /**
     * @return array<string,array{int}>
     */
    public static function provideLearnerHiddenWorkTypes(): array
    {
        return [
            'staff-only work type hides learners' => [self::WORK_TYPE_SI],
            'staff and parents only work type hides learners' => [self::WORK_TYPE_SIP],
        ];
    }

    /**
     * @return array<string,array{int}>
     */
    public static function provideParentHiddenWorkTypes(): array
    {
        return [
            'staff-only work type hides parents' => [self::WORK_TYPE_SI],
            'staff and students only work type hides parents' => [self::WORK_TYPE_SIL],
        ];
    }

    /**
     * @return array<string,array{int}>
     */
    public static function provideAtLeastOneVisibleWorkTypes(): array
    {
        return [
            'students only is visible to learners' => [self::WORK_TYPE_SIL],
            'parents only is visible to parents' => [self::WORK_TYPE_SIP],
            'students and parents is visible to both' => [self::WORK_TYPE_SILP],
        ];
    }

    private function workType(int $id): WorkType\Model
    {
        return (new WorkType\Model())
            ->setId($id)
            ->setName('Homework')
            ->setIsVisibleToLearners(in_array($id, [self::WORK_TYPE_SIL, self::WORK_TYPE_SILP], true))
            ->setIsVisibleToParents(in_array($id, [self::WORK_TYPE_SIP, self::WORK_TYPE_SILP], true));
    }

    private function effector(): User
    {
        return (new User())->setId(self::EFFECTOR_ID);
    }

    private function student(): User
    {
        return (new User())->setId(self::STUDENT_ID);
    }

    /**
     * Builds a submission box with the supplied work type linked through the assessment model.
     */
    private function submissionBoxForWorkType(int $workTypeId): SubmissionBox
    {
        $assessment = (new AssessmentModel())
            ->setId(self::ASSESSMENT_ID)
            ->setWorkType($this->workType($workTypeId));

        $box = (new SubmissionBox())
            ->setId(self::BOX_ID)
            ->setMarkVisibility(SubmissionBox::MARK_VISIBILITY_STAFF_STUDENT_PARENT);
        $box->setAssessmentModel($assessment);

        return $box;
    }

    /**
     * Builds the returned-work fixture with a submission box pointing at the supplied work type.
     */
    private function returnedWorkForWorkType(int $workTypeId): ReturnedWork
    {
        $returnedWork = new ReturnedWork();
        $returnedWork->setSubmissionBox($this->submissionBoxForWorkType($workTypeId));
        $returnedWork->setOwner($this->student());
        $returnedWork->setResubmissionRequired(false);
        $returnedWork->setReviewRoute(ReturnedWork::REVIEW_ROUTE_INSTRUCTOR);
        $returnedWork->setPublishedAt(\Alaress_Date_Format::now());

        return $returnedWork;
    }

    /**
     * Builds a notifier whose work-type mapper hydrates the supplied work type, and whose
     * dependencies are configured to reach the per-audience visibility check in dispatch.
     */
    private function notifierForWorkType(WorkType\Model $workType): ReturnedWorkNotifier
    {
        $workTypeMapper = $this->createStub(WorkType\Mapper::class);
        $workTypeMapper->method('findById')->willReturn($workType);

        $hydratedBox = $this->submissionBoxForWorkType((int)$workType->getId());
        $boxMapper = $this->createStub(SubmissionBoxMapper::class);
        $boxMapper->method('findById')->willReturn($hydratedBox);
        $boxMapper->method('findByIdWithFallback')->willReturn($hydratedBox);

        $assessmentDataSource = $this->createStub(AssessmentDataSource::class);
        $assessmentDataSource->method('findByIdForReview')->willReturn($this->assessmentFor($workType));

        $userMapper = $this->createStub(UserMapper::class);
        $userMapper->method('findById')->willReturn($this->student());

        $termMapper = $this->createStub(Term\Mapper::class);
        $termMapper->method('findForDate')->willReturn(new Term\Model());

        $namespace = new NS();
        $namespace->setId(901);
        $namespace->setSlug(NSMapper::NS_DUEWORK);
        $nsMapper = $this->createStub(NSMapper::class);
        $nsMapper->method('findOneBySlug')->willReturn($namespace);

        $config = $this->createStub(IConfigLoader::class);
        $config->method('get')->willReturnCallback(
            static function (string $key): bool {
                // grades enabled, no parent/student restrictions — dispatch must reach the visibility check.
                return $key === \Schoolbox\Config\ConfigMapper::NAME_GRADES_ENABLED;
            }
        );

        return new ReturnedWorkNotifier(
            $config,
            [
                'userMapper' => $userMapper,
                'boxMapper' => $boxMapper,
                'termMapper' => $termMapper,
                'workTypeMapper' => $workTypeMapper,
                'assessmentDataSource' => $assessmentDataSource,
                'externalUser' => $this->createStub(External\User::class),
                'nsMapper' => $nsMapper,
                'mapper' => $this->createStub(ReturnedWorkMapper::class),
            ]
        );
    }

    /**
     * Builds an assessment stub whose work type, participant, and folder satisfy the
     * dispatch-method preconditions ahead of the per-audience visibility check.
     */
    private function assessmentFor(WorkType\Model $workType): IAssessable
    {
        $participant = $this->createStub(Participant::class);
        $participant->method('getPublishDate')->willReturn(\Alaress_Date_Format::newFromTimestamp(time() - 3600));

        $folder = (new Folder())->setId(401)->setName('Test Folder');

        $context = $this->createStub(\Schoolbox\Learning\AssessmentContext::class);
        $context->method('getFolder')->willReturn($folder);

        $assessment = $this->createStub(IAssessable::class);
        $assessment->method('getId')->willReturn(self::ASSESSMENT_ID);
        $assessment->method('getTitle')->willReturn('Visibility due work');
        $assessment->method('getWorkType')->willReturn($workType);
        $assessment->method('getParticipantIfExtant')->willReturn($participant);
        $assessment->method('isFeedbackVisibleToRoleType')->willReturn(true);
        $assessment->method('getBaseContext')->willReturn($context);
        $assessment->method('getDueAt')->willReturn(\Alaress_Date_Format::now());

        return $assessment;
    }

    /**
     * Builds this test case as a trigger bundle for a returned-work event with the supplied work type.
     */
    private function bundleForReturnedWork(string $method, int $workTypeId): self
    {
        $this->attached = [];
        $this->detached = [];
        $this->method = $method;

        $returnedWork = $this->returnedWorkForWorkType($workTypeId);

        $this->event = new Event(
            $this->effector(),
            $method,
            $returnedWork,
            $this->createStub(BaseMapper::class),
            null
        );

        return $this;
    }
}
