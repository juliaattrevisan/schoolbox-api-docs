<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\TassApi\Base\Endpoint;

use Alaress_Date_Format;
use Schoolbox\GenericCollection;
use Schoolbox\IConfigLoader;

/**
 * Class AttendanceLink
 *
 * @package Schoolbox\External\Connector\TassApi\Base\Endpoint
 */
class AttendanceLink implements \External_AttendanceLinkInterface
{

    /**
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
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string
    {
        // TODO: Implement getAttendanceLink() method.



        return '';
    }


    /**
     * @return string
     */
    public function getAttendanceLinkTarget(): string
    {
        // TODO: Implement getAttendanceLinkTarget() method.

        return '';
    }


    /**
     * @param IConfigLoader $config
     * @param bool          $isStaff
     */
    public function init(IConfigLoader $config, bool $isStaff): void
    {
        // TODO: Implement init() method.
    }


    /**
     * @param string              $classCode
     * @param bool|null           $attendanceEnabled
     * @param Alaress_Date_Format $time
     * @return bool
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool
    {
        // TODO: Implement isEnabled() method.

        return false;
    }
}