<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Reports\Export;

use Schoolbox\Collection;

/**
 * Class for exporting a curriculum report as a CSV.
 */
class Csv
{
    /**
     * Constructor
     *
     * @param Collection $structure          The data structure which contains the courses and units to output.
     * @param Collection $usage              The data structure which contains the usages to output.
     * @param bool       $includeCourse      Should curriculum use on the course folder be included in count?
     * @param bool       $includeUnit        Should curriculum use on the unit folder be included in count?
     * @param bool       $includeActivity    Should curriculum use on activities (exc. lesson plans) within the unit be
     *                                       included in count?
     * @param bool       $includeLessonPlan  Should curriculum use on lesson plan activities within the unit be included
     *                                       in count?
     * @param bool       $includeRubric      Should curriculum use on rubric capabilities and indicators within the unit
     *                                       be included in count?
     * @param bool       $includeInUseRubric Should curriculum use on rubric capabilities and indicators in use on
     *                                       activities within the unit be included in count?
     */
    public function __construct(
        private Collection $structure,
        private Collection $usage,
        private bool $includeCourse,
        private bool $includeUnit,
        private bool $includeActivity,
        private bool $includeLessonPlan,
        private bool $includeRubric,
        private bool $includeInUseRubric,
    ) {
    }

    /**
     * Export the CSV to a file pointer.
     *
     * @param resource $fp
     * @return void
     */
    public function export($fp): void
    {
        $this->outputHeaderRows($fp);
        foreach ($this->usage as $node) {
            $this->outputCurriculumNodeRows($fp, $node);
        }
    }


    /**
     * Output the headers for a set of courses and units as a set of CSV rows.
     *
     * @param resource $fp The file pointer to output to.
     * @return void
     */
    private function outputHeaderRows($fp): void
    {
        $headerRows    = [''];
        $subheaderRows = ['Curriculum'];
        foreach ($this->structure as $course) {
            $headerRows[]    = 'Course';
            $subheaderRows[] = $course['name'];
            $unitRowOutput   = false;
            foreach ($course['units'] as $unit) {
                $headerRows[]    = $unitRowOutput ? '' : 'Unit';
                $subheaderRows[] = $unit['name'];
                $unitRowOutput   = true;
            }
        }
        fputcsv($fp, $headerRows);
        fputcsv($fp, $subheaderRows);
    }

    /**
     * Output a curriculum node and its children in a set of CSV rows.
     *
     * @param resource   $fp        The file pointer to output to.
     * @param array      $usage     The data structure which contains the usages
     *                              to output.
     *                              This also contains the course and unit
     *                              structure, but not in a reliable order.
     * @param int        $depth     The current depth of the curriculum node
     *                              traversal: required for controlling the
     *                              output of the curriculum node.
     * @return void
     */
    private function outputCurriculumNodeRows($fp, array $usage, int $depth = 0): void
    {
        $curriculumName = $usage['name'];
        if ($usage['code']) {
            $curriculumName = sprintf('[%s] %s', $usage['code'], $curriculumName);
        }
        if ($depth > 0) {
            $curriculumName = sprintf('%s %s', str_repeat('*', $depth), $curriculumName);
        }
        $row = [$curriculumName];

        // Not assuming that courses in usage are in same order as in structure:
        // instead, iterate the structure and extract usages from `$usage`
        foreach ($this->structure as $course) {
            $courseUsage = $usage['usage']['courses']->filter(fn ($c) => $course['id'] === $c['id'])->first();

            // Calculate course usage count
            $courseUsageCount = $this->includeCourse
                ? ($courseUsage['usage']['course']['count'] ?? 0)
                : 0;
            $row[] = $courseUsageCount;

            // Calculate unit usage count, dependent on selected filters
            foreach ($course['units'] as $unit) {
                $unitUsage      = $courseUsage['units']->filter(fn ($u) => $unit['id'] === $u['id'])->first();
                $unitUsageCount = 0;
                if ($this->includeUnit) {
                    $unitUsageCount += ($unitUsage['usage']['unit']['count'] ?? 0);
                }
                if ($this->includeActivity) {
                    $unitUsageCount += ($unitUsage['usage']['activity']['count'] ?? 0);
                }
                if ($this->includeLessonPlan) {
                    $unitUsageCount += ($unitUsage['usage']['lessonPlan']['count'] ?? 0);
                }
                if ($this->includeRubric) {
                    $unitUsageCount += ($unitUsage['usage']['rubric']['count'] ?? 0);
                }
                if ($this->includeInUseRubric) {
                    $unitUsageCount += ($unitUsage['usage']['inUseRubric']['count'] ?? 0);
                }
                $row[] = $unitUsageCount;
            }
        }
        fputcsv($fp, $row);

        // And output usage for child nodes
        foreach ($usage['children'] ?? [] as $childNode) {
            $this->outputCurriculumNodeRows($fp, $childNode, $depth + 1);
        }
    }
}
