<?php

/**
 * @author    Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Rubric\MarkedGrid\Translator;

use Schoolbox\Assessment\Rubric\Grid\CapabilityUsage;
use Schoolbox\Assessment\Rubric\Grid\IndicatorUsage;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;

/**
 * Translator for converting a Marked Rubric Grid into an array representation,
 * for outputting in a JSON response.
 *
 * @see /library/Schoolbox/Assessment/Rubric/templates/vue/types.ts
 */
class ModelToArray
{
    // TODO: make sure that the backend is responsible hiding marks to users that shouldn't see them, rather than the frontend hiding them...

    /**
     * @param MarkedGrid $markedGrid
     * @return array
     */
    public function __invoke(MarkedGrid $markedGrid): array
    {
        $capabilities = [];
        foreach ($markedGrid->iterateCapabilityUsages() as $capabilityUsage) {
            $capabilities[] = $this->translateCapabilityUsage($markedGrid, $capabilityUsage);
        }

        return [
            'gridId' => $markedGrid->getGrid()->getId(),
            // TODO: for self assessment, "only show the mark if it's self graded, as if it's self-reflect only they never marked themselves"
            // ^^^^ {% if selfAssessmentFeedback and selfAssessmentFeedback.isSelfGrading and selfAssessmentFeedback.humanReadableMark %}
            'humanReadableMark' => $markedGrid->retrieveHumanReadableMark(),
            'capabilities' => $capabilities,
        ];
    }

    /**
     * Translate a given capability on a rubric grid into an array, including its indicators and their descriptors.
     *
     * @param MarkedGrid $markedGrid
     * @param CapabilityUsage $capabilityUsage
     * @return array
     */
    private function translateCapabilityUsage(MarkedGrid $markedGrid, CapabilityUsage $capabilityUsage): array
    {
        $indicators = [];
        foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
            $indicators[] = $this->translateIndicatorUsage($markedGrid, $indicatorUsage);
        }

        $capabilityMark = $markedGrid->retrieveCapabilityMark($capabilityUsage);

        // TODO: (from twig) related to self-assessment feedback
        // /** @var DueWorkFeedback */
        // $feedback = $markedGrid->getFeedback();
        // $isSelfGrading = $feedback instanceof DueWorkFeedback && $feedback->isSelfGrading();
        // if (!$isSelfGrading) {
        //     $capabilityMark = null; // hide it, I guess?
        // }

        return [
            'id' => $capabilityUsage->getCapability()->getId(),
            // TODO: permissions, notably self assessment differs
            'humanReadableMark' => $markedGrid->retrieveCapabilityMark($capabilityUsage)?->getHumanReadableValue(),
            'indicators' => $indicators,
        ];
    }

    /**
     * Translate a given indicator on a rubric grid into an array, including its descriptors
     *
     * Missing descriptors (i.e. levels which don't have a descriptor for this indicator) will be filled in
     * with empty data, as the index must be preserved to link levels to descriptors in the frontend.
     *
     * @param MarkedGrid $markedGrid
     * @param IndicatorUsage $indicatorUsage
     * @return array
     */
    private function translateIndicatorUsage(MarkedGrid $markedGrid, IndicatorUsage $indicatorUsage): array
    {
        $indicatorMark = $markedGrid->retrieveIndicatorMark($indicatorUsage);

        // TODO: permissions, notably self assessment differs
        $isMarkDisplayed = true; // TODO: $grid->getAssessment()->isFeedbackVisibleToUser(VIEWER);
        if (!$isMarkDisplayed || $indicatorMark === null) {
            return [
                'id' => $indicatorUsage->getIndicator()->getId(),
                'humanReadableMark' => null,
                'descriptorId' => null,
            ];
        }

        return [
            'id' => $indicatorUsage->getIndicator()->getId(),
            'humanReadableMark' => $indicatorMark->getValue(),
            'descriptorId' => $indicatorMark->getDescriptor()?->getId(),
        ];
    }
}
