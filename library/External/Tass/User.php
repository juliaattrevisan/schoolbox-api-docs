<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*	    
*                                                           *
************************************************************/

class External_Tass_User implements External_UserInterface {

	private $database;

	function __construct () {
		$this->setDatabase( new MSSQL() );

		$this->groupHandler = new Group;
    }

    function getParent($synID) {

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
	
	function getEnrolledStudents($tassID) {
        $query = "SELECT stud_code RelatedID FROM studenrol WHERE par_code = '$tassID' AND cmpy_code = 01";

		return $this->getDatabase()->getAll($query);

	}
    public function getPortrait ($externalUserId) {
    }

    public function getEmail ($id) {
    }

	public function getStaffUserAccountInfo () {
	}

	public function getStudentUserAccountInfo () {
	}

	public function getStudentContactListExtended ($yearLevel) {
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
}


?>