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
use Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use Schoolbox\Learning\Assessment\ISelfAssessableFeedback;
use Schoolbox\User;

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
     * @param User $viewer the user viewing this marked grid, for permissions purposes
     * @param bool $showPoints the value of config.rubricShowPoints
     */
    public function __construct(
        private User $viewer,
        private bool $showPoints,
    ) {
    }

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

        // for a self-assessment, only show the overall mark if it's self graded, as if it's self-reflect only they never marked themselves
        // for a non self-assessment, the mark is shown
        $feedback = $markedGrid->getFeedback();
        $canSeeMark = !($feedback instanceof ISelfAssessableFeedback) || ($feedback instanceof DueWorkFeedback && $feedback->isSelfGrading());

        return [
            'gridId' => $markedGrid->getGrid()->getId(),
            'humanReadableMark' => $canSeeMark ? $markedGrid->retrieveHumanReadableMark() : null,
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

        // for a self-assessment, only show the mark if it's self graded, as if it's self-reflect only they never marked themselves
        // for a non self-assessment, the mark is shown
        $feedback = $markedGrid->getFeedback();
        $canSeeMark = !($feedback instanceof ISelfAssessableFeedback) || ($feedback instanceof DueWorkFeedback && $feedback->isSelfGrading());

        $capabilityMark = $canSeeMark ? $markedGrid->retrieveCapabilityMark($capabilityUsage) : null;

        return [
            'id' => $capabilityUsage->getCapability()->getId(),
            'humanReadableMark' => $capabilityMark?->getHumanReadableValue(),
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
        $indicatorId = $indicatorUsage->getIndicator()->getId();
        $indicatorMark = $markedGrid->retrieveIndicatorMark($indicatorUsage);

        if (null === $indicatorMark) {
            // if there's no mark, then there isn't anything to show
            return [
                'id' => $indicatorId,
                'descriptorId' => null,
                'humanReadableMark' => null,
            ];
        }

        $humanReadableMark = $indicatorMark->getValue();
        $descriptorId = $indicatorMark->getDescriptor()?->getId();

        $feedback = $markedGrid->getFeedback();
        if ($feedback instanceof ISelfAssessableFeedback && $feedback->isSelfAssessment()) {
            // Self-Assessment:

            return [
                'id' => $indicatorId,
                // for self assessment you always know which descriptor was selected
                'descriptorId' => $descriptorId,
                // however, the mark is only shown for self grading
                // TODO: remove instanceof
                'humanReadableMark' => ($feedback instanceof DueWorkFeedback && $feedback->isSelfGrading())
                    ? $humanReadableMark
                    : null,
            ];
        }

        // non self-assessment:

        // need to check if the viewer is allowed to view the feedback
        if (!$feedback->getAssessable()->isFeedbackVisibleToUser($this->viewer)) {
            // if the mark isn't displayed, then we shouldn't show any info about it
            return [
                'id' => $indicatorId,
                'descriptorId' => null,
                'humanReadableMark' => null,
            ];
        }

        return [
            'id' => $indicatorId,
            // at this point, you always know which descriptor was selected
            'descriptorId' => $descriptorId,
            // the mark is only shown if the viewer has permission (essentially the same config check in the rubric grid translator)
            'humanReadableMark' => (
                $indicatorMark->getDescriptor()?->hasValue() &&
                $markedGrid->getGrid()->showDescriptorValue() &&
                $this->showPoints
            ) ? $humanReadableMark : null,
        ];
    }
}
