<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Rubric\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid
 */
class MarkedGrid extends TestCase
{
    // TODO figure out what methods are required, and implement tests for them


    /**
     * Data provider for `getTestRetrieveNormalizedMarkData`
     *
     * @return iterable
     */
    public function getTestRetrieveNormalizedMarkData(): iterable
    {
        // Build the row set
        $folder = new FolderModel();
        $rowSet = new Rowset\RowSet($folder);

        // Build caps and indicators
        $capability1 = (new RowSet\Capability())->setId(1)->setName('Group 1');
        $capability2 = (new RowSet\Capability())->setId(2)->setName('Group 2');
        $indicator1  = (new RowSet\Indicator())->setId(1)->setName('Indicator 1.1');
        $indicator2  = (new RowSet\Indicator())->setId(2)->setName('Indicator 1.2');
        $indicator3  = (new RowSet\Indicator())->setId(3)->setName('Indicator 2.1');
        $indicator4  = (new RowSet\Indicator())->setId(4)->setName('Indicator 2.2');

        // Add indicators to caps
        $capability1->getIndicators()[$indicator1->getId()] = $indicator1;
        $capability1->getIndicators()[$indicator2->getId()] = $indicator2;
        $capability2->getIndicators()[$indicator3->getId()] = $indicator3;
        $capability2->getIndicators()[$indicator4->getId()] = $indicator4;

        // Add caps to row set
        $rowSet->getCapabilities()[$capability1->getId()] = $capability1;
        $rowSet->getCapabilities()[$capability2->getId()] = $capability2;


        // Build a grid that uses the row set
        $dueWork = new DueWork();
        $grid    = new Grid\Grid($dueWork, $rowSet);

        // Build and add levels
        $level1 = (new Grid\Level())->setId(1)->setName('0');
        $level2 = (new Grid\Level())->setId(2)->setName('2');
        $level3 = (new Grid\Level())->setId(3)->setName('4');
        $level4 = (new Grid\Level())->setId(4)->setName('6');
        $level5 = (new Grid\Level())->setId(5)->setName('8');
        $grid->getLevels()[$level1->getId()] = $level1;
        $grid->getLevels()[$level2->getId()] = $level2;
        $grid->getLevels()[$level3->getId()] = $level3;
        $grid->getLevels()[$level4->getId()] = $level4;
        $grid->getLevels()[$level5->getId()] = $level5;

        // Build capability and indicator usage
        $capabilityUsage1 = (new Grid\CapabilityUsage($capability1))->setMaxValue(8);
        $capabilityUsage2 = (new Grid\CapabilityUsage($capability2))->setMaxValue(4);
        $indicatorUsage1  = new Grid\IndicatorUsage($indicator1);
        $indicatorUsage2  = new Grid\IndicatorUsage($indicator2);
        $indicatorUsage3  = new Grid\IndicatorUsage($indicator3);
        $indicatorUsage4  = new Grid\IndicatorUsage($indicator4);

        // Build descriptors, add to indicators
        $descriptorValue = 0;
        foreach ($grid->getLevels() as $levelId => $level) {
            $descriptor = (new Grid\Descriptor())
                ->setId($levelId)
                ->setDescription((string)$descriptorValue)
                ->setMinValue((float)$descriptorValue)
                ->setMaxValue((float)$descriptorValue);
            $indicatorUsage1->getDescriptors()[$levelId] = $descriptor;
            $indicatorUsage2->getDescriptors()[$levelId] = $descriptor;
            $indicatorUsage3->getDescriptors()[$levelId] = $descriptor;
            $indicatorUsage4->getDescriptors()[$levelId] = $descriptor;

            $descriptorValue += 2;
        }

        // Add cap and ind usage
        $capabilityUsage1->getIndicatorUsages()[$indicator1->getId()] = $indicatorUsage1;
        $capabilityUsage1->getIndicatorUsages()[$indicator2->getId()] = $indicatorUsage2;

        $capabilityUsage2->getIndicatorUsages()[$indicator3->getId()] = $indicatorUsage3;
        $capabilityUsage2->getIndicatorUsages()[$indicator4->getId()] = $indicatorUsage4;

        $grid->getCapabilityUsages()[$capability1->getId()] = $capabilityUsage1;
        $grid->getCapabilityUsages()[$capability2->getId()] = $capabilityUsage2;


        // Build a marked grid
        $feedback   = new DueWorkFeedback();
        $markScheme = new TotalMarkFractional($grid->calculateMaxValueFromCapabilities());
        $markedGrid = new RubricMarkedGrid\MarkedGrid($feedback, $grid, $markScheme);

        // Add cap and ind marks to it
        $capabilityMark1 = new RubricMarkedGrid\CapabilityMark(
            $capabilityUsage1,
            new TotalMarkFractional($capabilityUsage1->getMaxValue())
        );
        $capabilityMark2 = new RubricMarkedGrid\CapabilityMark(
            $capabilityUsage2,
            new TotalMarkFractional($capabilityUsage2->getMaxValue())
        );

//        $indicatorMark1 = (new RubricMarkedGrid\IndicatorMark($indicatorUsage1))->setValue(7);
//        $indicatorMark2 = (new RubricMarkedGrid\IndicatorMark($indicatorUsage2))->setValue(8);
//        $indicatorMark3 = (new RubricMarkedGrid\IndicatorMark($indicatorUsage2))->setValue(1);
//        $indicatorMark4 = (new RubricMarkedGrid\IndicatorMark($indicatorUsage2))->setValue(2);

        // Remember, the value is NOT scaled here
        $capabilityMark1->setValue(15);
        $capabilityMark2->setValue(3);
//        $capabilityMark1->getIndicatorMarks()[$indicator1->getId()] = $indicatorMark1;
//        $capabilityMark1->getIndicatorMarks()[$indicator2->getId()] = $indicatorMark2;
//        $capabilityMark2->getIndicatorMarks()[$indicator3->getId()] = $indicatorMark3;
//        $capabilityMark2->getIndicatorMarks()[$indicator4->getId()] = $indicatorMark4;

        $markedGrid->getCapabilityMarks()[$capability1->getId()] = $capabilityMark1;
        $markedGrid->getCapabilityMarks()[$capability2->getId()] = $capabilityMark2;

        // Value should be: (15/16) * (8/12) + (3/16) * (4/12) => .625 + .0625 => .6875
        yield [$markedGrid, 68.75];
    }


    /**
     * @dataProvider getTestRetrieveNormalizedMarkData
     * @covers \Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid::retrieveNormalizedMark
     *
     * @param RubricMarkedGrid\MarkedGrid $grid
     * @param float                       $expected
     */
    public function testRetrieveNormalizedMark(RubricMarkedGrid\MarkedGrid $grid, float $expected): void
    {
        $this->assertSame($expected, $grid->retrieveNormalizedMark());
    }
}
