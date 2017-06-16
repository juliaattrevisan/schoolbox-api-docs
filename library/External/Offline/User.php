<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\User;

class External_Offline_User implements External_UserInterface
{
    /**
     * @inheritdoc
     */
    public function getParent($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getParentSync($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getParentEmails($synergyIds)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudent($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStaff($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getCurrentSemester($externalUserId = null)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear($externalUserId = null)
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function getSubjectList(User $user)
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getEnrolledStudents($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getEmail($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getPortrait($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStudentUserAccountInfo()
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getParentUserAccountInfo()
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getStaffUserAccountInfo()
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function determineBoarderType($boarderValue)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterByHouseId($houseID)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails($externalUserId)
    {
        // Return nothing
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @return array
     */
    public function getStudentForms()
    {
        return [];
    }

    /**
     * @inheritdoc
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return [];
    }
}
