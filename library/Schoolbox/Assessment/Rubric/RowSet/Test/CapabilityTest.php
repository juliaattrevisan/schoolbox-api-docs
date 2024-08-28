<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\RowSet\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;
use Schoolbox\Assessment\Rubric\RowSet\Capability as RubricCapability;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Task\Model as Task;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\RowSet\Capability::class)]
class CapabilityTest extends TestCase
{
    /**
     * Data provider for `testRetrieveIndicatorIds`.
     *
     * @return iterable
     */
    public static function getTestRetrieveIndicatorIdsData(): iterable
    {
        $capability = new RubricCapability();
        yield 'no indicators' => [$capability, []];

        $capability = (new RubricCapability())->setId(3);
        $capability->getIndicators()
              ->add(new Indicator())
              ->add((new Indicator())->setId(9))
              ->add((new Indicator())->setId(8))
              ->add((new Indicator())->setId(7))
              ->add((new Indicator())->setId(5))
              ->add((new Indicator())->setId(6));
        yield 'multiple indicators' => [$capability, [5, 6, 7, 8, 9,]];
    }


    /**
     * @param RubricCapability $capability
     * @param array            $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveIndicatorIdsData')]
    public function testRetrieveIndicatorIds(RubricCapability $capability, array $expected): void
    {
        $this->assertEquals($expected, $capability->retrieveIndicatorIds());
    }


    /**
     * Data provider for `testIsInUse`.
     *
     * @return iterable
     */
    public static function getTestIsInUseData(): iterable
    {
        $capability = (new RubricCapability())->setId(3);
        yield 'no indicators' => [$capability, false];

        $capability = (new RubricCapability())->setId(3);
        $capability->getIndicators()
              ->add((new Indicator())->setId(9));
        yield 'one indicator, none in use' => [$capability, false];

        $capability = (new RubricCapability())->setId(3);
        $indicator = (new Indicator())->setId(9);
        $indicator->getUsage()
                  ->add((new Task())->setId(1));
        $capability->getIndicators()->add($indicator);
        yield 'one indicator, one in use' => [$capability, true];

        $capability = (new RubricCapability())->setId(3);
        $capability->getIndicators()
              ->add((new Indicator())->setId(9))
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three indicators, none in use' => [$capability, false];

        $capability = (new RubricCapability())->setId(3);
        $indicator = (new Indicator())->setId(9);
        $indicator->getUsage()
                  ->add((new Task())->setId(1));
        $capability->getIndicators()
              ->add($indicator)
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three indicators, one in use' => [$capability, true];

        $capability = (new RubricCapability())->setId(3);
        $indicator1 = (new Indicator())->setId(9);
        $indicator1->getUsage()
                   ->add((new Task())->setId(1));
        $indicator2 = (new Indicator())->setId(10);
        $indicator2->getUsage()
                   ->add((new Task())->setId(2));
        $indicator3 = (new Indicator())->setId(11);
        $indicator3->getUsage()
                   ->add((new Task())->setId(3));
        $capability->getIndicators()
              ->add($indicator1)
              ->add($indicator2)
              ->add($indicator3);
        yield 'three indicators, three in use' => [$capability, true];
    }


    /**
     * @param RubricCapability $capability
     * @param bool             $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsInUseData')]
    public function testIsInUse(RubricCapability $capability, bool $expected): void
    {
        $this->assertEquals($expected, $capability->isInUse());
    }


    /**
     * Data provider for `testIsInUseExcluding`.
     *
     * @return iterable
     */
    public static function getTestIsInUseExcludingData(): iterable
    {
        $task1 = (new Task())->setId(1);
        $task2 = (new Task())->setId(2);
        $task3 = (new Task())->setId(3);
        $task4 = (new Task())->setId(4);

        $capability = (new RubricCapability())->setId(3);
        yield 'no indicators' => [$capability, $task1, false];

        $capability = (new RubricCapability())->setId(3);
        $capability->getIndicators()
              ->add((new Indicator())->setId(9));
        yield 'one indicator, none in use' => [$capability, $task1, false];

        $capability = (new RubricCapability())->setId(3);
        $indicator = (new Indicator())->setId(9);
        $indicator->getUsage()
                  ->add($task1);
        $capability->getIndicators()->add($indicator);
        yield 'one indicator, one in use, match' => [$capability, $task1, false];
        yield 'one indicator, one in use, no match' => [$capability, $task2, true];

        $capability = (new RubricCapability())->setId(3);
        $capability->getIndicators()
              ->add((new Indicator())->setId(9))
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three indicators, none in use' => [$capability, $task1, false];

        $capability = (new RubricCapability())->setId(3);
        $indicator = (new Indicator())->setId(9);
        $indicator->getUsage()
                  ->add($task1);
        $capability->getIndicators()
              ->add($indicator)
              ->add((new Indicator())->setId(10))
              ->add((new Indicator())->setId(11));
        yield 'three indicators, one in use, match' => [$capability, $task1, false];
        yield 'three indicators, one in use, no match' => [$capability, $task2, true];

        $capability = (new RubricCapability())->setId(3);
        $indicator1 = (new Indicator())->setId(9);
        $indicator1->getUsage()
                   ->add($task1);
        $indicator2 = (new Indicator())->setId(10);
        $indicator2->getUsage()
                   ->add($task2);
        $indicator3 = (new Indicator())->setId(11);
        $indicator3->getUsage()
                   ->add($task3);
        $capability->getIndicators()
              ->add($indicator1)
              ->add($indicator2)
              ->add($indicator3);
        yield 'three indicators, three in use, match' => [$capability, $task1, true];
        yield 'three indicators, three in use, no match' => [$capability, $task4, true];
    }


    /**
     *
     * @param RubricCapability $capability
     * @param IAssessable      $assessment
     * @param bool             $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestIsInUseExcludingData')]
    public function testIsInUseExcluding(
        RubricCapability $capability,
        IAssessable $assessment,
        bool $expected
    ): void {
        $this->assertEquals($expected, $capability->isInUseExcluding($assessment));
    }
}
