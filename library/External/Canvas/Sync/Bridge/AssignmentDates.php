<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge;

use Psr\Log\LoggerInterface;

use function strcmp;

/**
 * Responsible for mapping Canvas Assignment and Schoolbox Assessment dates
 *
 * In Schoolbox, an assessment has four dates that must occur in order.
 * A learner and their parents can only see an assessment after the publish date.
 *
 * In Canvas, an assignment has optional:
 * * due date
 * * unlock date (becomes visible to user)
 * * lock date (cannot submit after this)
 *
 * @see https://help.activelylearn.com/hc/en-us/articles/115000279574-Lock-and-unlocking-assignments
 */
class AssignmentDates
{

    /**
     * Derive the logical publish/open/due/close dates of a Canvas Assignment.
     *
     * These may not match the sequential ordering required by Schoolbox validation.
     *
     * @param string|null $unlockAt         Timestamp when Assignment is unlocked or visible to the user
     * @param string|null $dueAt            Timestamp for when the Assignment is due
     * @param bool        $allDay           TRUE if a specific for when the assignment is due was not given. The effective time will be 11:59pm
     * @param string|null $allDayDate       The date version of the due date if the all_day flag is 'true'
     * @param string|null $lockAt           Timestamp for when the Assignment is locked
     * @param string      $unitImplOpenDate Derived from Canvas CourseSection, CourseSection.EnrollmentTerm, CourseSection.Course, CourseSection.Course.EnrollmentTerm dates
     * @param string      $unitImplDueDate  Derived from Canvas CourseSection, CourseSection.EnrollmentTerm, CourseSection.Course, CourseSection.Course.EnrollmentTerm dates
     * @return array
     */
    public function assessmentDates(
        ?string $unlockAt,
        ?string $dueAt,
        bool $allDay,
        ?string $allDayDate,
        ?string $lockAt,
        string $unitImplOpenDate,
        string $unitImplDueDate
    ): array {
        $publishDate = $unlockAt ?? $unitImplOpenDate;
        $openDate    = $unlockAt ?? $unitImplOpenDate;
        if ($dueAt !== null) {
            $dueDate = $dueAt;
        } elseif ($allDay && $allDayDate !== null) {
            $dueDate = "$allDayDate 23:59:00";
        } else {
            $dueDate = $unitImplDueDate;
        }
        $closeDate = $lockAt ?? $dueDate;

        return [$publishDate, $openDate, $dueDate, $closeDate];
    }


    /**
     * Check, and possibly transform, raw translated dates to conform to schoolbox constraints.
     *
     * Schoolbox requires the four temporalState dates to be in order. The raw translated dates may not yet be.
     * Need to apply some transformations to adhere to schoolbox constraint but preserve semantics.
     *
     * These dates control two functions:
     * * visibility
     * * ability to submit
     *
     * For this current work, visibility in schoolbox is more important than the ability to submit
     *
     * @param string $publishDate
     * @param string $openDate
     * @param string $dueDate
     * @param string $closeDate
     * @param LoggerInterface $log
     * @return array
     */
    public function normaliseDates(string $publishDate, string $openDate, string $dueDate, string $closeDate, LoggerInterface $log): array
    {
        // 1. DueDate is most important, it should not change

        // 2. PublishDate controls when student/parent may see assessment, must not be after dueDate
        if (strcmp($publishDate, $dueDate) > 0) {
            $log->warning("Publish date changed from '$publishDate' to due '$dueDate'");
            $publishDate = $dueDate;
        }

        // 3. OpenDate must not be before publish date, or after due date
        if (strcmp($publishDate, $openDate) > 0) {
            $log->warning("Open date changed from '$openDate' to publish date '$publishDate'");
            $openDate = $publishDate;
        } elseif (strcmp($openDate, $dueDate) > 0) {
            $log->warning("Open date changed from '$openDate' to due '$dueDate'");
            $openDate = $dueDate;
        }

        // 4. Close date cannot be before dueDate
        if (strcmp($dueDate, $closeDate) > 0) {
            $log->warning("Close date changed from '$closeDate' to due '$dueDate'");
            $closeDate = $dueDate;
        }

        return [$publishDate, $openDate, $dueDate, $closeDate];
    }
}
