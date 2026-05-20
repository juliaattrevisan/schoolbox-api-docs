<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid;

use Schoolbox\Learning\Assessment\ISelfAssessableFeedback;

/**
 * Factory for building `MarkedGrid` objects.
 */
class Factory
{
    /**
     * Build a rubric's marked grid from POST data.
     *
     * @param MarkedGrid $markedGrid A grid (which already contains an assessment and rowset)
     * @param array $postData An array corresponding to the construction of a particular grid
     * @return MarkedGrid
     * @throws \InvalidArgumentException if a bad capability mark is found
     */
    public function buildFromPostData(MarkedGrid $markedGrid, array $postData): MarkedGrid
    {
        /* $postData is the stuff in a particular grid, like this:

            Array
            (
                [humanReadableMark] => 30.77%
                [normalisedMark] => 30.77
                [capabilities] => Array
                    (
                        [19] => Array
                            (
                                [humanReadableMark] => 100%
                                [value] => 100
                                [scaledMark] => 4
                            )

                        [20] => Array
                            (
                                [humanReadableMark] => 50%
                                [value] => 50
                                [scaledMark] => 4
                            )

                        [24] => Array
                            (
                                [humanReadableMark] =>
                                [value] =>
                                [scaledMark] =>
                            )

                    )

                [indicators] => Array
                    (
                        [37] => Array
                            (
                                [value] => 4
                                [descriptorId] => 211
                            )

                        [38] => Array
                            (
                                [value] => 4
                                [descriptorId] => 212
                            )

                        [39] => Array
                            (
                                [value] =>
                                [descriptorId] =>
                            )

                        [44] => Array
                            (
                                [value] =>
                                [descriptorId] =>
                            )

                        [45] => Array
                            (
                                [value] =>
                                [descriptorId] =>
                            )

                        [46] => Array
                            (
                                [value] =>
                                [descriptorId] =>
                            )

                        [47] => Array
                            (
                                [value] =>
                                [descriptorId] =>
                            )

                    )
            )
         */

        /* This is the state of the rubric grid as it was posted to us. Each grid's selections are contained in one big
         * json-encoded grid payload - we have to decode it so we can find out what was selected
         *
         * Anecdotally, it turns out that "scaledMark" means the value of the descriptor that was chosen, converted by
         * whatever the Capability Mark Type requires. For example, a chosen descriptor will be represented as a
         * percentage of the maximum descriptor value if Percentage was chosen as a capablity mark type.
         */

        // TODO mark schemes still need to be in the grid, not marked grid
        $markSchemeFactory = new \Schoolbox\Learning\MarkScheme\Factory();

        // Is this a self-reflection?
        // Don't even save capability/indicator mark values
        $feedback         = $markedGrid->getFeedback();
        $isSelfReflection = $feedback instanceof ISelfAssessableFeedback && $feedback->isSelfReflectionOnly();

        // Set marks for all the capabilities and indicators
        foreach ($markedGrid->getGrid()->getCapabilityUsages() as $capabilityUsage) {
            $capabilityId = $capabilityUsage->getCapability()->getId();

            // Add the cap mark if it wasn't in the marked grid already
            $capabilityMark = $markedGrid->retrieveCapabilityMark($capabilityUsage);

            if (!$capabilityMark) {
                $capabilityMark = new CapabilityMark(
                    $capabilityUsage,
                    $markSchemeFactory->spawnForCapability(
                        $markedGrid->getGrid()->getAssessment(),
                        $capabilityUsage->getMaxValue(),
                    )
                );

                $markedGrid->getCapabilityMarks()->add($capabilityMark);
            }

            /* The Capability Mark initial state is null until otherwise determined, I guess, from the post payload
             * that is sent back per grid. At this point a $capabilityMark is presumed to always exist, since it was
             * either retrieved from the $markedGrid or spawned anew and added to $markedGrid.
             */
            $capabilityMark
                ->setHumanReadableValue(null)
                ->setValue(null);

            /* Self reflection feedbacks have no utilisation of capability marks.
             */
            if (!$isSelfReflection) {
                /* Check the post payload for the capability mark type values.
                 */
                $capabilityMarkHumanReadable = $postData['capabilities'][$capabilityId]['humanReadableMark'] ?? null;

                /* This seems unused because there's a backend converter that reverse-engineers a numeric value from the
                 * human-readable input someone provided via the ui
                 */
                //$capabilityMarkValue = $post['capabilities'][$capabilityId]['value'] ?? null;

                /* Check whether the capability mark's human readable string is a valid input; if it is, assign its
                 * values to the $capabilityMark instance.
                 */
                if ((new ValidCapabilityMark($capabilityMark->getMarkScheme()))->isSatisfiedBy($capabilityMarkHumanReadable)) {
                    $capabilityMark
                        ->setHumanReadableValue($capabilityMark->convertFormInputToHumanReadableValue($capabilityMarkHumanReadable))
                        ->setValue($capabilityMark->convertFormInputToValue($capabilityMarkHumanReadable));
                } else {
                    throw new \InvalidArgumentException("Capability mark must be between 0 and " . $capabilityMark->getCapabilityUsage()->getMaxValue());
                }
            }

            // Set marks for indicators
            foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                $indicatorId = $indicatorUsage->getIndicator()->getId();

                $indicatorMarkValue = null;
                $selectedDescriptor = null;

                foreach ($indicatorUsage->getDescriptors() as $descriptor) {
                    $descriptorId = (int)$descriptor->getId();

                    // Find which descriptor was actually selected
                    $isDescriptorSelected = (int)$postData['indicators'][$indicatorId]['descriptorId'] == $descriptorId;

                    if ($isDescriptorSelected) {
                        $indicatorMarkValue = $postData['indicators'][$indicatorId]['value'] ?? $descriptor->getMaxValue();
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
