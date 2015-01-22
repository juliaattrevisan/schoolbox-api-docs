<?php
/**
 * Cathedral-specific external user class
 *
 * This contains the older student user account query.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class External_Tass_Cathedral_User extends External_Tass_User implements External_UserInterface
{
    /**
     * Retrieves student user account info for sync script.
     *
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        // Get all students who have a campus, and don't have a leaving date?
        // (Maybe students who do have a leaving date should be deactivated instead)
        $query = "
            SELECT
                s.stud_code AS StudentID,
                '' AS NetworkLogin,
                '' AS StudentTitle,
                s.preferred_name AS StudentPreferred,
                s.surname AS StudentSurname,
                s.e_mail AS StudentEmail,
                '' AS StudentOccupEmail,
                s.year_grp AS StudentYearLevel,
                s.house AS StudentHouse,
                s.campus_code AS StudentCampus, *
                -- s.dol AS StudentLeavingDate
            FROM student AS s
            WHERE s.campus_code IS NOT NULL
            AND s.dol IS NULL
        ";
        $result = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $result;
    }
}
