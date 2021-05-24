<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Indicator as RubricIndicator;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;

/**
 * @covers \Schoolbox\Assessment\Rubric\RowSet\Indicator
 */
class Indicator extends TestCase
{
    /**
     * Data provider for `testIsInUse`.
     *
     * @return iterable
     */
    public function getTestIsInUseData(): iterable
    {
        $indicator = (new RubricIndicator())->setId(3);
        yield 'none in use' => [$indicator, false];

        $indicator = (new RubricIndicator())->setId(3);
        $indicator->getUsage()
                  ->add((new Task())->setId(1));
        yield 'one in use' => [$indicator, true];
    }


    /**
     * @dataProvider getTestIsInUseData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Indicator::isInUse
     *
     * @param RubricIndicator $indicator
     * @param bool            $expected
     */
    public function testIsInUse(RubricIndicator $indicator, bool $expected): void
    {
        $this->assertEquals($expected, $indicator->isInUse());
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

        $indicator = (new RubricIndicator())->setId(3);
        yield 'none in use' => [$indicator, $task1, false];

        $indicator = (new RubricIndicator())->setId(3);
        $indicator->getUsage()
                  ->add($task1);
        yield 'one in use, match' => [$indicator, $task1, false];
        yield 'one in use, no match' => [$indicator, $task2, true];

        $indicator = (new RubricIndicator())->setId(3);
        $indicator->getUsage()
                  ->add($task1)
                  ->add($task2)
                  ->add($task3);
        yield 'three in use, match' => [$indicator, $task1, true];
        yield 'three in use, no match' => [$indicator, $task4, true];
    }


    /**
     * @dataProvider getTestIsInUseExcludingData
     * @covers \Schoolbox\Assessment\Rubric\RowSet\Indicator::isInUseExcluding
     *
     * @param RubricIndicator $indicator
     * @param IAssessable     $assessment
     * @param bool            $expected
     */
    public function testIsInUseExcluding(
        RubricIndicator $indicator,
        IAssessable $assessment,
        bool $expected
    ): void {
        $this->assertEquals($expected, $indicator->isInUseExcluding($assessment));
    }
}
