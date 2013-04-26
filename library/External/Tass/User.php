<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*
*                                                           *
************************************************************/

class External_Tass_User implements External_UserInterface {
	const SPLIT_PARENT_TASSID_LENGTH = 8;

	/**
	 * @var MSSQL
	 */
	private $database;

	function __construct () {
		$this->setDatabase( new MSSQL() );
		$this->getDatabase()->setConvertEncoding(false);

		$this->groupHandler = new Group;
    }

    function getParent($synID) {

    }


    /**
     * Get all the parents of the students id
     *
     * @param $synergyIds
     *
     * @return array|bool
     */
    public function getParentEmails($externalUserId) {

    }

	function getStudent($tassID)
	{

            $query = "SELECT stud_code, dob as StudentBirthDate, year_grp, form_cls as StudentForm, house as StudentHouseDescription, pctut_grp as StudentTutorDescription FROM student WHERE stud_code = '$tassID'";

		$student = $this->getDatabase()->getRow($query);

		preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
		$student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);
		return $student;

	}

	function getStaff($tassID)
	{

	}

	function getStudentContactList ($tassID) {

		$query = "SELECT student.par_code as StudentContactID, parent.f_name StudentContactPreferred, parent.m_name as StudentContactSpousePreferred, addr1, addr2 StudentContactAddress1, addr3, town_sub StudentContactSuburb, state_code StudentContactState, post_code StudentContactPostCode,
						home_phone as StudentContactPhoneActual, bus_phone as StudentContactOccupPhone, mob_phone as StudentContactMobilePhone
						FROM student
						JOIN parent ON student.par_code = parent.par_code AND student.cmpy_code = parent.cmpy_code
						JOIN paraddress ON parent.par_code = paraddress.par_code AND paraddress.cmpy_code = parent.cmpy_code AND add_num = 1
						WHERE stud_code = '$tassID'
					";

		$parent = $this->getDatabase()->getAll($query);
		return $parent;
	}

	function getCurrentSemester()
	{
        $query = "SELECT curr_year, curr_sem FROM ttparms WHERE cmpy_code = 01";
        $current = $this->getDatabase()->getRow($query);
        $_SESSION['FileSemester'] = $current['curr_sem'];
        $_SESSION['FileYear'] = $current['curr_year'];
	}

	function getSubjectList($tassCode, $userID)
	{
		if( $this->groupHandler->isStudent($userID) )
		{
			$sql ="SELECT class, studsub.cmpy_code, sub_code, year_grp
                                FROM studsub
                                JOIN student ON studsub.stud_code = student.stud_code AND studsub.cmpy_code=student.cmpy_code
                                WHERE studsub.stud_code = '$tassCode' AND semester = '{$_SESSION['FileSemester']}' AND yr_study = '{$_SESSION['FileYear']}'";

                }
		else
		{

			$sql = "SELECT class, cmpy_code, semester, sub_code, year_num, year_grp
                                FROM tchsub
                                WHERE tch_code = '$tassCode' AND semester = '{$_SESSION['FileSemester']}' AND year_num = '{$_SESSION['FileYear']}'";
		}

		$this->getDatabase()->query($sql);
		while($subject = $this->getDatabase()->getNextArray() ) {
			$subjectList[] = array('code'=>trim($subject['year_grp'].'-'.$subject['sub_code'].'-'.$subject['class']), 'name'=>$subject['class']);
		}

		if(is_array($subjectList))
			return $subjectList;
		else
			return false;
	}

	/**
	 * Get enrolled students
	 *
	 * @param $tassID
	 * @return mixed
	 */
	public function getEnrolledStudents($tassID) {

		/* If the TassID is 8 characters long then it is assumed to be
		 * someone in the split-parent scenario.
		 */

        // id has to have a [P]arent prefix
        if(!preg_match('#P[0-9]+#s', $tassID)) {
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
            if(count($result)) {
                foreach ($result as $row) {
                    // data is stored in a single field, comma separated
                    $list = explode(',', $row['RelatedID']);
                    foreach($list as $childID) {
                        $return[] = array('RelatedID' => $childID);
                    }
                }
            }
        }
        else {
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
	public function determineBoarderType($boarderValue) {
		$boarders = array('Y');

		if (!strlen($boarderValue)) {
			return '';
		}
		else if (in_array($boarderValue, $boarders)) {
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
    public function getPortrait ($externalUserId) {

        $portraitPath = realpath( Config::get('portrait_dir') ) . "/".strtolower($externalUserId).".jpg";

        if ( is_readable($portraitPath) ) {
            return $portraitPath;
        }
        else {
            return false;
        }
    }

    public function getEmail ($id) {
    }

	public function getStaffUserAccountInfo () {
	}

	public function getStudentUserAccountInfo () {
	}

	public function getStudentContactListExtended ($yearlevel, $name, $boardingStatus, $form) {
	}

	public function getParentSync ($externalUserId) {
	}

	public function getParentUserAccountInfo () {
	}

	private function setDatabase ($database) {
		$this->database = $database;
	}

	private function getDatabase () {
		return $this->database;
	}

    public function getHousemasterByHouseId($houseID) {
    }


}


?>