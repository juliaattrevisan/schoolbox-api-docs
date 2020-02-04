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
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

use function count;
use function sprintf;

/**
 * Builds a suitable data structure for rendering the Hsc Task Rubric components
 */
class HscAssessmentMatrix extends BaseComponent
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
    private $hscWorkTypeId;

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
     * @param int               $hscTaskWorkTypeId
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
        int $hscTaskWorkTypeId,
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
        $this->hscWorkTypeId = $hscTaskWorkTypeId;
        $this->rubricMapper  = $rubricMapper;
        $this->authorizer    = $authorizer;
        $this->router        = $router;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig
     *
     * Data is indexed by unitSpec.id. For each unit:
     * * if no data is found, returns null.
     * * if invalid data found, returns an error message to aid resolution
     *
     * @return array
     * @var string     $data ['data'][unitSpecId]['error']      Optional. If cannot build due to invalid data
     * @var Grid       $data ['data'][unitSpecId]['grid']       Optional. If a valid Task Mark rubric is found for the unit
     * @var MarkedGrid $data ['data'][unitSpecId]['markedGrid'] Optional. If student has visible marks for the rubric
     * @throws MapperException
     * @throws Router\Exception\RouteNotFound
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
     * Builds HscTask Rubric data for the specified unit.
     *
     * @param UnitSpec\Model $unitSpec
     * @return array|null
     * @throws MapperException
     * @throws Router\Exception\RouteNotFound
     */
    private function buildUnitData(UnitSpec\Model $unitSpec): ?array
    {
        // If report is for Sem1, only get data from Sem1, otherwise no limit
        $semester       = $this->getAttributes()->getCurriculumSemester() === 1 ? 1 : null;
        $hscAssessables = $this->findAssessmentsForFolderAndWorkType($unitSpec, $this->folder, $this->hscWorkTypeId)
                               ->filter(function (IAssessable $assessable) use ($semester) {
                                   return $this->isDueWithinReportWindow($assessable, $semester) && $assessable->isParticipant($this->student);
                               });

        // Did we find 2+ Hsc assessments in this unit?
        $count = count($hscAssessables);
        if ($count >= 2) {
            return ['error' => "A unit may have 0 or 1 Approaches to Learning assessments per unit. Found {$count}."];
        }

        // Did we find no data?
        if ($count === 0) {
            return null;
        }

        /** @var IAssessable $assessment */
        $assessment = $hscAssessables->first();

        // Not a Due Work?
        if (!$assessment instanceof DueWork\Model) {
            $requiredType = DueWork\Model::getDefaultTypeName();
            $msg          = sprintf('Only %ss can have rubrics.', $requiredType);
            $routeName    = 'learningAssessment.getAssessment';
            $routeParams  = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->getViewer())) {
                $route      = $this->router->generate($routeName, $routeParams);
                $actualType = $assessment::getDefaultTypeName();
                $title      = $assessment->getTitle();
                $msg        .= sprintf(' Found: %s - <a href="%s">%s</a>', $actualType, $route, $title);
            }
            return ['error' => $msg];
        }

        // No Rubric?
        if (!$assessment->getUseRubric()) {
            $routeName   = 'learningAssessment.getAssessment';
            $routeParams = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->getViewer())) {
                $route = $this->router->generate($routeName, $routeParams);
                $title = sprintf('<a href="%s">%s</a>', $route, $assessment->getTitle());
            } else {
                $title = $assessment->getTitle();
            }
            return ['error' => "{$assessment::getDefaultTypeName()} {$title} has a Hsc Task work-type but does not have a rubric grid"];
        }

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
     * Build hscTaskMatrix data from the specified due work
     *
     * @param DueWork\Model $dueWork
     * @return array
     * @throws MapperException
     */
    private function buildAssessmentData(DueWork\Model $dueWork): array
    {
        $data = [];
        // Fetch the grid
        $dueWork->setRubricGrid($this->rubricMapper->retrieveGrid($dueWork, false));
        $data['grid'] = $dueWork->getRubricGrid();

        // Load rubric marks if they exist and current user is permitted to view grades
        $participant = $dueWork->getParticipantIfExtant($this->student);
        if ($participant && !$participant->isExcluded()) {
            /** @var DueWork\Feedback\Model $feedback */
            $feedback = $participant->getAuthoritativeFeedback(true);
            if ($feedback) {
                $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
                $data['markedGrid'] = $feedback->getRubricMarkedGrid();

                // DP students' comments will come from this assessment
                if ($this->getAttributes()->isDp()) {
                    $data['comment']   = $feedback->getComment();
                    $data['commentBy'] = $feedback->getReviewer();
                }
            }
        }
        $data['dueDate'] = $dueWork->getDueAt();
        return $data;
    }
}
