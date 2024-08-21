<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Test;

use PHPUnit\Framework\TestCase;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Component\SubmissionBox\MarkTypeMapper;
use Schoolbox\Assessment\Rubric\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid as RubricMarkedGrid;
use Schoolbox\Assessment\Rubric\RowSet;
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\Learning\MarkScheme\TotalMarkFractional;
use Schoolbox\Resource\Folder\Model as FolderModel;

#[\PHPUnit\Framework\Attributes\CoversClass(\Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid::class)]
class MarkedGrid extends TestCase
{
    /**
     * Data provider for various test methods.
     *
     * @return iterable
     */
    public function getTestRetrieveMarkData(): iterable
    {
        $markedGrid = $this->generateTestRetrieveMarkDataMarkedGrid(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_TOTAL));
        yield 'mark-per-capability on' => [
            $markedGrid,
            [
                'normalizedMark'    => 58.33,
                'scaledMark'        => 7.00,
                'humanReadableMark' => '7 / 12',
            ]
        ];

        $markedGrid = $this->generateTestRetrieveMarkDataMarkedGrid(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_NONE));
        // Indicators used: 4, max mark for each indicator: 8 => max mark = 32
        yield 'mark-per-capability off' => [
            $markedGrid,
            [
                'normalizedMark'    => 21.88,
                'scaledMark'        => 7.00,
                'humanReadableMark' => '7 / 32',
            ]
        ];

        // If marking per capability, and all cap marks are null:
        // the grid's marks are null
        $markedGrid = $this->generateTestRetrieveMarkDataMarkedGrid(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_TOTAL));
        $markedGrid->getCapabilityMarks()[1]->setValue(null);
        $markedGrid->getCapabilityMarks()[2]->setValue(null);
        yield 'mark-per-capability on, all capability marks null' => [
            $markedGrid,
            [
                'normalizedMark'    => null,
                'scaledMark'        => null,
                'humanReadableMark' => null,
            ]
        ];

        // If not marking per capability: explicitly cap marks are ignored?
        $markedGrid = $this->generateTestRetrieveMarkDataMarkedGrid(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_NONE));
        $markedGrid->getCapabilityMarks()[1]->setValue(null);
        $markedGrid->getCapabilityMarks()[2]->setValue(null);
        yield 'mark-per-capability off, all capability marks null' => [
            $markedGrid,
            [
                'normalizedMark'    => null,
                'scaledMark'        => null,
                'humanReadableMark' => null,
            ]
        ];

        // Test clearing capability marks and setting indicator marks instead
        $markedGrid = $this->generateTestRetrieveMarkDataMarkedGrid(MarkTypeMapper::getInstance()->createModel()->setId(MarkType::TYPE_NONE));
        $markedGrid->getCapabilityMarks()[1]->setValue(null);
        $markedGrid->getCapabilityMarks()[2]->setValue(null);

        // Add indicator marks as the test grid doesn't have them.
        // Each indicator mark always uses the last descriptor
        // (the one with the highest mark) for testing.
        foreach ($markedGrid->iterateCapabilityUsages() as $capabilityUsage) {
            $capabilityMark = $markedGrid->retrieveCapabilityMark($capabilityUsage);
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                /** @var Grid\Descriptor $selectedDescriptor */
                $selectedDescriptor = $indicatorUsage->getDescriptors()->last();
                $indicatorMark      = (new RubricMarkedGrid\IndicatorMark($indicatorUsage))
                    ->setDescriptor($selectedDescriptor)
                    ->setValue($selectedDescriptor->getMaxValue());
                $capabilityMark->getIndicatorMarks()[$indicatorUsage->getIndicator()->getId()] = $indicatorMark;
            }
        }

        yield 'mark-per-capability off, all capability marks null, indicator marks set to max' => [
            $markedGrid,
            [
                'normalizedMark'    => 100.0,
                'scaledMark'        => 32.0,
                'humanReadableMark' => '32 / 32',
            ]
        ];
    }


    /**
     * Data provider for the data provider: generates a marked grid.
     *
     * @param MarkType $capabilityMarkType Is mark-per-capability on?
     *                                     This affects the grid's total mark.
     * @return RubricMarkedGrid\MarkedGrid
     */
    private function generateTestRetrieveMarkDataMarkedGrid(MarkType $capabilityMarkType): RubricMarkedGrid\MarkedGrid
    {
        // TODO consider generator for this data set

        // Build the row set
        $folder = new FolderModel();
        $rowSet = new Rowset\RowSet($folder);

        // Build caps and indicators
        $capability1 = (new RowSet\Capability())->setId(1)->setName('Capability 1');
        $capability2 = (new RowSet\Capability())->setId(2)->setName('Capability 2');
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
        $dueWork = (new DueWork())->setCapabilityMarkType($capabilityMarkType);
        $grid    = new Grid\Grid($dueWork, $rowSet);

        // Build and add levels
        $level1                              = (new Grid\Level())->setId(1)->setName('0');
        $level2                              = (new Grid\Level())->setId(2)->setName('2');
        $level3                              = (new Grid\Level())->setId(3)->setName('4');
        $level4                              = (new Grid\Level())->setId(4)->setName('6');
        $level5                              = (new Grid\Level())->setId(5)->setName('8');
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
            $descriptor                                  = (new Grid\Descriptor())
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

        // Remember, the value is NOT scaled here
        $capabilityMark1->setValue(4);
        $capabilityMark2->setValue(3);

        $markedGrid->getCapabilityMarks()[$capability1->getId()] = $capabilityMark1;
        $markedGrid->getCapabilityMarks()[$capability2->getId()] = $capabilityMark2;
        return $markedGrid;
    }


    /**
     * @param RubricMarkedGrid\MarkedGrid $grid
     * @param array                       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveMarkData')]
    public function testRetrieveNormalizedMark(RubricMarkedGrid\MarkedGrid $grid, array $expected): void
    {
        $this->assertSame($expected['normalizedMark'], $grid->retrieveNormalizedMark());
    }


    /**
     * @param RubricMarkedGrid\MarkedGrid $grid
     * @param array                       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveMarkData')]
    public function testCalculateScaledMark(RubricMarkedGrid\MarkedGrid $grid, array $expected): void
    {
        $this->assertSame($expected['scaledMark'], $grid->calculateScaledMark());
    }


    /**
     * @param RubricMarkedGrid\MarkedGrid $grid
     * @param array                       $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('getTestRetrieveMarkData')]
    public function testRetrieveHumanReadableMark(RubricMarkedGrid\MarkedGrid $grid, array $expected): void
    {
        $this->assertSame($expected['humanReadableMark'], $grid->retrieveHumanReadableMark());
    }

    // TODO test iterateCapabilityUsages
    // TODO test retrieveCapabilityMark
    // TODO test retrieveIndicatorMark
}
