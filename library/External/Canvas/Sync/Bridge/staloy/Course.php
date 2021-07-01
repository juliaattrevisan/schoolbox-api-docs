<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\staloy;

use Alaress\Database\Predicate\Builder;

/**
 * Responsible for providing the link between a Canvas Course and a Schoolbox Folder+UnitSpec.
 */
class Course extends \External\Canvas\Sync\Bridge\Course
{
    /**
     * Returns a predicate to match Canvas Course and CourseSections that should be synchronised
     *
     * Course must:
     *   * Have workflow_state 'available' or 'completed'
     *
     * CourseSection must:
     *   * Has a SisSourceId LIKE 'Y2019C%NOT' but NOT LIKE 'Y%C%TEACHNOT' (where year is from 2019 to current calendar year + 1)
     *
     * Or the the Schoolbox Test Course: canvas_id = 3137
     *
     * @inheritDoc
     */
    public function getCanvasCoursesToImport(string $courseAlias = 'c', string $sectionAlias = 'cs'): Builder
    {
        $minYear = 2019;
        $maxYear = intval(date('Y')) + 1;  // We need to include next year for students who commence Yr 12 curriculum in T4 of Yr 11.
        $innerPb = new Builder($this->canvasDb->getQuoter(), '');
        for ($year = $minYear; $year <= $maxYear; $year++) {
            $innerPb->like("{$sectionAlias}.sis_source_id", "Y{$year}C%NOT");
        }
        $stAloyCourses = (new Builder($this->canvasDb->getQuoter(), ''))
            ->any($innerPb)
            ->notLike("{$sectionAlias}.sis_source_id", "Y%C%TEACHNOT")
            ->in("{$courseAlias}.workflow_state", ['available', 'completed']);
        $sbTestCourses = (new Builder($this->canvasDb->getQuoter(), ''))
            ->equals("{$courseAlias}.canvas_id", 3137)
            ->notNull("{$sectionAlias}.sis_source_id");

        // Uncomment this line to just import the StAloy Schoolbox test course
//        return (new Builder($this->canvasDb, ''))->all($sbTestCourses);

        $outerPb = (new Builder($this->canvasDb->getQuoter(), ''))
            ->all($stAloyCourses)
            ->all($sbTestCourses);

        return (new Builder($this->canvasDb->getQuoter(), ''))->any($outerPb);
    }
}
