<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Translator\ModelToArray;

use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\Assessment\Rubric\Grid\Level;

/**
 * Translator for converting a Rubric Grid into an array representation,
 * for outputting in a JSON response.
 *
 * Specifically only contains used capabilities/indicators/descriptors,
 * it's intended for the rubric viewer / feedback views
 *
 * @see /library/Schoolbox/Assessment/Rubric/templates/vue/rubricMarkable/types.ts
 */
class Base
{
    /**
     * @param bool $showPoints the value of config.rubricShowPoints
     */
    public function __construct(
        private bool $showPoints,
    ) {
    }

    /**
     * @param Grid $rubricGrid
     * @return array
     */
    public function __invoke(Grid $rubricGrid): array
    {
        $capabilities = [];
        foreach ($rubricGrid->getCapabilityUsages() as $capabilityUsage) {
            $capabilities[] = $this->translateCapabilityUsage($rubricGrid, $capabilityUsage);
        }

        return [
            'id' => $rubricGrid->getId(),
            'title' => '(backend unimplemented)', // TODO: rubric title, string (not null)
            'weight' => null, // TODO: rubric weight, number|null
            'derivedMaxValue' => $rubricGrid->calculateMaxValueFromCapabilities(), // TODO: rubric-markable only, and check if it matters for perms
            'capabilityMarkScheme' => [
                // TODO: this array format has been used at least 4 times now... (here, grid-markable.twig, and course/class markbooks)
                'template' => $rubricGrid->getCapabilityMarkScheme()->getTemplate(),
                'context' => $rubricGrid->getCapabilityMarkScheme()->generateContext(),
            ],
            'levels' => $rubricGrid->getLevels()->map(fn(Level $level) => [
                'id' => $level->getId(),
                'name' => $level->getName(),
            ])->reindex(),
            'capabilities' => $capabilities,
        ];
    }

    /**
     * Translate a given capability on a rubric grid into an array, including its indicators and their descriptors.
     *
     * @param Grid $rubricGrid
     * @param CapabilityUsage $capabilityUsage
     * @return array
     */
    public function translateCapabilityUsage(Grid $rubricGrid, CapabilityUsage $capabilityUsage): array
    {
        $capability = $capabilityUsage->getCapability();

        $indicators = [];
        foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
            $indicators[] = $this->translateIndicatorUsage($rubricGrid, $indicatorUsage);
        }

        return [
            'id' => $capability->getId(),
            'name' => $capability->getName(),
            'maxValue' => $capabilityUsage->getMaxValue(), // TODO: rubric-markable only, and check if it matters for perms
            'derivedMaxValue' => $capabilityUsage->calculateMaxValueFromIndicators(), // TODO: rubric-markable only, and check if it matters for perms
            'showCapabilityMark' => $rubricGrid->isCapabilityMarkShown($capability), // TODO: rubric-markable only, and check if it matters for perms
            'indicators' => $indicators,
        ];
    }

    /**
     * Translate a given indicator on a rubric grid into an array, including its descriptors
     *
     * Missing descriptors (i.e. levels which don't have a descriptor for this indicator) will be filled in
     * with empty data, as the index must be preserved to link levels to descriptors in the frontend.
     *
     * @param Grid $rubricGrid
     * @param IndicatorUsage $indicatorUsage
     * @return array
     */
    public function translateIndicatorUsage(Grid $rubricGrid, IndicatorUsage $indicatorUsage): array
    {
        $indicator = $indicatorUsage->getIndicator();

        $descriptors = [];
        foreach ($rubricGrid->getLevels() as $levelIndex => $level) {
            $descriptor = $indicatorUsage->retrieveDescriptor($levelIndex);

            if ($descriptor === null) {
                // descriptors are always indexed by labelIndex, must preserve them even if they don't exist
                $descriptors[] = null;
                continue;
            }

            // include value info only if the viewer has permission to see it depending on various configs
            $value = null;
            if (
                $descriptor->hasValue() &&
                $rubricGrid->showDescriptorValue() &&
                $this->showPoints
            ) {
                $value = $rubricGrid->isUseMarkRange() && $descriptor->hasRange()
                    ? [ 'left' => $descriptor->retrieveLeftValue(), 'right' => $descriptor->retrieveRightValue() ]
                    : [ 'max' => $descriptor->getMaxValue() ];
            }

            $descriptors[] = [
                'id' => $descriptor->getId(),
                'description' => $descriptor->getDescription(),
                'value' => $value,
            ];
        }

        return [
            'id' => $indicator->getId(),
            'name' => $indicator->getName(),
            'descriptors' => $descriptors,
        ];
    }
}
