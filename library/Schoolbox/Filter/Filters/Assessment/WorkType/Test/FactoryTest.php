<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Filters\Assessment\WorkType\Test;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\WorkType\Definition;
use Schoolbox\Filter\Filters\Assessment\WorkType\Factory;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\WorkType;
use Schoolbox\Role;
use Schoolbox\User;

/**
 * Tests work type filter selections for different pages and viewer roles.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(Factory::class)]
class FactoryTest extends TestCase
{
    /**
     * Ensures parents only see parent-visible work types on due work pages.
     */
    public function testDueWorkPageShowsParentVisibleWorkTypesToParents(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
            Role::PARENT,
            'is_visible_to_parents',
            [3]
        );

        self::assertSame([3], $context['roleSelections']->keys());
    }

    /**
     * Ensures staff see learner-visible work types on due work pages.
     */
    public function testDueWorkPageShowsLearnerVisibleWorkTypesToStaff(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
            Role::STAFF,
            'is_visible_to_learners',
            [2]
        );

        self::assertSame([2], $context['roleSelections']->keys());
    }

    /**
     * Ensures students see learner-visible work types on due work pages.
     */
    public function testDueWorkPageShowsLearnerVisibleWorkTypesToStudents(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_UPCOMING_ASSESSMENTS,
            Role::STUDENT,
            'is_visible_to_learners',
            [2]
        );

        self::assertSame([2], $context['roleSelections']->keys());
    }

    /**
     * Ensures staff can select all work types on grades pages.
     */
    public function testGradesPageShowsAllWorkTypesToStaff(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_GRADES,
            Role::STAFF,
            null,
            [1, 2, 3]
        );

        self::assertSame([1, 2, 3], $context['roleSelections']->keys());
    }

    /**
     * Ensures parents only see parent-visible work types on grades pages.
     */
    public function testGradesPageShowsParentVisibleWorkTypesToParents(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_GRADES,
            Role::PARENT,
            'is_visible_to_parents',
            [3]
        );

        self::assertSame([3], $context['roleSelections']->keys());
    }

    /**
     * Ensures students only see learner-visible work types on grades pages.
     */
    public function testGradesPageShowsLearnerVisibleWorkTypesToStudents(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_GRADES,
            Role::STUDENT,
            'is_visible_to_learners',
            [2]
        );

        self::assertSame([2], $context['roleSelections']->keys());
    }

    /**
     * Ensures staff-only pages expose every work type.
     */
    public function testStaffOnlyPagesShowAllWorkTypes(): void
    {
        $context = $this->buildComponentContext(
            Filter\Page\Model::PAGE_CLASS_MARK_BOOK,
            Role::STAFF,
            null,
            [1, 2, 3]
        );

        self::assertSame([1, 2, 3], $context['roleSelections']->keys());
    }

    /**
     * @param int[] $expectedWorkTypeIds
     * @return array<string, mixed>
     */
    private function buildComponentContext(
        string $page,
        string $viewerRoleType,
        ?string $expectedPredicateColumn,
        array $expectedWorkTypeIds
    ): array {
        $allWorkTypes = $this->buildWorkTypes([1, 2, 3]);
        $expectedWorkTypes = $this->buildWorkTypes($expectedWorkTypeIds);

        $filter = (new Filter\Model())
            ->setId(10)
            ->setClass(Definition::class);

        $pageFilter = (new Filter\Page\Model())
            ->setFilter($filter)
            ->setPage($page)
            ->setUserConfigurable(false);
        $pageFilter->setOptions($this->buildOptions($pageFilter, $allWorkTypes));

        $workTypeMapper = $this->buildWorkTypeMapper(
            $allWorkTypes,
            $expectedWorkTypes,
            $expectedPredicateColumn
        );

        $viewer = $this->buildUser(1, $viewerRoleType);
        $viewee = $this->buildUser(2, Role::STUDENT);

        $selectionMapper = $this->createStub(Filter\Selection\Mapper::class);
        $selectionMapper
            ->method('createCollection')
            ->willReturnCallback(
                /**
                 * Creates the empty selection collection used while building the filter component.
                 */
                static function () {
                    return new GenericCollection(Filter\Selection\Model::class);
                }
            );
        $selectionMapper
            ->method('createModel')
            ->willReturnCallback(
                /**
                 * Creates a fresh selection model for generated filter options.
                 */
                static function () {
                    return new Filter\Selection\Model();
                }
            );

        $factory = new Factory(
            $this->createStub(Filter\Page\Option\Mapper::class),
            $this->createStub(Filter\Page\Option\Defaults\Mapper::class),
            $selectionMapper,
            $workTypeMapper
        );

        $definition = new Definition(
            $filter,
            $this->createStub(Filter\Page\Option\Mapper::class),
            $this->createStub(Filter\Page\Option\Defaults\Mapper::class),
            $allWorkTypes
        );

        return $factory
            ->buildComponent(
                $definition,
                $viewer,
                $pageFilter,
                null,
                $viewee,
                [],
                new GenericCollection(GenericCollection::class)
            )
            ->toContext();
    }

    /**
     * @param GenericCollection<WorkType\Model> $allWorkTypes
     * @param GenericCollection<WorkType\Model> $filteredWorkTypes
     * @return WorkType\Mapper&MockObject
     */
    private function buildWorkTypeMapper(
        GenericCollection $allWorkTypes,
        GenericCollection $filteredWorkTypes,
        ?string $expectedPredicateColumn
    ): WorkType\Mapper {
        $workTypeMapper = $this->createMock(WorkType\Mapper::class);

        if ($expectedPredicateColumn === null) {
            $workTypeMapper
                ->expects(self::once())
                ->method('findAll')
                ->willReturn($allWorkTypes);
            $workTypeMapper
                ->expects(self::never())
                ->method('getPredicateBuilder');
            $workTypeMapper
                ->expects(self::never())
                ->method('findByPredicates');

            return $workTypeMapper;
        }

        $predicateBuilder = $this->createMock(PredicateBuilder::class);
        $predicateBuilder
            ->expects(self::once())
            ->method('notEquals')
            ->with($expectedPredicateColumn, 0)
            ->willReturn($predicateBuilder);

        $workTypeMapper
            ->expects(self::never())
            ->method('findAll');
        $workTypeMapper
            ->expects(self::once())
            ->method('getPredicateBuilder')
            ->willReturn($predicateBuilder);
        $workTypeMapper
            ->expects(self::once())
            ->method('findByPredicates')
            ->with($predicateBuilder)
            ->willReturn($filteredWorkTypes);

        return $workTypeMapper;
    }

    /**
     * Builds a user with the role needed by the filter component tests.
     */
    private function buildUser(int $id, string $roleType): User
    {
        return (new User())
            ->setId($id)
            ->setRole((new Role())->setType($roleType));
    }

    /**
     * @param int[] $ids
     * @return GenericCollection<WorkType\Model>
     */
    private function buildWorkTypes(array $ids): GenericCollection
    {
        $workTypes = new GenericCollection(WorkType\Model::class);

        foreach ($ids as $id) {
            $workTypes->setOffset(
                $id,
                (new WorkType\Model())
                    ->setId($id)
                    ->setName("Work type {$id}")
            );
        }

        return $workTypes;
    }

    /**
     * @param GenericCollection<WorkType\Model> $workTypes
     * @return GenericCollection<Filter\Page\Option\Model>
     */
    private function buildOptions(Filter\Page\Model $pageFilter, GenericCollection $workTypes): GenericCollection
    {
        $options = new GenericCollection(Filter\Page\Option\Model::class);

        foreach ($workTypes as $workType) {
            $option = (new Filter\Page\Option\Model())
                ->setFilterPage($pageFilter)
                ->setKey((string)$workType->getId())
                ->setAvailable(true)
                ->setDefaults(new GenericCollection(Filter\Page\Option\Defaults\Model::class));

            foreach ([Role::STAFF, Role::PARENT, Role::STUDENT] as $roleType) {
                $option->getDefaults()->setOffset(
                    $roleType,
                    (new Filter\Page\Option\Defaults\Model())
                        ->setFilterOption($option)
                        ->setRoleType($roleType)
                        ->setSelected(true)
                );
            }

            $options->setOffset($option->getKey(), $option);
        }

        return $options;
    }
}
