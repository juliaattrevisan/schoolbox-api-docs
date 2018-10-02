<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid\Test\Generator as GridGenerator;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet\Test\Generator as RowSetGenerator;

/**
 * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark
 */
class CapabilityMark extends TestCase
{
    /**
     * Data provider for `testCalculateScaledMark`.
     *
     * @return iterable
     */
    public function getTestCalculateScaledMark(): iterable
    {
        $generator        = new Generator();
        $capabilities     = RowSetGenerator::TEST_CAPABILITIES;
        $levels           = GridGenerator::TEST_LEVELS;
        $capabilityUsages = GridGenerator::TEST_CAPABILITY_USAGES;
        $capabilityMarks  = Generator::TEST_CAPABILITY_MARKS;


        // No scaling on cap marks for this grid
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $capabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (unscaled)' => [$markedGrid->getCapabilityMarks()[1], 8];
        yield 'Capability mark 2 (unscaled)' => [$markedGrid->getCapabilityMarks()[2], 6];


        // The same grid, with scaling factors applied
        $scaledCapabilityUsages = $capabilityUsages;
        $scaledCapabilityUsages[1]['maxValue'] = 8;
        $scaledCapabilityUsages[2]['maxValue'] = 32;
        $markedGrid = $generator->generateMarkedGrid(
            $capabilities,
            $levels,
            $scaledCapabilityUsages,
            $capabilityMarks
        );
        yield 'Capability mark 1 (scaled 0.5x)' => [$markedGrid->getCapabilityMarks()[1], 4];
        yield 'Capability mark 2 (scaled 2.0x)' => [$markedGrid->getCapabilityMarks()[2], 12];
    }


    /**
     * @dataProvider getTestCalculateScaledMark
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\CapabilityMark::calculateScaledMark
     *
     * @param RubricMarkedGrid\CapabilityMark $capabilityMark
     * @param float                           $expected
     */
    public function testCalculateScaledMark(RubricMarkedGrid\CapabilityMark $capabilityMark, float $expected): void
    {
        $this->assertSame($expected, $capabilityMark->calculateScaledMark());
    }


    // Testable methods:
    // calculateScaledMark
    // calculateIndicatorMarkSum
    // retrieveNormalizedMark
    // retrieveHumanReadableMark
    // iterateIndicatorUsages
    // retrieveIndicatorMark
}
