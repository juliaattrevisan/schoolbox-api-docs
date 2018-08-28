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
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * @covers \Schoolbox\Assessment\Rubric\Grid\Grid
 * @codeCoverageIgnore
 */
class Grid extends TestCase
{
    /**
     * Data provider for `testRetrieveGroupUsage`.
     *
     * @return iterable
     */
    public function getTestRetrieveGroupUsageData(): iterable
    {
        // Default rowset
        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
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

        $group1->getIndicators()[$criterion1->getId()] = $criterion1;
        $group1->getIndicators()[$criterion2->getId()] = $criterion2;
        $group1->getIndicators()[$criterion3->getId()] = $criterion3;

        $group2->getIndicators()[$criterion4->getId()] = $criterion4;
        $group2->getIndicators()[$criterion5->getId()] = $criterion5;
        $group2->getIndicators()[$criterion6->getId()] = $criterion6;

        $group3->getIndicators()[$criterion7->getId()] = $criterion7;
        $group3->getIndicators()[$criterion8->getId()] = $criterion8;
        $group3->getIndicators()[$criterion9->getId()] = $criterion9;

        $dueWork = new DueWork();
        $grid    = new RubricGrid\Grid($dueWork, $rowSet);

        // Group and criterion usage
        $groupUsage1                                             = (new RubricGrid\GroupUsage($group1))->setMaxValue(15);
        $criterionUsage1                                         = new RubricGrid\CriterionUsage($criterion1);
        $criterionUsage2                                         = new RubricGrid\CriterionUsage($criterion2);
        $groupUsage1->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage1->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;
        $grid->getGroupUsages()[$group1->getId()]                = $groupUsage1;

        $groupUsage2                                             = (new RubricGrid\GroupUsage($group2))->setMaxValue(15);
        $criterionUsage6                                         = new RubricGrid\CriterionUsage($criterion6);
        $groupUsage2->getCriterionUsages()[$criterion6->getId()] = $criterionUsage6;
        $grid->getGroupUsages()[$group2->getId()]                = $groupUsage2;

        yield 'group 1: in use' => [$grid, $group1, $groupUsage1];
        yield 'group 2: in use' => [$grid, $group2, $groupUsage2];
        yield 'group 3: not in use' => [$grid, $group3, null];
    }


    /**
     * @dataProvider getTestRetrieveGroupUsageData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::retrieveGroupUsage
     *
     * @param RubricGrid\Grid            $grid
     * @param RowSet\Capability          $group
     * @param null|RubricGrid\GroupUsage $expected
     */
    public function testRetrieveGroupUsage(
        RubricGrid\Grid $grid,
        RowSet\Capability $group,
        ?RubricGrid\GroupUsage $expected
    ): void {
        $this->assertSame($expected, $grid->retrieveGroupUsage($group));
    }


    /**
     * Data provider for `testRetrieveCriterionUsage`.
     *
     * @return iterable
     */
    public function getTestRetrieveCriterionUsageData(): iterable
    {
        // Default rowset
        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
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

        $group1->getIndicators()[$criterion1->getId()] = $criterion1;
        $group1->getIndicators()[$criterion2->getId()] = $criterion2;
        $group1->getIndicators()[$criterion3->getId()] = $criterion3;

        $group2->getIndicators()[$criterion4->getId()] = $criterion4;
        $group2->getIndicators()[$criterion5->getId()] = $criterion5;
        $group2->getIndicators()[$criterion6->getId()] = $criterion6;

        $group3->getIndicators()[$criterion7->getId()] = $criterion7;
        $group3->getIndicators()[$criterion8->getId()] = $criterion8;
        $group3->getIndicators()[$criterion9->getId()] = $criterion9;

        $dueWork = new DueWork();
        $grid    = new RubricGrid\Grid($dueWork, $rowSet);

        // Group and criterion usage
        $groupUsage1                                             = (new RubricGrid\GroupUsage($group1))->setMaxValue(15);
        $criterionUsage1                                         = new RubricGrid\CriterionUsage($criterion1);
        $criterionUsage2                                         = new RubricGrid\CriterionUsage($criterion2);
        $groupUsage1->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage1->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;
        $grid->getGroupUsages()[$group1->getId()]                = $groupUsage1;

        $groupUsage2                                             = (new RubricGrid\GroupUsage($group2))->setMaxValue(15);
        $criterionUsage6                                         = new RubricGrid\CriterionUsage($criterion6);
        $groupUsage2->getCriterionUsages()[$criterion6->getId()] = $criterionUsage6;
        $grid->getGroupUsages()[$group2->getId()]                = $groupUsage2;

        yield 'criterion 1.1: in use'     => [$grid, $criterion1, $criterionUsage1];
        yield 'criterion 1.2: in use'     => [$grid, $criterion2, $criterionUsage2];
        yield 'criterion 1.3: not in use' => [$grid, $criterion3, null];
        yield 'criterion 2.1: not in use' => [$grid, $criterion4, null];
        yield 'criterion 2.2: not in use' => [$grid, $criterion5, null];
        yield 'criterion 2.3: in use'     => [$grid, $criterion6, $criterionUsage6];
        yield 'criterion 3.1: not in use' => [$grid, $criterion7, null];
        yield 'criterion 3.2: not in use' => [$grid, $criterion8, null];
        yield 'criterion 3.3: not in use' => [$grid, $criterion9, null];
        yield 'criterion 4.1: not in use' => [$grid, $criterion0, null];
    }


    /**
     * @dataProvider getTestRetrieveCriterionUsageData
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::retrieveCriterionUsage
     *
     * @param RubricGrid\Grid                $grid
     * @param RowSet\Criterion               $criterion
     * @param null|RubricGrid\CriterionUsage $expected
     */
    public function testRetrieveCriterionUsage(
        RubricGrid\Grid $grid,
        RowSet\Criterion $criterion,
        ?RubricGrid\CriterionUsage $expected
    ): void {
        $this->assertSame($expected, $grid->retrieveCriterionUsage($criterion));
    }


    /**
     * Data provider for `testCalculateMaxValueFromGroups`
     *
     * @return iterable
     */
    public function getTestCalculateMaxValueDataFromGroups(): iterable
    {
        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $criterion1 = (new RowSet\Criterion())->setId(1)->setName('Criterion 1.1');
        $criterion2 = (new RowSet\Criterion())->setId(2)->setName('Criterion 1.2');
        $criterion3 = (new RowSet\Criterion())->setId(3)->setName('Criterion 1.3');
        $criterion4 = (new RowSet\Criterion())->setId(4)->setName('Criterion 2.1');
        $criterion5 = (new RowSet\Criterion())->setId(5)->setName('Criterion 2.1');
        $criterion6 = (new RowSet\Criterion())->setId(6)->setName('Criterion 2.2');
        $criterion7 = (new RowSet\Criterion())->setId(7)->setName('Criterion 3.3');
        $criterion8 = (new RowSet\Criterion())->setId(8)->setName('Criterion 3.2');
        $criterion9 = (new RowSet\Criterion())->setId(9)->setName('Criterion 3.3');

        $group1->getIndicators()[$criterion1->getId()] = $criterion1;
        $group1->getIndicators()[$criterion2->getId()] = $criterion2;
        $group1->getIndicators()[$criterion3->getId()] = $criterion3;

        $group2->getIndicators()[$criterion4->getId()] = $criterion4;
        $group2->getIndicators()[$criterion5->getId()] = $criterion5;
        $group2->getIndicators()[$criterion6->getId()] = $criterion6;

        $group3->getIndicators()[$criterion7->getId()] = $criterion7;
        $group3->getIndicators()[$criterion8->getId()] = $criterion8;
        $group3->getIndicators()[$criterion9->getId()] = $criterion9;

        $groupUsage1                                             = (new RubricGrid\GroupUsage($group1))->setMaxValue(15);
        $criterionUsage1                                         = new RubricGrid\CriterionUsage($criterion1);
        $criterionUsage2                                         = new RubricGrid\CriterionUsage($criterion2);
        $groupUsage1->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage1->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;

        $groupUsage2                                             = (new RubricGrid\GroupUsage($group2))->setMaxValue(3);
        $criterionUsage6                                         = new RubricGrid\CriterionUsage($criterion6);
        $groupUsage2->getCriterionUsages()[$criterion6->getId()] = $criterionUsage6;

        $dueWork = new DueWork();
        $grid1   = new RubricGrid\Grid($dueWork, $rowSet);

        yield 'empty grid' => [$grid1, 0];


        // Add descriptors
        $grid2 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid2->getGroupUsages()[$group1->getId()] = $groupUsage1;

        yield 'one group usage' => [$grid2, 15];


        $grid3 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid3->getGroupUsages()[$group1->getId()] = $groupUsage1;
        $grid3->getGroupUsages()[$group2->getId()] = $groupUsage2;

        yield 'two group usages' => [$grid3, 18];
    }


    /**
     * @dataProvider getTestCalculateMaxValueDataFromGroups
     * @covers       \Schoolbox\Assessment\Rubric\Grid\Grid::calculateMaxValueFromGroups
     *
     * @param RubricGrid\Grid $grid
     * @param float|null      $expected
     */
    public function testCalculateMaxValueFromGroups(RubricGrid\Grid $grid, ?float $expected): void
    {
        $this->assertSame($expected, $grid->calculateMaxValueFromGroups());
    }


    /**
     * Data provider for `testIterateGroups`.
     *
     * @return iterable
     */
    public function getTestIterateGroupsData(): iterable
    {
        // rowset has three groups
        // try grids with: none, one, all


        $rowSet     = new Rowset\RowSet(new Folder());
        $group1     = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $group2     = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $group3     = (new RowSet\Capability())->setId(3)->setName('Group 3');
        $criterion1 = (new RowSet\Criterion())->setId(1)->setName('Criterion 1.1');
        $criterion2 = (new RowSet\Criterion())->setId(2)->setName('Criterion 1.2');
        $criterion3 = (new RowSet\Criterion())->setId(3)->setName('Criterion 1.3');
        $criterion4 = (new RowSet\Criterion())->setId(4)->setName('Criterion 2.1');
        $criterion5 = (new RowSet\Criterion())->setId(5)->setName('Criterion 2.1');
        $criterion6 = (new RowSet\Criterion())->setId(6)->setName('Criterion 2.2');
        $criterion7 = (new RowSet\Criterion())->setId(7)->setName('Criterion 3.3');
        $criterion8 = (new RowSet\Criterion())->setId(8)->setName('Criterion 3.2');
        $criterion9 = (new RowSet\Criterion())->setId(9)->setName('Criterion 3.3');

        $group1->getIndicators()[$criterion1->getId()] = $criterion1;
        $group1->getIndicators()[$criterion2->getId()] = $criterion2;
        $group1->getIndicators()[$criterion3->getId()] = $criterion3;

        $group2->getIndicators()[$criterion4->getId()] = $criterion4;
        $group2->getIndicators()[$criterion5->getId()] = $criterion5;
        $group2->getIndicators()[$criterion6->getId()] = $criterion6;

        $group3->getIndicators()[$criterion7->getId()] = $criterion7;
        $group3->getIndicators()[$criterion8->getId()] = $criterion8;
        $group3->getIndicators()[$criterion9->getId()] = $criterion9;

        $rowSet->getCapabilities()[$group1->getId()] = $group1;
        $rowSet->getCapabilities()[$group2->getId()] = $group2;
        $rowSet->getCapabilities()[$group3->getId()] = $group3;


        $groupUsage1 = new RubricGrid\GroupUsage($group1);
        $groupUsage2 = new RubricGrid\GroupUsage($group2);
        $groupUsage3 = new RubricGrid\GroupUsage($group3);

        $dueWork = new DueWork();
        $grid1   = new RubricGrid\Grid($dueWork, $rowSet);

        yield 'No groups used' => [$grid1, [$group1, $group2, $group3]];


        $grid2 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid2->getGroupUsages()[$group2->getId()] = $groupUsage2;

        yield 'Group 2 used' => [$grid2, [$group2, $group1, $group3]];


        $grid3 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid3->getGroupUsages()[$group3->getId()] = $groupUsage3;
        $grid3->getGroupUsages()[$group2->getId()] = $groupUsage2;


        yield 'Group 3, 2 used' => [$grid3, [$group3, $group2, $group1]];


        $grid4 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid4->getGroupUsages()[$group1->getId()] = $groupUsage1;
        $grid4->getGroupUsages()[$group3->getId()] = $groupUsage3;
        $grid4->getGroupUsages()[$group2->getId()] = $groupUsage2;

        yield 'Group 1, 3, 2 used' => [$grid4, [$group1, $group3, $group2]];


        $grid5 = new RubricGrid\Grid($dueWork, $rowSet);
        $grid5->getGroupUsages()[$group3->getId()] = $groupUsage3;
        $grid5->getGroupUsages()[$group2->getId()] = $groupUsage2;
        $grid5->getGroupUsages()[$group1->getId()] = $groupUsage1;

        yield 'Group 3, 2, 1 used' => [$grid5, [$group3, $group2, $group1]];
    }


    /**
     * @dataProvider getTestIterateGroupsData
     * @covers \Schoolbox\Assessment\Rubric\Grid\Grid::iterateGroups
     *
     * @param RubricGrid\Grid $grid
     * @param array           $expected
     */
    public function testIterateGroups(RubricGrid\Grid $grid, array $expected): void
    {
        $actual = [];
        foreach ($grid->iterateGroups() as $group) {
            $actual[] = $group;
        }
        $this->assertSame($expected, $actual);
    }
}
