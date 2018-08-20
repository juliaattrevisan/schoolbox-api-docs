<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid\CriterionUsage;
use Schoolbox\Assessment\Rubric\Grid\GroupUsage;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Resource\Folder\Model as Folder;

/**
 * @covers \Schoolbox\Assessment\Rubric\Grid\Grid
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

        $dueWork = new DueWork();
        $grid    = new \Schoolbox\Assessment\Rubric\Grid\Grid($dueWork, $rowSet);

        // Group and criterion usage
        $groupUsage1                                             = (new GroupUsage($group1))->setMaxValue(15);
        $criterionUsage1                                         = new CriterionUsage($criterion1);
        $criterionUsage2                                         = new CriterionUsage($criterion2);
        $groupUsage1->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage1->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;
        $grid->getGroupUsages()[$group1->getId()]                = $groupUsage1;

        $groupUsage2                                             = (new GroupUsage($group1))->setMaxValue(15);
        $criterionUsage6                                         = new CriterionUsage($criterion6);
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
     * @param \Schoolbox\Assessment\Rubric\Grid\Grid $grid
     * @param RowSet\Group                           $group
     * @param null|GroupUsage                        $expected
     */
    public function testRetrieveGroupUsage(
        \Schoolbox\Assessment\Rubric\Grid\Grid $grid,
        RowSet\Group $group,
        ?GroupUsage $expected
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

        $dueWork = new DueWork();
        $grid    = new \Schoolbox\Assessment\Rubric\Grid\Grid($dueWork, $rowSet);

        // Group and criterion usage
        $groupUsage1                                             = (new GroupUsage($group1))->setMaxValue(15);
        $criterionUsage1                                         = new CriterionUsage($criterion1);
        $criterionUsage2                                         = new CriterionUsage($criterion2);
        $groupUsage1->getCriterionUsages()[$criterion1->getId()] = $criterionUsage1;
        $groupUsage1->getCriterionUsages()[$criterion2->getId()] = $criterionUsage2;
        $grid->getGroupUsages()[$group1->getId()]                = $groupUsage1;

        $groupUsage2                                             = (new GroupUsage($group1))->setMaxValue(15);
        $criterionUsage6                                         = new CriterionUsage($criterion6);
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
     * @param \Schoolbox\Assessment\Rubric\Grid\Grid $grid
     * @param RowSet\Criterion                       $criterion
     * @param null|CriterionUsage                    $expected
     */
    public function testRetrieveCriterionUsage(
        \Schoolbox\Assessment\Rubric\Grid\Grid $grid,
        RowSet\Criterion $criterion,
        ?CriterionUsage $expected
    ): void {
        $this->assertSame($expected, $grid->retrieveCriterionUsage($criterion));
    }


    public function testIterateGroups(): void
    {
        // TODO implement
    }


    public function testIterateGroupsInUseOnly(): void
    {
        // TODO implement
    }


    // Things to consider:
    // iterator for the entire rowset
    // iterator for only the groups used by the grid
    // Both should return in the same format
}
