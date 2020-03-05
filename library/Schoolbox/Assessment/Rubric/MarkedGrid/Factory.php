<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;

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
                    $markSchemeFactory->spawnForCapability(
                        $markedGrid->getGrid()->getAssessment(),
                        $capabilityUsage->calculateMaxValueFromIndicators()
                    )
                );
                $markedGrid->getCapabilityMarks()->add($capabilityMark);
            }


            // Set its value
            $capabilityId        = $capabilityUsage->getCapability()->getId();

            // If mark per cap is off, this will be empty as no capability data gets posted
            if ($postCapabilities) {
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
                    $capabilityUnscaledMark = null;
                    if (!$isSelfReflection) {
                        // If the mark is passed as an empty string:
                        // treat that as a null cap mark, not zero
                        $capabilityUnscaledMark = $postCapabilities[$capabilityId]['unscaledMark'] ?? '';
                        if ($capabilityUnscaledMark === '') {
                            $capabilityUnscaledMark = null;
                        } else {
                            $capabilityUnscaledMark = (float)$capabilityUnscaledMark;
                        }
                    }
                    $capabilityMark->setValue($capabilityUnscaledMark);
                } else {
                    throw new \InvalidArgumentException("Capability mark must be between 0 and " . $capabilityMark->getCapabilityUsage()->getMaxValue());
                }
            } else {
                // If mark per cap is off, set capability mark to null; the model knows how to handle this.
                $capabilityMark->setValue(null);
            }

            // Set marks for indicators
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicatorMarkValue = null;
                $selectedDescriptor = null;
                foreach ($indicatorUsage->getDescriptors() as $descriptor) {
                    $descriptorId = $descriptor->getId();

                    // Find which descriptor was actually selected
                    $isDescriptorSelected = !empty($postDescriptors[$descriptorId]['selected']);
                    if ($isDescriptorSelected) {
                        $indicatorMarkValue = $postDescriptors[$descriptorId]['value'] ?? $descriptor->getMaxValue();
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
                    $indicatorMarkSpec = new ValidIndicatorMark($selectedDescriptor);
                    if ($indicatorMarkSpec->isSatisfiedBy($indicatorMarkValue)) {
                        $indicatorMark->setValue(
                            filter_var(
                                $indicatorMarkValue,
                                FILTER_VALIDATE_FLOAT,
                                FILTER_NULL_ON_FAILURE
                            )
                        );
                    } else {
                        if ($selectedDescriptor && $selectedDescriptor->hasValue()) {
                            $message = sprintf(
                                "Indicator mark must be between %s and %s",
                                $selectedDescriptor->getMinValue(),
                                $selectedDescriptor->getMaxValue()
                            );
                        } else {
                            $message = "Indicator mark must be null";
                        }
                        throw new \InvalidArgumentException($message);
                    }
                }
            }
        }

        return $markedGrid;
    }
}
