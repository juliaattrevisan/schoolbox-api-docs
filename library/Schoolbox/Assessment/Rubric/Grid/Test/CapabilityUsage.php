<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet;

/**
 * @codeCoverageIgnore
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\CapabilityUsage::class)]
class CapabilityUsage extends TestCase
{
    /**
     * Data provider for `testRetrieveIndicatorUsage`
     *
     * @return iterable
     */
    public static function getTestRetrieveIndicatorUsageData(): iterable
    {
        // Default rowset
        $capability1     = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $capability2     = (new RowSet\Capability())->setId(2)->setName('Capability 2');
        $capability3     = (new RowSet\Capability())->setId(3)->setName('Capability 3');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.1');
        $indicator5 = (new RowSet\Indicator())->setId(5)->setName('Indicator 2.1');
        $indicator6 = (new RowSet\Indicator())->setId(6)->setName('Indicator 2.2');
        $indicator7 = (new RowSet\Indicator())->setId(7)->setName('Indicator 3.3');
        $indicator8 = (new RowSet\Indicator())->setId(8)->setName('Indicator 3.2');
        $indicator9 = (new RowSet\Indicator())->setId(9)->setName('Indicator 3.3');
        $indicator0 = (new RowSet\Indicator())->setId(10)->setName('Indicator 4.1');

        $capability1->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability1->getIndicators()[$indicator2->getId()] = $indicator2;
        $capability1->getIndicators()[$indicator3->getId()] = $indicator3;

        $capability2->getIndicators()[$indicator4->getId()] = $indicator4;
        $capability2->getIndicators()[$indicator5->getId()] = $indicator5;
        $capability2->getIndicators()[$indicator6->getId()] = $indicator6;

        $capability3->getIndicators()[$indicator7->getId()] = $indicator7;
        $capability3->getIndicators()[$indicator8->getId()] = $indicator8;
        $capability3->getIndicators()[$indicator9->getId()] = $indicator9;


        // Capability and indicator usage
        $capabilityUsage1                                             = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(15);
        $indicatorUsage1                                         = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage2                                         = new RubricGrid\IndicatorUsage($indicator2);
        $capabilityUsage1->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $capabilityUsage1->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;

        $capabilityUsage2                                             = (new RubricGrid\CapabilityUsage($capability1))->setMaxValue(15);
        $indicatorUsage6                                         = new RubricGrid\IndicatorUsage($indicator6);
        $capabilityUsage2->getIndicatorUsages()[$indicator6->getId()] = $indicatorUsage6;

        yield 'capability usage 1, indicator 1: in use'            => [$capabilityUsage1, $indicator1, $indicatorUsage1];
        yield 'capability usage 1, indicator 2: in use'            => [$capabilityUsage1, $indicator2, $indicatorUsage2];
        yield 'capability usage 1, indicator 3: not in use'        => [$capabilityUsage1, $indicator3, null];
        yield 'capability usage 2, indicator 4: not in use'        => [$capabilityUsage2, $indicator4, null];
        yield 'capability usage 2, indicator 5: not in use'        => [$capabilityUsage2, $indicator5, null];
        yield 'capability usage 2, indicator 6: in use'            => [$capabilityUsage2, $indicator6, $indicatorUsage6];
        yield 'capability usage 1, indicator 6: not in this capability' => [$capabilityUsage1, $indicator6, null];
    }


    /**
     *
     * @param RubricGrid\CapabilityUsage     $capabilityUsage
     * @param RowSet\Indicator               $indicator
     * @param null|RubricGrid\IndicatorUsage $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveIndicatorUsageData')]
    public function testRetrieveIndicatorUsage(
        RubricGrid\CapabilityUsage $capabilityUsage,
        RowSet\Indicator $indicator,
        ?RubricGrid\IndicatorUsage $expected
    ): void {
        $this->assertSame($expected, $capabilityUsage->retrieveIndicatorUsage($indicator));
    }


    /**
     * Data provider for `testIterateIndicators`
     *
     * @return iterable
     */
    public static function getTestIterateIndicatorsData(): iterable
    {
        // Default rowset
        $capability      = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');

        $capability->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability->getIndicators()[$indicator2->getId()] = $indicator2;
        $capability->getIndicators()[$indicator3->getId()] = $indicator3;


        $capabilityUsage1 = new RubricGrid\CapabilityUsage($capability);

        yield 'No capabilities used' => [$capabilityUsage1, [$indicator1, $indicator2, $indicator3]];


        $capabilityUsage2                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage2->getIndicatorUsages()[$indicator1->getId()] = new RubricGrid\IndicatorUsage($indicator1);

        yield 'Capability 1 used' => [$capabilityUsage2, [$indicator1, $indicator2, $indicator3]];


        $capabilityUsage3                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage3->getIndicatorUsages()[$indicator3->getId()] = new RubricGrid\IndicatorUsage($indicator3);

        yield 'Capability 3 used' => [$capabilityUsage3, [$indicator3, $indicator1, $indicator2]];


        $capabilityUsage4                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage4->getIndicatorUsages()[$indicator3->getId()] = new RubricGrid\IndicatorUsage($indicator3);
        $capabilityUsage4->getIndicatorUsages()[$indicator2->getId()] = new RubricGrid\IndicatorUsage($indicator2);

        yield 'Capability 3, 2 used' => [$capabilityUsage4, [$indicator3, $indicator2, $indicator1]];


        $capabilityUsage5                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage5->getIndicatorUsages()[$indicator1->getId()] = new RubricGrid\IndicatorUsage($indicator1);
        $capabilityUsage5->getIndicatorUsages()[$indicator3->getId()] = new RubricGrid\IndicatorUsage($indicator3);
        $capabilityUsage5->getIndicatorUsages()[$indicator2->getId()] = new RubricGrid\IndicatorUsage($indicator2);

        yield 'Capability 1, 3, 2 used' => [$capabilityUsage5, [$indicator1, $indicator3, $indicator2]];
    }


    /**
     * @param RubricGrid\CapabilityUsage $capabilityUsage
     * @param array                      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIterateIndicatorsData')]
    public function testIterateIndicators(RubricGrid\CapabilityUsage $capabilityUsage, array $expected): void
    {
        $actual = [];
        foreach ($capabilityUsage->iterateIndicators() as $indicator) {
            $actual[] = $indicator;
        }
        $this->assertSame($expected, $actual);
    }


    /**
     * Data provider for `testCalculateMaxValueFromIndicators`.
     *
     * @return iterable
     */
    public static function getTestCalculateMaxValueFromIndicatorsData(): iterable
    {
        $capability      = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 1.4');

        $capability->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability->getIndicators()[$indicator2->getId()] = $indicator2;
        $capability->getIndicators()[$indicator3->getId()] = $indicator3;
        $capability->getIndicators()[$indicator4->getId()] = $indicator4;

        // Indicator usage 1: max is 7
        $indicatorUsage1 = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage1->getDescriptors()[0] = new RubricGrid\Descriptor();
        $indicatorUsage1->getDescriptors()[1] = (new RubricGrid\Descriptor())->setMaxValue(3);
        $indicatorUsage1->getDescriptors()[2] = (new RubricGrid\Descriptor())->setMaxValue(5);
        $indicatorUsage1->getDescriptors()[3] = (new RubricGrid\Descriptor())->setMaxValue(7);

        // Indicator usage 2: max is 0 (no descriptors with marks)
        $indicatorUsage2 = new RubricGrid\IndicatorUsage($indicator2);
        $indicatorUsage2->getDescriptors()[1] = new RubricGrid\Descriptor();
        $indicatorUsage2->getDescriptors()[2] = new RubricGrid\Descriptor();

        // Indicator usage 2: max is 8
        $indicatorUsage3 = new RubricGrid\IndicatorUsage($indicator3);
        $indicatorUsage3->getDescriptors()[1] = (new RubricGrid\Descriptor())->setMaxValue(8);
        $indicatorUsage3->getDescriptors()[2] = (new RubricGrid\Descriptor())->setMaxValue(5);
        $indicatorUsage3->getDescriptors()[3] = (new RubricGrid\Descriptor())->setMaxValue(2);

        // Indicator usage 4: max is 0 (no descriptors)
        $indicatorUsage4 = new RubricGrid\IndicatorUsage($indicator4);


        $capabilityUsage1 = new RubricGrid\CapabilityUsage($capability);

        yield 'No indicators' => [$capabilityUsage1, 0];


        $capabilityUsage2                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage2->getIndicatorUsages()[$indicator4->getId()] = $indicatorUsage4;

        yield 'No indicators with descriptors' => [$capabilityUsage2, 0];


        $capabilityUsage3                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage3->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;

        yield 'No indicators with descriptors with marks' => [$capabilityUsage3, 0];


        $capabilityUsage4                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage4->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $capabilityUsage4->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;
        $capabilityUsage4->getIndicatorUsages()[$indicator3->getId()] = $indicatorUsage3;
        $capabilityUsage4->getIndicatorUsages()[$indicator4->getId()] = $indicatorUsage4;

        yield 'Descriptors with marks' => [$capabilityUsage4, 15];
    }


    /**
     * @param RubricGrid\CapabilityUsage $capabilityUsage
     * @param float                      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestCalculateMaxValueFromIndicatorsData')]
    public function testCalculateMaxValueFromIndicators(
        RubricGrid\CapabilityUsage $capabilityUsage,
        float $expected
    ): void {
        $this->assertSame($expected, $capabilityUsage->calculateMaxValueFromIndicators());
    }


    /**
     * Data provider for `testContainsDescriptorsWithValue`
     *
     * @return iterable
     */
    public static function getTestContainsDescriptorsWithValueData(): iterable
    {
        $capability = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $indicator1 = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2 = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3 = (new RowSet\Indicator())->setId(3)->setName('Indicator 1.3');
        $indicator4 = (new RowSet\Indicator())->setId(4)->setName('Indicator 1.4');

        $capability->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability->getIndicators()[$indicator2->getId()] = $indicator2;
        $capability->getIndicators()[$indicator3->getId()] = $indicator3;
        $capability->getIndicators()[$indicator4->getId()] = $indicator4;

        // Indicator usage 1: max is 7
        $indicatorUsage1 = new RubricGrid\IndicatorUsage($indicator1);
        $indicatorUsage1->getDescriptors()[0] = new RubricGrid\Descriptor();
        $indicatorUsage1->getDescriptors()[1] = (new RubricGrid\Descriptor())->setMaxValue(3);
        $indicatorUsage1->getDescriptors()[2] = (new RubricGrid\Descriptor())->setMaxValue(5);
        $indicatorUsage1->getDescriptors()[3] = (new RubricGrid\Descriptor())->setMaxValue(7);

        // Indicator usage 2: max is 0 (no descriptors with marks)
        $indicatorUsage2 = new RubricGrid\IndicatorUsage($indicator2);
        $indicatorUsage2->getDescriptors()[1] = new RubricGrid\Descriptor();
        $indicatorUsage2->getDescriptors()[2] = new RubricGrid\Descriptor();

        // Indicator usage 2: max is 8
        $indicatorUsage3 = new RubricGrid\IndicatorUsage($indicator3);
        $indicatorUsage3->getDescriptors()[1] = (new RubricGrid\Descriptor())->setMaxValue(8);
        $indicatorUsage3->getDescriptors()[2] = (new RubricGrid\Descriptor())->setMaxValue(5);
        $indicatorUsage3->getDescriptors()[3] = (new RubricGrid\Descriptor())->setMaxValue(2);

        // Indicator usage 4: max is 0 (no descriptors)
        $indicatorUsage4 = new RubricGrid\IndicatorUsage($indicator4);


        $capabilityUsage1 = new RubricGrid\CapabilityUsage($capability);

        yield 'No indicators' => [$capabilityUsage1, false];


        $capabilityUsage2                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage2->getIndicatorUsages()[$indicator4->getId()] = $indicatorUsage4;

        yield 'No indicators with descriptors' => [$capabilityUsage2, false];


        $capabilityUsage3                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage3->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;

        yield 'No indicators with descriptors with marks' => [$capabilityUsage3, false];


        $capabilityUsage4                                             = new RubricGrid\CapabilityUsage($capability);
        $capabilityUsage4->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $capabilityUsage4->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;
        $capabilityUsage4->getIndicatorUsages()[$indicator3->getId()] = $indicatorUsage3;
        $capabilityUsage4->getIndicatorUsages()[$indicator4->getId()] = $indicatorUsage4;

        yield 'Descriptors with marks' => [$capabilityUsage4, true];
    }


    /**
     * @param RubricGrid\CapabilityUsage $capabilityUsage
     * @param bool                       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestContainsDescriptorsWithValueData')]
    public function testContainsDescriptorsWithValue(
        RubricGrid\CapabilityUsage $capabilityUsage,
        bool $expected
    ): void {
        $this->assertSame($expected, $capabilityUsage->containsDescriptorsWithValue());
    }

    /**
     * Helper for generating capability usage objects.
     *
     * @param array $capabilityUsage A single capability usage
     *                               (@see `\Schoolbox\Assessment\Rubric\Grid\Test\Generator::generateGrid`)
     * @return RubricGrid\CapabilityUsage
     */
    private function generateCapabilityUsage(array $capabilityUsage): RubricGrid\CapabilityUsage
    {
        $generator = new Generator();
        $testGrid  = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            [1 => $capabilityUsage]
        );
        return $testGrid->retrieveCapabilityUsage($testGrid->getRowSet()->retrieveCapability(1));
    }

    /**
     * Data provider for `testIsCapabilityMarkShown`.
     *
     * @return iterable
     */
    public function getTestIsCapabilityMarkShownData(): iterable
    {
        $capabilityUsage = $this->generateCapabilityUsage([
            'maxValue'   => 0,
            'indicators' => [],
        ]);
        yield 'Empty capability' => [$capabilityUsage, false];


        $capabilityUsage = $this->generateCapabilityUsage([
            'maxValue'   => 0,
            'indicators' => [
                11 => [],
            ],
        ]);
        yield 'Empty indicators' => [$capabilityUsage, false];


        $capabilityUsage = $this->generateCapabilityUsage([
            'maxValue'   => 0,
            'indicators' => [
                11 => [
                    'descriptors' => [
                        111 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                        ],
                        112 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                        ],
                    ],
                ],
                12 => [
                    'descriptors' => [
                        121 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                        ],
                        122 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                        ],
                    ],
                ],
            ],
        ]);
        yield 'Indicators with no value descriptors' => [$capabilityUsage, false];


        $capabilityUsage = $this->generateCapabilityUsage([
            'maxValue'   => 4,
            'indicators' => [
                11 => [
                    'descriptors' => [
                        111 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                            'value'       => 0,
                        ],
                        112 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                            'value'       => 2,
                        ],
                    ],
                ],
                12 => [
                    'descriptors' => [
                        121 => [
                            'levelId'     => 1,
                            'description' => 'Poor',
                            'value'       => 0,
                        ],
                        112 => [
                            'levelId'     => 2,
                            'description' => 'Okay',
                            'value'       => 2,
                        ],
                    ],
                ],
            ],
        ]);
        yield 'Indicators with valued descriptors' => [$capabilityUsage, true];


        $capabilityUsage = $this->generateCapabilityUsage([
            'maxValue'   => 12,
            'indicators' => [],
        ]);
        yield 'Capability with max value' => [$capabilityUsage, true];


        $capabilityUsage = $this->generateCapabilityUsage([
            'maxValue'   => 0,
            'indicators' => [],
        ]);
        yield 'Capability without max value' => [$capabilityUsage, false];
    }


    /**
     * @param RubricGrid\CapabilityUsage $capabilityUsage
     * @param bool                       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsCapabilityMarkShownData')]
    public function testIsCapabilityMarkShown(RubricGrid\CapabilityUsage $capabilityUsage, bool $expected): void
    {
        $this->assertSame($expected, $capabilityUsage->isCapabilityMarkShown());
    }


    /**
     * Data provider for `testIsCompatibleWith`.
     *
     * @return iterable
     */
    public static function getTestIsCompatibleWithData(): iterable
    {
        $generator = new Generator();


        // both empty: true
        $usage1 = new RubricGrid\CapabilityUsage(new RowSet\Capability());
        $usage2 = new RubricGrid\CapabilityUsage(new RowSet\Capability());
        yield "empty with empty" => [$usage1, $usage2, true];


        // both with the same indicators, descs: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        yield "unchanged" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            true
        ];


        // different points value for a descriptor: false
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid1->retrieveIndicatorUsage($grid1->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setMaxValue(100);
        $grid2->retrieveIndicatorUsage($grid2->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setMaxValue(101);
        yield "different descriptor value" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            false
        ];


        // missing descriptor: false
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $indicator = $grid2->getRowSet()->retrieveIndicator(11);
        unset($grid2->retrieveIndicatorUsage($indicator)->getDescriptors()[1]);
        yield "missing descriptor" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            false
        ];


        // different capability: false
        $grid = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $usage1 = $grid->getCapabilityUsages()[1];
        $usage2 = $grid->getCapabilityUsages()[2];
        yield "different capability" => [$usage1, $usage2, false];


        // different indicators: false
        // ensure that cap max is the same for both regardless, so that only
        // the indicators in use are checked
        $grid1CapUsages = Generator::TEST_CAPABILITY_USAGES;
        $grid1CapUsages[1]['maxValue'] = 100;
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid1CapUsages
        );

        $grid2CapUsages = Generator::TEST_CAPABILITY_USAGES;
        $grid2CapUsages[1]['maxValue'] = 100;
        unset($grid2CapUsages[1]['indicators'][11]);
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "removed indicator" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            false
        ];
        yield "added indicator" => [
            $grid2->getCapabilityUsages()[1],
            $grid1->getCapabilityUsages()[1],
            false
        ];


        // different description for a descriptor: true
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid1->retrieveIndicatorUsage($grid1->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setDescription('y');
        $grid2->retrieveIndicatorUsage($grid2->getRowSet()->retrieveIndicator(11))
              ->retrieveDescriptor(1)
              ->setDescription('z');
        yield "different descriptor description" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            true
        ];


        // different ordering of descriptors: true (regardless of whether they're in the same level)
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid2CapUsages   = Generator::TEST_CAPABILITY_USAGES;
        $grid2Descriptors = &$grid2CapUsages[1]['indicators'][11]['descriptors'];
        $grid2Descriptors[111]['levelId'] = 5;
        $grid2Descriptors[115]['levelId'] = 1;
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "different descriptor ordering" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            true
        ];


        // different max points per capability: true?
        $grid1 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );

        $grid2CapUsages = Generator::TEST_CAPABILITY_USAGES;
        $grid2CapUsages[1]['maxValue'] = 100;
        $grid2 = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            $grid2CapUsages
        );

        yield "different max points per capability" => [
            $grid1->getCapabilityUsages()[1],
            $grid2->getCapabilityUsages()[1],
            false
        ];
    }


    /**
     *
     * @param RubricGrid\CapabilityUsage $usage1
     * @param RubricGrid\CapabilityUsage $usage2
     * @param bool                       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsCompatibleWithData')]
    public function testIsCompatibleWith(
        RubricGrid\CapabilityUsage $usage1,
        RubricGrid\CapabilityUsage $usage2,
        bool $expected
    ): void {
        $this->assertSame($expected, $usage1->isCompatibleWith($usage2));
    }
}
