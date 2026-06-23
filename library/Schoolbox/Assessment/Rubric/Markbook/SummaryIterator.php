<?php

namespace Schoolbox\Assessment\Rubric\Markbook;

use Schoolbox\Learning\Analytics\BoxPlot\Summary;

class SummaryIterator
{
    /**
     * @param array $studentMarks This is a hunk of data sharded from the Rubric Markbook calculations and reused directly
     */
    public function __construct(private array $studentMarks)
    {
    }


    /**
     * Traverses the $studentMarks data set for normalised marks on a grid-by-grid basis
     *
     * @param int $assessmentId
     * @param int $rubricInstanceId
     * @param int $capabilityId
     * @param int $indicatorId
     * @return array
     */
    public function accumulate(
        int $assessmentId,
        int $rubricInstanceId,
        int $capabilityId,
        int $indicatorId
    ): array {
        $stack = [];

        /* A direct hash lookup per person in $studentMarks...lol
         * Don't even need to mass-iterate quintic complexity to find the part we needed.
         *
         * What a planned coincidence!
         */
        foreach ($this->studentMarks as $studentId => $studentData) {
            $val = $studentData['gridData'][$assessmentId][$rubricInstanceId]['capabilities'][$capabilityId]['indicators'][$indicatorId]['published']['normalisedMark'] ?? null;

            /* Summary() already does a filtering of bad values, but still
             */
            if (null !== $val) {
                $stack[] = $val;
            }
        }

        return (new Summary($stack))->getSummary();
    }
}
