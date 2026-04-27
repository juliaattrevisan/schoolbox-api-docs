<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\Grid\Translator\ModelToArray;

use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\Grid\Level;
use Schoolbox\Assessment\Rubric\RowSet\Capability;
use Schoolbox\Assessment\Rubric\RowSet\Indicator;

/**
 * Translator for converting a Rubric Grid into an array representation,
 * for outputting in a JSON response.
 *
 * Specifically only contains used capabilities/indicators/descriptors,
 * it's intended for the rubric viewer / feedback views
 *
 * @see /library/Schoolbox/Assessment/Rubric/templates/vue/types.ts
 */
class Base
{
    /**
     * @param Grid $rubricGrid
     * @return array
     */
    public function __invoke(Grid $rubricGrid): array
    {
        $capabilities = [];
        foreach ($rubricGrid->iterateCapabilities() as $capability) {
            $capabilities[] = $this->translateCapability($rubricGrid, $capability);
        }

        return [
            'id' => $rubricGrid->getId(),
            'title' => '(backend unimplemented)', // TODO: rubric title, string (not null)
            'weight' => null, // TODO: rubric weight, number|null
            'capabilityMarkType' => $rubricGrid->getAssessment()->getCapabilityMarkType()?->getSlug(), // TODO: from the rubric, not assessment
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
     * @param Capability $capability
     * @return array
     */
    public function translateCapability(Grid $rubricGrid, Capability $capability): array
    {
        $indicators = [];
        foreach ($rubricGrid->iterateIndicators($capability) as $indicator) {
            $indicators[] = $this->translateIndicator($rubricGrid, $indicator);
        }

        return [
            'id' => $capability->getId(),
            'name' => $capability->getName(),
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
     * @param Indicator $indicator
     * @return array
     */
    public function translateIndicator(Grid $rubricGrid, Indicator $indicator): array
    {
        $indicatorUsage = $rubricGrid->retrieveIndicatorUsage($indicator);

        $descriptors = [];
        foreach ($rubricGrid->getLevels() as $levelIndex => $level) {
            $descriptor = $indicatorUsage?->retrieveDescriptor($levelIndex);

            $descriptors[] = $descriptor === null ? null : [
                'id' => $descriptor->getId(),
                'description' => $descriptor->getDescription(),
            ];
        }

        return [
            'id' => $indicator->getId(),
            'name' => $indicator->getName(),
            'descriptors' => $descriptors,
        ];
    }
}
