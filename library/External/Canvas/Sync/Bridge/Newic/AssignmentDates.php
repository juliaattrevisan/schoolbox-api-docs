<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Newic;

class AssignmentDates extends \External\Canvas\Sync\Bridge\AssignmentDates
{
    /**
     * @inheritDoc
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
            // returning null, to avoid importing this assessment
            $dueDate = null;
        }
        $closeDate = $lockAt ?? $dueDate;

        return [$publishDate, $openDate, $dueDate, $closeDate];
    }
}
