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
 * @covers \Schoolbox\Assessment\Rubric\Grid\GroupUsage
 * @codeCoverageIgnore
 */
class GroupUsage extends TestCase
{
    /**
     * Data provider for `testRetrieveCriterionUsage`
     *
     * @return iterable
     */
    public function getTestRetrieveCriterionUsageData(): iterable
    {
        // Default rowset
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $criterion1 = (new RowSet\Indicator())->setId(1)->setName('Criterion 1.1');
        $criterion2 = (new RowSet\Indicator())->setId(2)->setName('Criterion 1.2');
        $criterion3 = (new RowSet\Indicator())->setId(3)->setName('Criterion 1.3');
        $criterion4 = (new RowSet\Indicator())->setId(4)->setName('Criterion 2.1');
        $criterion5 = (new RowSet\Indicator())->setId(5)->setName('Criterion 2.1');
        $criterion6 = (new RowSet\Indicator())->setId(6)->setName('Criterion 2.2');
        $criterion7 = (new RowSet\Indicator())->setId(7)->setName('Criterion 3.3');
        $criterion8 = (new RowSet\Indicator())->setId(8)->setName('Criterion 3.2');
        $criterion9 = (new RowSet\Indicator())->setId(9)->setName('Criterion 3.3');
        $criterion0 = (new RowSet\Indicator())->setId(10)->setName('Criterion 4.1');

        $group1->getIndicators()[$criterion1->getId()] = $criterion1;
        $group1->getIndicators()[$criterion2->getId()] = $criterion2;
        $group1->getIndicators()[$criterion3->getId()] = $criterion3;

        $group2->getIndicators()[$criterion4->getId()] = $criterion4;
        $group2->getIndicators()[$criterion5->getId()] = $criterion5;
        $group2->getIndicators()[$criterion6->getId()] = $criterion6;

        $group3->getIndicators()[$criterion7->getId()] = $criterion7;
        $group3->getIndicators()[$criterion8->getId()] = $criterion8;
        $group3->getIndicators()[$criterion9->getId()] = $criterion9;


        // Group and criterion usage
        $groupUsage1                                             = (new RubricGrid\GroupUsage($group1))->setMaxValue(15);
        $criterionUsage1                                         = new RubricGrid\CriterionUsage($criterion1);
        $criterionUsage2                                         = new RubricGrid\CriterionUsage($criterion2);
        $groupUsage1->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage1->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;

        $groupUsage2                                             = (new RubricGrid\GroupUsage($group1))->setMaxValue(15);
        $criterionUsage6                                         = new RubricGrid\CriterionUsage($criterion6);
        $groupUsage2->getCriterionUsages()[$criterion6->getId()] = $criterionUsage6;

        yield 'group usage 1, criterion 1: in use'            => [$groupUsage1, $criterion1, $criterionUsage1];
        yield 'group usage 1, criterion 2: in use'            => [$groupUsage1, $criterion2, $criterionUsage2];
        yield 'group usage 1, criterion 3: not in use'        => [$groupUsage1, $criterion3, null];
        yield 'group usage 2, criterion 4: not in use'        => [$groupUsage2, $criterion4, null];
        yield 'group usage 2, criterion 5: not in use'        => [$groupUsage2, $criterion5, null];
        yield 'group usage 2, criterion 6: in use'            => [$groupUsage2, $criterion6, $criterionUsage6];
        yield 'group usage 1, criterion 6: not in this group' => [$groupUsage1, $criterion6, null];
    }


    /**
     * @dataProvider getTestRetrieveCriterionUsageData
     * @covers \Schoolbox\Assessment\Rubric\Grid\GroupUsage::retrieveCriterionUsage
     *
     * @param RubricGrid\GroupUsage          $groupUsage
     * @param RowSet\Indicator               $criterion
     * @param null|RubricGrid\CriterionUsage $expected
     */
    public function testRetrieveCriterionUsage(
        RubricGrid\GroupUsage $groupUsage,
        RowSet\Indicator $criterion,
        ?RubricGrid\CriterionUsage $expected
    ): void
    {
        $this->assertSame($expected, $groupUsage->retrieveCriterionUsage($criterion));
    }


    /**
     * Data provider for `testIterateCriteria`
     *
     * @return iterable
     */
    public function getTestIterateCriteriaData(): iterable
    {
        // Default rowset
        $group      = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $criterion1 = (new RowSet\Indicator())->setId(1)->setName('Criterion 1.1');
        $criterion2 = (new RowSet\Indicator())->setId(2)->setName('Criterion 1.2');
        $criterion3 = (new RowSet\Indicator())->setId(3)->setName('Criterion 1.3');

        $group->getIndicators()[$criterion1->getId()] = $criterion1;
        $group->getIndicators()[$criterion2->getId()] = $criterion2;
        $group->getIndicators()[$criterion3->getId()] = $criterion3;


        $groupUsage1 = new RubricGrid\GroupUsage($group);

        yield 'No groups used' => [$groupUsage1, [$criterion1, $criterion2, $criterion3]];


        $groupUsage2 = new RubricGrid\GroupUsage($group);
        $groupUsage2->getCriterionUsages()[$criterion1->getId()] = new RubricGrid\CriterionUsage($criterion1);

        yield 'Group 1 used' => [$groupUsage2, [$criterion1, $criterion2, $criterion3]];


        $groupUsage3 = new RubricGrid\GroupUsage($group);
        $groupUsage3->getCriterionUsages()[$criterion3->getId()] = new RubricGrid\CriterionUsage($criterion3);

        yield 'Group 3 used' => [$groupUsage3, [$criterion3, $criterion1, $criterion2]];


        $groupUsage4 = new RubricGrid\GroupUsage($group);
        $groupUsage4->getCriterionUsages()[$criterion3->getId()] = new RubricGrid\CriterionUsage($criterion3);
        $groupUsage4->getCriterionUsages()[$criterion2->getId()] = new RubricGrid\CriterionUsage($criterion2);

        yield 'Group 3, 2 used' => [$groupUsage4, [$criterion3, $criterion2, $criterion1]];


        $groupUsage5 = new RubricGrid\GroupUsage($group);
        $groupUsage5->getCriterionUsages()[$criterion1->getId()] = new RubricGrid\CriterionUsage($criterion1);
        $groupUsage5->getCriterionUsages()[$criterion3->getId()] = new RubricGrid\CriterionUsage($criterion3);
        $groupUsage5->getCriterionUsages()[$criterion2->getId()] = new RubricGrid\CriterionUsage($criterion2);

        yield 'Group 1, 3, 2 used' => [$groupUsage5, [$criterion1, $criterion3, $criterion2]];
    }


    /**
     * @dataProvider getTestIterateCriteriaData
     * @covers \Schoolbox\Assessment\Rubric\Grid\GroupUsage::iterateCriteria
     *
     * @param RubricGrid\GroupUsage $groupUsage
     * @param array                 $expected
     */
    public function testIterateCriteria(RubricGrid\GroupUsage $groupUsage, array $expected): void
    {
        $actual = [];
        foreach ($groupUsage->iterateCriteria() as $criterion) {
            $actual[] = $criterion;
        }
        $this->assertSame($expected, $actual);
    }


    /**
     * Data provider for `testCalculateMaxValueFromCriteria`.
     *
     * @return iterable
     */
    public function getTestCalculateMaxValueFromCriteriaData(): iterable
    {
        $group      = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $criterion1 = (new RowSet\Indicator())->setId(1)->setName('Criterion 1.1');
        $criterion2 = (new RowSet\Indicator())->setId(2)->setName('Criterion 1.2');
        $criterion3 = (new RowSet\Indicator())->setId(3)->setName('Criterion 1.3');
        $criterion4 = (new RowSet\Indicator())->setId(4)->setName('Criterion 1.4');

        $group->getIndicators()[$criterion1->getId()] = $criterion1;
        $group->getIndicators()[$criterion2->getId()] = $criterion2;
        $group->getIndicators()[$criterion3->getId()] = $criterion3;
        $group->getIndicators()[$criterion4->getId()] = $criterion4;

        // Criterion usage 1: max is 7
        $criterionUsage1 = new RubricGrid\CriterionUsage($criterion1);
        $criterionUsage1->getDescriptors()[0] = new RubricGrid\Descriptor();
        $criterionUsage1->getDescriptors()[1] = (new RubricGrid\Descriptor())->setMaxValue(3);
        $criterionUsage1->getDescriptors()[2] = (new RubricGrid\Descriptor())->setMaxValue(5);
        $criterionUsage1->getDescriptors()[3] = (new RubricGrid\Descriptor())->setMaxValue(7);

        // Criterion usage 2: max is 0 (no descriptors with marks)
        $criterionUsage2 = new RubricGrid\CriterionUsage($criterion2);
        $criterionUsage2->getDescriptors()[1] = new RubricGrid\Descriptor();
        $criterionUsage2->getDescriptors()[2] = new RubricGrid\Descriptor();

        // Criterion usage 2: max is 8
        $criterionUsage3 = new RubricGrid\CriterionUsage($criterion3);
        $criterionUsage3->getDescriptors()[1] = (new RubricGrid\Descriptor())->setMaxValue(8);
        $criterionUsage3->getDescriptors()[2] = (new RubricGrid\Descriptor())->setMaxValue(5);
        $criterionUsage3->getDescriptors()[3] = (new RubricGrid\Descriptor())->setMaxValue(2);

        // Criterion usage 4: max is 0 (no descriptors)
        $criterionUsage4 = new RubricGrid\CriterionUsage($criterion4);


        $groupUsage1 = new RubricGrid\GroupUsage($group);

        yield 'No criteria' => [$groupUsage1, 0];


        $groupUsage2 = new RubricGrid\GroupUsage($group);
        $groupUsage2->getCriterionUsages()[$criterion4->getId()] = $criterionUsage4;

        yield 'No criteria with descriptors' => [$groupUsage2, 0];


        $groupUsage3 = new RubricGrid\GroupUsage($group);
        $groupUsage3->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;

        yield 'No criteria with descriptors with marks' => [$groupUsage3, 0];


        $groupUsage4 = new RubricGrid\GroupUsage($group);
        $groupUsage4->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage4->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;
        $groupUsage4->getCriterionUsages()[$criterion3->getId()] = $criterionUsage3;
        $groupUsage4->getCriterionUsages()[$criterion4->getId()] = $criterionUsage4;

        yield 'Descriptors with marks' => [$groupUsage4, 15];
    }


    /**
     * @dataProvider getTestCalculateMaxValueFromCriteriaData
     * @covers \Schoolbox\Assessment\Rubric\Grid\GroupUsage::calculateMaxValueFromCriteria
     *
     * @param RubricGrid\GroupUsage $groupUsage
     * @param float                 $expected
     */
    public function testCalculateMaxValueFromCriteria(RubricGrid\GroupUsage $groupUsage, float $expected): void
    {
        $this->assertSame($expected, $groupUsage->calculateMaxValueFromCriteria());
    }
}
