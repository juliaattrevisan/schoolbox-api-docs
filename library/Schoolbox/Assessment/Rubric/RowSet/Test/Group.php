<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\Capability as RubricGroup;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * @covers \Schoolbox\Assessment\Rubric\RowSet\Capability
 */
class Group extends TestCase
{
    /**
     * Data provider for `testRetrieveCriterionIds`.
     *
     * @return iterable
     */
    public function getTestRetrieveCriterionIdsData(): iterable
    {
        $group = new RubricGroup();
        yield 'no criteria' => [$group, []];

        $group = (new RubricGroup())->setId(3);
        $group->getIndicators()
              ->add(new Indicator())
              ->add((new Indicator())->setId(9))
              ->add((new Indicator())->setId(8))
              ->add((new Indicator())->setId(7))
              ->add((new Indicator())->setId(5))
              ->add((new Indicator())->setId(6));
        yield 'multiple criteria' => [$group, [5, 6, 7, 8, 9,]];
    }


    /**
     * @dataProvider getTestRetrieveCriterionIdsData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Capability::retrieveIndicatorIds
     *
     * @param RubricGroup $group
     * @param array       $expected
     */
    public function testRetrieveCriterionIds(RubricGroup $group, array $expected): void
    {
        $this->assertEquals($expected, $group->retrieveIndicatorIds());
    }


    /**
     * Data provider for `testIsInUse`.
     *
     * @return iterable
     */
    public function getTestIsInUseData(): iterable
    {
        $group = (new RubricGroup())->setId(3);
        yield 'no criteria' => [$group, false];

        $group = (new RubricGroup())->setId(3);
        $group->getIndicators()
              ->add((new Indicator())->setId(9));
        yield 'one criterion, none in use' => [$group, false];

        $group = (new RubricGroup())->setId(3);
        $criterion = (new Indicator())->setId(9);
        $criterion->getUsage()
                  ->add((new Task())->setId(1));
        $group->getIndicators()->add($criterion);
        yield 'one criterion, one in use' => [$group, true];

        $group = (new RubricGroup())->setId(3);
        $group->getIndicators()
              ->add((new Indicator())->setId(9))
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three criteria, none in use' => [$group, false];

        $group = (new RubricGroup())->setId(3);
        $criterion = (new Indicator())->setId(9);
        $criterion->getUsage()
                  ->add((new Task())->setId(1));
        $group->getIndicators()
              ->add($criterion)
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three criteria, one in use' => [$group, true];

        $group = (new RubricGroup())->setId(3);
        $criterion1 = (new Indicator())->setId(9);
        $criterion1->getUsage()
                   ->add((new Task())->setId(1));
        $criterion2 = (new Indicator())->setId(10);
        $criterion2->getUsage()
                   ->add((new Task())->setId(2));
        $criterion3 = (new Indicator())->setId(11);
        $criterion3->getUsage()
                   ->add((new Task())->setId(3));
        $group->getIndicators()
              ->add($criterion1)
              ->add($criterion2)
              ->add($criterion3);
        yield 'three criteria, three in use' => [$group, true];
    }


    /**
     * @dataProvider getTestIsInUseData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Capability::isInUse
     *
     * @param RubricGroup $group
     * @param bool        $expected
     */
    public function testIsInUse(RubricGroup $group, bool $expected): void
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

        $group = (new RubricGroup())->setId(3);
        yield 'no criteria' => [$group, $task1, false];

        $group = (new RubricGroup())->setId(3);
        $group->getIndicators()
              ->add((new Indicator())->setId(9));
        yield 'one criterion, none in use' => [$group, $task1, false];

        $group = (new RubricGroup())->setId(3);
        $criterion = (new Indicator())->setId(9);
        $criterion->getUsage()
                  ->add($task1);
        $group->getIndicators()->add($criterion);
        yield 'one criterion, one in use, match' => [$group, $task1, false];
        yield 'one criterion, one in use, no match' => [$group, $task2, true];

        $group = (new RubricGroup())->setId(3);
        $group->getIndicators()
              ->add((new Indicator())->setId(9))
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three criteria, none in use' => [$group, $task1, false];

        $group = (new RubricGroup())->setId(3);
        $criterion = (new Indicator())->setId(9);
        $criterion->getUsage()
                  ->add($task1);
        $group->getIndicators()
              ->add($criterion)
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three criteria, one in use, match' => [$group, $task1, false];
        yield 'three criteria, one in use, no match' => [$group, $task2, true];

        $group = (new RubricGroup())->setId(3);
        $criterion1 = (new Indicator())->setId(9);
        $criterion1->getUsage()
                   ->add($task1);
        $criterion2 = (new Indicator())->setId(10);
        $criterion2->getUsage()
                   ->add($task2);
        $criterion3 = (new Indicator())->setId(11);
        $criterion3->getUsage()
                   ->add($task3);
        $group->getIndicators()
              ->add($criterion1)
              ->add($criterion2)
              ->add($criterion3);
        yield 'three criteria, three in use, match' => [$group, $task1, true];
        yield 'three criteria, three in use, no match' => [$group, $task4, true];
    }


    /**
     * @dataProvider getTestIsInUseExcludingData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Capability::isInUseExcluding
     *
     * @param RubricGroup $group
     * @param IAssessable $assessment
     * @param bool        $expected
     */
    public function testIsInUseExcluding(
        RubricGroup $group,
        IAssessable $assessment,
        bool $expected
    ): void
    {
        $this->assertEquals($expected, $group->isInUseExcluding($assessment));
    }
}
