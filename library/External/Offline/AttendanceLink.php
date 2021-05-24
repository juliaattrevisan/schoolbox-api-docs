<?php

/**
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

class External_Offline_AttendanceLink implements External_AttendanceLinkInterface
{
    /**
     * @inheritDoc
     */
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getAttendanceLinkTarget(): string
    {
        return '_self';
    }

    /**
     * @inheritDoc
     */
    public function init(IConfigLoader $config, bool $isStaff): void
    {
        // do nothing
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool
    {
        return false;
    }
}
