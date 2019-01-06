<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Alaress\Authorizer\Authorizer;
use Custom\Tigs\Summative\Report\BaseComponent;
use Custom\Tigs\Summative\Report\StudentAttributes;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Aura\Router;

/**
 * Responsible for generating data that can be used by a template to render the
 * Academic Achievement Summary components
 */
class AcademicAchievementSummary extends BaseComponent
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
    private $aasWorkTypeId;

    /** @var RubricMapper */
    private $rubricMapper;

    /** @var Authorizer */
    private $authorizer;

    /** @var Router\Router */
    private $router;


    /**
     * @param StudentAttributes $attributes
     * @param GenericCollection $unitImpls
     * @param User              $student
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param int               $aasWorkTypeId
     * @param RubricMapper      $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     */
    public function __construct(
        StudentAttributes $attributes,
        GenericCollection $unitImpls,
        User $student,
        Visibility $visibility,
        User $viewer,
        int $aasWorkTypeId,
        RubricMapper $rubricMapper,
        Authorizer $authorizer,
        Router\Router $router
    ) {
        parent::__construct($attributes);

        $this->unitImpls     = $unitImpls;
        $this->student       = $student;
        $this->visibility    = $visibility;
        $this->viewer        = $viewer;
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
        foreach ($this->unitImpls as $unitImpl) {
            $unitData = $this->buildUnitData($unitImpl);
            if ($unitData) {
                $data[$unitImpl->getId()] = $unitData;
            }
        }
        return ['data' => $data];
    }


    /**
     * Builds AAS data for the specified unit.
     *
     * Returns an array containing one of:
     * * an error message as to why it can't render,
     * * display data, or
     * * null if no AAS assessment found for unit
     *
     * @param UnitImpl\Model $unitImpl
     * @return array|null
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    private function buildUnitData(UnitImpl\Model $unitImpl): ?array
    {
        $aasAssessables = $this->findAssessmentsWithWorkType($unitImpl, $this->aasWorkTypeId)
                               ->filter(function (IAssessable $assessable) {
                                   return $this->isDueWithinReportWindow($assessable);
                               });

        // Did we find 2+ AAS assessments in this unit?
        $countAas = \count($aasAssessables);
        if ($countAas >= 2) {
            return ['error' => "A unit may have 0 or 1 Academic Achievement Summary assessments per unit. Found {$countAas}."];
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
            return ['error' => "{$assessment::getDefaultTypeName()} {$title} has a AAS work-type but does not have a rubric grid"];
        }

        return $this->buildAssessmentData($assessment);
    }


    /**
     * Build AAS data from the specified due work
     *
     * If assessment not visible, or student is not a participant, returns NULL.
     * If no feedback, or viewer not permitted to view feedback, empty marks returned
     *
     * @param DueWork\Model $dueWork
     * @return null|array
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    private function buildAssessmentData(DueWork\Model $dueWork): ?array
    {
        // Did we find an assessment, but it shouldn't be visible to the current viewer?
        if (!$this->visibility->isVisibleToUserOrChildOfUser($dueWork, $this->viewer, true, null, false)) {
            return null;
        }

        // Don't render if student is not a participant
        $participant = $dueWork->getParticipantIfExtant($this->student);
        if (!$participant || $participant->isExcluded()) {
            return null;
        }

        return [
            'link'  => $this->getAasLink($dueWork),
            'marks' => $this->getMarks($dueWork, $participant),
        ];
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

        if ($this->authorizer->isPathAuthorizedForUser($path, $routeParams, $this->viewer)) {
            return $this->router->generate($path, $routeParams);
        }
        return null;
    }


    /**
     * @param DueWork\Model $dueWork
     * @param Participant   $participant
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function getMarks(DueWork\Model $dueWork, Participant $participant): array
    {
        // Fetch the grid
        $grid = $this->rubricMapper->retrieveGrid($dueWork, false);
        $dueWork->setRubricGrid($grid);

        $marks = [];

        /** @var DueWork\Feedback\Model $feedback */
        $feedback = $participant->getAuthoritativeFeedback(true);

        // No feedback, or feedback should not be visible?
        if (!$feedback || !$dueWork->isFeedbackVisibleToUser($this->viewer)) {
            foreach ($grid->getCapabilityUsages() as $capabilityUsage) {
                $capabilityName         = $capabilityUsage->getCapability()->getName();
                $marks[$capabilityName] = '';
            }
        } else {
            foreach ($this->rubricMapper->retrieveMarkedGrid($feedback)->getCapabilityMarks() as $capabilityMark) {
                $capabilityName         = $capabilityMark->getCapabilityUsage()->getCapability()->getName();
                $marks[$capabilityName] = $capabilityMark->getValue();
            }
        }
        return $marks;
    }
}
