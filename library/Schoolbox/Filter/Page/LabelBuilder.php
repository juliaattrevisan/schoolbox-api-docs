<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Filter\Page;

/**
 * Single point of control for labels for the different pages which use filters.
 *
 * The labels are intentionally not localised, as these labels are intended for
 * use within an admin panel; thus, consistent naming is a requirement so that
 * admins are able to search for consistently named items within documentation.
 */
class LabelBuilder
{
    /**
     * Builds a label for this page.
     *
     * @param string $page One of the `Model::PAGE_*` constants
     * @return string
     */
    public function buildLabel(string $page): string
    {
        switch ($page) {
            case Model::PAGE_PENDING_ASSESSMENTS:
                return "Pending Assessments";

            case Model::PAGE_ASSESSMENT_CALENDAR:
                return "Assessment Calendar";

            case Model::PAGE_GRADES:
                return 'Grades';

            case Model::PAGE_CLASS_MARK_BOOK:
                return 'Class Markbook';

            case Model::PAGE_COURSE_MARK_BOOK:
                return 'Course Markbook';

            case Model::PAGE_UPCOMING_ASSESSMENTS:
                return "Upcoming Due Work";

            case Model::PAGE_RUBRIC_MARK_BOOK:
                return 'Rubric Markbook';

            case Model::PAGE_BADGE_LIST:
                return "Badge List";

            case Model::PAGE_AWARD_LIST:
                return "Badge Report";

            case Model::PAGE_LEARNING_ANALYTICS:
                // I don't think this filter is in use at all:
                // it does have a label though
                return "Learning Analytics";

            case Model::PAGE_CLASS_PASTORAL:
                return 'Class Pastoral';

            default:
                return '';
        }
    }
}
