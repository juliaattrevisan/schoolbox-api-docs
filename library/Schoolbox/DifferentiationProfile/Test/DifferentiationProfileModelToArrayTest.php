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
use Schoolbox\DifferentiationProfile\AdjustmentCategories\Model as AdjustmentCategory;
use Schoolbox\DifferentiationProfile\AdjustmentLevels\Model as AdjustmentLevel;
use Schoolbox\Pastoral\Model as PastoralModel;

/**
 * Test for the differentiation profile model to array translator.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(ModelToArray::class)]
class DifferentiationProfileModelToArrayTest extends TestCase
{
    private Generator $routeGenerator;
    private Authorizer $authorizer;

    private const string ADJUSTMENT_CATEGORY_ID_1 = "1";
    private const string ADJUSTMENT_CATEGORY_ID_2 = "2";
    private const string ADJUSTMENT_CATEGORY_ID_3 = "3";
    private const string ADJUSTMENT_CATEGORY_ID_4 = "4";
    private const string ADJUSTMENT_CATEGORY_NAME_1 = "Category 1";
    private const string ADJUSTMENT_CATEGORY_NAME_2 = "Category 2";
    private const string ADJUSTMENT_CATEGORY_NAME_3 = "Category 3";
    private const string ADJUSTMENT_CATEGORY_NAME_4 = "Category 4";
    private const string ADJUSTMENT_LEVEL_ID_1 = "1";
    private const string ADJUSTMENT_LEVEL_ID_2 = "2";
    private const string ADJUSTMENT_LEVEL_ID_3 = "3";
    private const string ADJUSTMENT_LEVEL_ID_4 = "4";
    private const string ADJUSTMENT_LEVEL_NAME_1 = "Level 1";
    private const string ADJUSTMENT_LEVEL_NAME_2 = "Level 2";
    private const string ADJUSTMENT_LEVEL_NAME_3 = "Level 3";
    private const string ADJUSTMENT_LEVEL_NAME_4 = "Level 4";

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
                'date_short' => 'M j, Y',
                'date_time' => 'g:ia',
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
            '_links'   => [
                'profile' => null,
            ],
            'campus' => null,
        ];

        $pastoralActive = new PastoralModel()
            ->setId(1)
            ->setExpiresAt(null)
            ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-05-01 00:00:00', new DateTimeZone('UTC'), $locale));

        $staff1 = new User()
            ->setId(3)
            ->setLastname('Staff')
            ->setFirstname('Thor')
            ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT));
        $staffResponse1 = 'Thor Staff';

        $staff2 = new User()
            ->setId(4)
            ->setLastname('Staff')
            ->setFirstname('Odin')
            ->setNameFormat($configLoader->get(ConfigMapper::NAME_NAME_FORMAT));
        $staffResponse2 = 'Odin Staff';

        $routes = [
            'linkedRecords' => null,
            'customisations' => null,
            'edit' => null,
            'create' => null,
        ];

        return [
            'New Profile' => [
                // Test case 1: Empty/new model with minimal data
                new Model()
                    ->setStudent($student)
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => null,
                    'status'                 => ['unpopulated'],
                    'student'                => $userResponse,
                    'adjustment_category'    => null,
                    'adjustment_level'       => null,
                    'overview'               => null,
                    'interests_strengths'    => null,
                    'needs_challenges'       => null,
                    'strategies_adjustments' => null,
                    'goals'                  => null,
                    '_routes'                => $routes,
                    'created_at'             => '2024-01-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-01-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Complete Profile' => [
                // Test case 2: Fully populated model
                new Model()
                    ->setId(1)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1)]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_1)->setName(self::ADJUSTMENT_LEVEL_NAME_1)]))
                    ->setOverview('This is an overview.')
                    ->setInterestsStrengths('Interests and strengths details.')
                    ->setNeedsChallenges('Needs and challenges details.')
                    ->setStrategiesAdjustments('Strategies and adjustments details.')
                    ->setGoals('Goals details.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-02 01:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 1,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [['id' => self::ADJUSTMENT_CATEGORY_ID_1, 'name' => self::ADJUSTMENT_CATEGORY_NAME_1]],
                    'adjustment_level'       => [['id' => self::ADJUSTMENT_LEVEL_ID_1, 'name' => self::ADJUSTMENT_LEVEL_NAME_1]],
                    'overview'               => 'This is an overview.',
                    'interests_strengths'    => 'Interests and strengths details.',
                    'needs_challenges'       => 'Needs and challenges details.',
                    'strategies_adjustments' => 'Strategies and adjustments details.',
                    'goals'                  => 'Goals details.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-01-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-01-02T01:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Deleted Profile' => [
                // Test case 3: Model with deleted_at set
                new Model()
                    ->setId(2)
                    ->setStudent($student)
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 2,
                    'status'                 => ['unpopulated'],
                    'student'                => $userResponse,
                    'adjustment_category'    => null,
                    'adjustment_level'       => null,
                    'overview'               => null,
                    'interests_strengths'    => null,
                    'needs_challenges'       => null,
                    'strategies_adjustments' => null,
                    'goals'                  => null,
                    '_routes'                => $routes,
                    'created_at'             => null,
                    'created_by'             => null,
                    'updated_at'             => '2024-03-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => '2024-03-01T00:00:00+00:00',
                ],
            ],
            'Partial Config - Only Adjustment Fields' => [
                // Test case 4: Only adjustment_category and adjustment_level enabled
                new Model()
                    ->setId(3)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_2)->setName(self::ADJUSTMENT_CATEGORY_NAME_2)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_2)->setName(self::ADJUSTMENT_LEVEL_NAME_2),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_4)->setName(self::ADJUSTMENT_LEVEL_NAME_4)
                    ]))
                    ->setOverview('This overview should not appear.')
                    ->setInterestsStrengths('These interests should not appear.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                ]),
                [
                    'id'                     => 3,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_1, 'name' => self::ADJUSTMENT_CATEGORY_NAME_1],
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_2, 'name' => self::ADJUSTMENT_CATEGORY_NAME_2]
                    ],
                    'adjustment_level'       => [
                        ['id' => self::ADJUSTMENT_LEVEL_ID_2, 'name' => self::ADJUSTMENT_LEVEL_NAME_2],
                        ['id' => self::ADJUSTMENT_LEVEL_ID_4, 'name' => self::ADJUSTMENT_LEVEL_NAME_4]
                    ],
                    '_routes'                => $routes,
                    'created_at'             => '2024-02-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-02-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Partial Config - Overview and Goals Only' => [
                // Test case 5: Only overview and goals fields enabled
                new Model()
                    ->setId(4)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_3)->setName(self::ADJUSTMENT_CATEGORY_NAME_3),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_4)->setName(self::ADJUSTMENT_CATEGORY_NAME_4)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_3)->setName(self::ADJUSTMENT_LEVEL_NAME_3),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_1)->setName(self::ADJUSTMENT_LEVEL_NAME_1)
                    ]))
                    ->setOverview('Visible overview.')
                    ->setGoals('Visible goals.')
                    ->setInterestsStrengths('Hidden interests.')
                    ->setNeedsChallenges('Hidden needs.')
                    ->setStrategiesAdjustments('Hidden strategies.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-03-15 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-03-20 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 4,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'overview'               => 'Visible overview.',
                    'goals'                  => 'Visible goals.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-03-15T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-03-20T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Partial Config - No Text Fields' => [
                // Test case 6: No text fields enabled (only adjustment fields)
                new Model()
                    ->setId(5)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_4)->setName(self::ADJUSTMENT_CATEGORY_NAME_4),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_4)->setName(self::ADJUSTMENT_LEVEL_NAME_4),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_3)->setName(self::ADJUSTMENT_LEVEL_NAME_3)
                    ]))
                    ->setOverview('This should not be visible.')
                    ->setInterestsStrengths('This should not be visible.')
                    ->setNeedsChallenges('This should not be visible.')
                    ->setStrategiesAdjustments('This should not be visible.')
                    ->setGoals('This should not be visible.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-04-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-04-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                ]),
                [
                    'id'                     => 5,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_4, 'name' => self::ADJUSTMENT_CATEGORY_NAME_4],
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_1, 'name' => self::ADJUSTMENT_CATEGORY_NAME_1]
                    ],
                    'adjustment_level'       => [
                        ['id' => self::ADJUSTMENT_LEVEL_ID_4, 'name' => self::ADJUSTMENT_LEVEL_NAME_4],
                        ['id' => self::ADJUSTMENT_LEVEL_ID_3, 'name' => self::ADJUSTMENT_LEVEL_NAME_3]
                    ],
                    '_routes'                => $routes,
                    'created_at'             => '2024-04-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-04-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Empty Config Collection' => [
                // Test case 7: No fields enabled at all
                new Model()
                    ->setId(6)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_2)->setName(self::ADJUSTMENT_CATEGORY_NAME_2)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_2)->setName(self::ADJUSTMENT_LEVEL_NAME_2),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_4)->setName(self::ADJUSTMENT_LEVEL_NAME_4)
                    ]))
                    ->setOverview('Nothing should be visible.')
                    ->setInterestsStrengths('Nothing should be visible.')
                    ->setNeedsChallenges('Nothing should be visible.')
                    ->setStrategiesAdjustments('Nothing should be visible.')
                    ->setGoals('Nothing should be visible.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-05-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-05-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, []),
                [
                    'id'                     => 6,
                    'status'                 => [],
                    'student'                => $userResponse,
                    '_routes'                => $routes,
                    'created_at'             => '2024-05-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-05-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Partial Config - Three Middle Fields' => [
                // Test case 8: interests_strengths, needs_challenges, and strategies_adjustments enabled
                new Model()
                    ->setId(7)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_2)->setName(self::ADJUSTMENT_CATEGORY_NAME_2)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_2)->setName(self::ADJUSTMENT_LEVEL_NAME_2),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_4)->setName(self::ADJUSTMENT_LEVEL_NAME_4)
                    ]))
                    ->setOverview('Hidden overview.')
                    ->setInterestsStrengths('Visible interests and strengths.')
                    ->setNeedsChallenges('Visible needs and challenges.')
                    ->setStrategiesAdjustments('Visible strategies and adjustments.')
                    ->setGoals('Hidden goals.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-06-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-06-15 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                ]),
                [
                    'id'                     => 7,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'interests_strengths'    => 'Visible interests and strengths.',
                    'needs_challenges'       => 'Visible needs and challenges.',
                    'strategies_adjustments' => 'Visible strategies and adjustments.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-06-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-06-15T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Partial Config - Single Field Enabled' => [
                // Test case 9: Only one field (overview) enabled
                new Model()
                    ->setId(8)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_2)->setName(self::ADJUSTMENT_CATEGORY_NAME_2)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_2)->setName(self::ADJUSTMENT_LEVEL_NAME_2),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_4)->setName(self::ADJUSTMENT_LEVEL_NAME_4)
                    ]))
                    ->setOverview('Only this overview is visible.')
                    ->setInterestsStrengths('Hidden.')
                    ->setNeedsChallenges('Hidden.')
                    ->setStrategiesAdjustments('Hidden.')
                    ->setGoals('Hidden.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-07-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-07-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('overview')->setModule('details'),
                ]),
                [
                    'id'                     => 8,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'overview'               => 'Only this overview is visible.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-07-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-07-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Partial Config - Alternating Fields' => [
                // Test case 10: Alternating fields enabled (category, overview, needs, goals)
                new Model()
                    ->setId(9)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_4)->setName(self::ADJUSTMENT_CATEGORY_NAME_4),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_4)->setName(self::ADJUSTMENT_LEVEL_NAME_4)
                    ]))
                    ->setOverview('Visible overview.')
                    ->setInterestsStrengths('Hidden interests.')
                    ->setNeedsChallenges('Visible needs.')
                    ->setStrategiesAdjustments('Hidden strategies.')
                    ->setGoals('Visible goals.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-08-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-08-10 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 9,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_4, 'name' => self::ADJUSTMENT_CATEGORY_NAME_4],
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_1, 'name' => self::ADJUSTMENT_CATEGORY_NAME_1]
                    ],
                    'overview'               => 'Visible overview.',
                    'needs_challenges'       => 'Visible needs.',
                    'goals'                  => 'Visible goals.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-08-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2024-08-10T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Expired Profile' => [
                // Test case 11: Profile marked as expired
                new Model()
                    ->setId(10)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_1)->setName(self::ADJUSTMENT_LEVEL_NAME_1)
                    ]))
                    ->setOverview('This profile has expired.')
                    ->setPastoralRecords(
                        new GenericCollection(
                            PastoralModel::class,
                            [
                                (clone $pastoralActive)->setExpiresAt(Alaress_Date_Format::newDatabaseDate('2022-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                            ]
                        )
                    )
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2023-01-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-06-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                ]),
                [
                    'id'                     => 10,
                    'status'                 => ['expired'],
                    'student'                => $userResponse,
                    'adjustment_category'    => [['id' => self::ADJUSTMENT_CATEGORY_ID_1, 'name' => self::ADJUSTMENT_CATEGORY_NAME_1]],
                    'adjustment_level'       => [['id' => self::ADJUSTMENT_LEVEL_ID_1, 'name' => self::ADJUSTMENT_LEVEL_NAME_1]],
                    'overview'               => 'This profile has expired.',
                    '_routes'                => $routes,
                    'created_at'             => '2023-01-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2023-06-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Out of Date Profile' => [
                // Test case 12: Profile marked as out of date
                new Model()
                    ->setId(11)
                    ->setStudent($student)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_2)->setName(self::ADJUSTMENT_CATEGORY_NAME_2)
                    ]))
                    ->setGoals('This is an overview.')
                    ->setPastoralRecords(
                        new GenericCollection(
                            PastoralModel::class,
                            [
                                (clone $pastoralActive)->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                            ]
                        )
                    )
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2023-03-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-09-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 11,
                    'status'                 => ['outOfDate'],
                    'student'                => $userResponse,
                    'adjustment_category'    => [['id' => self::ADJUSTMENT_CATEGORY_ID_2, 'name' => self::ADJUSTMENT_CATEGORY_NAME_2]],
                    'goals'                  => 'This is an overview.',
                    '_routes'                => $routes,
                    'created_at'             => '2023-03-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2023-09-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Expired and Out of Date Profile' => [
                // Test case 13: Profile marked as both expired and out of date
                new Model()
                    ->setId(12)
                    ->setStudent($student)
                    ->setOverview('This is an overview.')
                    ->setPastoralRecords(
                        new GenericCollection(
                            PastoralModel::class,
                            [
                                (clone $pastoralActive)
                                    ->setExpiresAt(Alaress_Date_Format::newDatabaseDate('2021-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                            ]
                        )
                    )
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2022-01-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2022-12-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('overview')->setModule('details'),
                ]),
                [
                    'id'                     => 12,
                    'status'                 => ['expired', 'outOfDate'],
                    'student'                => $userResponse,
                    'overview'               => 'This is an overview.',
                    '_routes'                => $routes,
                    'created_at'             => '2022-01-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2022-12-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Expired, Out of Date, and Unpopulated Profile' => [
                // Test case 14: Profile with all status flags
                new Model()
                    ->setId(13)
                    ->setStudent($student)
                    ->setPastoralRecords(
                        new GenericCollection(
                            PastoralModel::class,
                            [
                                (clone $pastoralActive)
                                    ->setExpiresAt(Alaress_Date_Format::newDatabaseDate('2020-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2022-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                            ]
                        )
                    )
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2021-01-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2021-01-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 13,
                    'status'                 => ['expired', 'outOfDate', 'unpopulated'],
                    'student'                => $userResponse,
                    'overview'               => null,
                    'goals'                  => null,
                    '_routes'                => $routes,
                    'created_at'             => '2021-01-01T00:00:00+00:00',
                    'created_by'             => null,
                    'updated_at'             => '2021-01-01T00:00:00+00:00',
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ],
            ],
            'Newly created profile' => [
                // Test case 15: Newly created profile with no data
                new Model()
                    ->setStudent($student)
                    ->setCreatedBy($staff1)
                    ->setUpdatedBy($staff1)
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2025-01-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => null,
                    'status'                 => ['unpopulated'],
                    'student'                => $userResponse,
                    'adjustment_category'    => null,
                    'adjustment_level'       => null,
                    'overview'               => null,
                    'interests_strengths'    => null,
                    'needs_challenges'       => null,
                    'strategies_adjustments' => null,
                    'goals'                  => null,
                    '_routes'                => $routes,
                    'created_at'             => null,
                    'created_by'             => $staffResponse1,
                    'updated_at'             => '2025-01-01T00:00:00+00:00',
                    'updated_by'             => $staffResponse1,
                    'deleted_at'             => null,
                ]
            ],
            'Profile is updated by another staff' => [
                // Test case 16: Profile created by one staff and updated by another
                new Model()
                    ->setId(14)
                    ->setStudent($student)
                    ->setCreatedBy($staff1)
                    ->setUpdatedBy($staff2)
                    ->setOverview('This is an overview.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-01-10 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-02-15 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 14,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => null,
                    'adjustment_level'       => null,
                    'overview'               => 'This is an overview.',
                    'interests_strengths'    => null,
                    'needs_challenges'       => null,
                    'strategies_adjustments' => null,
                    'goals'                  => null,
                    '_routes'                => $routes,
                    'created_at'             => '2024-01-10T00:00:00+00:00',
                    'created_by'             => $staffResponse1,
                    'updated_at'             => '2024-02-15T00:00:00+00:00',
                    'updated_by'             => $staffResponse2,
                    'deleted_at'             => null,
                ]
            ],
            'Profile with only created_by set' => [
                // Test case 17: Profile with only creator information (never updated)
                new Model()
                    ->setId(15)
                    ->setStudent($student)
                    ->setCreatedBy($staff1)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_1)->setName(self::ADJUSTMENT_CATEGORY_NAME_1)
                    ]))
                    ->setOverview('Profile with creator only.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-03-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                ]),
                [
                    'id'                     => 15,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [['id' => self::ADJUSTMENT_CATEGORY_ID_1, 'name' => self::ADJUSTMENT_CATEGORY_NAME_1]],
                    'overview'               => 'Profile with creator only.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-03-01T00:00:00+00:00',
                    'created_by'             => $staffResponse1,
                    'updated_at'             => null,
                    'updated_by'             => null,
                    'deleted_at'             => null,
                ]
            ],
            'Profile with only updated_by set' => [
                // Test case 18: Edge case - Profile with only updater info (unusual but possible)
                new Model()
                    ->setId(16)
                    ->setStudent($student)
                    ->setUpdatedBy($staff2)
                    ->setGoals('Profile with updater only.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-04-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 16,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'goals'                  => 'Profile with updater only.',
                    '_routes'                => $routes,
                    'created_at'             => null,
                    'created_by'             => null,
                    'updated_at'             => '2024-04-01T00:00:00+00:00',
                    'updated_by'             => $staffResponse2,
                    'deleted_at'             => null,
                ]
            ],
            'Deleted profile with creator and updater' => [
                // Test case 19: Deleted profile retains creator and updater information
                new Model()
                    ->setId(17)
                    ->setStudent($student)
                    ->setCreatedBy($staff1)
                    ->setUpdatedBy($staff2)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_2)->setName(self::ADJUSTMENT_CATEGORY_NAME_2)
                    ]))
                    ->setOverview('This profile was deleted.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-05-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-05-15 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setDeletedAt(Alaress_Date_Format::newDatabaseDate('2024-06-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                ]),
                [
                    'id'                     => 17,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [['id' => self::ADJUSTMENT_CATEGORY_ID_2, 'name' => self::ADJUSTMENT_CATEGORY_NAME_2]],
                    'overview'               => 'This profile was deleted.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-05-01T00:00:00+00:00',
                    'created_by'             => $staffResponse1,
                    'updated_at'             => '2024-05-15T00:00:00+00:00',
                    'updated_by'             => $staffResponse2,
                    'deleted_at'             => '2024-06-01T00:00:00+00:00',
                ]
            ],
            'Expired profile with full audit trail' => [
                // Test case 20: Expired profile with complete creator/updater information
                new Model()
                    ->setId(18)
                    ->setStudent($student)
                    ->setCreatedBy($staff2)
                    ->setUpdatedBy($staff1)
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_3)->setName(self::ADJUSTMENT_LEVEL_NAME_3)
                    ]))
                    ->setInterestsStrengths('Expired profile with audit.')
                    ->setPastoralRecords(
                        new GenericCollection(
                            PastoralModel::class,
                            [
                                (clone $pastoralActive)->setExpiresAt(Alaress_Date_Format::newDatabaseDate('2022-12-31 00:00:00', new DateTimeZone('UTC'), $locale))
                            ]
                        )
                    )
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2023-07-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2023-12-01 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                ]),
                [
                    'id'                     => 18,
                    'status'                 => ['expired'],
                    'student'                => $userResponse,
                    'adjustment_level'       => [['id' => self::ADJUSTMENT_LEVEL_ID_3, 'name' => self::ADJUSTMENT_LEVEL_NAME_3]],
                    'interests_strengths'    => 'Expired profile with audit.',
                    '_routes'                => $routes,
                    'created_at'             => '2023-07-01T00:00:00+00:00',
                    'created_by'             => $staffResponse2,
                    'updated_at'             => '2023-12-01T00:00:00+00:00',
                    'updated_by'             => $staffResponse1,
                    'deleted_at'             => null,
                ]
            ],
            'Complete profile with same creator and updater' => [
                // Test case 21: Fully populated profile updated by the same person who created it
                new Model()
                    ->setId(19)
                    ->setStudent($student)
                    ->setCreatedBy($staff2)
                    ->setUpdatedBy($staff2)
                    ->setAdjustmentCategory(new GenericCollection(AdjustmentCategory::class, [
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_3)->setName(self::ADJUSTMENT_CATEGORY_NAME_3),
                        new AdjustmentCategory()->setId(self::ADJUSTMENT_CATEGORY_ID_4)->setName(self::ADJUSTMENT_CATEGORY_NAME_4)
                    ]))
                    ->setAdjustmentLevel(new GenericCollection(AdjustmentLevel::class, [
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_1)->setName(self::ADJUSTMENT_LEVEL_NAME_1),
                        new AdjustmentLevel()->setId(self::ADJUSTMENT_LEVEL_ID_2)->setName(self::ADJUSTMENT_LEVEL_NAME_2)
                    ]))
                    ->setOverview('Complete profile.')
                    ->setInterestsStrengths('Interests.')
                    ->setNeedsChallenges('Needs.')
                    ->setStrategiesAdjustments('Strategies.')
                    ->setGoals('Goals.')
                    ->setPastoralRecords(new GenericCollection(PastoralModel::class, [$pastoralActive]))
                    ->setCreatedAt(Alaress_Date_Format::newDatabaseDate('2024-07-01 00:00:00', new DateTimeZone('UTC'), $locale))
                    ->setUpdatedAt(Alaress_Date_Format::newDatabaseDate('2024-07-20 00:00:00', new DateTimeZone('UTC'), $locale)),
                new GenericCollection(ProfileConfig::class, [
                    new ProfileConfig()->setName('adjustment_category')->setModule('adjustments'),
                    new ProfileConfig()->setName('adjustment_level')->setModule('adjustments'),
                    new ProfileConfig()->setName('overview')->setModule('details'),
                    new ProfileConfig()->setName('interests_strengths')->setModule('details'),
                    new ProfileConfig()->setName('needs_challenges')->setModule('details'),
                    new ProfileConfig()->setName('strategies_adjustments')->setModule('details'),
                    new ProfileConfig()->setName('goals')->setModule('details'),
                ]),
                [
                    'id'                     => 19,
                    'status'                 => [],
                    'student'                => $userResponse,
                    'adjustment_category'    => [
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_3, 'name' => self::ADJUSTMENT_CATEGORY_NAME_3],
                        ['id' => self::ADJUSTMENT_CATEGORY_ID_4, 'name' => self::ADJUSTMENT_CATEGORY_NAME_4]
                    ],
                    'adjustment_level'       => [
                        ['id' => self::ADJUSTMENT_LEVEL_ID_1, 'name' => self::ADJUSTMENT_LEVEL_NAME_1],
                        ['id' => self::ADJUSTMENT_LEVEL_ID_2, 'name' => self::ADJUSTMENT_LEVEL_NAME_2]
                    ],
                    'overview'               => 'Complete profile.',
                    'interests_strengths'    => 'Interests.',
                    'needs_challenges'       => 'Needs.',
                    'strategies_adjustments' => 'Strategies.',
                    'goals'                  => 'Goals.',
                    '_routes'                => $routes,
                    'created_at'             => '2024-07-01T00:00:00+00:00',
                    'created_by'             => $staffResponse2,
                    'updated_at'             => '2024-07-20T00:00:00+00:00',
                    'updated_by'             => $staffResponse2,
                    'deleted_at'             => null,
                ]
            ]
        ];
    }
}
