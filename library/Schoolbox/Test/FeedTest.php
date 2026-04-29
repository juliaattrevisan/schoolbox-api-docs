<?php

declare(strict_types=1);

namespace Schoolbox\Test;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\IStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;
use Schoolbox\User;

#[CoversClass(\Feed::class)]
class FeedTest extends TestCase
{
    private mixed $originalAccessInstance;

    /**
     * Preserves the original access singleton before each test runs.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->originalAccessInstance = $this->getAccessSingletonInstance();
    }

    /**
     * Restores the original access singleton after each test finishes.
     */
    protected function tearDown(): void
    {
        $this->setAccessSingletonInstance($this->originalAccessInstance);
        parent::tearDown();
    }

    public function testStaffUserWithReadAccessButNoAssessmentParticipationCanSeeStaffAndInstructorOnlyQuiz(): void
    {
        $user = $this->newUser(100, Role::STAFF);
        $this->setAccessSingletonInstance($this->newAccess([], [100]));

        $assessment = $this->newAssessment(9001);

        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow()]]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertCount(1, $result);
        self::assertSame('/assess/intro/77', $result[0]['link']);
    }

    public function testStaffUserWithReadAccessAndAssessmentParticipationCannotSeeStaffAndInstructorOnlyQuiz(): void
    {
        $user = $this->newUser(100, Role::STAFF);
        $this->setAccessSingletonInstance($this->newAccess([], [100]));

        $assessment = $this->newAssessment(9001, [100]);

        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow()], []]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertSame([], $result);
    }

    public function testStaffUserWithAssessmentParticipationButWithoutReadAccessCanSeeStaffAndInstructorOnlyQuiz(): void
    {
        $user = $this->newUser(100, Role::STAFF);
        $this->setAccessSingletonInstance($this->newAccess());

        $assessment = $this->newAssessment(9001, [100]);

        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow()]]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertCount(1, $result);
        self::assertSame('/assess/intro/77', $result[0]['link']);
    }

    public function testLearnerWithoutAssessmentParticipationCannotSeeStaffInstructorAndLearnerQuiz(): void
    {
        $user = $this->newUser(100, Role::STUDENT);
        $this->setAccessSingletonInstance($this->newAccess([], [100]));

        $assessment = $this->newAssessment(9001);
        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow(false, true)], []]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertSame([], $result);
    }

    public function testStaffUserWithReadAccessButNoAssessmentParticipationCanSeeStaffInstructorAndParentQuiz(): void
    {
        $user = $this->newUser(100, Role::STAFF);
        $this->setAccessSingletonInstance($this->newAccess([], [100]));

        $assessment = $this->newAssessment(9001);
        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow(true, false)]]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertCount(1, $result);
        self::assertSame('/assess/intro/77', $result[0]['link']);
    }

    public function testParentOfLearnerParticipantCanSeeStaffInstructorAndParentQuiz(): void
    {
        $child = $this->newUser(200, Role::STUDENT);
        $user = $this->newUser(100, Role::PARENT, [$child]);
        $this->setAccessSingletonInstance($this->newAccess([], [200]));

        $assessment = $this->newAssessment(9001, [200]);
        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], null);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow(true, false)]]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertCount(1, $result);
        self::assertSame('/assess/intro/77', $result[0]['link']);
    }

    public function testParentOfLearnerWithoutAssessmentParticipationCannotSeeStaffInstructorAndLearnerAndParentQuiz(): void
    {
        $child = $this->newUser(200, Role::STUDENT);
        $user = $this->newUser(100, Role::PARENT, [$child]);
        $this->setAccessSingletonInstance($this->newAccess([], [200]));

        $assessment = $this->newAssessment(9001);
        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], null);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow(true, true)], []]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertSame([], $result);
    }

    public function testLearnerWithoutAssessmentParticipationCannotSeeStaffInstructorAndLearnerAndParentQuiz(): void
    {
        $user = $this->newUser(100, Role::STUDENT);
        $this->setAccessSingletonInstance($this->newAccess([], [100]));

        $assessment = $this->newAssessment(9001);
        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[$this->newAssessmentRow(true, true)], []]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertSame([], $result);
    }

    /**
     * Ensures raw integer zero visibility values are treated as hidden.
     */
    public function testParentOfLearnerParticipantCannotSeeStaffInstructorOnlyQuizWhenDbReturnsIntegerZero(): void
    {
        $child = $this->newUser(200, Role::STUDENT);
        $user = $this->newUser(100, Role::PARENT, [$child]);
        $this->setAccessSingletonInstance($this->newAccess([], [200]));

        $assessment = $this->newAssessment(9001, [200]);
        $assessmentDataSource = $this->newAssessmentDataSource([$assessment], $user);
        $feed = $this->newFeed(
            $this->newDbReturningRows([[
                $this->newAssessmentRowWithRawVisibility(0, 0),
            ], []]),
            $user,
            $assessmentDataSource
        );

        $result = $feed->getOnlineAssessments([22], 1);

        self::assertSame([], $result);
    }

    /**
     * Creates a feed instance that uses the supplied assessment data source.
     */
    private function newFeed(
        IConnector $db,
        User $user,
        MapperProxy $assessmentDataSource
    ): \Feed {
        return new class ($db, $user, $assessmentDataSource) extends \Feed {
            public function __construct(
                IConnector $db,
                User $user,
                MapperProxy $assessmentDataSource
            ) {
                parent::__construct($db, $user, $assessmentDataSource);
            }

            /**
             * Returns a stable username for feed assertions.
             */
            protected function formatOnlineAssessmentUserName(array $row): string
            {
                return 'Jane Teacher';
            }
        };
    }

    /**
     * Creates an assessment data source mock for the supplied assessments.
     *
     * @param IAssessable[] $assessments
     */
    private function newAssessmentDataSource(array $assessments, ?User $participantUser): MapperProxy
    {
        $assessmentIds = array_map(
            static fn(IAssessable $assessment): int => $assessment->getId(),
            $assessments
        );
        $assessmentCollection = new GenericCollection(IAssessable::class, $assessments);

        $assessmentDataSource = $this->createMock(MapperProxy::class);
        $assessmentDataSource->expects($this->once())
            ->method('findAllByIds')
            ->with($assessmentIds)
            ->willReturn($assessmentCollection);
        $assessmentDataSource->expects($this->once())
            ->method('loadParticipantsIntoAssessmentCollection')
            ->with($assessmentCollection, $participantUser, true)
            ->willReturn($assessmentCollection);

        return $assessmentDataSource;
    }

    /**
     * Creates a database connector mock that returns the supplied row batches.
     */
    private function newDbReturningRows(array $rowSets): IConnector
    {
        $db = $this->createMock(IConnector::class);
        $db->expects($this->exactly(count($rowSets)))
            ->method('executeQuery')
            ->willReturnCallback(function () use (&$rowSets) {
                $statement = $this->createMock(IStatement::class);
                $statement->expects($this->once())
                    ->method('fetchAll')
                    ->willReturn(array_shift($rowSets));

                return $statement;
            });

        return $db;
    }

    /**
     * Creates an access stub with the supplied instructor and learner memberships.
     *
     * @param int[] $instructorUserIds
     * @param int[] $learnerUserIds
     */
    private function newAccess(array $instructorUserIds = [], array $learnerUserIds = []): Access
    {
        $access = $this->createStub(Access::class);
        $access->method('isInstructorInFolderId')
            ->willReturnCallback(static function (User $user, int $folderId) use ($instructorUserIds): bool {
                return $folderId === 22 && in_array($user->getId(), $instructorUserIds, true);
            });
        $access->method('isLearnerInFolderId')
            ->willReturnCallback(static function (User $user, int $folderId) use ($learnerUserIds): bool {
                return $folderId === 22 && in_array($user->getId(), $learnerUserIds, true);
            });
        $access->method('isParentOfLearnerInFolderId')
            ->willReturnCallback(function (User $user, int $folderId) use ($learnerUserIds): bool {
                if ($folderId !== 22 || $user->getChildren() === null) {
                    return false;
                }

                foreach ($user->getChildren() as $child) {
                    if (in_array($child->getId(), $learnerUserIds, true)) {
                        return true;
                    }
                }

                return false;
            });

        return $access;
    }

    /**
     * Creates an online assessment feed row with the supplied work type visibility.
     */
    private function newAssessmentRow(bool $isVisibleToParents = false, bool $isVisibleToLearners = false): array
    {
        return [
            'assessment_id' => 9001,
            'assess_id' => 77,
            'heading' => 'Test Quiz',
            'start' => 1_714_214_400,
            'folderId' => 22,
            'title' => 'Ms',
            'firstname' => 'Jane',
            'preferred_name' => 'Jane',
            'lastname' => 'Teacher',
            'uid' => 55,
            'external_id' => 'external-55',
            'u.deleted_at' => null,
            'cat_name' => 'Class 22',
            'cat_id' => 22,
            'is_visible_to_parents' => $isVisibleToParents ? '1' : '0',
            'is_visible_to_learners' => $isVisibleToLearners ? '1' : '0',
            'work_type_id' => 5,
        ];
    }

    /**
     * Creates an online assessment feed row with un-hydrated database visibility values.
     *
     * @param mixed $isVisibleToParents
     * @param mixed $isVisibleToLearners
     * @return array<string, mixed>
     */
    private function newAssessmentRowWithRawVisibility(
        mixed $isVisibleToParents,
        mixed $isVisibleToLearners
    ): array {
        return [
            ...$this->newAssessmentRow(),
            'is_visible_to_parents' => $isVisibleToParents,
            'is_visible_to_learners' => $isVisibleToLearners,
        ];
    }

    /**
     * Creates a user with the supplied role type and optional children.
     *
     * @param User[]|null $children
     */
    private function newUser(int $id, string $roleType, ?array $children = null): User
    {
        $role = (new Role())
            ->setId(1)
            ->setName($roleType)
            ->setType($roleType);

        $user = (new User())
            ->setId($id)
            ->setRole($role);

        if ($children !== null) {
            $user->setChildren(new GenericCollection(User::class, $children));
        }

        return $user;
    }

    /**
     * Creates an assessment stub with participants for the supplied user IDs.
     *
     * @param int[] $participantUserIds
     */
    private function newAssessment(int $assessmentId, array $participantUserIds = []): IAssessable
    {
        $participantsByUserId = [];
        foreach ($participantUserIds as $participantUserId) {
            $participantsByUserId[$participantUserId] = $this->createStub(Participant::class);
        }

        $assessment = $this->createStub(IAssessable::class);
        $assessment->method('getId')->willReturn($assessmentId);
        $assessment->method('getParticipantIfExtant')
            ->willReturnCallback(static function (User $user) use ($participantsByUserId): ?Participant {
                return $participantsByUserId[$user->getId()] ?? null;
            });

        return $assessment;
    }

    /**
     * Returns the current value of the access singleton instance.
     */
    private function getAccessSingletonInstance(): mixed
    {
        $reflection = new ReflectionClass(Access::class);
        return $reflection->getProperty('instance')->getValue();
    }

    /**
     * Sets the access singleton instance used by the feed code under test.
     */
    private function setAccessSingletonInstance(mixed $instance): void
    {
        $reflection = new ReflectionClass(Access::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, $instance);
    }
}
