<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Criterion;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * @covers \Schoolbox\Assessment\Rubric\RowSet\Group
 */
class Group extends TestCase
{
    /**
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Group::retrieveCriterionIds
     */
    public function testRetrieveCriterionIds(): void
    {
        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $group->getCriteria()
              ->add(new Criterion())
              ->add((new Criterion())->setId(9))
              ->add((new Criterion())->setId(8))
              ->add((new Criterion())->setId(7))
              ->add((new Criterion())->setId(5))
              ->add((new Criterion())->setId(6));
        $this->assertEquals([5, 6, 7, 8, 9,], $group->retrieveCriterionIds());
    }


    /**
     * Data provider for `testIsInUse`.
     *
     * @return iterable
     */
    public function getTestIsInUseData(): iterable
    {
        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        yield 'no criteria' => [$group, false];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $group->getCriteria()
              ->add((new Criterion())->setId(9));
        yield 'one criterion, none in use' => [$group, false];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $criterion = (new Criterion())->setId(9);
        $criterion->getUsage()
                  ->add((new Task())->setId(1));
        $group->getCriteria()->add($criterion);
        yield 'one criterion, one in use' => [$group, true];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $group->getCriteria()
              ->add((new Criterion())->setId(9))
              ->add((new Criterion())->setId(10))
              ->add((new Criterion())->setId(11));
        yield 'three criteria, none in use' => [$group, false];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $criterion = (new Criterion())->setId(9);
        $criterion->getUsage()
                  ->add((new Task())->setId(1));
        $group->getCriteria()
              ->add($criterion)
              ->add((new Criterion())->setId(10))
              ->add((new Criterion())->setId(11));
        yield 'three criteria, one in use' => [$group, true];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $criterion1 = (new Criterion())->setId(9);
        $criterion1->getUsage()
                   ->add((new Task())->setId(1));
        $criterion2 = (new Criterion())->setId(10);
        $criterion2->getUsage()
                   ->add((new Task())->setId(2));
        $criterion3 = (new Criterion())->setId(11);
        $criterion3->getUsage()
                   ->add((new Task())->setId(3));
        $group->getCriteria()
              ->add($criterion1)
              ->add($criterion2)
              ->add($criterion3);
        yield 'three criteria, three in use' => [$group, true];
    }


    /**
     * @dataProvider getTestIsInUseData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Group::isInUse
     *
     * @param \Schoolbox\Assessment\Rubric\RowSet\Group $group
     * @param bool                                      $expected
     */
    public function testIsInUse(\Schoolbox\Assessment\Rubric\RowSet\Group $group, bool $expected): void
    {
        $this->assertEquals($expected, $group->isInUse());
    }


    /**
     * Data provider for `testIsInUseExcluding`.
     *
     * @return iterable
     */
    public function getTestIsInUseExcludingData(): iterable
    {
        $task1 = (new Task())->setId(1);
        $task2 = (new Task())->setId(2);
        $task3 = (new Task())->setId(3);
        $task4 = (new Task())->setId(4);

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        yield 'no criteria' => [$group, $task1, false];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $group->getCriteria()
              ->add((new Criterion())->setId(9));
        yield 'one criterion, none in use' => [$group, $task1, false];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $criterion = (new Criterion())->setId(9);
        $criterion->getUsage()
                  ->add($task1);
        $group->getCriteria()->add($criterion);
        yield 'one criterion, one in use, match' => [$group, $task1, false];
        yield 'one criterion, one in use, no match' => [$group, $task2, true];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $group->getCriteria()
              ->add((new Criterion())->setId(9))
              ->add((new Criterion())->setId(10))
              ->add((new Criterion())->setId(11));
        yield 'three criteria, none in use' => [$group, $task1, false];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $criterion = (new Criterion())->setId(9);
        $criterion->getUsage()
                  ->add($task1);
        $group->getCriteria()
              ->add($criterion)
              ->add((new Criterion())->setId(10))
              ->add((new Criterion())->setId(11));
        yield 'three criteria, one in use, match' => [$group, $task1, false];
        yield 'three criteria, one in use, no match' => [$group, $task2, true];

        $group = (new \Schoolbox\Assessment\Rubric\RowSet\Group())->setId(3);
        $criterion1 = (new Criterion())->setId(9);
        $criterion1->getUsage()
                   ->add($task1);
        $criterion2 = (new Criterion())->setId(10);
        $criterion2->getUsage()
                   ->add($task2);
        $criterion3 = (new Criterion())->setId(11);
        $criterion3->getUsage()
                   ->add($task3);
        $group->getCriteria()
              ->add($criterion1)
              ->add($criterion2)
              ->add($criterion3);
        yield 'three criteria, three in use, match' => [$group, $task1, true];
        yield 'three criteria, three in use, no match' => [$group, $task4, true];
    }


    /**
     * @dataProvider getTestIsInUseExcludingData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Group::isInUseExcluding
     *
     * @param \Schoolbox\Assessment\Rubric\RowSet\Group $group
     * @param IAssessable                               $assessment
     * @param bool                                      $expected
     */
    public function testIsInUseExcluding(
        \Schoolbox\Assessment\Rubric\RowSet\Group $group,
        IAssessable $assessment,
        bool $expected
    ): void
    {
        $this->assertEquals($expected, $group->isInUseExcluding($assessment));
    }
}
