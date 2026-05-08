<?php

declare(strict_types=1);

namespace Schoolbox\Learning\Upcoming\Test;

use Alaress\Web\Request\Values;
use Alaress_Date_Format;
use External_ClassInterface;
use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Filter;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\WorkType\Model as WorkType;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * Tests Upcoming controller filtering for student due work visibility.
 */
class ControllerTest extends TestCase
{
    /**
     * Ensures parent viewers use parent visibility when loading a student's upcoming work.
     */
    public function testPostUpcomingForStudentChecksVisibilityAsParentForParentViewer(): void
    {
        $this->assertUpcomingWorkCountForViewer(
            Role::PARENT,
            true,
            false,
            0
        );
    }

    /**
     * Ensures staff viewers use student visibility when loading a student's upcoming work.
     */
    public function testPostUpcomingForStudentChecksVisibilityAsStudentForStaffViewer(): void
    {
        $this->assertUpcomingWorkCountForViewer(
            Role::STAFF,
            false,
            false,
            0
        );
    }

    /**
     * Builds controller dependencies and asserts the rendered upcoming work count for the supplied viewer role.
     */
    private function assertUpcomingWorkCountForViewer(
        string $viewerRoleType,
        bool $isVisibleToLearners,
        bool $isVisibleToParents,
        int $expectedCount
    ): void {
        $student = $this->buildUser(10, Role::STUDENT);
        $viewer = $this->buildUser(20, $viewerRoleType);
        $folder = (new Folder())->setId(30);

        $participant = $this->createMock(Participant::class);
        $participant
            ->expects($this->once())
            ->method('isResponseRequired')
            ->willReturn(true);
        $participant
            ->method('getUser')
            ->willReturn($student);
        $participant
            ->method('getCloseDate')
            ->willReturn(Alaress_Date_Format::now()->addDays(1));

        $workType = (new WorkType())
            ->setIsVisibleToLearners($isVisibleToLearners)
            ->setIsVisibleToParents($isVisibleToParents);

        $assessment = $this->createStub(IAssessable::class);
        $assessment
            ->method('getParticipantIfExtant')
            ->willReturnCallback(
                /**
                 * Returns the participant only when the controller asks for the target student.
                 */
                static function (User $user) use ($student, $participant): ?Participant {
                    return $user->getId() === $student->getId() ? $participant : null;
                }
            );
        $assessment
            ->method('getBaseContext')
            ->willReturn($folder);
        $assessment
            ->method('getWorkType')
            ->willReturn($workType);
        $assessment
            ->method('isPublished')
            ->willReturn(true);
        $assessment
            ->method('getCloseAt')
            ->willReturn(Alaress_Date_Format::now()->addDays(1));
        $assessment
            ->method('getContext')
            ->willReturn($folder);

        $allWork = new GenericCollection(IAssessable::class, [$assessment]);

        $assessmentIdFinder = $this->createMock(Filter\Filters\Assessment\IdFinder::class);
        $assessmentIdFinder
            ->expects($this->once())
            ->method('findIdsByFilters')
            ->willReturn([123]);

        $assessmentDataSource = $this->createMock(AssessmentMapperProxy::class);
        $assessmentDataSource
            ->expects($this->once())
            ->method('findAllByStudentBetweenDates')
            ->willReturn($allWork);

        $access = $this->createStub(Access::class);
        $access
            ->method('isInstructor')
            ->willReturn(false);
        $access
            ->method('isLearner')
            ->willReturnCallback(
                /**
                 * Treats only the target student as a learner in the access stub.
                 */
                static function (User $user) use ($student): bool {
                    return $user->getId() === $student->getId();
                }
            );
        $access
            ->method('getChildLearnersOfParent')
            ->willReturn(new GenericCollection(User::class, [$student]));
        $access
            ->method('getFolderAccess')
            ->willReturn(UserAccess::ACCESS_DENY);

        $filterFactoryFactory = $this->createMock(Filter\IFactoryFactory::class);
        $filterFactoryFactory
            ->expects($this->once())
            ->method('convertSelectionsToFilters')
            ->willReturn([]);

        $filterSelectionMapper = $this->createMock(Filter\Selection\Mapper::class);
        $filterSelectionMapper
            ->expects($this->once())
            ->method('updateSelections');

        $controller = new TestableController(
            $this->createStub(External_ClassInterface::class),
            $this->createStub(FolderMapper::class),
            $this->createStub(UserMapper::class),
            $this->createStub(TermMapper::class),
            $assessmentDataSource,
            $this->createStub(\Schoolbox\Learning\Assessment\Participant\DataSource::class),
            $filterFactoryFactory,
            $assessmentIdFinder,
            $filterSelectionMapper,
            $access
        );
        $controller
            ->setUser($viewer)
            ->setRequest(new ServerRequest('POST', '/learning/due/10', [], null, '1.1', ['REQUEST_URI' => '/learning/due/10']))
            ->setRequestPost(new Values([]));

        $controller->postUpcomingForStudent($student);

        self::assertCount($expectedCount, $controller->getRenderedData()['upcomingWork']);
    }

    /**
     * Builds a user with the role required by the controller scenario.
     */
    private function buildUser(int $id, string $roleType): User
    {
        $role = (new Role())->setType($roleType);

        return (new User())
            ->setId($id)
            ->setRole($role);
    }
}
