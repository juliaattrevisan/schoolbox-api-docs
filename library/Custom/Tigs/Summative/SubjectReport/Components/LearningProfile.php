<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Tigs\Summative\SubjectReport\Components;

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
class LearningProfile extends AbstractMatrix
{
}
