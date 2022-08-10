<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course\Export;

use Alaress\Web\Controller\Base;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpecification;

/**
 * Controller for exporting assessments into courses.
 */
class Controller extends Base
{
    /**
     * Post `$assessment` into `$unitSpec`.
     *
     * This will always create a new assessment within `$unitSpec`, regardless
     * of whether one already exists.
     *
     * @api POST /course/export/unitspecs/{unitSpecId}/assessment/{assessmentId}
     * @since 22.1
     *
     * @param CourseSpecification\Unit\Model $unitSpec
     * @param IAssessable                    $assessment
     * @return void
     *
     */
    public function postAssessmentIntoUnit(
        CourseSpecification\Unit\Model $unitSpec,
        IAssessable $assessment,
    ): void {
    }


    /**
     * Put `$assessment` into `$unitSpec`.
     *
     * If `$assessment` has a spec assessment which is within `$unitSpec`,
     * attempt to override the existing spec assessment with whatever is in
     * `$assessment`: otherwise, this should return an error.
     *
     * @api PUT /course/export/unitspecs/{unitSpecId}/assessment/{assessmentId}
     * @since 22.1
     *
     * @param CourseSpecification\Unit\Model $unitSpec
     * @param IAssessable                    $assessment
     * @return void
     */
    public function putAssessmentIntoUnit(
        CourseSpecification\Unit\Model $unitSpec,
        IAssessable $assessment,
    ): void {
    }
}
