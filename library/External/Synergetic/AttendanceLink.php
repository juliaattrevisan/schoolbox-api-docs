<?php

/**
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

class External_Synergetic_AttendanceLink implements External_AttendanceLinkInterface
{
    /**
     * @var int
     */
    private $externalAttendanceDays;

    /**
     * @var bool
     */
    private $isStaff;

    /**
     * @inheritDoc
     */
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string
    {
        if (empty($attendancePeriods)) {
            return '';
        }
        $encodedClass = urlencode(urlencode(urlencode($classCode)));
        $periodLink   = implode(',', $attendancePeriods);
        $classLink    = $campus ? $campus . '/' . $encodedClass : '0/' . $encodedClass;
        return "/modules/attendance/modify/{$classLink}/{$time->getDatabaseDate()}/{$periodLink}/{$campusList[$campusId]->getExtTimetableCode()}/{$fileType}";
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
        // load configuration
        $this->externalAttendanceDays = $config->get('external_attendance_days');
        $this->isStaff                = $isStaff;
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool
    {
        if (!$this->isStaff || ('' === $classCode) || empty($attendanceEnabled)) {
            return false;
        }

        $now = new Alaress_Date_Format();
        $now->endOfDay();

        $from = new Alaress_Date_Format();
        $from->startOfDay();
        $from->addDays($this->externalAttendanceDays * -1);

        return $time->gte($from) && $time->lte($now);
    }
}
