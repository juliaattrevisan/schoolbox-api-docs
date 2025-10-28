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
        $translator = new ModelToArray(
            $this->routeGenerator,
            $this->authorizer,
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
            'id'               => 100,
            'fullName'         => 'Unknown User',
            'yearLevel'        => null,
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
                ],
            ],
        ];
    }
}
