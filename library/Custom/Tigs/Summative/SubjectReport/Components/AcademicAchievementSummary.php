<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Aura\Router;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Mapper\Exception as MapperException;
use function array_key_exists;

/**
 * Responsible for generating data that can be used by a template to render the
 * Academic Achievement Summary components
 */
class AcademicAchievementSummary extends AbstractMatrix
{
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
            'grid'                => $dueWork->getRubricGrid(),
            'useMarkRange'        => $dueWork->getUseMarkRange(),
            'showDescriptorValue' => true,
            'showGroupTotal'      => $dueWork->getMarkPerCapability(),
            'showRubricTotal'     => true,
            'capabilityMarks'     => null,
        ];

        // Load rubric marks if they exist and current user is permitted to view grades
        $participant = $dueWork->getParticipantIfExtant($this->student);
        if ($participant && !$participant->isExcluded()) {
            /** @var DueWork\Feedback\Model $feedback */
            $feedback = $participant->getAuthoritativeFeedback(true);
            if ($feedback) {
                $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
                $data['markedGrid']      = $feedback->getRubricMarkedGrid();
                $data['capabilityMarks'] = $this->buildCapabilityMarks($data['markedGrid']);
            }
        }
        $data['dueDate'] = $dueWork->getDueAt();
        $data['link']    = $this->getAasLink($dueWork);
        return $data;
    }


    /**
     * @param DueWork\Model $dueWork
     * @return string|null
     * @throws Router\Exception\RouteNotFound
     */
    private function getAasLink(DueWork\Model $dueWork): ?string
    {
        // Build the link for the unit title
        $path        = 'learningAssessment.getSummary';
        $routeParams = [
            'assessmentId' => $dueWork->getId(),
            'assessment'   => $dueWork,
            'studentId'    => $this->student->getId(),
            'student'      => $this->student,
        ];

        if ($this->authorizer->isPathAuthorizedForUser($path, $routeParams, $this->getViewer())) {
            return $this->router->generate($path, $routeParams);
        }
        return null;
    }


    /**
     * Derive capability marks to support Academic Transcript using the mark as a placeholder until FAL marks are given
     *
     * Returns NULL if grid is null, or has no non-null marks for capabilities named in (A,B,C,D)
     *
     * @param MarkedGrid|null $markedGrid
     * @return null|array
     */
    private function buildCapabilityMarks(?MarkedGrid $markedGrid): ?array
    {
        $capabilityMarks = [
            'A' => null,
            'B' => null,
            'C' => null,
            'D' => null,
        ];
        $hasMarks        = false;
        if ($markedGrid !== null) {
            foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
                $capabilityName  = $capabilityMark->getCapabilityUsage()->getCapability()->getName();
                $capabilityValue = $capabilityMark->getValue();
                if (array_key_exists($capabilityName, $capabilityMarks) && $capabilityValue !== null) {
                    $hasMarks                         = true;
                    $capabilityMarks[$capabilityName] = $capabilityMark->getValue();
                }
            }
        }
        return $hasMarks ? $capabilityMarks : null;
    }
}
