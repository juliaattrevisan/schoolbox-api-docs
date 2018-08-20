<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Criterion as RubricCriterion;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * @covers \Schoolbox\Assessment\Rubric\RowSet\Criterion
 */
class Criterion extends TestCase
{
    /**
     * Data provider for `testIsInUse`.
     *
     * @return iterable
     */
    public function getTestIsInUseData(): iterable
    {
        $criterion = (new RubricCriterion())->setId(3);
        yield 'none in use' => [$criterion, false];

        $criterion = (new RubricCriterion())->setId(3);
        $criterion->getUsage()
                  ->add((new Task())->setId(1));
        yield 'one in use' => [$criterion, true];
    }


    /**
     * @dataProvider getTestIsInUseData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Criterion::isInUse
     *
     * @param RubricCriterion $criterion
     * @param bool            $expected
     */
    public function testIsInUse(RubricCriterion $criterion, bool $expected): void
    {
        $this->assertEquals($expected, $criterion->isInUse());
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

        $criterion = (new RubricCriterion())->setId(3);
        yield 'none in use' => [$criterion, $task1, false];

        $criterion = (new RubricCriterion())->setId(3);
        $criterion->getUsage()
                  ->add($task1);
        yield 'one in use, match' => [$criterion, $task1, false];
        yield 'one in use, no match' => [$criterion, $task2, true];

        $criterion = (new RubricCriterion())->setId(3);
        $criterion->getUsage()
                  ->add($task1)
                  ->add($task2)
                  ->add($task3);
        yield 'three in use, match' => [$criterion, $task1, true];
        yield 'three in use, no match' => [$criterion, $task4, true];
    }


    /**
     * @dataProvider getTestIsInUseExcludingData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Criterion::isInUseExcluding
     *
     * @param RubricCriterion $criterion
     * @param IAssessable     $assessment
     * @param bool            $expected
     */
    public function testIsInUseExcluding(
        RubricCriterion $criterion,
        IAssessable $assessment,
        bool $expected
    ): void
    {
        $this->assertEquals($expected, $criterion->isInUseExcluding($assessment));
    }
}
