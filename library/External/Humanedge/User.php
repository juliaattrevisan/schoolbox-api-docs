<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

/**
 * HumanEdge implementation of user retrieve methods.
 */
class External_Humanedge_User implements External_UserInterface
{
    /**
     * @var MSSQL $database
     */
    private $database;

    /**
     * @var External_UserInterface
     */
    private $externalUser;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->externalUser = new External_User('humanedge');
    }

    /**
     * @inheritDoc
     */
    public function getStudent($externalUserId)
    {
        // TODO: Implement getStudent() method.
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactList($externalUserId)
    {
        // TODO: Implement getStudentContactList() method.
    }

    /**
     * @inheritDoc
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        // TODO: Implement getStudentContactListExtended() method.
    }

    /**
     * @inheritDoc
     */
    public function getStudentUserAccountInfo()
    {
        // TODO: Implement getStudentUserAccountInfo() method.
    }

    /**
     * @inheritDoc
     */
    public function getParentEmails($synergyIds)
    {
        // TODO: Implement getParentEmails() method.
    }

    /**
     * @inheritDoc
     */
    public function getParent($externalUserId)
    {
        // TODO: Implement getParent() method.
    }

    /**
     * @inheritDoc
     */
    public function getParentSync($externalUserId)
    {
        // TODO: Implement getParentSync() method.
    }

    /**
     * @inheritDoc
     */
    public function getParentUserAccountInfo()
    {
        // TODO: Implement getParentUserAccountInfo() method.
    }

    /**
     * @inheritDoc
     */
    public function getStaff($externalUserId)
    {
        // TODO: Implement getStaff() method.
    }

    /**
     * @inheritDoc
     */
    public function getStaffUserAccountInfo()
    {
        // TODO: Implement getStaffUserAccountInfo() method.
    }

    /**
     * @inheritDoc
     */
    public function getStaffContactListExtended()
    {
        // TODO: Implement getStaffContactListExtended() method.
    }

    /**
     * @inheritDoc
     */
    public function getCurrentSemester($externalUserId = null)
    {
        // TODO: Implement getCurrentSemester() method.
    }

    /**
     * @inheritDoc
     */
    public function getCurrentYear($externalUserId = null)
    {
        // TODO: Implement getCurrentYear() method.
    }

    /**
     * @inheritDoc
     */
    public function getSubjectList($externalUserId, $userId)
    {
        // TODO: Implement getSubjectList() method.
    }

    /**
     * @inheritDoc
     */
    public function getEnrolledStudents($externalUserId)
    {
        // TODO: Implement getEnrolledStudents() method.
    }

    /**
     * @inheritDoc
     */
    public function getEmail($externalUserId)
    {
        // TODO: Implement getEmail() method.
    }

    /**
     * @inheritDoc
     */
    public function getPortrait($externalUserId)
    {
        // TODO: Implement getPortrait() method.
    }

    /**
     * @inheritDoc
     */
    public function determineBoarderType($boarderValue)
    {
        // TODO: Implement determineBoarderType() method.
    }

    /**
     * @inheritDoc
     */
    public function getHousemasterByHouseId($houseID)
    {
        // TODO: Implement getHousemasterByHouseId() method.
    }

    /**
     * @inheritDoc
     */
    public function getCampusFromTimetable($staffID)
    {
        // TODO: Implement getCampusFromTimetable() method.
    }

    /**
     * @inheritDoc
     */
    public function getStudentForms()
    {
        // TODO: Implement getStudentForms() method.
    }

    /**
     * @param MSSQL $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }
}
