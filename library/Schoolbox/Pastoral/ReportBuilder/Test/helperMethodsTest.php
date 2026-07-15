<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\Pastoral\ReportBuilder\Test;

use Alaress_Date_Format;
use PHPUnit\Framework\TestCase;
use Schoolbox\Collection;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\GenericCollection;
use Schoolbox\Pastoral\AccessLevelCensor;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\ReportBuilder\Helper;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByMonth;
use Schoolbox\Pastoral\ReportBuilder\TabType\StudentByWeek;
use Schoolbox\Pastoral\Type\Model as Type;
use Schoolbox\Pastoral\SubType\Model as Subtype;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Role;
use Schoolbox\User;
use DateTimeZone;

class helperMethodsTest extends TestCase
{
    private ArrayLoader $configLoader;

    /**
     * Setup method to initialize common dependencies.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configLoader = new ArrayLoader(
            [
                'date_local' => 'd/m/Y',
                'calendar_start_day' => 1, // Monday as week start
            ]
        );
    }

    public function testSetFiltersBuildsDenyAwareAccessFilter(): void
    {
        $viewer = (new User())
            ->setId(123)
            ->setRole((new Role())->setId(456)->setType(Role::STAFF))
            ->setAccessList(new GenericCollection(UserAccess::class));

        $pastoralMapper = $this->createMock(PastoralMapper::class);
        $pastoralMapper
            ->method('isUserInPastoralService')
            ->with($viewer)
            ->willReturn(false);

        $helper = new Helper(
            $this->buildBaseReport($viewer),
            $pastoralMapper,
            $this->configLoader,
            $viewer,
            $this->createStub(AccessLevelCensor::class),
        );

        $helper->setFilters();

        $accessFilter = current(
            array_filter(
                $helper->getFilters(),
                fn(string $filter): bool => str_contains($filter, ':user_role_id_0')
            )
        );

        $this->assertIsString($accessFilter);
        $this->assertStringContainsString('NOT EXISTS', $accessFilter);
        $this->assertStringContainsString('pa_deny.`access` = 0', $accessFilter);
        $this->assertStringContainsString('pa_role.`access` > 0', $accessFilter);
        $this->assertStringContainsString('pa_user.`access` > 0', $accessFilter);
        $this->assertStringContainsString('pa_group.`access` > 0', $accessFilter);
        $this->assertStringContainsString('`pastoral`.`owner_id` = :user_id_2', $accessFilter);
        $this->assertStringNotContainsString('COUNT(*)', $accessFilter);
        $this->assertSame(
            [
                'user_role_id_0' => 456,
                'user_id_0' => 123,
                'user_id_1' => 123,
                'user_id_2' => 123,
                'viewerCampusId_0' => -1,
                'viewerId' => 123,
                'accessDeny' => 0,
                'viewerRoleId' => 456,
                'viewerRoleType' => Role::STAFF,
            ],
            $helper->getParams()
        );
    }

    private function buildBaseReport(User $user): array
    {
        return [
            'types' => new GenericCollection(Type::class),
            'subtypes' => new GenericCollection(Subtype::class),
            'beforeDate' => null,
            'afterDate' => null,
            'severity' => null,
            'students' => new GenericCollection(User::class),
            'includeStudentsWithoutYear' => false,
            'years' => new Collection(),
            'includeStudentsWithoutHouse' => false,
            'houses' => new Collection(),
            'campuses' => new Collection(),
            'authors' => new GenericCollection(User::class),
            'actions' => new Collection(),
            'includeRecordsWithoutAction' => false,
            'involved' => true,
            'includeArchivedRecords' => true,
            'tags' => new Collection(),
            'user' => $user,
        ];
    }

    /**
     * Test the getMonthsInRange method of the Helper class.
     *
     * @param mixed $startDate
     * @param mixed $endDate
     * @param int   $expectedMonthCount
     * @param array $expectedMonths
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerMonthsInRange')]
    public function testMonthsInRange(
        $startDate,
        $endDate,
        $expectedMonthCount,
        $expectedMonths
    ) {
        $helper = new Helper(
            [],
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );
        $months = $helper->getMonthsInRange(
            $startDate,
            $endDate,
            StudentByMonth::COLUMN_DATE_FORMAT,
            Alaress_Date_Format::newDatabaseDate(
                '2025-10-02 12:00:00',
                new DateTimeZone('UTC')
            )
        );

        // Validate the count of months
        $this->assertEquals($expectedMonthCount, $months->count());

        // Validate the actual month values
        $this->assertEquals($expectedMonths, $months->getAll());
    }

    /**
     * Data provider for testMonthsInRange.
     *
     * @return array[]
     */
    public static function providerMonthsInRange(): array
    {
        /* Arbitrary fixed 'now' date for consistent testing
         */
        $now = Alaress_Date_Format::newDatabaseDate(
            '2025-03-15 12:00:00',
            new DateTimeZone('UTC')
        );

        return [
            'Case 1: Past Fortnight' => [
                (clone $now)->addWeeks(-2),
                (clone $now),
                1,
                [
                    'mar_2025'
                ],
            ],
            'Case 2: Past 3 Months' => [
                (clone $now)->addMonths(-3),
                (clone $now),
                4,
                [
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            /* The year up until the current month
             */
            'Case 3: This Year' => [
                (clone $now)->startOfYear(),
                (clone $now)->endOfYear(),
                10,
                [
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                    'apr_2025',
                    'may_2025',
                    'jun_2025',
                    'jul_2025',
                    'aug_2025',
                    'sep_2025',
                    'oct_2025',
                ]
            ],
            'Case 4: Single Month (Full)' => [
                Alaress_Date_Format::newDatabaseDate('2025-01-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-01-31 23:59:59', new DateTimeZone('UTC')),
                1,
                [
                    'jan_2025',
                ],
            ],
            'Case 5: Two Consecutive Months' => [
                Alaress_Date_Format::newDatabaseDate('2025-01-15 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-02-28 23:59:59', new DateTimeZone('UTC')),
                2,
                [
                    'jan_2025',
                    'feb_2025',
                ],
            ],
            'Case 6: Cross Year Boundary (6 months)' => [
                Alaress_Date_Format::newDatabaseDate('2024-10-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-03-31 23:59:59', new DateTimeZone('UTC')),
                6,
                [
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            'Case 7: Full Calendar Year (Past Year)' => [
                Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2024-12-31 23:59:59', new DateTimeZone('UTC')),
                12,
                [
                    'jan_2024',
                    'feb_2024',
                    'mar_2024',
                    'apr_2024',
                    'may_2024',
                    'jun_2024',
                    'jul_2024',
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                ],
            ],
            'Case 8: Same Month (Different Days)' => [
                Alaress_Date_Format::newDatabaseDate('2025-03-05 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-03-25 23:59:59', new DateTimeZone('UTC')),
                1,
                [
                    'mar_2025',
                ],
            ],
            'Case 9: Six Months Range' => [
                Alaress_Date_Format::newDatabaseDate('2024-08-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-01-31 23:59:59', new DateTimeZone('UTC')),
                6,
                [
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                ],
            ],
            'Case 10: One Month in Past' => [
                (clone $now)->addMonths(-1)->startOfMonth(),
                (clone $now)->addMonths(-1)->endOfMonth(),
                1,
                [
                    'feb_2025',
                ],
            ],
            'Case 11: Past 12 Months' => [
                (clone $now)->addMonths(-12),
                (clone $now),
                13,
                [
                    'mar_2024',
                    'apr_2024',
                    'may_2024',
                    'jun_2024',
                    'jul_2024',
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            'Case 12: February Leap Year' => [
                Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2024-02-29 23:59:59', new DateTimeZone('UTC')),
                1,
                [
                    'feb_2024',
                ],
            ],
            'Case 13: Quarter (Q1)' => [
                Alaress_Date_Format::newDatabaseDate('2025-01-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-03-31 23:59:59', new DateTimeZone('UTC')),
                3,
                [
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                ],
            ],
            'Case 14: Academic Year (Jul-Jun)' => [
                Alaress_Date_Format::newDatabaseDate('2024-07-01 00:00:00', new DateTimeZone('UTC')),
                Alaress_Date_Format::newDatabaseDate('2025-06-30 23:59:59', new DateTimeZone('UTC')),
                12,
                [
                    'jul_2024',
                    'aug_2024',
                    'sep_2024',
                    'oct_2024',
                    'nov_2024',
                    'dec_2024',
                    'jan_2025',
                    'feb_2025',
                    'mar_2025',
                    'apr_2025',
                    'may_2025',
                    'jun_2025',
                ],
            ],
        ];
    }

    /**
     * @param $startDate
     * @param $endDate
     * @param $expectedWeekCount
     * @param $validateLastWeek
     * @param $validateFirstWeek
     * @return void
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerWeeksInRange')]
    public function testWeeksInRange(
        $startDate,
        $endDate,
        $expectedWeekCount,
        $validateLastWeek = null,
        $validateFirstWeek = null
    ) {
        $helper = new Helper(
            [],
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );
        $weeks = $helper->getWeeksInRange(
            $startDate,
            $endDate,
            StudentByWeek::COLUMN_QUERY_KEY,
        );

        $this->assertEquals($expectedWeekCount, $weeks->count());

        // Validate structure of first and last week if provided
        if (null !== $validateFirstWeek && $weeks->count() > 0) {
            $firstWeek = $weeks->first();
            $this->assertArrayHasKey('start', $firstWeek);
            $this->assertArrayHasKey('end', $firstWeek);
            $this->assertArrayHasKey('key', $firstWeek);
            $this->assertArrayHasKey('label', $firstWeek);

            if (isset($validateFirstWeek['key'])) {
                $this->assertEquals($validateFirstWeek['key'], $firstWeek['key']);
            }
            if (isset($validateFirstWeek['label'])) {
                $this->assertEquals($validateFirstWeek['label'], $firstWeek['label']);
            }
        }

        if (null !== $validateLastWeek && $weeks->count() > 0) {
            $lastWeek = $weeks->last();
            if (isset($validateLastWeek['key'])) {
                $this->assertEquals($validateLastWeek['key'], $lastWeek['key']);
            }
            if (isset($validateLastWeek['label'])) {
                $this->assertEquals($validateLastWeek['label'], $lastWeek['label']);
            }
        }
    }

    /**
     * Data provider for testWeeksInRange.
     *
     * @return array[]
     */
    public static function providerWeeksInRange(): array
    {
        /* Arbitrary fixed 'now' date for consistent testing
         */
        $now = Alaress_Date_Format::newDatabaseDate(
            '2025-03-15 12:00:00',
            new DateTimeZone('UTC')
        );

        return [
            'Case 1: Past Fortnight (2 weeks)' => [
                (clone $now)->addWeeks(-2),
                (clone $now),
                3, // Should include partial weeks
                ['key' => 'week_0'],
                ['key' => 'week_2'],
            ],
            'Case 2: Past Month (approximately 4-5 weeks)' => [
                (clone $now)->addMonths(-1),
                (clone $now),
                5,
                ['key' => 'week_0'],
                ['key' => 'week_4'],
            ],
            'Case 3: Past 3 Months' => [
                (clone $now)->addMonths(-3),
                (clone $now),
                13, // Approximately 13 weeks in 3 months
                ['key' => 'week_0'],
                ['key' => 'week_12'],
            ],
            'Case 4: Single Week' => [
                (clone $now)->addWeeks(-1)->startOfWeek(), // Start of last week (Sunday)
                (clone $now)->addWeeks(-1)->endOfWeek(),   // End of last week (Saturday)
                1,
                ['key' => 'week_0'],
                ['key' => 'week_0'],
            ],
            'Case 5: Current Week Only' => [
                (clone $now)->startOfWeek(),
                (clone $now),
                1,
                ['key' => 'week_0'],
                ['key' => 'week_0'],
            ],
            'Case 9: Year to Date' => [
                (clone $now)->startOfYear(),
                (clone $now),
                11, // From Jan 1 to Mar 15, 2025
                ['key' => 'week_0'],
                ['key' => 'week_10'],
            ],
        ];
    }

    /**
     * Test that getMonthsInRange throws DateRangeError when startDate is null.
     */
    public function testMonthsInRangeThrowsExceptionForNullStartDate()
    {
        $helper = new Helper(
            [],
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );

        $this->expectException(\DateRangeError::class);
        $this->expectExceptionMessage('Timeframe is invalid. No start date provided.');

        $helper->getMonthsInRange(
            null,
            Alaress_Date_Format::newDatabaseDate(
                '2025-03-15 12:00:00',
                new DateTimeZone('UTC')
            ),
            StudentByMonth::COLUMN_DATE_FORMAT,
            Alaress_Date_Format::newDatabaseDate(
                '2025-03-15 12:00:00',
                new DateTimeZone('UTC')
            )
        );
    }

    /**
     * Test that getWeeksInRange throws DateRangeError when startDate is null.
     */
    public function testWeeksInRangeThrowsExceptionForNullStartDate()
    {
        $helper = new Helper(
            [],
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );

        $this->expectException(\DateRangeError::class);
        $this->expectExceptionMessage('Timeframe is invalid. No start date provided.');

        $helper->getWeeksInRange(
            null,
            Alaress_Date_Format::newDatabaseDate(
                '2025-03-15 12:00:00',
                new DateTimeZone('UTC')
            ),
            StudentByWeek::COLUMN_QUERY_KEY,
            Alaress_Date_Format::newDatabaseDate(
                '2025-03-15 12:00:00',
                new DateTimeZone('UTC')
            )
        );
    }

    /**
     * Test the getType method to ensure it returns types that don't have subtypes.
     *
     * @param array $report
     * @param int   $expectedCount
     * @param array $expectedTypeNames
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerGetType')]
    public function testGetType(array $report, int $expectedCount, array $expectedTypeNames = [])
    {
        $helper = new Helper(
            $report,
            $this->createMock(PastoralMapper::class),
            $this->configLoader,
            new User(),
            $this->createMock(AccessLevelCensor::class),
        );

        $result = $helper->getType();

        // Validate the count of types returned
        $this->assertEquals(
            $expectedCount,
            $result->count(),
            "Expected {$expectedCount} types without subtypes, but got {$result->count()}"
        );

        // Validate it returns a Collection
        $this->assertInstanceOf(Collection::class, $result);

        // Validate the specific type names if provided
        if (!empty($expectedTypeNames)) {
            $actualTypeNames = $result->map(fn($type) => $type->getName())->getAll();
            $this->assertEquals(
                $expectedTypeNames,
                $actualTypeNames,
                "Expected types: [" . implode(', ', $expectedTypeNames) . "], " .
                "but got: [" . implode(', ', $actualTypeNames) . "]"
            );
        }
    }

    /**
     * Data provider for testGetType.
     *
     * @return array[]
     */
    public static function providerGetType(): array
    {
        return [
            'Case 1: Empty types and subtypes' => [
                [
                    'types' => new GenericCollection(Type::class),
                    'subtypes' => new GenericCollection(Subtype::class),
                ],
                0, // Expected count
                [], // Expected type names
            ],
            'Case 2: Empty subtypes' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add(new Type()->setName('Type A'))
                        ->add(new Type()->setName('Type B')),
                    'subtypes' => new GenericCollection(Subtype::class)
                ],
                2, // Expected count
                ['Type A', 'Type B'], // Expected type names
            ],
            'Case 3: One type with subtypes, one without' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A'))
                        ->add(new Type()->setName('Type B')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype B'))
                ],
                1, // Expected count
                ['Type B'], // Expected type names
            ],
            'Case 4: All types have subtypes' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A'))
                        ->add($typeB = new Type()->setName('Type B')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A1'))
                        ->add(new Subtype()->setPastoralType($typeB)->setName('Subtype B1'))
                ],
                0, // Expected count
                [], // Expected type names
            ],
            'Case 5: Multiple types with mixed subtypes' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A'))
                        ->add(new Type()->setName('Type B'))
                        ->add($typeC = new Type()->setName('Type C'))
                        ->add(new Type()->setName('Type D')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A1'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A2'))
                        ->add(new Subtype()->setPastoralType($typeC)->setName('Subtype C1'))
                ],
                2, // Expected count
                ['Type B', 'Type D'], // Expected type names
            ],
            'Case 6: Single type without subtype' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add(new Type()->setName('Type A')),
                    'subtypes' => new GenericCollection(Subtype::class)
                ],
                1, // Expected count
                ['Type A'], // Expected type names
            ],
            'Case 7: Single type with single subtype' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A1'))
                ],
                0, // Expected count
                [], // Expected type names
            ],
            'Case 8: Three types, two with subtypes' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A'))
                        ->add($typeB = new Type()->setName('Type B'))
                        ->add(new Type()->setName('Type C')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A1'))
                        ->add(new Subtype()->setPastoralType($typeB)->setName('Subtype B1'))
                        ->add(new Subtype()->setPastoralType($typeB)->setName('Subtype B2'))
                ],
                1, // Expected count
                ['Type C'], // Expected type names
            ],
            'Case 9: Many subtypes for one type' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A'))
                        ->add(new Type()->setName('Type B')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A1'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A2'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A3'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A4'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A5'))
                ],
                1, // Expected count
                ['Type B'], // Expected type names
            ],
            'Case 10: Duplicate type references in subtypes' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Type A'))
                        ->add(new Type()->setName('Type B'))
                        ->add(new Type()->setName('Type C')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A1'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A2'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Subtype A3'))
                ],
                2, // Expected count
                ['Type B', 'Type C'], // Expected type names
            ],
            'Case 11: Real-world pastoral types' => [
                [
                    'types' => new GenericCollection(Type::class)
                        ->add($typeA = new Type()->setName('Attendance'))
                        ->add(new Type()->setName('Behaviour'))
                        ->add($typeC = new Type()->setName('Academic'))
                        ->add(new Type()->setName('Wellbeing'))
                        ->add(new Type()->setName('Achievement')),
                    'subtypes' => new GenericCollection(Subtype::class)
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Late'))
                        ->add(new Subtype()->setPastoralType($typeA)->setName('Absent'))
                        ->add(new Subtype()->setPastoralType($typeC)->setName('Homework Not Completed'))
                        ->add(new Subtype()->setPastoralType($typeC)->setName('Excellent Work'))
                ],
                3, // Expected count
                ['Behaviour', 'Wellbeing', 'Achievement'], // Expected type names
            ],
        ];
    }
}
