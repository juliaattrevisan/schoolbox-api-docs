<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Exporter;

use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;

/**
 * Exporter for converting a rubric grid to an array.
 */
class ArrayExporter
{
    /**
     * Exports this rubric grid as an array.
     *
     * Whether the export has mark-per-capability or use mark range switched on
     * depends on whether the grid's assessment has these fields switched on.
     *
     * @param Grid $grid
     * @return array
     */
    public function export(Grid $grid): array
    {
        // start by generating a rectangle which is (w+2 x 2h+1)
        $width  = $grid->getLevels()->count();
        $height = 0;
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            $height += $capabilityUsage->getIndicatorUsages()->count();
        }

        // rows contain columns
        $square = array_fill(0, (2 * $height) + 1, array_fill(0, $width + 2, ''));


        // Capability Mark Type: slug, then grade group if applicable
        // Use Mark Range: On/Off
        // TODO markPerCapability should be localized, as should the input
        $capabilityMarkType   = null;
        $capabilityGradeGroup = null;
        $usingMarkRange       = false;
        $assessment           = $grid->getAssessment();

        if ($assessment instanceof DueWork) {
            $capabilityMarkType   = $assessment->isMarkPerCapability() ? $assessment->getCapabilityMarkType()->getSlug() : MarkType::SLUG_NONE;
            $capabilityGradeGroup = $assessment->getCapabilityGradeGroup() ? $assessment->getCapabilityGradeGroup()->getId() : null;
            $usingMarkRange       = $assessment->getUseMarkRange();
        }

        if ($capabilityMarkType === MarkType::SLUG_LETTER && isset($capabilityGradeGroup)) {
            $square[0][0] = 'capabilityMarkType: ' . $capabilityMarkType . ':' . $capabilityGradeGroup;
        } else {
            $square[0][0] = 'capabilityMarkType: ' . $capabilityMarkType;
        }
        $square[0][1] = 'useMarkRange: ' . ($usingMarkRange ? 'On' : 'Off');


        // first row is level names
        $levelColumn = 0;
        foreach ($grid->getLevels() as $level) {
            $square[0][$levelColumn + 2] = $level->getName();
            $levelColumn++;
        }


        // Subsequent two-row sets represent a capability/indicator:
        // Row 1:
        // * col 1: capability name
        // * col 2: indicator name
        // * col x: descriptor name
        // Row 2:
        // * col 1: capability max points (if mark-per-cap is on)
        // * col 2: <empty>
        // * col x: descriptor points
        $currentDRow = 1;
        foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
            $groupRendered = false;
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $square[$currentDRow][0] = $groupRendered ? '' : $capabilityUsage->getCapability()->getName();
                $square[$currentDRow][1] = $indicatorUsage->getIndicator()->getName();

                $currentVRow = $currentDRow + 1;

                // Max points per capability (if enabled)
                if ($assessment->isMarkPerCapability() && !$groupRendered) {
                    $square[$currentVRow][0] = $capabilityUsage->getMaxValue();
                }

                $groupRendered = true;

                $levelColumn = 0;
                foreach ($grid->getLevels() as $level) {
                    $descriptor = $indicatorUsage->retrieveDescriptor($levelColumn);
                    if ($descriptor) {
                        $square[$currentDRow][$levelColumn + 2] = $descriptor->getDescription();
                        $square[$currentVRow][$levelColumn + 2] = $descriptor->getMaxValue();
                    }
                    $levelColumn++;
                }

                $currentDRow += 2;
            }
        }

        return $square;
    }
}
