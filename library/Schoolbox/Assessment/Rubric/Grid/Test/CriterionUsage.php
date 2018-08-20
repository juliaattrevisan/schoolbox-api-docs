<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid as RubricGrid;

/**
 * @covers \Schoolbox\Assessment\Rubric\Grid\CriterionUsage
 */
class CriterionUsage extends TestCase
{
    /**
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
        $criterionUsage->applyDescriptorMinValues();
        $descriptor = $criterionUsage->retrieveDescriptor($level);
        $this->assertNotNull($descriptor);
        $this->assertSame($expected, $descriptor->getMinValue());
    }
}
