<?php

/**
 *
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

interface External_AttendanceLinkInterface extends External\IConnector
{
    /**
     * Returns a link the user can follow to mark attendance in their SIS
     *
     * @param string              $classCode
     * @param string|null         $campus
     * @param string|null         $fileType
     * @param array               $attendancePeriods
     * @param int|null            $currentPeriod
     * @param Alaress_Date_Format $time
     * @param GenericCollection   $campusList
     * @param int                 $campusId
     * @param string|null         $externalId
     * @param int                 $dayNumber
     * @return string
     */
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string;

    /**
     * Returns how the attendance link should be opened.
     *
     * @return string One of ['_self', '_blank']
     */
    public function getAttendanceLinkTarget(): string;

    /**
     * Sets basic configurations
     * @param IConfigLoader $config
     * @param bool          $isStaff
     */
    public function init(IConfigLoader $config, bool $isStaff): void;

    /**
     * Returns whether attendance link is enabled for this class
     * @param string              $classCode
     * @param bool|null           $attendanceEnabled
     * @param Alaress_Date_Format $time
     * @return bool
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool;
}
