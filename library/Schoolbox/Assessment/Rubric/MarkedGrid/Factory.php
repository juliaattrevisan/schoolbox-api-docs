<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Assessment\Rubric\MarkedGrid\ValidCapabilityMark;

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
     * @throws \InvalidArgumentException if a bad capability mark is found
     */
    public function buildFromPostData(MarkedGrid $markedGrid, array $postData): MarkedGrid
    {
        // TODO mark schemes still need to be in the grid, not marked grid
        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();
        $postDescriptors   = $postData['descriptors'] ?? [];
        $postCapabilities  = $postData['groups']      ?? [];


        // Is this a self-reflection?
        // Don't even save capability/indicator mark values
        $feedback         = $markedGrid->getFeedback();
        $isSelfReflection = $feedback instanceof DueWorkFeedback && $feedback->isSelfReflectionOnly();


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
            $capabilityId        = $capabilityUsage->getCapability()->getId();

            // This is the literal string the user entered into the capability mark input field
            // (or its percentage bracket equivalent, if letter grade)
            $capabilityCustomMarkInput = $isSelfReflection
                ? null
                : $postCapabilities[$capabilityId]['customMark'];

            $capabilityCustomMarkInput = $capabilityCustomMarkInput ?: null;

            // Validate the input string first
            $capMarkSpec = new ValidCapabilityMark($capabilityUsage->getMaxValue(), $capabilityMark->getMarkScheme());
            if ($capMarkSpec->isSatisfiedBy($capabilityCustomMarkInput)) {
                // Set the capability mark normalised to a value out of the total descriptor points for the capability
                $capabilityUnscaledMark = $isSelfReflection
                    ? null
                    : floatval($postCapabilities[$capabilityId]['unscaledMark']);

                $capabilityMark->setValue($capabilityUnscaledMark);
            } else {
                throw new \InvalidArgumentException("Capability mark must be between 0 and " . $capabilityMark->getCapabilityUsage()->getMaxValue());
            }

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

                $indicatorMark->setDescriptor($selectedDescriptor);
                if ($isSelfReflection) {
                    $indicatorMark->setValue(null);
                } else {
                    $indicatorMarkValue = filter_var($descriptorMark, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
                    $indicatorMarkSpec  = new ValidIndicatorMark($selectedDescriptor);
                    if ($indicatorMarkSpec->isSatisfiedBy($indicatorMarkValue)) {
                        $indicatorMark->setValue($indicatorMarkValue);
                    } else {
                        throw new \InvalidArgumentException("Capability mark must be between 0 and " . $capabilityMark->getCapabilityUsage()->getMaxValue());
                    }
                }
            }
        }

        return $markedGrid;
    }
}
