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
use Custom\Tigs\Summative\SubjectReport\BaseComponent;
use InvalidArgumentException;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;
use Schoolbox\User;
use function array_key_exists;

/**
 * Responsible for generating data that can be used by a template to render the
 * Academic Achievement Summary components
 */
class AcademicAchievementSummary extends BaseComponent
{

    /** @var GenericCollection|UnitSpec\Model[] */
    private $unitSpecs;

    /** @var User */
    private $student;

    /** Folder\Model $folder */
    private $folder;

    /** @var Visibility */
    private $visibility;

    /** @var int */
    private $aasWorkTypeId;

    /** @var RubricDataSource */
    private $rubricMapper;

    /** @var Authorizer */
    private $authorizer;

    /** @var Router\Router */
    private $router;


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
        parent::__construct($attributes, $viewer, $roleTypeOverride);

        $this->unitSpecs     = $unitSpecs;
        $this->student       = $student;
        $this->folder        = $folder;
        $this->visibility    = $visibility;
        $this->aasWorkTypeId = $aasWorkTypeId;
        $this->rubricMapper  = $rubricMapper;
        $this->authorizer    = $authorizer;
        $this->router        = $router;
    }


    /**
     * @return array
     * @throws Router\Exception\RouteNotFound
     * @throws \Schoolbox\Mapper\Exception
     */
    public function toContext(): array
    {
        $data = [];
        foreach ($this->unitSpecs as $unitSpec) {
            $unitData = $this->buildUnitData($unitSpec);
            if ($unitData) {
                $data[$unitSpec->getId()] = $unitData;
            }
        }
        return ['data' => $data];
    }


    /**
     * Builds AAS data for the specified unit spec.
     *
     * Returns an array containing one of:
     * * an error message as to why it can't render,
     * * display data, or
     * * null if no AAS assessment found for unit spec
     *
     * @param UnitSpec\Model $unitSpec
     * @return array|null
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    private function buildUnitData(UnitSpec\Model $unitSpec): ?array
    {
        // If report is for Sem1, only get data from Sem1, otherwise no limit
        $semester       = $this->getAttributes()->getCurriculumSemester() === 1 ? 1 : null;
        $aasAssessables = $this->findAssessmentsForFolderAndWorkType($unitSpec, $this->folder, $this->aasWorkTypeId)
                               ->filter(function (IAssessable $assessable) use ($semester) {
                                   return $this->isDueWithinReportWindow($assessable, $semester) && $assessable->isParticipant($this->student);
                               });

        // Did we find 2+ AAS assessments in this unit?
        $countAas = \count($aasAssessables);
        if ($countAas >= 2) {
            return ['error' => "A folder may have 0 or 1 Academic Achievement Summary assessments per unit. Found {$countAas}."];
        }

        // Did we find no data?
        if ($countAas === 0) {
            return null;
        }

        /** @var IAssessable $assessment */
        $assessment = $aasAssessables->first();

        // Not a Due Work?
        if (!$assessment instanceof DueWork\Model) {
            $requiredType = DueWork\Model::getDefaultTypeName();
            $msg          = \sprintf('Only %ss can have rubrics.', $requiredType);
            $routeName    = 'learningAssessment.getAssessment';
            $routeParams  = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->getViewer())) {
                $route      = $this->router->generate($routeName, $routeParams);
                $actualType = $assessment::getDefaultTypeName();
                $title      = $assessment->getTitle();
                $msg        .= \sprintf(' Found: %s - <a href="%s">%s</a>', $actualType, $route, $title);
            }
            return ['error' => $msg];
        }

        // No Rubric?
        if (!$assessment->getUseRubric()) {
            $routeName   = 'learningAssessment.getAssessment';
            $routeParams = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->getViewer())) {
                $route = $this->router->generate($routeName, $routeParams);
                $title = \sprintf('<a href="%s">%s</a>', $route, $assessment->getTitle());
            } else {
                $title = $assessment->getTitle();
            }
            return ['error' => "{$assessment::getDefaultTypeName()} {$title} has a AAS work-type but does not have a rubric grid"];
        }

        // If assessment not visible, or student is not a participant, returns NULL.
        // Did we find an assessment, but it shouldn't be visible to the current viewer?
        if (!$this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->getViewer(), true, null, false)) {
            return null;
        }

        // Is grade visibility off for the current viewer?
        if (!$this->isFeedbackVisibleToUser($assessment)) {
            return null;
        }

        return $this->buildAssessmentData($assessment);
    }


    /**
     * Build AAS data from the specified due work
     *
     * If no feedback, or viewer not permitted to view feedback, empty marks returned
     *
     * @param DueWork\Model $dueWork
     * @return null|array
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    private function buildAssessmentData(DueWork\Model $dueWork): ?array
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
                $data['markedGrid'] = $feedback->getRubricMarkedGrid();
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
