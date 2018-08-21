<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid as RubricGrid;
use Schoolbox\Assessment\Rubric\RowSet;

/**
 * @covers \Schoolbox\Assessment\Rubric\Grid\CriterionUsage
 */
class CriterionUsage extends TestCase
{
    /**
     * Data provider for `testRetrieveDescriptor`.
     *
     * @return iterable
     */
    public function getTestRetrieveDescriptorData(): iterable
    {
        $criterion      = (new RowSet\Criterion())->setId(1)->setName('Criterion 1');
        $criterionUsage = new RubricGrid\CriterionUsage($criterion);

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

        $criterionUsage->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage->getDescriptors()[$level3->getId()] = $descriptor3;
        // level 4: not set
        $criterionUsage->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'descriptor 1: set'     => [$criterionUsage, $level1, $descriptor1];
        yield 'descriptor 2: set'     => [$criterionUsage, $level2, $descriptor2];
        yield 'descriptor 3: set'     => [$criterionUsage, $level3, $descriptor3];
        yield 'descriptor 4: not set' => [$criterionUsage, $level4, null];
        yield 'descriptor 5: set'     => [$criterionUsage, $level5, $descriptor5];
    }


    /**
     * @dataProvider getTestRetrieveDescriptorData
     * @covers \Schoolbox\Assessment\Rubric\Grid\CriterionUsage::retrieveDescriptor
     *
     * @param RubricGrid\CriterionUsage  $criterionUsage
     * @param RubricGrid\Level           $level
     * @param null|RubricGrid\Descriptor $expected
     */
    public function testRetrieveDescriptor(
        RubricGrid\CriterionUsage $criterionUsage,
        RubricGrid\Level $level,
        ?RubricGrid\Descriptor $expected
    ): void {
        $this->assertSame($expected, $criterionUsage->retrieveDescriptor($level));
    }


    /**
     * Data provider for `testRetrieveDescriptor`.
     *
     * @return iterable
     */
    public function getTestCalculateMaxValueData(): iterable
    {
        $criterion1      = (new RowSet\Criterion())->setId(1)->setName('Criterion 1');
        $criterionUsage1 = new RubricGrid\CriterionUsage($criterion1);

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

        $criterionUsage1->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage1->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage1->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage1->getDescriptors()[$level4->getId()] = $descriptor4;
        $criterionUsage1->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'criterion 1: descriptors' => [$criterionUsage1, 8];


        $criterion2      = (new RowSet\Criterion())->setId(2)->setName('Criterion 2');
        $criterionUsage2 = new RubricGrid\CriterionUsage($criterion2);

        yield 'criterion 2: no descriptors'          => [$criterionUsage2, null];


        $criterion3      = (new RowSet\Criterion())->setId(3)->setName('Criterion 3');
        $criterionUsage3 = new RubricGrid\CriterionUsage($criterion3);

        $descriptor6 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.1');
        $descriptor7 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.2')->setMaxValue(null);

        $criterionUsage1->getDescriptors()[$level1->getId()] = $descriptor6;
        $criterionUsage1->getDescriptors()[$level2->getId()] = $descriptor7;

        yield 'criterion 3: no non-null descriptors' => [$criterionUsage3, null];
    }


    /**
     * @dataProvider getTestCalculateMaxValueData
     * @covers \Schoolbox\Assessment\Rubric\Grid\CriterionUsage::calculateMaxValue
     *
     * @param RubricGrid\CriterionUsage $criterionUsage
     * @param float|null                $expected
     */
    public function testCalculateMaxValue(
        RubricGrid\CriterionUsage $criterionUsage,
        ?float $expected
    ): void {
        $this->assertSame($expected, $criterionUsage->calculateMaxValue());
    }


    /**
     * Data provider for `testApplyDescriptorMinValues`.
     *
     * @return iterable
     */
    public function getTestApplyDescriptorMinValuesData(): iterable
    {
        // Ascending: [0, 2, 4, 6, 8] => [0, 1..2, 3..4, 5..6, 7..8]
        $criterion       = (new RowSet\Criterion())->setId(1)->setName('Criterion');
        $criterionUsage1 = new RubricGrid\CriterionUsage($criterion);

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

        $criterionUsage1->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage1->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage1->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage1->getDescriptors()[$level4->getId()] = $descriptor4;
        $criterionUsage1->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Ascending 0' => [$criterionUsage1, $level1, 0];
        yield 'Ascending 2' => [$criterionUsage1, $level2, 1];
        yield 'Ascending 4' => [$criterionUsage1, $level3, 3];
        yield 'Ascending 6' => [$criterionUsage1, $level4, 5];
        yield 'Ascending 8' => [$criterionUsage1, $level5, 7];


        // Descending: [8, 6, 4, 2, 0] => [7..8, 5..6, 3..4, 1..2, 0]
        $criterionUsage2 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.1')->setMaxValue(8);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.2')->setMaxValue(6);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.3')->setMaxValue(4);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.4')->setMaxValue(2);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 2.5')->setMaxValue(0);

        $criterionUsage2->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage2->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage2->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage2->getDescriptors()[$level4->getId()] = $descriptor4;
        $criterionUsage2->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Descending 8' => [$criterionUsage2, $level1, 7];
        yield 'Descending 6' => [$criterionUsage2, $level2, 5];
        yield 'Descending 4' => [$criterionUsage2, $level3, 3];
        yield 'Descending 2' => [$criterionUsage2, $level4, 1];
        yield 'Descending 0' => [$criterionUsage2, $level5, 0];


        // Asc, skip null before: [0, null, 5, 10] => [0, null, 1..5, 6..10]
        $criterionUsage3 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.2')->setMaxValue(null);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.3')->setMaxValue(5);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 3.4')->setMaxValue(10);

        $criterionUsage3->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage3->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage3->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage3->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Ascending skip-null-before 0'    => [$criterionUsage3, $level1, 0];
        yield 'Ascending skip-null-before 5'    => [$criterionUsage3, $level2, null];
        yield 'Ascending skip-null-before null' => [$criterionUsage3, $level3, 1];
        yield 'Ascending skip-null-before 10'   => [$criterionUsage3, $level4, 6];


        // Asc, skip null after: [0, 5, null, 10] => [0, 1..5, null, 6..10]
        $criterionUsage4 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.2')->setMaxValue(5);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.3')->setMaxValue(null);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 4.4')->setMaxValue(10);

        $criterionUsage4->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage4->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage4->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage4->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Ascending skip-null-after 0'    => [$criterionUsage4, $level1, 0];
        yield 'Ascending skip-null-after 5'    => [$criterionUsage4, $level2, 1];
        yield 'Ascending skip-null-after null' => [$criterionUsage4, $level3, null];
        yield 'Ascending skip-null-after 10'   => [$criterionUsage4, $level4, 6];


        // Asc, skip null at start: [null, 5, 10] => [null, 5, 6..10]
        $criterionUsage5 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 5.1')->setMaxValue(null);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 5.2')->setMaxValue(5);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 5.3')->setMaxValue(10);

        $criterionUsage5->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage5->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage5->getDescriptors()[$level3->getId()] = $descriptor3;

        yield 'Ascending skip-null-start null' => [$criterionUsage5, $level1, null];
        yield 'Ascending skip-null-start 5'    => [$criterionUsage5, $level2, 5];
        yield 'Ascending skip-null-start 10'   => [$criterionUsage5, $level3, 6];


        // Desc, skip null before: [10, null, 5, 0] => [6..10, null, 1..5, 0]
        $criterionUsage6 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.1')->setMaxValue(10);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.2')->setMaxValue(null);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.3')->setMaxValue(5);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 6.4')->setMaxValue(0);

        $criterionUsage6->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage6->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage6->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage6->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Descending skip-null-before 0'    => [$criterionUsage6, $level1, 6];
        yield 'Descending skip-null-before 5'    => [$criterionUsage6, $level2, null];
        yield 'Descending skip-null-before null' => [$criterionUsage6, $level3, 1];
        yield 'Descending skip-null-before 10'   => [$criterionUsage6, $level4, 0];


        // Desc, skip null after: [10, 5, null, 0] => [6..10, 1..5, null, 0]
        $criterionUsage7 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.1')->setMaxValue(10);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.2')->setMaxValue(5);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.3')->setMaxValue(null);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 7.4')->setMaxValue(0);

        $criterionUsage7->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage7->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage7->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage7->getDescriptors()[$level4->getId()] = $descriptor4;

        yield 'Descending skip-null-after 0'    => [$criterionUsage7, $level1, 6];
        yield 'Descending skip-null-after 5'    => [$criterionUsage7, $level2, 1];
        yield 'Descending skip-null-after null' => [$criterionUsage7, $level3, null];
        yield 'Descending skip-null-after 10'   => [$criterionUsage7, $level4, 0];


        // Asc+Desc: [0, 4, 8, 3, 0]  => [0, 1..4, 5..8, 1..3, 0] (use highest max below this max)
        $criterionUsage8 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.1')->setMaxValue(0);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.2')->setMaxValue(4);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.3')->setMaxValue(8);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.4')->setMaxValue(3);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 8.5')->setMaxValue(0);

        $criterionUsage8->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage8->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage8->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage8->getDescriptors()[$level4->getId()] = $descriptor4;
        $criterionUsage8->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Asc+Desc 0 (start)' => [$criterionUsage8, $level1, 0];
        yield 'Asc+Desc 4'         => [$criterionUsage8, $level2, 1];
        yield 'Asc+Desc 8'         => [$criterionUsage8, $level3, 5];
        yield 'Asc+Desc 3'         => [$criterionUsage8, $level4, 1];
        yield 'Asc+Desc 0 (end)'   => [$criterionUsage8, $level5, 0];


        // Fibonacci: [1, 1, 2, 3, 5, 8] => [1, 1, 2, 3, 4..5, 6..8]
        $criterionUsage9 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.1')->setMaxValue(1);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.2')->setMaxValue(1);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.3')->setMaxValue(2);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.4')->setMaxValue(3);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.5')->setMaxValue(5);
        $descriptor6 = (new RubricGrid\Descriptor())->setDescription('Descriptor 9.4')->setMaxValue(8);

        $criterionUsage9->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage9->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage9->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage9->getDescriptors()[$level4->getId()] = $descriptor4;
        $criterionUsage9->getDescriptors()[$level5->getId()] = $descriptor5;
        $criterionUsage9->getDescriptors()[$level6->getId()] = $descriptor6;

        yield 'Fibonacci 1 (1)' => [$criterionUsage9, $level1, 1];
        yield 'Fibonacci 2 (1)' => [$criterionUsage9, $level2, 1];
        yield 'Fibonacci 3 (2)' => [$criterionUsage9, $level3, 2];
        yield 'Fibonacci 4 (3)' => [$criterionUsage9, $level4, 3];
        yield 'Fibonacci 5 (5)' => [$criterionUsage9, $level5, 4];
        yield 'Fibonacci 6 (8)' => [$criterionUsage9, $level6, 6];


        // All null: [null, null, null, null, null] => [null, null, null, null, null]
        $criterionUsage10 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.1')->setMaxValue(null);
        $descriptor2 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.2')->setMaxValue(null);
        $descriptor3 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.3')->setMaxValue(null);
        $descriptor4 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.4')->setMaxValue(null);
        $descriptor5 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.5')->setMaxValue(null);

        $criterionUsage10->getDescriptors()[$level1->getId()] = $descriptor1;
        $criterionUsage10->getDescriptors()[$level2->getId()] = $descriptor2;
        $criterionUsage10->getDescriptors()[$level3->getId()] = $descriptor3;
        $criterionUsage10->getDescriptors()[$level4->getId()] = $descriptor4;
        $criterionUsage10->getDescriptors()[$level5->getId()] = $descriptor5;

        yield 'Null 1 (null)' => [$criterionUsage10, $level1, null];
        yield 'Null 2 (null)' => [$criterionUsage10, $level2, null];
        yield 'Null 3 (null)' => [$criterionUsage10, $level3, null];
        yield 'Null 4 (null)' => [$criterionUsage10, $level4, null];
        yield 'Null 5 (null)' => [$criterionUsage10, $level5, null];


        // Only one descriptor: [5] => [5]
        $criterionUsage11 = new RubricGrid\CriterionUsage($criterion);

        $descriptor1 = (new RubricGrid\Descriptor())->setDescription('Descriptor 10.1')->setMaxValue(5);

        $criterionUsage11->getDescriptors()[$level1->getId()] = $descriptor1;

        yield 'Single Descriptor' => [$criterionUsage11, $level1, 5];
    }


    /**
     * @dataProvider getTestApplyDescriptorMinValuesData
     * @covers \Schoolbox\Assessment\Rubric\Grid\CriterionUsage::applyDescriptorMinValues
     *
     * @param RubricGrid\CriterionUsage $criterionUsage
     * @param RubricGrid\Level          $level
     * @param float|null                $expected
     */
    public function testApplyDescriptorMinValues(
        RubricGrid\CriterionUsage $criterionUsage,
        RubricGrid\Level $level,
        ?float $expected
    ): void {
        $criterionUsage->applyDescriptorMinValues(true);
        $descriptor = $criterionUsage->retrieveDescriptor($level);
        $this->assertNotNull($descriptor);
        $this->assertSame($expected, $descriptor->getMinValue());
    }
}
