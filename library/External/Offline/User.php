<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

class External_Offline_User implements External_UserInterface
{

    public function getParent($externalUserId)
    {
        // Return nothing
    }

    public function getParentSync($externalUserId)
    {
        // Return nothing
    }

    public function getParentEmails($synergyIds)
    {
        // Return nothing
    }

    public function getStudent($externalUserId)
    {
        // Return nothing
    }

    public function getStaff($externalUserId)
    {
        // Return nothing
    }

    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        // Return nothing
    }

    public function getStudentContactList($externalUserId)
    {
        // Return nothing
    }

    public function getCurrentSemester($externalUserId = null)
    {
        return null;
    }

    public function getCurrentYear($externalUserId = null)
    {
        return null;
    }

    public function getSubjectList($externalUserId, $userId)
    {
        return array();
    }

    public function getEnrolledStudents($externalUserId)
    {
        // Return nothing
    }

    public function getEmail($externalUserId)
    {
        // Return nothing
    }

    public function getPortrait($externalUserId)
    {
        // Return nothing
    }

    public function getStudentUserAccountInfo()
    {
        // Return nothing
    }

    public function getParentUserAccountInfo()
    {
        // Return nothing
    }

    public function getStaffUserAccountInfo()
    {
        // Return nothing
    }

    public function determineBoarderType($boarderValue)
    {
        // Return nothing
    }

    public function getHousemasterByHouseId($houseID)
    {
        // Return nothing
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        // Return nothing
        return array();
    }

    /**
     * Not yet implemented for this connector
     *
     * @return array
     */
    public function getStudentForms()
    {
        return [];
    }

    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return array();
    }

}
