<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Custom\Tigs\Summative\Report\Components;

use Alaress\Authorizer\Authorizer;
use Custom\Tigs\Summative\Config;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit as UnitImpl;
use Aura\Router;

/**
 * Responsible for generating data that can be used by a template to render the
 * 'MYP Final Achievement Levels' components on the Subject Report
 */
class FinalAchievementLevels extends BaseComponent
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
    private $falWorkTypeId;

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
        $this->falWorkTypeId = $aamWorkTypeId;
        $this->rubricMapper  = $rubricMapper;
        $this->authorizer    = $authorizer;
        $this->router        = $router;
    }


    /**
     * Generates and returns a data structure, suitable for passing to twig
     *
     * * if no data is found, returns null.
     * * if invalid data found, returns an error message to aid resolution
     *
     * @return null|array
     * @var string    $data ['error']     Optional. If invalid data encountered
     * @var int[]     $data ['data']      Optional. Indexed by concat(Criterion.name, Indicator.name)
     * @throws \Schoolbox\Mapper\Exception
     * @throws Router\Exception\RouteNotFound
     */
    public function toContext(): ?array
    {
        // Not set? Then we can't identify FAL assessments.
        if (null === $this->falWorkTypeId) {
            $errorMsg = 'Cannot render Final Achievement Levels component. Work type not set.';
            if ($this->authorizer->isPathAuthorizedForUser('tigsSummativeAdmin.getAdmin', $this->viewer)) {
                $routeConfig = $this->router->generate('tigsSummativeAdmin.getAdmin');
                $hash        = Config::_TIGS_WORK_TYPE_FINAL_ACHIEVEMENT_LEVELS;
                $errorMsg    .= " <a href =\"{$routeConfig}#{$hash}\">Set Final Achievement Levels Work Type</a>";
            }
            return ['error' => $errorMsg];
        }

        $falAssessables = [];
        foreach($this->unitImpls as $unitImpl){
            /** @noinspection SlowArrayOperationsInLoopInspection */
            $falAssessables = \array_merge($falAssessables, $this->findAssessmentsWithWorkType($unitImpl, $this->falWorkTypeId));
        }

        // Did we find 2+ FAL assessments in this class?
        $countFal = \count($falAssessables);
        if ($countFal >= 2) {
            return ['error' => "A class may have 0 or 1 'Final Achievement Levels' assessments. Found {$countFal}."];
        }

        // Did we find no data?
        if ($countFal === 0) {
            return null;
        }

        $assessment = \reset($falAssessables);

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
            return ['error' => "{$assessment::getDefaultTypeName()} {$title} has a 'Final Achievement Levels' work-type but does not have a rubric grid"];
        }

        // Did we find an assessment, but it shouldn't be visible to the current viewer?
        if (!$this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->viewer, true)) {
            return null;
        }

        // Don't render if student is not a participant, or has been excluded
        $participant = $assessment->getParticipantIfExtant($this->student);
        if (!$participant || $participant->isExcluded()) {
            return null;
        }

        return ['data' => $this->buildAssessmentData($assessment, $participant)];
    }


    /**
     * Build component data
     *
     * @param DueWork\Model $dueWork
     * @param Participant   $participant
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    private function buildAssessmentData(DueWork\Model $dueWork, Participant $participant): array
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
                $capabilityName = $capabilityUsage->getCapability()->getName();
                foreach ($capabilityUsage->getIndicatorUsages() as $indicatorUsage) {
                    $indicatorName                                 = $indicatorUsage->getIndicator()->getName();
                    $marks[$capabilityName . ' ' . $indicatorName] = '';
                }
            }
        } else {
            $markedGrid = $this->rubricMapper->retrieveMarkedGrid($feedback);
            foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
                $capabilityName = $capabilityMark->getCapabilityUsage()->getCapability()->getName();
                foreach ($capabilityMark->getCapabilityUsage()->getIndicatorUsages() as $indicatorUsage) {
                    $indicatorName                                 = $indicatorUsage->getIndicator()->getName();
                    $indicatorMark                                 = $markedGrid->retrieveIndicatorMark($indicatorUsage);
                    $marks[$capabilityName . ' ' . $indicatorName] = $indicatorMark ? $indicatorMark->getValue() : '';
                }
            }
        }
        return $marks;
    }
}
