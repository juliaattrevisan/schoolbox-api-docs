<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

use Alaress\Authorizer\Authorizer;
use Alaress_Date_Format;
use Aura\Router;
use Custom\Tigs\Summative\Config;
use Custom\Tigs\Summative\StudentAttributes;
use Custom\Tigs\Summative\SubjectReport\BaseComponent;
use Schoolbox\Assessment\Rubric\DataModel\DataSource as RubricDataSource;
use Schoolbox\Assessment\Rubric\MarkedGrid\MarkedGrid;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DueWork;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
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
     * @param StudentAttributes $attributes
     * @param GenericCollection|UnitSpec\Model[] $unitSpecs
     * @param User              $student
     * @param Folder\Model      $folder
     * @param array<array{workTypeId: int, term: int, semester: int}> $searches
     * @param Visibility        $visibility
     * @param User              $viewer
     * @param RubricDataSource  $rubricMapper
     * @param Authorizer        $authorizer
     * @param Router\Router     $router
     * @param string|null       $roleTypeOverride
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
        private Router\Router $router,
        private IConfigLoader $config,
        private ?string $roleTypeOverride
    ) {
        parent::__construct($attributes, $viewer, $roleTypeOverride);
    }

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
                            ->filter(fn (IAssessable $assessment) => $assessment instanceof DueWork\Model)
                            ->filter(fn (DueWork\Model $assessment) => $assessment->getWorkType()?->getId() === $search['workTypeId'])
                            ->filter(fn (DueWork\Model $assessment) => $assessment->isParticipant($this->student))
                            ->filter(fn (DueWork\Model $assessment) => $this->isDueWithinTermWindow($assessment, $search['term']))
                            ->filter(fn (DueWork\Model $assessment) => $assessment->isFeedbackVisibleToRoleType($this->roleTypeOverride ?? $this->viewer->getRole()->getType()))
                            ->sortByCallback(fn (DueWork\Model $a1, DueWork\Model $a2) => $a1->getDueAt()->lt($a2->getDueAt()));

                        // For each filtered assessment: build data I guess
                        foreach ($filteredAssessments as $assessment) {
                            $data[$assessment->getId()] = $this->buildAssessmentData($assessment);
                        }
                    }
                }
            }
        }

        return ['data' => $data];
    }

    public function buildAssessmentData(DueWork\Model $dueWork): array
    {
        // Fetch the grid
        $dueWork->setRubricGrid($this->rubricMapper->retrieveGrid($dueWork, false));

        $data = [
            'assessment' => $dueWork,
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
            $feedback = $participant->getAuthoritativeFeedback(true);
            if ($feedback instanceof DueWork\Feedback\Model) {
                $feedback->setRubricMarkedGrid($this->rubricMapper->retrieveMarkedGrid($feedback));
//                $data['markedGrid']      = $feedback->getRubricMarkedGrid();
                $data['indicators'] = $this->buildIndicatorMarks($feedback->getRubricMarkedGrid());
                $data['levels']     = $this->buildLevels($feedback->getRubricMarkedGrid());
            }
        }
//        $data['dueDate'] = $dueWork->getDueAt();
        $data['link']    = $this->getLink($dueWork);
        return $data;
    }


    /**
     *
     * @param MarkedGrid|null $markedGrid
     * @return null|array
     */
    private function buildIndicatorMarks(?MarkedGrid $markedGrid): ?array
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

    private function isDueWithinTermWindow(IAssessable $assessment, int $term): bool
    {
        // Consider semester search instead?
        // As it happens: the terms 1 and 2 work types are always different,
        // as are the terms 3 and 4 work types.
        // This means we may get away with picking both term 1 and 2 due works
        // from semester 1, and term 3 and 4 from semester 2?
        $startYearOffset = 0;
        $endYearOffset   = 0;
        switch ($term) {
            case 1:
                $startConfig = $this->config->get(Config::_TIGS_START_TERM_1);
                $endConfig   = $this->config->get(Config::_TIGS_START_TERM_2);
//                $startDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_1))
//                    ->startOfDay();
//                $endDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_2))
//                    ->addDays(-1)
//                    ->endOfDay();
                break;
            case 2:
                $startConfig = $this->config->get(Config::_TIGS_START_TERM_2);
                $endConfig   = $this->config->get(Config::_TIGS_START_TERM_3);
//                $startDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_2))
//                    ->startOfDay();
//                $endDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_3))
//                    ->addDays(-1)
//                    ->endOfDay();
                break;
            case 3:
                $startConfig = $this->config->get(Config::_TIGS_START_TERM_3);
                $endConfig   = $this->config->get(Config::_TIGS_START_TERM_4);
//                $startDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_3))
//                    ->startOfDay();
//                $endDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->endOfDay()
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_4))
//                    ->addDays(-1)
//                    ->endOfDay();
                break;
            case 4:
                $startConfig   = $this->config->get(Config::_TIGS_START_TERM_4);
                $endConfig     = $this->config->get(Config::_TIGS_START_TERM_1);
                $endYearOffset = 1;
//                $startDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_4))
//                    ->startOfDay();
//                $endDate = (new Alaress_Date_Format())
//                    ->setYear($reportYear + 1)
//                    ->modify($this->config->get(Config::_TIGS_START_TERM_1))
//                    ->addDays(-1)
//                    ->endOfDay();
                break;
            default:
                return false;
        }

        $reportYear = $this->getAttributes()->getCalendarYear();
        $startDate  = (new Alaress_Date_Format())
            ->setYear($reportYear + $startYearOffset)
            ->modify($startConfig)
            ->startOfDay();
        $endDate   = (new Alaress_Date_Format())
            ->setYear($reportYear + $endYearOffset)
            ->modify($endConfig)
            ->addDays(-1)
            ->endOfDay();

        return $assessment->getDueAt()->between($startDate, $endDate);
    }

    /**
     * Retrieve a link to the due work.
     *
     * @param DueWork\Model $dueWork
     * @return string|null
     * @throws Router\Exception\RouteNotFound
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
            return $this->router->generate($path, $routeParams);
        }
        return null;
    }
}
