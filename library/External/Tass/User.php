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
    /**
     * @var MSSQL
    */
    private $database;

    /**
     * The TASS company code to use, for queries which require it.
     *
     * @var string
     */
    private $companyCode;

    function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->groupHandler = new Group;
        $this->companyCode  = Config::get('external_tass_company_code');
    }

    function getParent($synID)
    {

    }


    /**
     * Get all the parents of the students id
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = array();
        $idList    = implode(',', array_map('intval', $synergyIds));
        if (empty($idList)) {
            return array();
        }
        $query = "
            SELECT pa.par_code, pa.e_mail, pa.add_num
            FROM paraddress pa
            INNER JOIN parent p ON pa.par_code=p.par_code AND pa.cmpy_code=p.cmpy_code
            INNER JOIN student s ON p.par_code=s.par_code AND p.cmpy_code=s.cmpy_code
            WHERE s.stud_code IN ($idList)
            AND pa.add_num IN (1, 4, 5)
            ORDER BY pa.add_num ASC
        ";
        $this->getDatabase()->query($query);
        while ($row = $this->getDatabase()->getNextArray()) {
            $emailList[$row['par_code'].'-'.$row['add_num']] = trim($row['e_mail']);
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
				'' AS StudentStudiesCode,
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

    public function getCurrentSemester($externalUserId = null)
    {
        $query = "
            SELECT curr_sem
            FROM ttparms
            WHERE cmpy_code = {$this->getDatabase()->smartQuote($this->companyCode)}
        ";
        return $this->getDatabase()->getOne($query);
    }

    public function getCurrentYear($externalUserId = null)
    {
        $query = "
            SELECT curr_year
            FROM ttparms
            WHERE cmpy_code = {$this->getDatabase()->smartQuote($this->companyCode)}
        ";
        return $this->getDatabase()->getOne($query);
    }

    function getSubjectList($tassCode, $userID)
    {
        if (empty($tassCode)) {
            return false;
        }
        if ($this->groupHandler->isStudent($userID)) {
            $sql = "SELECT class, studsub.cmpy_code, semester, studsub.sub_code, year_grp, sub_desc, sub_type
					FROM studsub
					JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
					JOIN subtab ON subtab.sub_code=studsub.sub_code AND subtab.cmpy_code=studsub.cmpy_code
					WHERE studsub.stud_code = '$tassCode'
					AND semester = '{$this->database->quote($this->getCurrentSemester($tassCode))}'
					AND yr_study = '{$this->database->quote($this->getCurrentYear($tassCode))}'
					ORDER BY studsub.sub_code ASC, studsub.class ASC
			";
        } else {
            $sql = "SELECT class, tchsub.cmpy_code, semester, tchsub.sub_code, year_num, year_grp, sub_desc, sub_type
					FROM tchsub
					JOIN subtab ON subtab.sub_code=tchsub.sub_code AND subtab.cmpy_code=tchsub.cmpy_code
					WHERE tch_code = '$tassCode'
					AND semester = '{$this->database->quote($this->getCurrentSemester($tassCode))}'
					AND year_num = '{$this->database->quote($this->getCurrentYear($tassCode))}'
					ORDER BY tchsub.sub_code ASC, tchsub.class ASC
			";
        }
        $this->getDatabase()->query($sql);
        $subjectList = array();
        while ($subject = $this->getDatabase()->trimRow($this->getDatabase()->getNextArray())) {
            if (Cfg::get('external_tass_year_in_class_code') == '0') {
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
        if (!preg_match('#(?:P|S)[0-9A-Za-z]+#s', $tassID)) {
            return array();
        }

        // remove the P prefix
        $id = substr($tassID, 1);
        $splitParentLength = Config::get('external_tass_parent_id_length') + 1;
        if (strlen($id) == $splitParentLength) {
            // This is a split family parent, deal with them differently
            $parentCode = substr($id, 0, $splitParentLength-1);
            $parentNum = $id[$splitParentLength-1];

            $query = "
                SELECT
                    sf.stud_codes AS RelatedID
                FROM parent AS p
                INNER JOIN paraddress as pa
                    ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
                LEFT JOIN pia_sfaccess AS sf
                    ON p.par_code = sf.par_code
                    AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                    AND p.cmpy_code = sf.cmpy_code
                LEFT JOIN student as s
                    ON p.par_code = s.par_code
                    AND p.cmpy_code = s.cmpy_code
                WHERE sf.par_code = {$this->getDatabase()->smartQuote($parentCode)}
                AND   sf.sfa_num  = {$this->getDatabase()->smartQuote($parentNum)}
                ORDER BY p.par_code ASC
            ";

            $return = array();
            $result = $this->getDatabase()->getAll($query);

            // parse the array, because this data is stored in a funky way
            if (count($result)) {
                foreach ($result as $row) {
                    // data is stored in a single field, comma separated
                    $list = explode(',', $row['RelatedID']);
                    foreach ($list as $childID) {
                        $return[$childID] = array('RelatedID' => $childID);
                    }
                }
            }
        } else {
            $query = "
				SELECT s.stud_code AS RelatedID
				FROM student s
                LEFT JOIN parent p
                    ON  p.par_code = s.par_code
                    AND p.cmpy_code = s.cmpy_code
				WHERE s.par_code = {$this->getDatabase()->smartQuote($id)}
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

    /**
     * Retrieves staff user account info for sync script.
     *
     * @return array
     */
    public function getStaffUserAccountInfo()
    {
        // For Meriden:
        // * filter on t.curr_flg = Y
        // * filter out non-teachers
        $query = "
            SELECT
                te.tch_code AS StaffID,
                '' AS NetworkLogin,
                CASE
                    WHEN (t.term_date) IS NULL THEN 0
                    ELSE 1
                END AS ActiveFlag,
                t.salutation_flag AS StaffTitle,
                t.prefer_name_text AS StaffPreferredName,
                t.surname_text AS StaffSurname,
                '' AS StaffFullName,
                '' AS DefaultEmailCode,
                '' AS OccupEmail,
                t.e_mail AS Email,
                '' AS StaffCampus,
                '' AS StaffCategoryType,
                '' AS StaffCategory,
                td.tch_dept AS StaffDepartment,
                *
            FROM tel_vw_telemf AS t
            LEFT JOIN teacher AS te ON t.emp_code = te.emp_code
            LEFT JOIN tchdept AS td ON te.tch_code = td.tch_code
        ";
        $staffList = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $staffList;
    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        // TODO: Implement getStaffUserAccountIterator() method.
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
                s.campus_code AS StudentCampus,
                sb.resid_code AS StudentBoardingHouse,
                s.*
            FROM student AS s
            LEFT JOIN studboard AS sb ON s.stud_code = sb.stud_code AND s.cmpy_code = sb.cmpy_code
            WHERE s.dol IS NULL
        ";

        $result = $this->getDatabase()->trimAll($this->getDatabase()->getAll($query));
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        $query = "
            SELECT
                s.stud_code AS StudentID,
                '' AS NetworkLogin,
                '' AS StudentTitle,
                s.preferred_name AS StudentPreferred,
                s.surname AS StudentSurname,
                s.sex AS StudentGender,
                s.e_mail AS StudentEmail,
                '' AS StudentOccupEmail,
                s.year_grp AS StudentYearLevel,
                s.house AS StudentHouse,
                (IF ((sb.resid_code IS NOT NULL), 'Y', 'N')) AS StudentBoarder,
                sb.resid_code AS StudentBoardingHouse,
                s.campus_code AS StudentCampus
            FROM student AS s
            LEFT JOIN studboard AS sb ON s.stud_code = sb.stud_code AND s.cmpy_code = sb.cmpy_code
            WHERE s.dol IS NULL
        ";
        return new \Schoolbox\Sync\Iterator\Student(
            $this->getDatabase()->getIterator($query)->addMap([$this, 'trimRow'])
        );
    }

    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
    }

    public function getParentSync($externalUserId)
    {
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

    /**
     * Retrieves parent user account info for sync script.
     *
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        // For Meriden: ignore split families,
        // because they weren't retrieved with their original query.

        // pia_sfaccess:
        // * both add_nums and stud_codes are comma-separated lists, unfortunately
        // joining students to parents:
        // * if pia_sfaccess: join if student.stud_code is in pia_sfaccess.stud_codes
        // * otherwise: always join

        // pia_sfaccess: only join the relevant paraddress
        // no pia_sfaccess: get all paraddress
        $database = $this->getDatabase();
        $query = "
            SELECT
                p.par_code AS StudentContactID,
                '' AS StudentContactTitle,
                p.f_name AS StudentContactPreferred,
                p.surname AS StudentContactSurname,
                pa.e_mail AS StudentContactDefaultEmail,

                '' AS StudentContactSpouseID,
                '' AS StudentContactSpouseTitle,
                p.m_name AS StudentContactSpousePreferred,
                '' AS StudentContactSpouseSurname,
                '' AS StudentContactSpouseDefaultEmail,

                s.year_grp AS StudentYearLevel,
                s.campus_code AS StudentCampus,
                s.sex AS StudentGender,

                sf.sfa_num,
                sf.stud_codes AS SplitStudentIDList,

                p.*,
                pa.*
            FROM parent AS p
            INNER JOIN paraddress as pa
                ON p.par_code = pa.par_code AND p.cmpy_code = pa.cmpy_code
            LEFT JOIN pia_sfaccess AS sf
                ON p.par_code = sf.par_code
                AND (CHARINDEX(','+RTRIM(pa.add_num)+',', ','+RTRIM(sf.add_nums)+',') > 0)
                AND p.cmpy_code = sf.cmpy_code
            LEFT JOIN student as s
                ON p.par_code = s.par_code
                AND p.cmpy_code = s.cmpy_code
            ORDER BY p.par_code ASC
        ";
        $list = $database->trimAll($database->getAll($query));

        // Inject pia_sfaccess address numbers separately?

        // Inject student campuses, year levels, etc.
        $newList = array();
        foreach ($list as $listItem) {
            if (isset($listItem['StudentYearLevel'])) {
                // Base related student accounts are fetched in base query.
                $newList[] = $listItem;
            }
            if (isset($listItem['SplitStudentIDList'])) {
                // Inject pia_sfaccess children separately, because they may
                // not belong to the same parent account
                // (but they are still allowed access)
                $codes = array_map(
                    function ($code) use ($database) {
                        return "'".$database->quote($code)."'";
                    },
                    explode(',', $listItem['SplitStudentIDList'])
                );
                $splitStudentQuery = sprintf(
                    "SELECT
                        s.year_grp AS StudentYearLevel,
                        s.campus_code AS StudentCampus,
                        s.sex AS StudentGender
                    FROM student as s
                    WHERE s.stud_code IN (%s)",
                    implode(',', $codes)
                );
                foreach ($database->trimAll($database->getAll($splitStudentQuery)) as $child) {
                    $newList[] = array_merge($listItem, $child);
                }
            }
        }

        return $newList;
    }

    /**
     * {@inheritdoc}
     */
    public function getParentUserAccountIterator()
    {
        // TODO: Implement getParentUserAccountIterator() method.
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

    /**
     * Not yet implemented for this connector
     *
     * @return array
     */
    public function getStudentForms()
    {
        return [];
    }
}
