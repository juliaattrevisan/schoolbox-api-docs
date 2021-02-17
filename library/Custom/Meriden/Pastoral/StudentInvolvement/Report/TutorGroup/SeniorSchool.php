<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Meriden\Pastoral\StudentInvolvement\Report\TutorGroup;

/**
 * Representation of report data for a senior school student's tutor group.
 *
 * The senior school variant contains endeavor grades for all of the student's
 * subjects for that year.
 */
class SeniorSchool extends Base
{
    /**
     * A list of endeavor grades, collated for this report.
     */
    private array $endeavorGrades;

    /**
     * Collate the endeavor grades out of `$allGrades`, and inject them into
     * `$this->endeavorGrades`.
     *
     * @param array $allGrades
     * @return $this
     */
    public function injectEndeavorGrades(array $allGrades): self
    {
        return $this;
    }

    /**
     * @return array
     */
    public function getEndeavorGrades(): array
    {
        return $this->endeavorGrades;
    }
}
