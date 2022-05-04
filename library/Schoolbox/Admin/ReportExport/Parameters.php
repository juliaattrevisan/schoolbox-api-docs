<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\ReportExport;

use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model;
use Schoolbox\Role;

class Parameters
{

    /**
     * Report options
     */
    public const YEAR_LEVELS = 'yearLevels';
    public const CAMPUSES    = 'campuses';
    public const STUDENT_IDS = 'studentIds';
    public const TERMS       = 'terms';

    public const START_DATE               = 'startDate';
    public const END_DATE                 = 'endDate';
    public const VIEW_AS                  = 'viewAs';
    public const SHOW_SUMMARY             = 'showSummary';
    public const WORK_TYPES               = 'workTypes';
    public const SHOW_CLASS               = 'showClass';
    public const CLASS_WORK_TYPES         = 'classWorkTypes';
    public const SHOW_OVERALL_GRADE       = 'showOverallGrade';
    public const SHOW_CLASS_OVERALL_GRADE = 'showClassOverallGrade';
    public const SHOW_LEARNING_LEVELS     = 'showLearningLevels';
    public const SHOW_COHORT_RESULTS      = 'showCohortResults';
    public const SHOW_CAPABILITY_GRADES   = 'showCapabilityGrades';
    public const SHOW_PROGRESSION         = 'showProgression';
    public const SHOW_GRADES              = 'showGrades';
    public const SHOW_COMMENTS            = 'showComments';
    public const SHOW_RUBRICS             = 'showRubrics';
    public const SHOW_CLASS_GRADES        = 'showClassGrades';
    public const SHOW_CLASS_COMMENTS      = 'showClassComments';
    public const SHOW_CLASS_RUBRICS       = 'showClassRubrics';

    public const SUMMARY_PAGE_OPTIONS = [
        self::SHOW_OVERALL_GRADE => 'Show overall grade',
    ];

    public const CLASS_PAGE_OPTIONS = [
        self::SHOW_CLASS_OVERALL_GRADE => 'Show overall grade',
        self::SHOW_LEARNING_LEVELS     => 'Show learning levels',
        self::SHOW_COHORT_RESULTS      => 'Show cohort / class results',
        self::SHOW_CAPABILITY_GRADES   => 'Show capability grades',
        self::SHOW_PROGRESSION         => 'Show progression',
    ];

    public const SUMMARY_ASSESSMENT_OPTIONS = [
        self::SHOW_GRADES   => 'Show grades',
        self::SHOW_COMMENTS => 'Show comments',
        self::SHOW_RUBRICS  => 'Show rubrics',
    ];
    public const CLASS_ASSESSMENT_OPTIONS   = [
        self::SHOW_CLASS_GRADES   => 'Show grades',
        self::SHOW_CLASS_COMMENTS => 'Show comments',
        self::SHOW_CLASS_RUBRICS  => 'Show rubrics',
    ];

    private ?string $startDate;
    private ?string $endDate;

    private TermMapper $termMapper;

    private array $termIds;

    private string $viewAs;

    // summary
    private bool $showSummary;

    private bool $showSummaryOverallGrade;

    private array $summaryWorkTypeIds;

    private bool $showSummaryGrades;

    private bool $showSummaryComments;

    private bool $showSummaryRubrics;

    // class
    private bool $showClass;

    private bool $showClassOverallGrade;

    private array $classWorkTypeIds;

    private bool $showClassGrades;

    private bool $showClassComments;

    private bool $showClassRubrics;

    // students
    private array $yearLevels;

    private array $campuses;

    private array $studentIds;


    public function __construct(array $params, TermMapper $termMapper)
    {
        $this->startDate  = $params[self::START_DATE] ?? null;
        $this->endDate    = $params[self::END_DATE] ?? null;
        $this->viewAs     = $params[self::VIEW_AS] ?? Role::STAFF;
        $this->termMapper = $termMapper;
        $this->termIds    = (isset($params[self::TERMS]) && $params[self::TERMS] !== null) ? $this->filter($params[self::TERMS]) : [];
        // summary
        $this->showSummary             = (bool)($params[self::SHOW_SUMMARY] ?? false);
        $this->showSummaryOverallGrade = (bool)($params[self::SHOW_OVERALL_GRADE] ?? false);
        $this->summaryWorkTypeIds      = (isset($params[self::WORK_TYPES]) && $params[self::WORK_TYPES] !== null) ? $this->filter($params[self::WORK_TYPES]) : [];
        $this->showSummaryGrades       = (bool)($params[self::SHOW_GRADES] ?? false);
        $this->showSummaryComments     = (bool)($params[self::SHOW_COMMENTS] ?? false);
        $this->showSummaryRubrics      = (bool)($params[self::SHOW_RUBRICS] ?? false);
        // class
        $this->showClass             = (bool)($params[self::SHOW_CLASS] ?? false);
        $this->showClassOverallGrade = (bool)($params[self::SHOW_CLASS_OVERALL_GRADE] ?? false);
        $this->classWorkTypeIds      = (isset($params[self::CLASS_WORK_TYPES]) && $params[self::CLASS_WORK_TYPES] !== null) ? $this->filter($params[self::CLASS_WORK_TYPES]) : [];
        $this->showClassGrades       = (bool)($params[self::SHOW_CLASS_GRADES] ?? false);
        $this->showClassComments     = (bool)($params[self::SHOW_CLASS_COMMENTS] ?? false);
        $this->showClassRubrics      = (bool)($params[self::SHOW_CLASS_RUBRICS] ?? false);
        // student
        $this->yearLevels = (isset($params[self::YEAR_LEVELS]) && $params[self::YEAR_LEVELS] !== null) ? $this->filter($params[self::YEAR_LEVELS]) : [];
        $this->campuses   = (isset($params[self::CAMPUSES]) && $params[self::CAMPUSES] !== null) ? $this->filter($params[self::CAMPUSES]) : [];
        $this->studentIds = (isset($params[self::STUDENT_IDS]) && $params[self::STUDENT_IDS] !== null) ? $this->filter($params[self::STUDENT_IDS]) : [];
    }

    /**
     * @return mixed|string
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @return mixed|string
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @return array
     */
    public function getTermIds(): array
    {
        return $this->termIds;
    }

    /**
     * @return string
     */
    public function getViewAs(): string
    {
        return $this->viewAs;
    }

    /**
     * @return bool
     */
    public function getShowSummary(): bool
    {
        return $this->showSummary;
    }

    /**
     * @return bool
     */
    public function getShowSummaryOverallGrade(): bool
    {
        return $this->showSummaryOverallGrade;
    }

    /**
     * @return array
     */
    public function getSummaryWorkTypeIds(): array
    {
        return $this->summaryWorkTypeIds;
    }

    /**
     * @return bool
     */
    public function getShowSummaryGrades(): bool
    {
        return $this->showSummaryGrades;
    }

    /**
     * @return bool
     */
    public function getShowSummaryComments(): bool
    {
        return $this->showSummaryComments;
    }

    /**
     * @return bool
     */
    public function getShowSummaryRubrics(): bool
    {
        return $this->showSummaryRubrics;
    }

    /**
     * @return bool
     */
    public function getShowClass(): bool
    {
        return $this->showClass;
    }

    /**
     * @return bool
     */
    public function getShowClassOverallGrade(): bool
    {
        return $this->showClassOverallGrade;
    }

    /**
     * @return array
     */
    public function getClassWorkTypeIds(): array
    {
        return $this->classWorkTypeIds;
    }

    /**
     * @return bool
     */
    public function getShowClassGrades(): bool
    {
        return $this->showClassGrades;
    }

    /**
     * @return bool
     */
    public function getShowClassComments(): bool
    {
        return $this->showClassComments;
    }

    /**
     * @return bool
     */
    public function getShowClassRubrics(): bool
    {
        return $this->showClassRubrics;
    }

    /**
     * @return array
     */
    public function getYearLevels(): array
    {
        return $this->yearLevels;
    }

    /**
     * @return array
     */
    public function getCampuses(): array
    {
        return $this->campuses;
    }

    /**
     * @return array
     */
    public function getStudentIds(): array
    {
        return $this->studentIds;
    }

    /**
     * @return void
     */
    private function setDates(): void
    {
        if ($this->startDate !== null && $this->endDate !== null) {
            // let's just use them
            return;
        }

        if (!empty($this->termIds)) {
            $terms = $this->termMapper->findByColumn('id', $this->termIds);
            /** @var Model $term */
            foreach ($terms as $term) {
                if (!isset($startDate) || $term->getStart()->lt($startDate)) {
                    $startDate = $term->getStart();
                }
                if (!isset($endDate) || $term->getDisplayFinish()->gt($endDate)) {
                    $endDate = $term->getDisplayFinish();
                }
            }
            if (isset($startDate) && isset($endDate)) {
                $this->startDate = $startDate->getDatabaseDate();
                $this->endDate   = $endDate->getDatabaseDate();
                return;
            }
        }
        $currentTerm     = $this->termMapper->findForToday();
        $this->startDate = $currentTerm->getStart()->getDatabaseDate();
        $this->endDate   = $currentTerm->getDisplayFinish()->getDatabaseDate();
    }

    /**
     * Return all parameters
     * @return array
     */
    public function getParams(): array
    {
        $this->setDates();
        $params = [];

        if ($this->startDate) {
            $params[self::START_DATE] = $this->startDate;
        }
        if ($this->endDate) {
            $params[self::END_DATE] = $this->endDate;
        }
        $params[self::VIEW_AS] = $this->viewAs;

        // summary
        $params[self::SHOW_SUMMARY] = $this->showSummary;
        if ($this->showSummary) {
            $params[self::SHOW_OVERALL_GRADE] = $this->showSummaryOverallGrade;
            $params[self::WORK_TYPES]         = implode(',', $this->summaryWorkTypeIds);
            $params[self::SHOW_GRADES]        = $this->showSummaryGrades;
            $params[self::SHOW_COMMENTS]      = $this->showSummaryComments;
            $params[self::SHOW_RUBRICS]       = $this->showSummaryRubrics;
        }
        // class
        $params[self::SHOW_CLASS] = $this->showClass;
        if ($this->showClass) {
            $params[self::SHOW_CLASS_OVERALL_GRADE] = $this->showClassOverallGrade;
            $params[self::CLASS_WORK_TYPES]         = implode(',', $this->classWorkTypeIds);
            $params[self::SHOW_CLASS_GRADES]        = $this->showClassGrades;
            $params[self::SHOW_CLASS_COMMENTS]      = $this->showClassComments;
            $params[self::SHOW_CLASS_RUBRICS]       = $this->showClassRubrics;
        }
        return $params;
    }

    /**
     * @param array|string $idList
     * @return array
     */
    private function filter($idList): array
    {
        if (is_string($idList)) {
            $idList = explode(',', $idList);
        }
        $filteredList = array_map('intval', array_filter($idList, 'is_numeric'));
        return $filteredList ?: [];
    }
}
