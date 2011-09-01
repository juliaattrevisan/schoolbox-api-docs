<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*	    
*                                                           *
************************************************************/

class ExternalUser extends MSSQL{

    function ExternalUser() {
            parent::MSSQL();
            $this->groupHandler = new Group;
    }

    function getParent($synID) {

        $query = "SELECT    StudentContactLegalFullName, StudentContactEmail, StudentContactMobilePhone,
                            StudentContactSpouseLegalFullName, StudentContactSpouseMobilePhone, StudentContactSpouseEmail,
                            StudentContactHomePhone, StudentContactSilentPhoneFlag, Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag, Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                            StudentParentsSeparatedFlag
        		  FROM
				  	SynergyOne.dbo.vStudentContactAllAddress
                    JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
                    JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
				  WHERE
				  	StudentContactID = $synID
					AND FileSemester=".$_SESSION['FileSemester']."
					AND FileYear=".$_SESSION[ 'FileYear']."
                    ";
        return $this->getRow($query);
        

    }

	function getStudent($tassID)
	{

            $query = "SELECT stud_code, dob as StudentBirthDate, year_grp, form_cls as StudentForm, house as StudentHouseDescription, pctut_grp as StudentTutorDescription FROM student WHERE stud_code = '$tassID'";
				
		$student = $this->getRow($query);

		preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
		$student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);
		return $student;
		
	}

       function getStaff($tassID)
	{
            //$query = " SELECT SchoolStaffCode, StaffBuilding, StaffRoom, StaffExtension, StaffMobilePhone
            //    FROM SynergyOne.dbo.vStaff vStaff
            //    WHERE vStaff.StaffID = $synID ";

           /* $query = "SELECT * FROM teacher WHERE tch_code = '$tassID'";

            $staff = $this->getRow($query);
        print_r($staff);

            */
            return $staff;

	}

	function getStudentContactList ($tassID) {

                $query = "SELECT parent.f_name StudentContactPreferred, parent.m_name as StudentContactSpousePreferred, addr1, addr2 StudentContactAddress1, addr3, town_sub StudentContactSuburb, state_code StudentContactState, post_code StudentContactPostCode,
                                home_phone as StudentContactPhoneActual, bus_phone as StudentContactOccupPhone, mob_phone as StudentContactMobilePhone
                                FROM student
                                JOIN parent ON student.par_code = parent.par_code AND student.cmpy_code = parent.cmpy_code
                                JOIN paraddress ON parent.par_code = paraddress.par_code AND paraddress.cmpy_code = parent.cmpy_code AND add_num = 1
                                WHERE stud_code = '$tassID'
                            ";

		$parent = $this->getAll($query);
                return $parent;
	}

	function getCurrentSemester()
	{
        $query = "SELECT curr_year, curr_sem FROM ttparms WHERE cmpy_code = 01";
        $current = $this->getRow($query);
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

		$this->query($sql);
		while($subject = $this->getNextArray() ) {
			$subjectList[] = array('code'=>trim($subject['year_grp'].'-'.$subject['sub_code'].'-'.$subject['class']), 'name'=>$subject['class']);
		}

		if(is_array($subjectList))
			return $subjectList;
		else 
			return false;
	}
	
	function getEnrolledStudents($synergyID)
	{	
		$query = "SELECT Relationships.RelatedID
				FROM SynergyOne.dbo.Constituencies Constituencies, SynergyOne.dbo.Relationships Relationships
				WHERE Relationships.RelatedID = Constituencies.ID AND 
				Relationships.ID = $synergyID AND 
				Constituencies.ConstitCode='@SC'
				AND Relationships.Relationship IN ('SN','STSN','DR','STDR','WD')";
	
		
		return $this->getAll($query);
		
	}

    function getEmail($id) {
        $query = "SELECT Email FROM Community WHERE id = $id";
        return $this->getOne($query);
    }



}


?>