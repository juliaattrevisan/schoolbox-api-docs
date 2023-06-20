<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Alaress\Authorizer\Authorizer;
use Aura\Router;
use Aura\Router\Exception\RouteNotFound;
use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\BaseComponent;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder;
use Schoolbox\User;

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
class LearningProfile extends BaseComponent
{
    /**
     * The capability name which the learning profile component uses.
     */
    private const CAPABILITY_NAME = 'Learning Profile';

    /**
     * Constructor
     *
     * @param StudentAttributes                                       $attributes
     * @param GenericCollection|UnitSpec\Model[]                      $unitSpecs
     * @param User                                                    $student
     * @param Folder\Model                                            $folder
     * @param array<array{workTypeId: int, term: int, semester: int}> $searches
     * @param Visibility                                              $visibility
     * @param User                                                    $viewer
     * @param RubricDataSource                                        $rubricMapper
     * @param Authorizer                                              $authorizer
     * @param Router\Generator                                        $routeGenerator
     * @param string|null                                             $roleTypeOverride
     */
    public function __construct(
        StudentAttributes $attributes,
        private GenericCollection $unitSpecs,
        private User $student,
        private Folder\Model $folder,
        private array $searches,
        private Visibility $visibility,
        private User $viewer,
        private RubricDataSource $rubricMapper,
        private Authorizer $authorizer,
        private Router\Generator $routeGenerator,
        private ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $viewer, $roleTypeOverride);
    }

    /**
     * Converts the unit specs for this component into a data set.
     *
     * @return array{data: array}|null
     * @throws Router\Exception\RouteNotFound if somehow a route wasn't found
     * @throws MapperException                if something went wrong whilst
     *                                        fetching a rubric
     */
    public function toContext(): ?array
    {
        // Do something with each search
        $data = [];
        foreach ($this->unitSpecs as $unitSpec) {
            foreach ($unitSpec->getUnitImplementations() as $implementation) {
                foreach ($this->searches as $search) {
                    // Search within unit spec for due works matching search
                    if ($implementation->getFolder()->getId() === $this->folder->getId()) {
                        $filteredAssessments = $implementation->getAssessables()
                            ->filter($this->createAssessmentFilter($search));

                        // For each filtered assessment: build data I guess
                        foreach ($filteredAssessments as $assessment) {
                            $assessmentData = $this->buildAssessmentData($assessment, $search);
                            if (isset($assessmentData)) {
                                $data[$assessment->getId()] = $assessmentData;
                            }
                        }
                    }
                }
            }
        }

        return ['data' => $data];
    }

    /**
     * Builds data for a single assessment.
     *
     * @param DueWork\Model $dueWork
     * @param array         $search
     * @return array|null
     * @throws Router\Exception\RouteNotFound if somehow a route wasn't found
     * @throws MapperException                if something went wrong whilst
     *                                        fetching a rubric
     */
    public function buildAssessmentData(DueWork\Model $dueWork, array $search): ?array
    {
        // Fetch the grid
        $dueWork->setRubricGrid($this->rubricMapper->retrieveGrid($dueWork, false));

        $data = [
            'assessment' => $dueWork,
            'term'       => $search['term'] ?? null,
            'semester'   => $search['semester'] ?? null,
            'workTypeId' => $search['workTypeId'] ?? null,
        ];

        // Load rubric marks if they exist and current user is permitted to view grades
        $participant = $dueWork->getParticipantIfExtant($this->student);
        if ($participant && !$participant->isExcluded()) {
            $feedback = $participant->getAuthoritativeFeedback(true);
            if ($feedback instanceof DueWork\Feedback\Model) {
                $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
                $data['indicators'] = $this->buildIndicatorMarks($feedback->getRubricMarkedGrid());
                $data['levels']     = $this->buildLevels($feedback->getRubricMarkedGrid());
            } else {
                return null;
            }
        }
        $data['link'] = $this->getLink($dueWork);
        return $data;
    }

    /**
     * Create a filter callback for this set of search parameters.
     *
     * The assessment will be included only if all the below conditions are met:
     * * it's a due work
     * * its work type matches the one being searched for
     * * the current student is a participant
     * * it's due within the report window for the semester being searched for
     * * its feedback is visible to the current role type
     * * it's visible to the user or one of the user's children
     *
     * @param array $search
     * @return callable(IAssessable): bool
     */
    private function createAssessmentFilter(array $search): callable
    {
        return function (IAssessable $assessment) use ($search) {
            if (!$assessment instanceof DueWork\Model) {
                return false;
            }
            if (!($assessment->getWorkType()?->getId() === $search['workTypeId'])) {
                return false;
            }
            if (!$assessment->isParticipant($this->student)) {
                return false;
            }
            if (!$this->isDueWithinReportWindow($assessment, $search['semester'])) {
                return false;
            }
            if (
                !$assessment->isFeedbackVisibleToRoleType(
                    $this->roleTypeOverride ?? $this->viewer->getRole()->getType()
                )
            ) {
                return false;
            }
            if (!$this->visibility->isVisibleToUserOrChildOfUser($assessment, $this->viewer, true, null, false)) {
                return false;
            }
            return true;
        };
    }

    /**
     * Build a set of indicator marks for the given rubric marked grid.
     *
     * @param MarkedGrid|null $markedGrid
     * @return null|array<array{id: int, name: string, descriptor: array{id: int, name: string}}>
     */
    private function buildIndicatorMarks(?MarkedGrid $markedGrid): ?array
    {
        $capabilityMarks = null;
        if ($markedGrid) {
            foreach ($markedGrid->getCapabilityMarks() as $capabilityMark) {
                $capabilityName = $capabilityMark->getCapabilityUsage()->getCapability()->getName();
                if ($capabilityName === self::CAPABILITY_NAME) {
                    foreach ($capabilityMark->getCapabilityUsage()->getIndicatorUsages() as $indicatorUsage) {
                        $indicatorMark = $capabilityMark->retrieveIndicatorMark($indicatorUsage);
                        $capabilityMarks ??= [];
                        $capabilityMarks[] = [
                            'id'         => $indicatorUsage->getIndicator()->getId(),
                            'name'       => $indicatorUsage->getIndicator()->getName(),
                            'descriptor' => [
                                'id'   => $indicatorMark?->getDescriptor()->getId(),
                                'name' => $indicatorMark?->getDescriptor()->getDescription(),
                            ]
                        ];
                    }
                }
            }
        }
        return $capabilityMarks;
    }

    /**
     * Build a set of indicator levels for the given rubric marked grid.
     *
     * The levels are derived from the capability's descriptors, not the actual
     * levels of the rubric.
     *
     * @param MarkedGrid|null $markedGrid
     * @return null|array<array{name: string}>
     */
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

    /**
     * Retrieve a link to the due work.
     *
     * @param DueWork\Model $dueWork
     * @return string|null
     * @throws Router\Exception\RouteNotFound if somehow the route wasn't found
     */
    private function getLink(DueWork\Model $dueWork): ?string
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
            return $this->routeGenerator->generate($path, $routeParams);
        }
        return null;
    }
}
