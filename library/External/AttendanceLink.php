<?php

/**
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\IConfigLoader;
use Schoolbox\GenericCollection;

class External_AttendanceLink implements External_AttendanceLinkInterface
{
    /** @var External_AttendanceLinkInterface */
    private $database;

    /**
     */
    public function __construct(IConfigLoader $config)
    {
        $this->database = (new External_DatabaseFactory())->buildAttendanceLinkConnector($config);
    }

    /**
     * @inheritDoc
     */
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string
    {
        return $this->database->getAttendanceLink($classCode, $campus, $fileType, $attendancePeriods, $currentPeriod, $time, $campusList, $campusId, $externalId, $dayNumber);
    }

    /**
     * @inheritDoc
     */
    public function getAttendanceLinkTarget(): string
    {
        return $this->database->getAttendanceLinkTarget();
    }

    /**
     * @inheritDoc
     */
    public function init(IConfigLoader $config, bool $isStaff): void
    {
        $this->database->init($config, $isStaff);
    }

    /**
     * @inheritDoc
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool
    {
        return $this->database->isEnabled($classCode, $attendanceEnabled, $time);
    }
}
