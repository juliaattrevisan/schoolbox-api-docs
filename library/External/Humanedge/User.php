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
        // Smoosh all of the contacts into one
        $query = "
            SELECT
                s.Code AS StudentID,
                s.PreferredName AS StudentGiven1,
                s.LastName AS StudentSurname,
                s.DOB AS StudentBirthDate,
                '' AS StudentHouseDescription,
                s.House AS StudentHouse,
                '' AS HeadOfHouseID,
                '' AS StudentHeadOfHouse,
                s.BoarderType AS StudentBoarder
                s.Form AS StudentForm,
                '' AS FormDescription,
                '' AS StudentFormStaffName,
                '' AS StudentTutor,
                '' AS StudentTutorName,
                '' AS StudentTutorDescription,
                '' AS TeacherFormID,
                '' AS TeacherTutorID,
                s.Year AS StudentYearLevel,
                '' AS YearLevelCoordID,
                '' AS StudentYearLevelCoordinator,
                '' AS StudentLockerBook,
                (c.PreferredName + ' ' + c.LastName) AS StudentEmergencyName,
                c.PhoneHome AS StudentEmergencyPhone,
                c.PhoneWork AS StudentEmergencyPhoneAlt,
                '' AS StudentMedicalAlert,
                s.SpecialNeeds AS StudentSpecialNeedsFlag,
                '' AS StudentStudiesCode,
                '' AS AlertMessage
            FROM Student s
            LEFT JOIN StudentContact sc ON s.ID = sc.StudentID
            LEFT JOIN Contact c ON sc.ContactID = c.ID
            WHERE s.Code = {$this->getDatabase()->smartQuote($externalUserId)}
        ";
        $result = [];
        foreach ($this->getDatabase()->getAll($query) as $i => $row) {
            if (empty($result)) {
                $result = $row;
            } else {
                $result["StudentEmergency{$i}Name"]     = $row['StudentEmergencyName'];
                $result["StudentEmergency{$i}PhoneAlt"] = $row['StudentEmergencyPhoneAlt'];
                $result["StudentEmergency{$i}Phone"]    = $row['StudentEmergencyPhone'];
            }
        }
        return $result;
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
