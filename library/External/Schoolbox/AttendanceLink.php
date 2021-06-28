<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\IConfigLoader;


/**
 * Class External_Schoolbox_AttendanceLink
 *
 * Generate the link to the sis, so a teacher can mark a student's attendance
 * in a particular class
 */
class External_Schoolbox_AttendanceLink implements External_AttendanceLinkInterface
{

    /**
     * @var External_AttendanceLinkInterface
     */
    private External_AttendanceLinkInterface $attendanceLink;


    /**
     * Generate the link to the sis, using the parts provided
     *
     * @inheritDoc
     *
     * @param string                       $classCode
     * @param string|null                  $campus
     * @param string|null                  $fileType
     * @param array                        $attendancePeriods
     * @param int|null                     $currentPeriod
     * @param Alaress_Date_Format          $time
     * @param \Schoolbox\GenericCollection $campusList
     * @param int                          $campusId
     * @param string|null                  $externalId
     * @param int                          $dayNumber
     * @return string
     */
    public function getAttendanceLink(string $classCode, ?string $campus, ?string $fileType, array $attendancePeriods, ?int $currentPeriod, Alaress_Date_Format $time, \Schoolbox\GenericCollection $campusList, int $campusId, ?string $externalId, int $dayNumber): string
    {
        return $this->attendanceLink->getAttendanceLink(
            $classCode,
            $campus,
            $fileType,
            $attendancePeriods,
            $currentPeriod,
            $time,
            $campusList,
            $campusId,
            $externalId,
            $dayNumber
        );
    }


    /**
     * Decide whether this should open in a new window or the current window
     *
     * @inheritDoc
     *
     * @return string
     */
    public function getAttendanceLinkTarget(): string
    {
        return $this->attendanceLink->getAttendanceLinkTarget();
    }


    /**
     * Set up all the dependencies that allow this external to function properly
     * SUDS connectors will need the Endpoint Data Source to decide how to continue
     *
     * @inheritDoc
     *
     * @param IConfigLoader $config
     * @param bool          $isStaff
     */
    public function init(IConfigLoader $config, bool $isStaff): void
    {
        /* Any of the SUDS importers will be using the Schoolbox connector
         * but they'll all generate AttendanceLink information differently
         * We need to decide which of the AttendanceLink generators should
         * spawn - it should correspond to the Endpoint Data Source that's
         * current selected in the suds ui
         */
        $this->attendanceLink = (new AttendanceLinkFactory())
            ->produce($config->get('schooldata_import_data_source'));
    }


    /**
     * Returns whether attendance link is enabled for this class
     *
     * @param string              $classCode
     * @param bool|null           $attendanceEnabled
     * @param Alaress_Date_Format $time
     * @return bool
     */
    public function isEnabled(string $classCode, ?bool $attendanceEnabled, Alaress_Date_Format $time): bool
    {
        return $this->attendanceLink->isEnabled(
            $classCode,
            $attendanceEnabled,
            $time
        );
    }
}