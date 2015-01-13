<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2013 Alaress Pty Ltd			    	*
*                                                           *
*	This file provides access to TASS user info		        *
*                                                           *
************************************************************/

class External_Tass_User implements External_UserInterface
{
    const SPLIT_PARENT_TASSID_LENGTH = 8;

    /**
    * @var MSSQL
    */
    private $database;

    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());

        $this->groupHandler = new Group;
    }

    function getParent($synID)
    {

    }


    /**
     * Get all the parents of the students id
     *
     * @param array $synergyIds
     *
     * @return array Currently returns empty list, not yet implemented.
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = array();
        $idList    = implode(',', array_map('intval', $synergyIds));
        if (empty($idList)) {
            return array();
        }
        $query = "
            SELECT pa.par_code, pa.e_mail
            FROM paraddress pa
            INNER JOIN parent p ON pa.par_code=p.par_code AND pa.cmpy_code=p.cmpy_code
            INNER JOIN student s ON p.par_code=s.par_code AND p.cmpy_code=s.cmpy_code
            WHERE s.stud_code IN ($idList)";
        $this->getDatabase()->query($query);
        while ($row = $this->getDatabase()->getNextArray()) {
            $emailList[$row['par_code']] = trim($row['e_mail']);
        }
        return $emailList;
    }

    function getStudent($tassID)
    {
        $query = "
			SELECT
				s.stud_code AS StudentID,
				s.preferred_name AS StudentGiven1,
				s.surname AS StudentSurname,
				s.dob as StudentBirthDate,
				'' AS StudentTutorName,
				'' AS StudentHeadOfHouse,
				'' AS HeadOfHouseID,
				t.tch_name AS StudentFormStaffName,
				'' AS StudentTutorDescription,
				'' AS TeacherFormID,
				s.form_cls AS StudentForm,
				t.tch_code AS TeacherTutorID,
				'' AS StudentTutor,
				'' AS FormDescription,
				h.hse_name AS StudentHouseDescription,
				s.house AS StudentHouse,
				s.year_grp AS StudentYearLevel,
				'' AS StudentYearLevelCoordinator,
				'' AS YearLevelCoordID,
				'' AS StudentLockerBook,
				'' AS StudentEmergencyName,
				'' AS StudentEmergencyPhone,
				'' AS StudentEmergencyPhoneAlt,
				'' AS StudentEmergency2Name,
				'' AS StudentEmergency2Phone,
				'' AS StudentEmergency2PhoneAlt,
				'' AS StudentEmergency3Name,
				'' AS StudentEmergency3Phone,
				'' AS StudentEmergency3PhoneAlt,
				'' AS StudentMedicalAlert,
				'' AS StudentSpecialNeedsFlag,
				'' AS AlertMessage,
				boarder AS StudentBoarder
			FROM student AS s
			LEFT JOIN house AS h ON s.house=h.hse_code AND s.cmpy_code=h.cmpy_code
			-- LEFT JOIN parent AS p ON s.par_code=p.par_code AND s.cmpy_code=p.cmpy_code
			LEFT JOIN teacher AS t ON s.pctut_grp=t.pctut_grp AND s.cmpy_code=t.cmpy_code
			WHERE stud_code = '$tassID'
		";
        $student = $this->getDatabase()->trimRow($this->getDatabase()->getRow($query));
        if (preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches)) {
            $student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);
        } else {
            $student['StudentBirthDate'] = false;
        }
        return $student;
    }

    function getStaff($tassID)
    {
        $query = "
            SELECT *
            FROM tel_vw_telemf
        ";
    }

    function getStudentContactList($tassID)
    {

        $query = "SELECT  'P' + student.par_code as StudentContactID,
                          parent.f_name StudentContactPreferred,
                          addr1 StudentContactAddress1,
                          addr2 StudentContactAddress2,
                          addr3 StudentContactAddress3,
                          town_sub StudentContactSuburb,
                          state_code StudentContactState,
                          post_code StudentContactPostCode,
						  home_phone as StudentContactPhoneActual,
						  bus_phone as StudentContactOccupPhone,
						  mobile1 as StudentContactMobilePhone,
						  parent.m_name as StudentContactSpousePreferred,
                          mobile2 as StudentContactSpouseMobilePhone
						FROM student
						JOIN parent ON student.par_code = parent.par_code AND student.cmpy_code = parent.cmpy_code
						JOIN paraddress ON parent.par_code = paraddress.par_code AND paraddress.cmpy_code = parent.cmpy_code AND add_num = 1
						WHERE stud_code = '$tassID'
					";

        $parent = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));

        return $parent;
    }

    function getCurrentSemester($exteralUserId = null)
    {
        $query = "SELECT curr_year, curr_sem FROM ttparms WHERE cmpy_code = 01";
        $current = $this->getDatabase()->getRow($query);
        $_SESSION['FileSemester'] = $current['curr_sem'];
        $_SESSION['FileYear'] = $current['curr_year'];
    }

    function getSubjectList($tassCode, $userID)
    {
     /* Ensure a filesemester is set */
        if (!(isset($_SESSION['FileSemester']) && isset($_SESSION['FileYear']))) {
            $this->getCurrentSemester($tassCode);
        }
        if (empty($tassCode)) {
            return false;
        }
        if ($this->groupHandler->isStudent($userID)) {
            $sql = "SELECT class, studsub.cmpy_code, semester, studsub.sub_code, year_grp, sub_desc, sub_type
					FROM studsub
					JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
					JOIN subtab ON subtab.sub_code=studsub.sub_code AND subtab.cmpy_code=studsub.cmpy_code
					WHERE studsub.stud_code = '$tassCode' AND semester = '{$_SESSION['FileSemester']}' AND yr_study = '{$_SESSION['FileYear']}'
					ORDER BY studsub.sub_code ASC, studsub.class ASC
			";
        } else {
            $sql = "SELECT class, tchsub.cmpy_code, semester, tchsub.sub_code, year_num, year_grp, sub_desc, sub_type
					FROM tchsub
					JOIN subtab ON subtab.sub_code=tchsub.sub_code AND subtab.cmpy_code=tchsub.cmpy_code
					WHERE tch_code = '$tassCode' AND semester = '{$_SESSION['FileSemester']}' AND year_num = '{$_SESSION['FileYear']}'
					ORDER BY tchsub.sub_code ASC, tchsub.class ASC
			";
        }
        $this->getDatabase()->query($sql);
        $subjectList = array();
        while ($subject = $this->getDatabase()->trimRow($this->getDatabase()->getNextArray())) {
            if (Cfg::get('site_location') == 'meriden') {
                $subject['year_grp'] = '';
            }
            $subjectList[] = array('code'=>$subject['year_grp'].'-'.$subject['sub_code'].'-'.$subject['class'], 'name'=>trim($subject['sub_desc']));
        }
        if (is_array($subjectList)) {
            return $subjectList;
        } else {
            return false;
        }
    }

    /**
    * Get enrolled students
    *
    * @param $tassID
    * @return mixed
    */
    public function getEnrolledStudents($tassID)
    {

        /* If the TassID is 8 characters long then it is assumed to be
		 * someone in the split-parent scenario.
		 */

        // id has to have a [P]arent prefix (or [S]pouse)
        if (!preg_match('#(?:P|S)[0-9]+#s', $tassID)) {
            return array();
        }

        // remove the P prefix
        $id = substr($tassID, 1);
        if (strlen($id) == self::SPLIT_PARENT_TASSID_LENGTH) {
            $parentCode = substr($id, 0, 7);
            $parentNum = $id[self::SPLIT_PARENT_TASSID_LENGTH-1];

            $query = "
                SELECT stud_codes AS RelatedID
                FROM pia_sfaccess
                WHERE cmpy_code = '01'
                AND par_code = '$parentCode'
                AND sfa_num = '$parentNum'
            ";

            $return = array();
            $result = $this->getDatabase()->getAll($query);

            // parse the array, because this data is stored in a funky way
            if (count($result)) {
                foreach ($result as $row) {
                    // data is stored in a single field, comma separated
                    $list = explode(',', $row['RelatedID']);
                    foreach ($list as $childID) {
                        $return[] = array('RelatedID' => $childID);
                    }
                }
            }
        } else {
            $query = "
				SELECT stud_code AS RelatedID
				FROM student
				WHERE par_code = '$id'
				AND cmpy_code = '01'
			";
            $return = $this->getDatabase()->getAll($query);
        }

        return $return;

    }

    /**
    * Determine the boarder type based on a value
    * Returns a human-readable string
    *
    * @param $boarderValue
    * @return string
    */
    public function determineBoarderType($boarderValue)
    {
        $boarders = array('Y');

        if (!strlen($boarderValue)) {
            return '';
        } elseif (in_array($boarderValue, $boarders)) {
            return 'Boarder';
        }

        return 'Day';
    }

    /**
     * Returns a local path to the user's portrait
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {

        /* Try several different combinations it has to be one of these */
        $portraitPathList = array( realpath( Config::get('portrait_dir') ) .'/'.$externalUserId.".jpg",
                                   realpath( Config::get('portrait_dir') ) .'/'.strtolower($externalUserId).".jpg",
                                   realpath( Config::get('portrait_dir') ) .'/'.strtoupper($externalUserId).".jpg",
        );

        foreach($portraitPathList as $portraitPath) {
            if (is_readable($portraitPath)) {
                return $portraitPath;
            }
        }
        return false;
    }

    public function getEmail($id)
    {
    }

    public function getStaffUserAccountInfo()
    {

        // Include Synergetic fields, and attempt to perform data normalisation
        // using extra fields.
        $query = "
            SELECT
                t.tch_code AS StaffID,
                '' AS NetworkLogin,
                t.curr_flg AS ActiveFlag,
                '' AS StaffTitle,
                '' AS StaffPreferredName,
                t.surname AS StaffSurname,
                t.tch_name AS StaffFullName,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                t.e_mail AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                td.tch_dept AS StaffDepartment,
                *
            FROM teacher AS t
            JOIN tchdept AS td ON t.tch_code = td.tch_code
            WHERE t.curr_flg = 'Y'
        ";

        $staffList = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $staffList;
    }

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

    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
    }

    public function getParentSync($externalUserId)
    {
    }

    public function getParentUserAccountInfo()
    {
        // status of TASS support for single, gay, transgendered or polygamous
        // parents - unknown
        // par_code: most likely login
        // According to getEnrolledStudents(): parent codes are prefixed with 'P'
        // There seems to be no separate accounts for separate parents, maybe
        // I'll have to create one.
        $query = "
            SELECT
                p.par_code AS StudentContactID,
                '' AS StudentContactTitle,
                p.f_name AS StudentContactPreferred,
                p.surname AS StudentContactSurname,
                '' AS StudentContactDefaultEmail,

                '' AS StudentContactSpouseID,
                '' AS StudentContactSpouseTitle,
                p.m_name AS StudentContactSpousePreferred,
                '' AS StudentContactSpouseSurname,
                '' AS StudentContactSpouseDefaultEmail,

                s.year_grp AS StudentYearLevel,
                s.campus_code AS StudentCampus,
                s.sex AS StudentGender,

                p.*
            FROM parent AS p
            -- INNER JOIN pia_sfaccess AS sf
            --    ON p.par_code = sf.par_code AND p.cmpy_code = sf.cmpy_code
            INNER JOIN student as s
                ON p.par_code = s.par_code AND p.cmpy_code = s.cmpy_code
            ORDER BY p.par_code ASC
        ";
        $list = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $list;
    }

    /**
     * Get contact list for staff directory
     *
     * @return array
     */
    public function getStaffContactListExtended()
    {
        // TODO: Implement getStaffContactListExtended() method.
        return array();
    }

    private function setDatabase($database)
    {
        $this->database = $database;
    }

    protected function getDatabase()
    {
        return $this->database;
    }

    public function getHousemasterByHouseId($houseID)
    {
    }
}
