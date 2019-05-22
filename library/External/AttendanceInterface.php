<?php

/**
 * External_AttendanceInterface
 *
 * Interface for External Attendances, to mark who was absent/present for
 * a particular class
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
interface External_AttendanceInterface extends External\IConnector
{

    /**
     * Returns a list of attendees for a particular class, date and period
     *
     * @return array
     */
    public function findAttendanceRecords(): array;


    /**
     * Returns a list of options for the absence type drop-down
     *
     * @return array
     */
    public function findAbsenceDropdownOptions(): array;


    /**
     * Returns list of options for the attendance reason drop-down
     *
     * @return array
     */
    public function findReasonDropdownOptions(): array;
}
