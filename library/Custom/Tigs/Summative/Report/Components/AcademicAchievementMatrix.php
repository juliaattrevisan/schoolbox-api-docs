<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Alaress\Authorizer\Authorizer;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Assessment\Rubric\Grid\Grid;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Schoolbox\Learning\Assessment\DueWork;
use Aura\Router;

/**
 * Builds a suitable data structure for rendering the AAM components
 */
class AcademicAchievementMatrix
{

    /** @var GenericCollection|UnitImpl\Model[] */
    private $unitImpls;

    /** @var User */
    private $student;

    /** @var Visibility */
    private $visibility;

    /** @var User */
    private $viewer;

    /** @var int */
    private $aamWorkTypeId;

    /** @var RubricMapper */
    private $rubricMapper;

    /** @var Authorizer */
    private $authorizer;

    /** @var Router\Router */
    private $router;


    /**
     * @param GenericCollection $unitImpls
     * @param User              $student
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $aamWorkTypeId
     * @param RubricMapper      $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     */
    public function __construct(
        GenericCollection $unitImpls,
        User $student,
        Visibility $visibility,
        User $viewer,
        int $aamWorkTypeId,
        RubricMapper $rubricMapper,
        Authorizer $authorizer,
        Router\Router $router
    ) {
        $this->unitImpls     = $unitImpls;
        $this->student       = $student;
        $this->visibility    = $visibility;
        $this->viewer        = $viewer;
        $this->aamWorkTypeId = $aamWorkTypeId;
        $this->rubricMapper  = $rubricMapper;
        $this->authorizer    = $authorizer;
        $this->router        = $router;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig
     *
     * Data is indexed by unitImpl.id. For each unit:
     * * if no data is found, returns null.
     * * if invalid data found, returns an error message to aid resolution
     *
     * @return array
     * @var string     $data ['data'][unitImplId]['error']      Optional. If cannot build due to invalid data
     * @var Grid       $data ['data'][unitImplId]['grid']       Optional. If a valid AAM rubric is found for the unit
     * @var MarkedGrid $data ['data'][unitImplId]['markedGrid'] Optional. If student has visible marks for the rubric
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    public function toContext(): array
    {
        $data = [];
        foreach ($this->unitImpls as $unitImpl) {
            $unitData = $this->buildUnitData($unitImpl);
            if ($unitData) {
                $data[$unitImpl->getId()] = $unitData;
            }
        }

        return ['data' => $data];
    }


    /**
     * Builds AAM data for the specified unit.
     *
     * @param UnitImpl\Model $unitImpl
     * @return array|null
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    private function buildUnitData(UnitImpl\Model $unitImpl): ?array
    {
        $aamAssessables = $this->findAamAssessments($unitImpl);

        // Did we find 2+ AAM assessments in this unit?
        $countAam = \count($aamAssessables);
        if ($countAam >= 2) {
            return ['error' => "A unit may have 0 or 1 Academic Achievement Matrix assessments per unit. Found {$countAam}."];
        }

        // Did we find no data?
        if ($countAam === 0) {
            return null;
        }

        /** @var IAssessable $assessment */
        $assessment = \reset($aamAssessables);

        // Not a Due Work?
        if (!$assessment instanceof DueWork\Model) {
            $requiredType = DueWork\Model::getDefaultTypeName();
            $msg          = \sprintf('Only %ss can have rubrics.', $requiredType);
            $routeName    = 'learningAssessment.getAssessment';
            $routeParams  = ['assessmentId' => $assessment->getId(), 'assessment' => $assessment];
            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->viewer)) {
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
            if ($this->authorizer->isPathAuthorizedForUser($routeName, $routeParams, $this->viewer)) {
                $route = $this->router->generate($routeName, $routeParams);
                $title = \sprintf('<a href="%s">%s</a>', $route, $assessment->getTitle());
            } else {
                $title = $assessment->getTitle();
            }
            return ['error' => "DueWork {$title} has a AAM/ATL work-type but does not have a rubric grid"];
        }

        // Did we find an assessment, but it shouldn't be visible to the current viewer?
        if (!$this->visibility->isVisibleToUser($assessment, $this->viewer, true)) {
            return null;
        }

        return $this->buildAssessmentData($assessment);
    }


    /**
     * Iterates through a unit to return all AAM assessments
     *
     * @param UnitImpl\Model $unitImpl
     * @return array
     */
    private function findAamAssessments(UnitImpl\Model $unitImpl): array
    {
        $aamAssessables = [];
        foreach ($unitImpl->getAssessables() as $assessable) {
            if ($assessable instanceof Project) {
                foreach ($assessable->getAssessables() as $subAssessable) {
                    if ($this->isWorkType($subAssessable, $this->aamWorkTypeId)) {
                        $aamAssessables[$subAssessable->getId()] = $subAssessable;
                    }
                }
            } elseif ($this->isWorkType($assessable, $this->aamWorkTypeId)) {
                $aamAssessables[$assessable->getId()] = $assessable;
            }
        }
        return $aamAssessables;
    }


    /**
     * Returns whether an assessment is of the specified work type.
     *
     * A NULL $workTypeId semantically represents an assessment with no work type.
     *
     * @param IAssessable $assessable
     * @param null|int    $workTypeId
     * @return bool
     */
    private function isWorkType(IAssessable $assessable, ?int $workTypeId): bool
    {
        if (!$assessable->getWorkType()) {
            return null === $workTypeId;
        }

        return $assessable->getWorkType()->getId() === $workTypeId;
    }


    /**
     * Build AAM data from the specified due work
     *
     * @param DueWork\Model $dueWork
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAssessmentData(DueWork\Model $dueWork): array
    {
        $data = [];
        // Fetch the grid
        $dueWork->setRubricGrid($this->rubricMapper->retrieveGrid($dueWork, false));
        $data['grid'] = $dueWork->getRubricGrid();

        // Load rubric marks if they exist and current user is permitted to view grades
        $participant = $dueWork->getParticipantIfExtant($this->student);
        if ($participant && !$participant->isExcluded() && $dueWork->isFeedbackVisibleToUser($this->viewer)) {
            /** @var DueWork\Feedback\Model $feedback */
            $feedback = $participant->getAuthoritativeFeedback(true);
            if ($feedback) {
                $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
                $data['markedGrid'] = $feedback->getRubricMarkedGrid();
            }
        }
        return $data;
    }
}
