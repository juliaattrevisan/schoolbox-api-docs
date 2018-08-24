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
        $group1     = (new RowSet\Group())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Group())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Group())->setId(3)->setName('Group 3');
        $criterion1 = (new RowSet\Criterion())->setId(1)->setName('Criterion 1.1');
        $criterion2 = (new RowSet\Criterion())->setId(2)->setName('Criterion 1.2');
        $criterion3 = (new RowSet\Criterion())->setId(3)->setName('Criterion 1.3');
        $criterion4 = (new RowSet\Criterion())->setId(4)->setName('Criterion 2.1');
        $criterion5 = (new RowSet\Criterion())->setId(5)->setName('Criterion 2.1');
        $criterion6 = (new RowSet\Criterion())->setId(6)->setName('Criterion 2.2');
        $criterion7 = (new RowSet\Criterion())->setId(7)->setName('Criterion 3.3');
        $criterion8 = (new RowSet\Criterion())->setId(8)->setName('Criterion 3.2');
        $criterion9 = (new RowSet\Criterion())->setId(9)->setName('Criterion 3.3');
        $criterion0 = (new RowSet\Criterion())->setId(10)->setName('Criterion 4.1');

        $group1->getCriteria()[$criterion1->getId()] = $criterion1;
        $group1->getCriteria()[$criterion2->getId()] = $criterion2;
        $group1->getCriteria()[$criterion3->getId()] = $criterion3;

        $group2->getCriteria()[$criterion4->getId()] = $criterion4;
        $group2->getCriteria()[$criterion5->getId()] = $criterion5;
        $group2->getCriteria()[$criterion6->getId()] = $criterion6;

        $group3->getCriteria()[$criterion7->getId()] = $criterion7;
        $group3->getCriteria()[$criterion8->getId()] = $criterion8;
        $group3->getCriteria()[$criterion9->getId()] = $criterion9;


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
     * @param RowSet\Criterion         $criterion
     * @param null|RubricGrid\CriterionUsage $expected
     */
    public function testRetrieveCriterionUsage(
        RubricGrid\GroupUsage $groupUsage,
        RowSet\Criterion $criterion,
        ?RubricGrid\CriterionUsage $expected
    ): void
    {
        $this->assertSame($expected, $groupUsage->retrieveCriterionUsage($criterion));
    }
}
