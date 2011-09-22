<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*
*                                                           *
************************************************************/

class ExternalUser extends MSSQL{

    function __construct() {
            parent::MSSQL();
            $this->groupHandler = new Group;
    }

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student.
	 *
	 * @param <type> $synID
	 * @return <type>
	 */
    public function getParent($synID) {
        $query = "
			SELECT
				Contact.Title, Contact.Preferred, Contact.Surname, Contact.Email, Contact.MobilePhone, Contact.DirectoryIncludeFlag,
                Contact.SpouseID, Spouse.Title SpouseTitle, Spouse.Preferred SpousePreferred, Spouse.Surname SpouseSurname,
                Spouse.Email SpouseEmail, Spouse.MobilePhone SpouseMobilePhone,Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag
			FROM SynergyOne.dbo.Community Contact
			LEFT JOIN SynergyOne.dbo.Community Spouse ON Contact.SpouseID = Spouse.ID
			WHERE Contact.ID = '$synID'";

        return $this->getRow($query);
    }

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student. Anyone who is
	 * not supposed to be having their details publicised (via the
	 * DirectoryIncludeFlag) are not included in the result set.
	 *
	 * Accept array of Synergy IDs, or just an int
	 *
	 * @param <type> $synID
	 * @return <type>
	 */
    public function getParentSync($synID) {
		if (is_array($synID)) {
			$assignation = ' IN (' . implode(',', $synID) . ')';
		}
		else {
			$assignation = " = '$synID'";
		}

        $query = "
			SELECT
				ID, Title,
				Preferred, Surname,
				Email, OccupEmail, DefaultEmailCode,
				DirectoryIncludeFlag
			FROM SynergyOne.dbo.Community
			WHERE ID $assignation";

		if (is_array($synID)) {
			return $this->getAll($query);
		}

        return $this->getRow($query);
    }

	/**
	 * Get a guy's info from Synergetic
	 *
	 * @param  $synID
	 * @return array|bool
	 */
	public function getStudent($synID) {
		$query = "SELECT
					vStudents.StudentID, vStudents.StudentGiven1, vStudents.StudentSurname, vStudents.StudentBirthDate,
					vStudents.StudentTutorName, vStudents.StudentHeadOfHouse, vStudents.StudentFormStaffName, vStudents.StudentTutorDescription,
					luForm.ID as TeacherFormID, vStudents.StudentForm, luTutor.ID as TeacherTutorID, vStudents.StudentTutor, luForm.Description as FormDescription,
					vStudents.StudentHouseDescription, vStudents.StudentHouse, vStudents.StudentYearLevel, vStudents.StudentLockerBook,
					vStudents.StudentEmergencyName, vStudents.StudentEmergencyPhone, vStudents.StudentEmergencyPhoneAlt, vStudents.StudentMedicalAlert,vStudents.StudentSpecialNeedsFlag, AlertMessage,
					vStudents.StudentBoarder
                    FROM SynergyOne.dbo.vStudents vStudents
                    LEFT JOIN CommunityAlerts ON CommunityAlerts.ID = StudentID
					LEFT JOIN SynergyOne.dbo.luForm ON vStudents.StudentForm=luForm.Code
					LEFT JOIN SynergyOne.dbo.luTutor ON vStudents.StudentTutor=luTutor.Code
				WHERE
					vStudents.StudentID = $synID
					AND vStudents.FileSemester=".$_SESSION['FileSemester']."
					AND vStudents.FileYear=".$_SESSION[ 'FileYear']."
		";

		$student = $this->getRow($query);

		preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
		$student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);
		return $student;

	}

       function getStaff($synID)
	{
            $query = " SELECT SchoolStaffCode, StaffBuilding, StaffRoom, StaffExtension, StaffMobilePhone, StaffCampusDescription, StaffCategoryDescription, StaffDepartment
                FROM SynergyOne.dbo.vStaff vStaff
                WHERE vStaff.StaffID = $synID ";

            $staff = $this->getRow($query);
            return $staff;

	}

	/**
	 * Gets a list of Contacts for all Students in a particular year level. The
	 * year level is stored in Synergetic.
	 *
	 * Used in reporting.
	 *
	 * @param <type> $synID
	 * @return <type>
	 */
    public function getStudentContactListExtended($yearlevel) {
            $query = "
                    SELECT
                            StudentID, StudentGiven1, StudentSurname, StudentYearLevel,
                            StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
                            StudentContactAddress1, StudentContactAddress2, StudentContactAddress3,
                            StudentContactSuburb, StudentContactPostCode, StudentContactState, StudentContactCountryDesc,
                            StudentContactPhoneActual, StudentContactMobilePhone, StudentContactOccupPhone,
                            StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname,
                            StudentContactSpouseMobilePhone, StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail, StudentContactType,
                            StudentContactSilentPhoneFlag, Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag, Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                            StudentParentsSeparatedFlag
                    FROM SynergyOne.dbo.vStudentContactAllAddress
                    LEFT JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
                    LEFT JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
                    WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
                    AND FileSemester = " . $_SESSION['FileSemester'] . "
                    AND FileYear = " . $_SESSION[ 'FileYear'] . "
                    AND StudentYearLevel = '$yearlevel'
                    ORDER BY StudentSurname, StudentID, StudentContactType";

            return $this->getAll($query);
    }


	function getStudentContactList ($synID) {
		$query = "SELECT StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
                                StudentContactAddress1, StudentContactSuburb, StudentContactPostCode, StudentContactState,
                                StudentContactPhoneActual, StudentContactMobilePhone, StudentContactOccupPhone,
                                StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname,
                                StudentContactSpouseMobilePhone, StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail, StudentContactType
                          FROM
                                SynergyOne.dbo.vStudentContactAllAddress
                          WHERE
                                (StudentContactType = 'SC1' OR StudentContactType = 'SC2') AND StudentID = $synID
                                AND FileSemester=".$_SESSION['FileSemester']."
                                AND FileYear=".$_SESSION[ 'FileYear'];

		return $this->getAll($query);
	}

	function getCurrentSemester()
	{
		#Get current FileSemester and FileYear
		$query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag=1";
                $semester = $this->getRow($query);
                $_SESSION['FileSemester'] = $semester['FileSemester'];
                $_SESSION['FileYear'] = $semester['FileYear'];
		return true;
	}

	function getSubjectList($synergyID, $userID)
	{
		if( $this->groupHandler->isStudent($userID) )
		{
			$sql ="SELECT StudentClasses.ClassCode, SubjectClasses.Description as name
					FROM StudentClasses, SubjectClasses
					WHERE
					StudentClasses.FileType = SubjectClasses.FileType AND
					StudentClasses.FileYear = SubjectClasses.FileYear AND
					StudentClasses.FileSemester = SubjectClasses.FileSemester AND
					StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
					StudentClasses.ClassCode = SubjectClasses.ClassCode AND
					StudentClasses.FileSemester=".$_SESSION['FileSemester']." AND
					StudentClasses.FileYear=".$_SESSION[ 'FileYear']." AND
					StudentClasses.ID=$synergyID
					AND StudentClasses.StartDate <= getdate()
					AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)";
		}
		else
		{

			$sql = "SELECT DISTINCT(c1.ClassCode), c2.description as name
					FROM SubjectClassStaff c1, SubjectClasses c2
					WHERE
					c1.FileType = c2.FileType AND
					c1.FileYear = c2.FileYear AND
					c1.FileSemester = c2.FileSemester AND
					c1.ClassCampus = c2.ClassCampus AND
					c1.ClassCode = c2.ClassCode AND
					c1.FileSemester = ".$_SESSION['FileSemester']." AND
					c1.FileYear = ".$_SESSION[ 'FileYear']." AND
					c1.StaffID = $synergyID";
		}

		$this->query($sql);
		while($subject = $this->getNextArray() ) {
			$subjectList[] = array('code'=>$subject['ClassCode'], 'name'=>$subject['name']);
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

	/**
	 * Gets all Students' account info from Synergetic
	 *
	 * @return array|bool
	 */
    public function getStudentUserAccountInfo() {
		$query = "
			SELECT
				vStudents.StudentID, NetworkLogin, vStudents.StudentTitle, vStudents.StudentPreferred, vStudents.StudentSurname,
				vStudents.StudentYearLevel, vStudents.StudentHouse, StudentCampus
			FROM SynergyOne.dbo.vStudents vStudents
			LEFT JOIN Community ON Community.ID = StudentID
			WHERE vStudents.FileSemester = '{$_SESSION['FileSemester']}'
			AND vStudents.FileYear = '{$_SESSION[ 'FileYear']}'
		";

        return $this->getAll($query);
    }

	/**
	 * Gets all Parents' account info from Synergetic
	 * Used for Bialik sync
	 *
	 * @return array|bool
	 */
	public function getParentUserAccountInfo() {
		$query = "
			SELECT DISTINCT
				StudentContactID,
				StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
				StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname,
				StudentYearLevel
			FROM SynergyOne.dbo.vStudentContactAllAddress
			WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
			AND FileSemester = '{$_SESSION['FileSemester']}'
			AND FileYear = '{$_SESSION[ 'FileYear']}'
			ORDER BY StudentContactID
		";

        return $this->getAll($query);
	}

	/**
	 * Gets all Staff's account info from Synergetic
	 *
	 * @return array|bool
	 */
    public function getStaffUserAccountInfo() {
		$query = "
			SELECT
				*, StaffID, NetworkLogin, StaffTitle, StaffPreferredName, StaffSurname, Email, StaffCampus
			FROM SynergyOne.dbo.vStaff vStaff
			LEFT JOIN Community ON Community.ID = StaffID
		";

        return $this->getAll($query);
    }
}