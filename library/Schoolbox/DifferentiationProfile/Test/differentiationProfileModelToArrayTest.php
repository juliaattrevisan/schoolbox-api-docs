<?php

// All new classes should use strict typing.
declare(strict_types=1);

namespace Schoolbox\DifferentiationProfile\Test;

use Alaress_Date_Format;
use Alaress\Authorizer\Authorizer;
use Alaress_Date_Locale_Config;
use Aura\Router\Generator;
use PHPUnit\Framework\TestCase;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Config\ArrayLoader;
use Schoolbox\DifferentiationProfile\Model;
use Schoolbox\DifferentiationProfile\Translator\ModelToArray;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use DateTimeZone;
use Schoolbox\Admin\DifferentiationProfiles\Model as ProfileConfig;

/**
 * Test for the differentiation profile model to array translator.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ModelToArray::class)]
class differentiationProfileModelToArrayTest extends TestCase
{
    private Generator $routeGenerator;
    private Authorizer $authorizer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock objects for dependencies
        $this->routeGenerator = $this->createMock(Generator::class);
        $this->authorizer     = $this->createMock(Authorizer::class);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInvokeData')]
    public function testInvokeData($model, $configs, $expected)
    {
        $user = $this->createMock(User::class);

        $translator = new ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
            $user,
            $configs,
        );

        $actual = call_user_func($translator, $model);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Data provider for `testInvokeData`.
     *
     * @return array
     */
    public static function providerInvokeData(): array
    {
        $configLoader = new ArrayLoader(
            [
                ConfigMapper::NAME_MAIL_ADDRESS => 'test.schoolbox.com.au',
                ConfigMapper::NAME_NAME_FORMAT  => '{title} {firstname} {lastname}',
                'date_local' => 'd/m/Y',
            ]
        );

        $locale = new Alaress_Date_Locale_Config();
        $locale->setConfig($configLoader);

        $student = new User()
            ->setId(100)
            ->setLastname(null)
            ->setFirstname(null)
            ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT));

        $userResponse = [
            'id'        => 100,
            'fullName'  => 'Unknown User',
            'givenName' => null,
            'lastName'  => null,
            'yearLevel' => null,
        ];

        $routes = [
            'linkedRecords' => null,
        ];

        return [
            'New Profile' => [
                // Test case 1: Empty/new model with minimal data
                new Model()->setStudent($student),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category'),
                    new ProfileConfig()->setName('adjustment_level'),
                    new ProfileConfig()->setName('overview'),
                    new ProfileConfig()->setName('interests_strengths'),
                    new ProfileConfig()->setName('needs_challenges'),
                    new ProfileConfig()->setName('strategies_adjustments'),
                    new ProfileConfig()->setName('goals'),
                ]),
                [
                    'id'                     => null,
                    'student'                => $userResponse,
                    'adjustment_category'    => null,
                    'adjustment_level'       => null,
                    'overview'               => null,
                    'interests_strengths'    => null,
                    'needs_challenges'       => null,
                    'strategies_adjustments' => null,
                    'goals'                  => null,
                    'created_at'             => null,
                    'updated_at'             => null,
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Complete Profile' => [
                // Test case 2: Fully populated model
                new Model()
                    ->setId(1)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Category A')
                    ->setAdjustmentLevel('Level 1')
                    ->setOverview('This is an overview.')
                    ->setInterestsStrengths('Interests and strengths details.')
                    ->setNeedsChallenges('Needs and challenges details.')
                    ->setStrategiesAdjustments('Strategies and adjustments details.')
                    ->setGoals('Goals details.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-02 01:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category'),
                    new ProfileConfig()->setName('adjustment_level'),
                    new ProfileConfig()->setName('overview'),
                    new ProfileConfig()->setName('interests_strengths'),
                    new ProfileConfig()->setName('needs_challenges'),
                    new ProfileConfig()->setName('strategies_adjustments'),
                    new ProfileConfig()->setName('goals'),
                ]),
                [
                    'id'                     => 1,
                    'student'                => $userResponse,
                    'adjustment_category'    => 'Category A',
                    'adjustment_level'       => 'Level 1',
                    'overview'               => 'This is an overview.',
                    'interests_strengths'    => 'Interests and strengths details.',
                    'needs_challenges'       => 'Needs and challenges details.',
                    'strategies_adjustments' => 'Strategies and adjustments details.',
                    'goals'                  => 'Goals details.',
                    'created_at'             => '01/01/2024',
                    'updated_at'             => '02/01/2024',
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Deleted Profile' => [
                // Test case 3: Model with deleted_at set
                new Model()
                    ->setId(2)
                    ->setStudent($student)
                    ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category'),
                    new ProfileConfig()->setName('adjustment_level'),
                    new ProfileConfig()->setName('overview'),
                    new ProfileConfig()->setName('interests_strengths'),
                    new ProfileConfig()->setName('needs_challenges'),
                    new ProfileConfig()->setName('strategies_adjustments'),
                    new ProfileConfig()->setName('goals'),
                ]),
                [
                    'id'                     => 2,
                    'student'                => $userResponse,
                    'adjustment_category'    => null,
                    'adjustment_level'       => null,
                    'overview'               => null,
                    'interests_strengths'    => null,
                    'needs_challenges'       => null,
                    'strategies_adjustments' => null,
                    'goals'                  => null,
                    'created_at'             => null,
                    'updated_at'             => null,
                    'deleted_at'             => '01/03/2024',
                    '_routes'                => $routes,
                ],
            ],
            'Partial Config - Only Adjustment Fields' => [
                // Test case 4: Only adjustment_category and adjustment_level enabled
                new Model()
                    ->setId(3)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Category B')
                    ->setAdjustmentLevel('Level 2')
                    ->setOverview('This overview should not appear.')
                    ->setInterestsStrengths('These interests should not appear.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category'),
                    new ProfileConfig()->setName('adjustment_level'),
                ]),
                [
                    'id'                     => 3,
                    'student'                => $userResponse,
                    'adjustment_category'    => 'Category B',
                    'adjustment_level'       => 'Level 2',
                    'created_at'             => '01/02/2024',
                    'updated_at'             => null,
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Partial Config - Overview and Goals Only' => [
                // Test case 5: Only overview and goals fields enabled
                new Model()
                    ->setId(4)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Category C')
                    ->setAdjustmentLevel('Level 3')
                    ->setOverview('Visible overview.')
                    ->setGoals('Visible goals.')
                    ->setInterestsStrengths('Hidden interests.')
                    ->setNeedsChallenges('Hidden needs.')
                    ->setStrategiesAdjustments('Hidden strategies.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-03-15 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-03-20 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('overview'),
                    new ProfileConfig()->setName('goals'),
                ]),
                [
                    'id'                     => 4,
                    'student'                => $userResponse,
                    'overview'               => 'Visible overview.',
                    'goals'                  => 'Visible goals.',
                    'created_at'             => '15/03/2024',
                    'updated_at'             => '20/03/2024',
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Partial Config - No Text Fields' => [
                // Test case 6: No text fields enabled (only adjustment fields)
                new Model()
                    ->setId(5)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Category D')
                    ->setAdjustmentLevel('Level 4')
                    ->setOverview('This should not be visible.')
                    ->setInterestsStrengths('This should not be visible.')
                    ->setNeedsChallenges('This should not be visible.')
                    ->setStrategiesAdjustments('This should not be visible.')
                    ->setGoals('This should not be visible.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-04-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category'),
                    new ProfileConfig()->setName('adjustment_level'),
                ]),
                [
                    'id'                     => 5,
                    'student'                => $userResponse,
                    'adjustment_category'    => 'Category D',
                    'adjustment_level'       => 'Level 4',
                    'created_at'             => '01/04/2024',
                    'updated_at'             => null,
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Empty Config Collection' => [
                // Test case 7: No fields enabled at all
                new Model()
                    ->setId(6)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Category E')
                    ->setAdjustmentLevel('Level 5')
                    ->setOverview('Nothing should be visible.')
                    ->setInterestsStrengths('Nothing should be visible.')
                    ->setNeedsChallenges('Nothing should be visible.')
                    ->setStrategiesAdjustments('Nothing should be visible.')
                    ->setGoals('Nothing should be visible.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-05-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, []),
                [
                    'id'                     => 6,
                    'student'                => $userResponse,
                    'created_at'             => '01/05/2024',
                    'updated_at'             => null,
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Partial Config - Three Middle Fields' => [
                // Test case 8: interests_strengths, needs_challenges, and strategies_adjustments enabled
                new Model()
                    ->setId(7)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Hidden Category')
                    ->setAdjustmentLevel('Hidden Level')
                    ->setOverview('Hidden overview.')
                    ->setInterestsStrengths('Visible interests and strengths.')
                    ->setNeedsChallenges('Visible needs and challenges.')
                    ->setStrategiesAdjustments('Visible strategies and adjustments.')
                    ->setGoals('Hidden goals.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-06-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-06-15 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('interests_strengths'),
                    new ProfileConfig()->setName('needs_challenges'),
                    new ProfileConfig()->setName('strategies_adjustments'),
                ]),
                [
                    'id'                     => 7,
                    'student'                => $userResponse,
                    'interests_strengths'    => 'Visible interests and strengths.',
                    'needs_challenges'       => 'Visible needs and challenges.',
                    'strategies_adjustments' => 'Visible strategies and adjustments.',
                    'created_at'             => '01/06/2024',
                    'updated_at'             => '15/06/2024',
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Partial Config - Single Field Enabled' => [
                // Test case 9: Only one field (overview) enabled
                new Model()
                    ->setId(8)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Hidden Category')
                    ->setAdjustmentLevel('Hidden Level')
                    ->setOverview('Only this overview is visible.')
                    ->setInterestsStrengths('Hidden.')
                    ->setNeedsChallenges('Hidden.')
                    ->setStrategiesAdjustments('Hidden.')
                    ->setGoals('Hidden.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-07-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('overview'),
                ]),
                [
                    'id'                     => 8,
                    'student'                => $userResponse,
                    'overview'               => 'Only this overview is visible.',
                    'created_at'             => '01/07/2024',
                    'updated_at'             => null,
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
            'Partial Config - Alternating Fields' => [
                // Test case 10: Alternating fields enabled (category, overview, needs, goals)
                new Model()
                    ->setId(9)
                    ->setStudent($student)
                    ->setAdjustmentCategory('Visible Category')
                    ->setAdjustmentLevel('Hidden Level')
                    ->setOverview('Visible overview.')
                    ->setInterestsStrengths('Hidden interests.')
                    ->setNeedsChallenges('Visible needs.')
                    ->setStrategiesAdjustments('Hidden strategies.')
                    ->setGoals('Visible goals.')
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-08-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-08-10 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category'),
                    new ProfileConfig()->setName('overview'),
                    new ProfileConfig()->setName('needs_challenges'),
                    new ProfileConfig()->setName('goals'),
                ]),
                [
                    'id'                     => 9,
                    'student'                => $userResponse,
                    'adjustment_category'    => 'Visible Category',
                    'overview'               => 'Visible overview.',
                    'needs_challenges'       => 'Visible needs.',
                    'goals'                  => 'Visible goals.',
                    'created_at'             => '01/08/2024',
                    'updated_at'             => '10/08/2024',
                    'deleted_at'             => null,
                    '_routes'                => $routes,
                ],
            ],
        ];
    }
}
