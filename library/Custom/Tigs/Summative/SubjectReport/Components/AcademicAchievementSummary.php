<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Alaress\Authorizer\Authorizer;
use Aura\Router;
use Custom\Tigs\Summative\StudentAttributes;
use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use function array_key_exists;

/**
 * Responsible for generating data that can be used by a template to render the
 * Academic Achievement Summary components
 */
class AcademicAchievementSummary extends AbstractMatrix
{

    /**
     * @param StudentAttributes $attributes
     * @param GenericCollection $unitSpecs
     * @param User              $student
     * @param Folder\Model      $folder
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $aasWorkTypeId
     * @param RubricDataSource  $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     * @param string|null       $roleTypeOverride
     * @throws InvalidArgumentException
     */
    public function __construct(
        StudentAttributes $attributes,
        GenericCollection $unitSpecs,
        User $student,
        Folder\Model $folder,
        Visibility $visibility,
        User $viewer,
        int $aasWorkTypeId,
        RubricDataSource $rubricMapper,
        Authorizer $authorizer,
        Router\Router $router,
        ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $unitSpecs, $student, $folder, $visibility, $viewer, $aasWorkTypeId, $rubricMapper, $authorizer, $router, $roleTypeOverride);
    }


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

                // MYP students' comments will come from this assessment
                if ($this->getAttributes()->isMyp()) {
                    $data['comment']   = $feedback->getComment();
                    $data['commentBy'] = $feedback->getReviewer();
                }
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
