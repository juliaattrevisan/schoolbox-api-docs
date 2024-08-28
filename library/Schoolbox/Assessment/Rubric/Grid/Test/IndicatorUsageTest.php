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
#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\Grid\IndicatorUsage::class)]
class IndicatorUsageTest extends TestCase
{
    /**
     * Data provider for `testRetrieveDescriptor`.
     *
     * @return iterable
     */
    public static function getTestRetrieveDescriptorData(): iterable
    {
        $indicator      = (new RowSet\Indicator())->setId(1)->setName('Indicator 1');
        $indicatorUsage = new RubricGrid\IndicatorUsage($indicator);

        $level1 = (new RubricGrid\Level())->setId(1)->setName('Level 1');
        $level2 = (new RubricGrid\Level())->setId(2)->setName('Level 2');
        $level3 = (new RubricGrid\Level())->setId(3)->setName('Level 3');
        $level4 = (new RubricGrid\Level())->setId(4)->setName('Level 4');
        $level5 = (new RubricGrid\Level())->setId(5)->setName('Level 5');

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.2')->setMaxValue(2);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.3')->setMaxValue(4);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.4')->setMaxValue(6);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.5')->setMaxValue(8);

        $indicatorUsage->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage->getDescriptors()[$level3->getId()] = $descriptor3;
        // level 4: not set
        $indicatorUsage->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'descriptor 1: set'     => [$indicatorUsage, $level1->getId(), $descriptor1];
        yield 'descriptor 2: set'     => [$indicatorUsage, $level2->getId(), $descriptor2];
        yield 'descriptor 3: set'     => [$indicatorUsage, $level3->getId(), $descriptor3];
        yield 'descriptor 4: not set' => [$indicatorUsage, $level4->getId(), null];
        yield 'descriptor 5: set'     => [$indicatorUsage, $level5->getId(), $descriptor5];
    }


    /**
     *
     * @param RubricGrid\IndicatorUsage  $indicatorUsage
     * @param int                        $levelIndex
     * @param null|RubricGrid\Descriptor $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveDescriptorData')]
    public function testRetrieveDescriptor(
        RubricGrid\IndicatorUsage $indicatorUsage,
        int $levelIndex,
        ?RubricGrid\Descriptor $expected
    ): void {
        $this->assertSame($expected, $indicatorUsage->retrieveDescriptor($levelIndex));
    }


    /**
     * Data provider for `testCalculateMaxValueFromDescriptors`.
     *
     * @return iterable
     */
    public static function getTestCalculateMaxValueDataFromDescriptors(): iterable
    {
        $indicator1      = (new RowSet\Indicator())->setId(1)->setName('Indicator 1');
        $indicatorUsage1 = new RubricGrid\IndicatorUsage($indicator1);

        $level1 = (new RubricGrid\Level())->setId(1)->setName('Level 1');
        $level2 = (new RubricGrid\Level())->setId(2)->setName('Level 2');
        $level3 = (new RubricGrid\Level())->setId(3)->setName('Level 3');
        $level4 = (new RubricGrid\Level())->setId(4)->setName('Level 4');
        $level5 = (new RubricGrid\Level())->setId(5)->setName('Level 5');

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.2')->setMaxValue(2);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.3')->setMaxValue(4);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.4')->setMaxValue(6);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.5')->setMaxValue(8);

        $indicatorUsage1->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage1->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage1->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage1->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage1->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'indicator 1: descriptors' => [$indicatorUsage1, 8];


        $indicator2      = (new RowSet\Indicator())->setId(2)->setName('Indicator 2');
        $indicatorUsage2 = new RubricGrid\IndicatorUsage($indicator2);

        yield 'indicator 2: no descriptors' => [$indicatorUsage2, null];


        $indicator3      = (new RowSet\Indicator())->setId(3)->setName('Indicator 3');
        $indicatorUsage3 = new RubricGrid\IndicatorUsage($indicator3);

        $descriptor6 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.1');
        $descriptor7 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.2')->setMaxValue(null);

        $indicatorUsage1->getDescriptors()[$level1->getId()] = $descriptor6;
        $indicatorUsage1->getDescriptors()[$level2->getId()] = $descriptor7;

        yield 'indicator 3: no non-null descriptors' => [$indicatorUsage3, null];
    }


    /**
     * @param RubricGrid\IndicatorUsage $indicatorUsage
     * @param float|null                $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestCalculateMaxValueDataFromDescriptors')]
    public function testCalculateMaxValueFromDescriptors(
        RubricGrid\IndicatorUsage $indicatorUsage,
        ?float $expected
    ): void {
        $this->assertSame($expected, $indicatorUsage->calculateMaxValueFromDescriptors());
    }



    /**
     * Data provider for `testContainsDescriptorsWithValue`
     *
     * @return iterable
     */
    public static function getTestContainsDescriptorsWithValueData(): iterable
    {
        $indicator1      = (new RowSet\Indicator())->setId(1)->setName('Indicator 1');
        $indicatorUsage1 = new RubricGrid\IndicatorUsage($indicator1);

        $level1 = (new RubricGrid\Level())->setId(1)->setName('Level 1');
        $level2 = (new RubricGrid\Level())->setId(2)->setName('Level 2');
        $level3 = (new RubricGrid\Level())->setId(3)->setName('Level 3');
        $level4 = (new RubricGrid\Level())->setId(4)->setName('Level 4');
        $level5 = (new RubricGrid\Level())->setId(5)->setName('Level 5');

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.2')->setMaxValue(2);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.3')->setMaxValue(4);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.4')->setMaxValue(6);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.5')->setMaxValue(8);

        $indicatorUsage1->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage1->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage1->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage1->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage1->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'indicator 1: descriptors' => [$indicatorUsage1, true];


        $indicator2      = (new RowSet\Indicator())->setId(2)->setName('Indicator 2');
        $indicatorUsage2 = new RubricGrid\IndicatorUsage($indicator2);

        yield 'indicator 2: no descriptors' => [$indicatorUsage2, false];


        $indicator3      = (new RowSet\Indicator())->setId(3)->setName('Indicator 3');
        $indicatorUsage3 = new RubricGrid\IndicatorUsage($indicator3);

        $descriptor6 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.1');
        $descriptor7 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.2')->setMaxValue(null);

        $indicatorUsage1->getDescriptors()[$level1->getId()] = $descriptor6;
        $indicatorUsage1->getDescriptors()[$level2->getId()] = $descriptor7;

        yield 'indicator 3: no non-null descriptors' => [$indicatorUsage3, false];
    }


    /**
     * @param RubricGrid\IndicatorUsage $indicatorUsage
     * @param bool                      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestContainsDescriptorsWithValueData')]
    public function testContainsDescriptorsWithValue(
        RubricGrid\IndicatorUsage $indicatorUsage,
        bool $expected
    ): void {
        $this->assertSame($expected, $indicatorUsage->containsDescriptorsWithValue());
    }


    /**
     * Data provider for `testApplyDescriptorMinValues`.
     *
     * @return iterable
     */
    public static function getTestApplyDescriptorMinValuesData(): iterable
    {
        // Ascending: [0, 2, 4, 6, 8] => [0, 1..2, 3..4, 5..6, 7..8]
        $indicator       = (new RowSet\Indicator())->setId(1)->setName('Indicator');
        $indicatorUsage1 = new RubricGrid\IndicatorUsage($indicator);

        $level1 = (new RubricGrid\Level())->setId(1)->setName('Level 1');
        $level2 = (new RubricGrid\Level())->setId(2)->setName('Level 2');
        $level3 = (new RubricGrid\Level())->setId(3)->setName('Level 3');
        $level4 = (new RubricGrid\Level())->setId(4)->setName('Level 4');
        $level5 = (new RubricGrid\Level())->setId(5)->setName('Level 5');
        $level6 = (new RubricGrid\Level())->setId(6)->setName('Level 6');

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.2')->setMaxValue(2);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.3')->setMaxValue(4);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.4')->setMaxValue(6);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 1.5')->setMaxValue(8);

        $indicatorUsage1->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage1->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage1->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage1->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage1->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Ascending 0' => [$indicatorUsage1, $level1->getId(), 0, 0];
        yield 'Ascending 2' => [$indicatorUsage1, $level2->getId(), 1, 2];
        yield 'Ascending 4' => [$indicatorUsage1, $level3->getId(), 3, 4];
        yield 'Ascending 6' => [$indicatorUsage1, $level4->getId(), 5, 6];
        yield 'Ascending 8' => [$indicatorUsage1, $level5->getId(), 7, 8];


        // Descending: [8, 6, 4, 2, 0] => [8..7, 6..5, 4..3, 2..1, 0]
        $indicatorUsage2 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.1')->setMaxValue(8);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.2')->setMaxValue(6);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.3')->setMaxValue(4);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.4')->setMaxValue(2);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.5')->setMaxValue(0);

        $indicatorUsage2->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage2->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage2->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage2->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage2->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Descending 8' => [$indicatorUsage2, $level1->getId(), 8, 7];
        yield 'Descending 6' => [$indicatorUsage2, $level2->getId(), 6, 5];
        yield 'Descending 4' => [$indicatorUsage2, $level3->getId(), 4, 3];
        yield 'Descending 2' => [$indicatorUsage2, $level4->getId(), 2, 1];
        yield 'Descending 0' => [$indicatorUsage2, $level5->getId(), 0, 0];


        // Asc, skip null before: [0, null, 5, 10] => [0, null, 1..5, 6..10]
        $indicatorUsage3 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.2')->setMaxValue(null);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.3')->setMaxValue(5);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.4')->setMaxValue(10);

        $indicatorUsage3->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage3->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage3->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage3->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Ascending skip-null-before 0'    => [$indicatorUsage3, $level1->getId(), 0, 0];
        yield 'Ascending skip-null-before 5'    => [$indicatorUsage3, $level2->getId(), null, null];
        yield 'Ascending skip-null-before null' => [$indicatorUsage3, $level3->getId(), 1, 5];
        yield 'Ascending skip-null-before 10'   => [$indicatorUsage3, $level4->getId(), 6, 10];


        // Asc, skip null after: [0, 5, null, 10] => [0, 1..5, null, 6..10]
        $indicatorUsage4 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.2')->setMaxValue(5);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.3')->setMaxValue(null);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.4')->setMaxValue(10);

        $indicatorUsage4->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage4->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage4->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage4->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Ascending skip-null-after 0'    => [$indicatorUsage4, $level1->getId(), 0, 0];
        yield 'Ascending skip-null-after 5'    => [$indicatorUsage4, $level2->getId(), 1, 5];
        yield 'Ascending skip-null-after null' => [$indicatorUsage4, $level3->getId(), null, null];
        yield 'Ascending skip-null-after 10'   => [$indicatorUsage4, $level4->getId(), 6, 10];


        // Asc, skip null at start: [null, 5, 10] => [null, 5, 6..10]
        $indicatorUsage5 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 5.1')->setMaxValue(null);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 5.2')->setMaxValue(5);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 5.3')->setMaxValue(10);

        $indicatorUsage5->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage5->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage5->getDescriptors()[$level3->getId()] = $descriptor3;

        yield 'Ascending skip-null-start null' => [$indicatorUsage5, $level1->getId(), null, null];
        yield 'Ascending skip-null-start 5'    => [$indicatorUsage5, $level2->getId(), 1, 5];
        yield 'Ascending skip-null-start 10'   => [$indicatorUsage5, $level3->getId(), 6, 10];


        // Desc, skip null before: [10, null, 5, 0] => [10..6, null, 5..1, 0]
        $indicatorUsage6 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.1')->setMaxValue(10);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.2')->setMaxValue(null);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.3')->setMaxValue(5);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.4')->setMaxValue(0);

        $indicatorUsage6->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage6->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage6->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage6->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Descending skip-null-before 0'    => [$indicatorUsage6, $level1->getId(), 10, 6];
        yield 'Descending skip-null-before 5'    => [$indicatorUsage6, $level2->getId(), null, null];
        yield 'Descending skip-null-before null' => [$indicatorUsage6, $level3->getId(), 5, 1];
        yield 'Descending skip-null-before 10'   => [$indicatorUsage6, $level4->getId(), 0, 0];


        // Desc, skip null after: [10, 5, null, 0] => [10..6, 5..1, null, 0]
        $indicatorUsage7 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.1')->setMaxValue(10);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.2')->setMaxValue(5);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.3')->setMaxValue(null);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.4')->setMaxValue(0);

        $indicatorUsage7->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage7->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage7->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage7->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Descending skip-null-after 0'    => [$indicatorUsage7, $level1->getId(), 10, 6];
        yield 'Descending skip-null-after 5'    => [$indicatorUsage7, $level2->getId(), 5, 1];
        yield 'Descending skip-null-after null' => [$indicatorUsage7, $level3->getId(), null, null];
        yield 'Descending skip-null-after 10'   => [$indicatorUsage7, $level4->getId(), 0, 0];


        // Asc+Desc: [0, 4, 8, 3, 0]  => [0, 1..4, 5..8, 3..1, 0] (use highest max below this max)

        $indicatorUsage8 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.2')->setMaxValue(4);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.3')->setMaxValue(8);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.4')->setMaxValue(3);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.5')->setMaxValue(0);

        $indicatorUsage8->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage8->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage8->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage8->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage8->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Asc+Desc 0 (start)' => [$indicatorUsage8, $level1->getId(), 0, 0];
        yield 'Asc+Desc 4'         => [$indicatorUsage8, $level2->getId(), 4, 1];
        yield 'Asc+Desc 8'         => [$indicatorUsage8, $level3->getId(), 8, 4];
        yield 'Asc+Desc 3'         => [$indicatorUsage8, $level4->getId(), 3, 1];
        yield 'Asc+Desc 0 (end)'   => [$indicatorUsage8, $level5->getId(), 0, 0];


        // Fibonacci: [1, 1, 2, 3, 5, 8] => [1, 1, 2, 3, 4..5, 6..8]
        $indicatorUsage9 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.1')->setMaxValue(1);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.2')->setMaxValue(1);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.3')->setMaxValue(2);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.4')->setMaxValue(3);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.5')->setMaxValue(5);
        $descriptor6 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.4')->setMaxValue(8);

        $indicatorUsage9->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage9->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage9->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage9->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage9->getDescriptors()[$level5->getId()] = $descriptor5;
        $indicatorUsage9->getDescriptors()[$level6->getId()] = $descriptor6;

        yield 'Fibonacci 1 (1)' => [$indicatorUsage9, $level1->getId(), 1, 1];
        yield 'Fibonacci 2 (1)' => [$indicatorUsage9, $level2->getId(), 1, 1];
        yield 'Fibonacci 3 (2)' => [$indicatorUsage9, $level3->getId(), 2, 2];
        yield 'Fibonacci 4 (3)' => [$indicatorUsage9, $level4->getId(), 3, 3];
        yield 'Fibonacci 5 (5)' => [$indicatorUsage9, $level5->getId(), 4, 5];
        yield 'Fibonacci 6 (8)' => [$indicatorUsage9, $level6->getId(), 6, 8];


        // All null: [null, null, null, null, null] => [null, null, null, null, null]
        $indicatorUsage10 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.1')->setMaxValue(null);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.2')->setMaxValue(null);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.3')->setMaxValue(null);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.4')->setMaxValue(null);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.5')->setMaxValue(null);

        $indicatorUsage10->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage10->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage10->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage10->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage10->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Null 1 (null)' => [$indicatorUsage10, $level1->getId(), null, null];
        yield 'Null 2 (null)' => [$indicatorUsage10, $level2->getId(), null, null];
        yield 'Null 3 (null)' => [$indicatorUsage10, $level3->getId(), null, null];
        yield 'Null 4 (null)' => [$indicatorUsage10, $level4->getId(), null, null];
        yield 'Null 5 (null)' => [$indicatorUsage10, $level5->getId(), null, null];


        // Only one descriptor: [5] => [5,4,3,2,1] to support IB International Baccalaureate
        $indicatorUsage11 = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.1')->setMaxValue(5);

        $indicatorUsage11->getDescriptors()[$level1->getId()] = $descriptor1;

        yield 'Single Descriptor' => [$indicatorUsage11, $level1->getId(), 1, 5];
    }


    /**
     *
     * @param RubricGrid\IndicatorUsage $indicatorUsage
     * @param int                       $levelIndex
     * @param float|null                $expectedLeft
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApplyDescriptorMinValuesData')]
    public function testApplyDescriptorMinValuesLeft(
        RubricGrid\IndicatorUsage $indicatorUsage,
        int $levelIndex,
        ?float $expectedLeft
    ): void {
        $indicatorUsage->applyDescriptorMinValues(true);
        $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);
        $this->assertNotNull($descriptor);
        $this->assertSame($expectedLeft, $descriptor->retrieveLeftValue());
    }


    /**
     *
     * @param RubricGrid\IndicatorUsage $indicatorUsage
     * @param int                       $levelIndex
     * @param float|null                $_
     * @param float|null                $expectedRight
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApplyDescriptorMinValuesData')]
    public function testApplyDescriptorMinValuesRight(
        RubricGrid\IndicatorUsage $indicatorUsage,
        int $levelIndex,
        ?float $_,
        ?float $expectedRight
    ): void {
        $indicatorUsage->applyDescriptorMinValues(true);
        $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);
        $this->assertNotNull($descriptor);
        $this->assertSame($expectedRight, $descriptor->retrieveRightValue());
    }


    /**
     * Data provider for `testApplyDescriptorMinValuesWithoutValueRange`.
     *
     * @return iterable
     */
    public static function getTestApplyDescriptorMinValuesWithoutValueRange(): iterable
    {
        // Test using the Fibonacci sequence
        // Fibonacci: [1, 1, 2, 3, 5, 8] => [1, 1, 2, 3, 4..5, 6..8]

        $indicator = (new RowSet\Indicator())->setId(1)->setName('Indicator');

        $level1 = (new RubricGrid\Level())->setId(1)->setName('Level 1');
        $level2 = (new RubricGrid\Level())->setId(2)->setName('Level 2');
        $level3 = (new RubricGrid\Level())->setId(3)->setName('Level 3');
        $level4 = (new RubricGrid\Level())->setId(4)->setName('Level 4');
        $level5 = (new RubricGrid\Level())->setId(5)->setName('Level 5');
        $level6 = (new RubricGrid\Level())->setId(6)->setName('Level 6');

        $indicatorUsage = new RubricGrid\IndicatorUsage($indicator);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.1')->setMaxValue(1);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.2')->setMaxValue(1);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.3')->setMaxValue(2);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.4')->setMaxValue(3);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.5')->setMaxValue(5);
        $descriptor6 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.4')->setMaxValue(8);

        $indicatorUsage->getDescriptors()[$level1->getId()] = $descriptor1;
        $indicatorUsage->getDescriptors()[$level2->getId()] = $descriptor2;
        $indicatorUsage->getDescriptors()[$level3->getId()] = $descriptor3;
        $indicatorUsage->getDescriptors()[$level4->getId()] = $descriptor4;
        $indicatorUsage->getDescriptors()[$level5->getId()] = $descriptor5;
        $indicatorUsage->getDescriptors()[$level6->getId()] = $descriptor6;

        yield 'Fibonacci 1 (1)' => [$indicatorUsage, $level1->getId(), 1];
        yield 'Fibonacci 2 (1)' => [$indicatorUsage, $level2->getId(), 1];
        yield 'Fibonacci 3 (2)' => [$indicatorUsage, $level3->getId(), 2];
        yield 'Fibonacci 4 (3)' => [$indicatorUsage, $level4->getId(), 3];
        yield 'Fibonacci 5 (5)' => [$indicatorUsage, $level5->getId(), 5];
        yield 'Fibonacci 6 (8)' => [$indicatorUsage, $level6->getId(), 8];
    }


    /**
     *
     * @param RubricGrid\IndicatorUsage $indicatorUsage
     * @param int                       $levelIndex
     * @param float|null                $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestApplyDescriptorMinValuesWithoutValueRange')]
    public function testApplyDescriptorMinValuesWithoutValueRange(
        RubricGrid\IndicatorUsage $indicatorUsage,
        int $levelIndex,
        ?float $expected
    ): void {
        $indicatorUsage->applyDescriptorMinValues(false);
        $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);
        $this->assertNotNull($descriptor);
        $this->assertSame($expected, $descriptor->getMinValue());
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
        $usage1 = new RubricGrid\IndicatorUsage(new RowSet\Indicator());
        $usage2 = new RubricGrid\IndicatorUsage(new RowSet\Indicator());
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
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
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
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
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
        unset($grid2->retrieveIndicatorUsage($indicator)->getDescriptors()[5]);
        yield "removed descriptor" => [
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
            false
        ];
        yield "added descriptor" => [
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            false
        ];


        // new descriptors: false, even if they're exactly the same
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

        $indicator1 = $grid1->getRowSet()->retrieveIndicator(11);
        $grid1->retrieveIndicatorUsage($indicator1)->getDescriptors()[5]->setId(null);
        unset($grid2->retrieveIndicatorUsage($indicator)->getDescriptors()[5]);
        yield "added new descriptor" => [
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
            false
        ];
        yield "removed new descriptor" => [
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            false
        ];
        yield "same indicator, both with new descriptors" => [
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            false
        ];


        // different indicator: false
        $grid = $generator->generateGrid(
            RowSet\Test\Generator::TEST_CAPABILITIES,
            Generator::TEST_LEVELS,
            Generator::TEST_CAPABILITY_USAGES
        );
        yield "different indicator" => [
            $grid->retrieveIndicatorUsageByDescriptorId(111),
            $grid->retrieveIndicatorUsageByDescriptorId(121),
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
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
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
            $grid1->retrieveIndicatorUsageByDescriptorId(111),
            $grid2->retrieveIndicatorUsageByDescriptorId(111),
            true
        ];
    }


    /**
     *
     * @param RubricGrid\IndicatorUsage $usage1
     * @param RubricGrid\IndicatorUsage $usage2
     * @param bool                      $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsCompatibleWithData')]
    public function testIsCompatibleWith(
        RubricGrid\IndicatorUsage $usage1,
        RubricGrid\IndicatorUsage $usage2,
        bool $expected
    ): void {
        $this->assertSame($expected, $usage1->isCompatibleWith($usage2));
    }
}
