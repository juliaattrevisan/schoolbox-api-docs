<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to synergetic user info		*
*                                                           *
************************************************************/

class External_Synergetic_User implements External_UserInterface {

	private $database;

    private $groupHandler;

    function __construct () {
		$this->setDatabase( new MSSQL );

        $this->setGroup( new Group );

    }

    /**
     * Retrieves the user's Synergetic image
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait ($externalUserId) {

        $query = "
            SELECT vPhotos.ID, vPhotos.Photo, vPhotos.PhotoType
            FROM SynergyOne.dbo.vPhotos vPhotos
            WHERE vPhotos.ID = '{$externalUserId}'
        ";

        if ( $photo = $this->getDatabase()->getRow($query) ) {
            //$photoPath = tempnam(Cfg::get('zip_dir'), 'synport');

            $photoPath = Config::get('portrait_dir').'/'.$externalUserId.'.jpg';

            //return file from cache
            //TODO: delete photos on rollover
            if(file_exists($photoPath)) {
                return $photoPath;
            }
            else {
                $photoHandle = fopen($photoPath, "w");

                fwrite($photoHandle, $photo['Photo']);
                fclose($photoHandle);

                return $photoPath;
            }
        }

        return false;
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
				Contact.Title, Contact.Preferred, Contact.Surname, Contact.Email, Addresses.Phone as HomePhone,
				Addresses.SilentPhoneFlag, Contact.MobilePhone, Contact.DirectoryIncludeFlag, Contact.SpouseID,
				Spouse.Title SpouseTitle, Spouse.Preferred SpousePreferred, Spouse.Surname SpouseSurname,
				Spouse.Email SpouseEmail, Spouse.MobilePhone SpouseMobilePhone,
				Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag
			FROM
			    SynergyOne.dbo.Community Contact
            LEFT JOIN
                SynergyOne.dbo.Addresses Addresses ON Contact.AddressID = Addresses.AddressID
			LEFT JOIN
			    SynergyOne.dbo.Community Spouse ON Contact.SpouseID = Spouse.ID
			WHERE
			    Contact.ID = '$synID'";

        return $this->getDatabase()->getRow($query);
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
			return $this->getDatabase()->getAll($query);
		}

        return $this->getDatabase()->getRow($query);
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
					vStudents.StudentTutorName, vStudents.StudentHeadOfHouse, House.HeadOfHouseID,  vStudents.StudentFormStaffName, vStudents.StudentTutorDescription,
					luForm.ID as TeacherFormID, vStudents.StudentForm, luTutor.ID as TeacherTutorID, vStudents.StudentTutor, luForm.Description as FormDescription,
					vStudents.StudentHouseDescription, vStudents.StudentHouse, vStudents.StudentYearLevel, vStudents.StudentLockerBook,
					vStudents.StudentEmergencyName, vStudents.StudentEmergencyPhone, vStudents.StudentEmergencyPhoneAlt, vStudents.StudentMedicalAlert,vStudents.StudentSpecialNeedsFlag, AlertMessage,
					vStudents.StudentBoarder
                    FROM SynergyOne.dbo.vStudents vStudents
                    LEFT JOIN CommunityAlerts ON CommunityAlerts.ID = StudentID
					LEFT JOIN SynergyOne.dbo.luForm ON vStudents.StudentForm=luForm.Code
					LEFT JOIN SynergyOne.dbo.luTutor ON vStudents.StudentTutor=luTutor.Code
					LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
				WHERE
					vStudents.StudentID = '$synID'
					AND vStudents.FileSemester=".$_SESSION['FileSemester']."
					AND vStudents.FileYear=".$_SESSION[ 'FileYear']."
		";
		$student = $this->getDatabase()->getRow($query);

        if(is_array($student)) {
            preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
            $student['StudentBirthDate'] = strtotime($matches[2].' '.$matches[1].' '.$matches[3]);

            return $student;
        }

        return false;
	}

    function getStaff($synID)
    {
         $query = " SELECT SchoolStaffCode, luBuilding.Description as StaffBuilding, StaffRoom, StaffExtension,
                           StaffMobilePhone, StaffCampusDescription, StaffCategoryDescription, luDepartment.Description as StaffDepartment
             FROM SynergyOne.dbo.vStaff vStaff
             LEFT JOIN SynergyOne.dbo.luBuilding ON StaffBuilding = luBuilding.Code
             LEFT JOIN SynergyOne.dbo.luDepartment ON StaffDepartment = luDepartment.Code
             WHERE vStaff.StaffID = $synID ";

         $staff = $this->getDatabase()->getRow($query);

         return $staff;

    }

	/**
	 * Gets a list of Contacts for all Students in a particular year level. The
	 * year level is stored in Synergetic.
	 *
	 * Used in reporting.
	 *
	 * @param $yearlevel
	 * @return mixed
	 */
    public function getStudentContactListExtended($yearlevel) {
		$query = "
			SELECT
				StudentID, StudentGiven1, StudentSurname, StudentYearLevel, House.Description AS StudentHouse, StudentBoarder,
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
			LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
			WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
			AND FileSemester = " . $_SESSION['FileSemester'] . "
			AND FileYear = " . $_SESSION[ 'FileYear'] . "
			AND StudentYearLevel = '$yearlevel'
			ORDER BY StudentSurname, StudentID, StudentContactType";

		return $this->getDatabase()->getAll($query);
    }

	/**
	 * Gets a list of Contacts for all Student Boarders.
	 *
	 * Used in reporting.
	 *
	 * @return mixed
	 */
    public function getStudentBoarderContactListExtended() {
		$query = "
			SELECT
				StudentID, StudentGiven1, StudentSurname, StudentYearLevel, House.Description AS StudentHouse,
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
			LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
			WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
			AND FileSemester = " . $_SESSION['FileSemester'] . "
			AND FileYear = " . $_SESSION[ 'FileYear'] . "
			AND StudentBoarder = 'B'
			ORDER BY StudentSurname, StudentID, StudentContactType";

		return $this->getDatabase()->getAll($query);
    }

	function getStudentContactList ($synID) {
		$query = "
			SELECT
				StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
				StudentContactAddress1, StudentContactSuburb, StudentContactPostCode, StudentContactState,
				StudentContactPhoneActual, StudentContactMobilePhone, StudentContactOccupPhone,
				StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname,
				StudentContactSpouseMobilePhone, StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail, StudentContactType
			FROM
				SynergyOne.dbo.vStudentContactAllAddress
			WHERE
				StudentID = $synID
				AND (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
				AND FileSemester = " . $_SESSION['FileSemester'] . "
				AND FileYear = " . $_SESSION[ 'FileYear'] ;

		return $this->getDatabase()->getAll($query);
	}

	function getCurrentSemester()
	{
		#Get current FileSemester and FileYear
		$query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1";

        if ( $semester = $this->getDatabase()->getRow($query) ) {
            $_SESSION['FileSemester'] = $semester['FileSemester'];
            $_SESSION['FileYear'] = $semester['FileYear'];

            return true;
        }
        else {
            return false;
        }
    }

	function getSubjectList($synergyID, $userID)
	{
        if ( !isset($_SESSION['FileSemester']) || !isset($_SESSION['FileYear']) ) {
            return false;
        }

		if( $this->getGroup()->isStudent($userID) )
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

		$this->getDatabase()->query($sql);
		while($subject = $this->getDatabase()->getNextArray() ) {
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


		return $this->getDatabase()->getAll($query);

	}

    function getEmail($id) {
        $query = "SELECT Email FROM Community WHERE id = $id";
        return $this->getDatabase()->getOne($query);
    }

	/**
	 * Gets all Students' account info from Synergetic
	 *
	 * @return array|bool
	 */
    public function getStudentUserAccountInfo() {
		$query = "
			SELECT
				vStudents.StudentID, NetworkLogin, vStudents.StudentTitle, vStudents.StudentPreferred, vStudents.StudentSurname, StudentEmail,
				vStudents.StudentYearLevel, vStudents.StudentHouse, StudentCampus
			FROM SynergyOne.dbo.vStudents vStudents
			LEFT JOIN Community ON Community.ID = StudentID
			WHERE vStudents.FileSemester = '{$_SESSION['FileSemester']}'
			AND vStudents.FileYear = '{$_SESSION[ 'FileYear']}'
		";

        return $this->getDatabase()->getAll($query);
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
				StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
				StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname, StudentContactSpouseDefaultEmail,
				StudentYearLevel
			FROM SynergyOne.dbo.vStudentContactAllAddress
			WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
			AND FileSemester = '{$_SESSION['FileSemester']}'
			AND FileYear = '{$_SESSION[ 'FileYear']}'
			ORDER BY StudentContactID
		";

        return $this->getDatabase()->getAll($query);
	}

	/**
	 * Gets all active Staff's account info from Synergetic
	 *
	 * @return array|bool
	 */
    public function getStaffUserAccountInfo() {
		$query = "
			SELECT
				StaffID, NetworkLogin, ActiveFlag,
				StaffTitle, StaffPreferredName, StaffSurname,
				DefaultEmailCode, OccupEmail, Email,
				StaffCampus
			FROM SynergyOne.dbo.vStaff vStaff
			LEFT JOIN Community ON Community.ID = StaffID
			AND ActiveFlag = 1
		";

        return $this->getDatabase()->getAll($query);
    }

	/**
	 * Contact list for staff directory
	 * Exclude people who aren't active.
	 * Exclude people who are on leave (category description for this is 'Staff on leave')
	 *
	 * @return mixed
	 */
	public function getStaffContactListExtended() {
		$query = "
			SELECT
				vStaff.StaffID, vStaff.StaffTitle, vStaff.StaffPreferredName, vStaff.StaffSurname,
				vStaff.StaffOccupPhone, vStaff.StaffOccupEmail,
				vStaff.StaffCampusDescription, vStaff.StaffCategoryType,
				JobPositions.StaffDepartmentDescription AS StaffDepartment, JobPositions.JobPositionDescription
			FROM SynergyOne.dbo.vStaff vStaff
			LEFT JOIN vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
			LEFT JOIN StaffJobPositions ON vStaff.StaffID = StaffJobPositions.ID
			WHERE ActiveFlag = 1
			AND StaffCategoryDescription != 'Staff on leave'
			AND ( StaffJobPositions.StartDate < GETDATE()
			OR    StaffJobPositions.StartDate IS NULL )
			AND ( StaffJobPositions.EndDate > GETDATE()
			OR    StaffJobPositions.EndDate IS NULL )
			ORDER BY StaffSurname
		";

        return $this->getDatabase()->getAll($query);
	}

	/**
	 * Get latest image for a particular staff member
	 *
	 * @param $staffID
	 * @return mixed
	 */
	public function getLatestStaffImageFor($staffID) {
		$query = "
			SELECT TOP 1
				Documents.Document, Documents.DocumentType, Documents.DocumentCreatedDate
			FROM SynergyOne.dbo.vStaff vStaff
			LEFT JOIN SynergyOneMedia.dbo.DocumentReferences DocumentReferences ON vStaff.StaffID = DocumentReferences.ID
			INNER JOIN SynergyOneMedia.dbo.tDocuments Documents ON DocumentReferences.DocumentReferencesSeq = Documents.tDocumentsSeq
			WHERE ClassificationCode = 'StudentPhoto'
			AND vStaff.StaffID = '{$staffID}'
			ORDER BY Documents.DocumentCreatedDate DESC
		";

        return $this->getDatabase()->getRow($query);
	}

	private function setDatabase ($database) {
		$this->database = $database;
	}

	private function getDatabase () {
		return $this->database;
	}

    /**
     * @param Group $groupHandler
     */
    public function setGroup($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->groupHandler;
    }

}
