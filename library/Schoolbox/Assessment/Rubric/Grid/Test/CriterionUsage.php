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
        // Examples:
        // Ascending: [0, 2, 4, 6, 8]  => [0, 1..2, 3..4, 5..6, 7..8]
        $criterion       = (new RowSet\Criterion())->setId(1)->setName('Criterion');
        $criterionUsage1 = new RubricGrid\CriterionUsage($criterion);

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

        yield 'Ascending 0' => [$criterionUsage1, $level1, 0];
        yield 'Ascending 2' => [$criterionUsage1, $level2, 1];
        yield 'Ascending 4' => [$criterionUsage1, $level3, 3];
        yield 'Ascending 6' => [$criterionUsage1, $level4, 5];
        yield 'Ascending 8' => [$criterionUsage1, $level5, 7];



        // Descending:              [8, 6, 4, 2, 0]  => [7..8, 5..6, 3..4, 1..2, 0]
        // Asc, skip null before:   [0, null, 5, 10] => [0, null, 1..5, 6..10]
        // Asc, skip null after:    [0, 5, null, 10] => [0, 1..5, null, 6..10]
        // Asc, skip null at start: [null, 5, 10]    => [null, 5, 6..10]
        // Desc, skip null before:  [0, null, 5, 10] => [0, null, 1..5, 6..10]
        // Desc, skip null after:   [0, 5, null, 10] => [0, 1..5, null, 6..10]
        // Asc+Desc:                [0, 4, 8, 3, 0]  => [0, 0..4, 5..8, 0..3, 0] (use highest max below this max)
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
