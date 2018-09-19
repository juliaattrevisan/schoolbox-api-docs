<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

/**
 * Factory for building `MarkedGrid` objects.
 */
class Factory
{
    /**
     * Build a rubric's marked grid from POST data.
     *
     * @param MarkedGrid $markedGrid A grid (which already contains an assessment and rowset)
     * @param array      $postData
     * @return MarkedGrid
     */
    public function buildFromPostData(MarkedGrid $markedGrid, array $postData): MarkedGrid
    {
        // TODO mark schemes still need to be in the grid, not marked grid
        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $postDescriptors  = $postData['descriptors'] ?? [];
        $postCapabilities = $postData['groups']      ?? [];


        // Set marks for all the capabilities and indicators
        foreach ($markedGrid->getGrid()->getCapabilityUsages() as $capabilityUsage) {
            // Add the cap mark if it wasn't in the marked grid already
            $capabilityMark = $markedGrid->retrieveCapabilityMark($capabilityUsage);
            if (!$capabilityMark) {
                $capabilityMark = new CapabilityMark(
                    $capabilityUsage,
                    $markSchemeFactory->spawnMarkSchemeFor(
                        $markedGrid->getGrid()->getAssessment(),
                        $capabilityUsage->calculateMaxValueFromIndicators()
                    )
                );
                $markedGrid->getCapabilityMarks()->add($capabilityMark);
            }


            // Set its value
            // TODO validate the value is within the cap's max mark bounds
            $capabilityId = $capabilityUsage->getCapability()->getId();
            $capabilityMark->setValue(
                filter_var(
                    $postCapabilities[$capabilityId]['unscaledMark'] ?? null,
                    FILTER_VALIDATE_FLOAT,
                    FILTER_NULL_ON_FAILURE
                )
            );


            // Set marks for indicators
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $descriptorMark     = null;
                $selectedDescriptor = null;
                foreach ($indicatorUsage->getDescriptors() as $descriptor) {
                    $descriptorId = $descriptor->getId();

                    // Find which descriptor was actually selected
                    $isDescriptorSelected = !empty($postDescriptors[$descriptorId]['selected']);
                    if ($isDescriptorSelected) {
                        // TODO validate descriptor mark is between min/max?
                        $descriptorMark     = $postDescriptors[$descriptorId]['value'] ?? $descriptor->getMaxValue();
                        $selectedDescriptor = $descriptor;
                        break;
                    }
                }

                // Add the indicator mark, and set its descriptor/value
                $indicatorMark = $markedGrid->retrieveIndicatorMark($indicatorUsage);
                if (!$indicatorMark) {
                    $indicatorMark = new IndicatorMark($indicatorUsage);
                    $capabilityMark->getIndicatorMarks()->add($indicatorMark);
                }

                $indicatorMark
                    ->setDescriptor($selectedDescriptor)
                    ->setValue(filter_var($descriptorMark, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE));
            }
        }

        return $markedGrid;
    }
}
