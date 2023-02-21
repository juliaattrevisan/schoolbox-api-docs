<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Aura\Router;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Learning profile component for TIGS subject report.
 *
 * For the student attribute's year:
 * * determine the current and past semesters
 * * for each year+semester combination:
 *   * determine the configured work type for that year+semester
 *   * display a rubric for the due work which matches that work type, and is
 *     due within that year+semester
 */
class LearningProfile extends AbstractMatrix
{
    /**
     * The capability name which the learning profile component uses.
     */
    private const CAPABILITY_NAME = 'Learning Profile';


    /**
     * Build AAS data from the specified due work
     *
     * If no feedback, or viewer not permitted to view feedback, empty marks returned
     *
     * @param DueWork\Model $dueWork
     * @return null|array
     * @throws MapperException
     * @throws Router\Exception\RouteNotFound
     */
    public function buildAssessmentData(DueWork\Model $dueWork): array
    {
        // Fetch the grid
        $dueWork->setRubricGrid($this->rubricMapper->retrieveGrid($dueWork, false));

        $data = [
//            'grid'                => $dueWork->getRubricGrid(),
//            'useMarkRange'        => $dueWork->getUseMarkRange(),
//            'showDescriptorValue' => true,
//            'showGroupTotal'      => $dueWork->isMarkPerCapability(),
//            'showRubricTotal'     => true,
//            'capabilityMarks'     => null,
        ];

        // Load rubric marks if they exist and current user is permitted to view grades
        $participant = $dueWork->getParticipantIfExtant($this->student);
        if ($participant && !$participant->isExcluded()) {
            /** @var DueWork\Feedback\Model $feedback */
            $feedback = $participant->getAuthoritativeFeedback(true);
            if ($feedback) {
                $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
//                $data['markedGrid']      = $feedback->getRubricMarkedGrid();
                $data['capabilityMarks'] = $this->buildCapabilityMarks($feedback->getRubricMarkedGrid());
                $data['levels']          = $this->buildLevels($feedback->getRubricMarkedGrid());
            }
        }
        $data['dueDate'] = $dueWork->getDueAt();
//        $data['link']    = $this->getAasLink($dueWork);
        return $data;
    }


    /**
     *
     * @param MarkedGrid|null $markedGrid
     * @return null|array
     */
    private function buildCapabilityMarks(?MarkedGrid $markedGrid): ?array
    {
        $capabilityMarks = null;
        if ($markedGrid) {
            foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
                $capabilityName = $capabilityMark->getCapabilityUsage()->getCapability()->getName();
                if ($capabilityName === self::CAPABILITY_NAME) {
                    foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                        $capabilityMarks ??= [];
                        $capabilityMarks[] = [
                            'id'   => $indicatorMark->getIndicatorUsage()->getIndicator()->getId(),
                            'name' => $indicatorMark->getIndicatorUsage()->getIndicator()->getName(),
                            'descriptor' => [
                                'id'   => $indicatorMark->getDescriptor()->getId(),
                                'name' => $indicatorMark->getDescriptor()->getDescription(),
                            ]
                        ];
                    }
                }
            }
        }
        return $capabilityMarks;
    }


    private function buildLevels(?MarkedGrid $markedGrid): ?array
    {
        // The rubrics do not have levels:
        // instead, each descriptor in a column is the same.
        // Therefore: get the first indicator's descriptors, and assume the
        // rest of the indicators have the same descriptor names.
        if ($markedGrid) {
            foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
                $capabilityName = $capabilityMark->getCapabilityUsage()->getCapability()->getName();
                if ($capabilityName === self::CAPABILITY_NAME) {
                    foreach ($capabilityMark->getIndicatorMarks() as $indicatorMark) {
                        $levels = [];
                        foreach ($indicatorMark->getIndicatorUsage()->getDescriptors() as $descriptor) {
                            $levels[] = [
                                'name' => $descriptor->getDescription(),
                            ];
                        }
                        return $levels;
                    }
                }
            }
        }
        return null;
    }
}
