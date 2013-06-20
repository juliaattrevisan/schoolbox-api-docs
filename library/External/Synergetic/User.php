<?php
/************************************************************
 *                                                           *
 *         Copyright (c) 2008 Lektec Consulting                *
 *                                                           *
 *    This file provides access to synergetic user info        *
 *                                                           *
 ************************************************************/

class External_Synergetic_User implements External_UserInterface {
	/**
	 * @var MSSQL
	 */
	private $database;
	private $groupHandler;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->setDatabase(new MSSQL());
		$this->setGroup(new Group());
	}

	/**
	 * Retrieves the user's Synergetic image
	 *
	 * @param Integer $externalUserId
	 * @return Bool|String
	 */
	public function getPortrait($externalUserId) {
		$query = "
			SELECT vPhotos.ModifiedDate
			FROM SynergyOne.dbo.vPhotos vPhotos
			WHERE vPhotos.ID = '{$externalUserId}'
		";

		if ($photo = $this->getDatabase()->getRow($query)) {
			$photoPath = Config::get('portrait_dir') . '/' . $externalUserId . '.jpg';

			//TODO: delete photos on rollover

			/* If the cached file doesn't exist, or the synergetic image is more recent than our
			 * last known cached version, regenerate cached version.
			 *
			 * Separate the queries out, fetching the photo data only if it's newer than the cached
			 * version, or if the cache version doesn't already exist.
			 */
			if (!file_exists($photoPath) || (@filemtime($photoPath) > strtotime($photo['ModifiedDate']))) {
				$query = "
					SELECT vPhotos.Photo, vPhotos.PhotoType
					FROM SynergyOne.dbo.vPhotos vPhotos
					WHERE vPhotos.ID = '{$externalUserId}'
				";

				//
				// SB-2063 - Don't try to convert charsets for binary fields.
				$convert_encoding = $this->getDatabase()->getConvertEncoding();
				$this->getDatabase()->setConvertEncoding(false);
				$photo = $this->getDatabase()->getRow($query);
				$this->getDatabase()->setConvertEncoding($convert_encoding);

				/* Purge all previously transformed copies
				 */
				$pattern = Config::get('portrait_dir') . '/' . $externalUserId . '-*.dat';

				foreach (glob($pattern) as $oldCacheFile) {
					unlink($oldCacheFile);
				}

				/* Save the image
				 */
				$photoHandle = fopen($photoPath, "w");
				fwrite($photoHandle, $photo['Photo']);
				fclose($photoHandle);
			}

			return $photoPath;
		}

		return false;
	}

	/**
	 * Gets the contact info of the person who is a Student's Contact. The ID
	 * passed is the Synergetic of the Contact, not the Student.
	 *
	 * @param $synID
	 *
	 * @return mixed
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
	 * Accept array of Synergy IDs, or just an int
	 *
	 * @param $synID
	 *
	 * @return mixed
	 */
	public function getParentSync($synID) {
		if (is_array($synID)) {
			$assignation = ' IN (' . implode(',', $synID) . ')';
		} else {
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
	 * Get all the parents emails for a class, or group of students
	 *
	 * @param array $synergyIds
	 *
	 * @return array
	 */
	public function getParentEmails($synergyIds) {

		$emailList = array();
		$id_list   = implode(',', array_map('intval', $synergyIds));

		$query = "
			SELECT
				RelatedID,UseEmail,OccupEmail,Email,DefaultEmail
			FROM
				SynergyOne.dbo.RelationShips r
				JOIN SynergyOne.dbo.vCommunityAddresses ON RelatedID=vCommunityAddresses.ID
			WHERE
				r.ID IN ($id_list)";

		$this->getDatabase()->query($query);
		while ($row = $this->getDatabase()->getNextArray()) {
			switch($row['UseEmail']) {
				case 'O': //Use occupation email address
					$emailList[] = $row['OccupEmail'];
					break;
				case 'P': //Use personal email address
					$emailList[] = $row['Email'];
					break;
				case 'D': //Use default email address
					$emailList[] = $row['DefaultEmail'];
					break;
				default:
					break;
			}

		}

		return $emailList;
	}

	/**
	 * Get a guy's info from Synergetic
	 *
	 * @param  $synID
	 * @return array|bool
	 */
	public function getStudent($synID) {
		$query = "SELECT
					vStudents.StudentID,
					vStudents.StudentGiven1,
					vStudents.StudentSurname,
					vStudents.StudentBirthDate,
					vStudents.StudentTutorName,
					vStudents.StudentHeadOfHouse,
					House.HeadOfHouseID,
					vStudents.StudentFormStaffName,
					vStudents.StudentTutorDescription,
					luForm.ID as TeacherFormID,
					vStudents.StudentForm,
					luTutor.ID as TeacherTutorID,
					vStudents.StudentTutor,
					luForm.Description as FormDescription,
					vStudents.StudentHouseDescription,
					vStudents.StudentHouse,
					vStudents.StudentYearLevel,
					vStudents.StudentYearLevelCoordinator,
					luYearLevel.YearLevelCoordID,
					vStudents.StudentLockerBook,
					vStudents.StudentEmergencyName,
					vStudents.StudentEmergencyPhone,
					vStudents.StudentEmergencyPhoneAlt,
					vStudents.StudentEmergency2Name,
					vStudents.StudentEmergency2Phone,
					vStudents.StudentEmergency2PhoneAlt,
					vStudents.StudentEmergency3Name,
					vStudents.StudentEmergency3Phone,
					vStudents.StudentEmergency3PhoneAlt,
					vStudents.StudentMedicalAlert,
					vStudents.StudentSpecialNeedsFlag,
					AlertMessage,
					vStudents.StudentBoarder
				FROM SynergyOne.dbo.vStudents vStudents
					LEFT JOIN CommunityAlerts ON CommunityAlerts.ID = StudentID
					LEFT JOIN luForm ON vStudents.StudentForm = luForm.Code
					LEFT JOIN luTutor ON vStudents.StudentTutor = luTutor.Code
					LEFT JOIN luYearLevel ON vStudents.StudentCampus = luYearLevel.Campus AND vStudents.StudentYearLevel = luYearLevel.Code
					LEFT JOIN luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
				WHERE
					vStudents.StudentID = '$synID'
					AND vStudents.FileSemester=" . $_SESSION['FileSemester'] . "
					AND vStudents.FileYear=" . $_SESSION['FileYear'] . "
		";
		$student = $this->getDatabase()->getRow($query);

		if (is_array($student)) {
			preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches);
			$student['StudentBirthDate'] = strtotime($matches[2] . ' ' . $matches[1] . ' ' . $matches[3]);

			return $student;
		}

		return false;
	}

	function getStaff($synID) {
		$query = " SELECT SchoolStaffCode, luBuilding.Description as StaffBuilding, StaffRoom, StaffExtension,
						   StaffMobilePhone, StaffCampusDescription, StaffCategoryDescription,
						   luDepartment.Description as StaffDepartment, StaffVoiceMail
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
	 * Note that there is no StudentContactHomeCountryDesc in the Synergetic views
	 * so I changed it to use CountryCode instead.
	 *
	 * @param $yearlevel
	 * @param $name
	 * @param $boardingStatus
	 * @param $form
	 *
	 * @return mixed
	 */
	public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form) {
		$query = "
			SELECT
				StudentID, StudentGiven1, StudentSurname, StudentYearLevel, House.Description AS StudentHouse, StudentBoarder, StudentForm,
				StudentContactID,
				StudentContactTitle, StudentContactPreferred, StudentContactLegalFullName, StudentContactSurname,
				StudentContactDefaultEmail,
				StudentContactAddress1, StudentContactAddress2, StudentContactAddress3,
				StudentContactSuburb, StudentContactPostCode, StudentContactState, StudentContactCountryCode,
				StudentContactHomeAddress1, StudentContactHomeAddress2, StudentContactHomeAddress3,
				StudentContactHomeSuburb, StudentContactHomePostCode, StudentContactHomeState, StudentContactHomeCountryCode,
				StudentContactPhoneActual, StudentContactMobilePhone, StudentContactOccupPhone,
				StudentContactSpouseID,
				StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseLegalFullName,StudentContactSpouseSurname,
				StudentContactSpouseMobilePhone, StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail, StudentContactType,
				StudentContactSilentPhoneFlag, Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag, Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
				StudentParentsSeparatedFlag
			FROM SynergyOne.dbo.vStudentContactAllAddress
			LEFT JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
			LEFT JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
			LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
			LEFT JOIN SynergyOne.dbo.luYearLevel Year ON StudentYearLevel = Year.Code
			WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
			AND FileSemester = " . $_SESSION['FileSemester'] . "
			AND FileYear = " . $_SESSION['FileYear'];

		/* Search for a particular year level
		 */
		if (strlen($yearlevel)) {
			if(is_numeric($yearlevel)) {
				// forcibly cast to int in case it's a something non-int that's still numeric
				$yearlevel = (int)$yearlevel;
				$query .= "
				AND (StudentYearLevel = '{$yearlevel}' OR Year.Description = '{$yearlevel}')
			";

			}
			// if year is NOT numeric, don't attempt to compare StudentYearLevel (will bug out)
			else {
				$query .= "
				AND Year.Description = '{$this->getDatabase()->quote($yearlevel)}'";
			}

		}

		/* Search for a student name
		 */
		if (strlen($name)) {
			$name = $this->getDatabase()->quote($name);
			$query .= "
				AND (
					(StudentSurname LIKE '%{$name}%')
					OR
					(StudentGiven1 LIKE '%{$name}%')
				)
			";
		}

		/* Search for a particular boarding status
		 *
		 * In Synergetic, assume the following:
		 * B: Boarder
		 * D: Day student
		 * HS: Home stay
		 */
		if ($boardingStatus == '') {

			/* No restrictions on result set
			 */
		}
		else if ($boardingStatus == 'yes') {
			$query .= "
				AND StudentBoarder = 'B'
			";
		}
		else {
			$query .= "
				AND StudentBoarder != 'B'
			";
		}

		/* Search for a particular form
		 */
		if ($form) {
			$form = $this->getDatabase()->quote($form);
			$query .= "
				AND StudentForm = '{$form}'
			";
		}

		$query .= "
			ORDER BY StudentSurname, StudentID, StudentContactType
		";


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
			AND FileYear = " . $_SESSION['FileYear'] . "
			AND StudentBoarder = 'B'
			ORDER BY StudentSurname, StudentID, StudentContactType";

		return $this->getDatabase()->getAll($query);
	}

	/**
	 * Get a list of student contacts (parents) and their contact information
	 *
	 * @param $externalUserId
	 * @return mixed
	 */
	public function getStudentContactList($externalUserId) {
		$query = "
			SELECT
				StudentContactID, StudentContactTitle, StudentContactPreferred, StudentContactSurname, StudentContactDefaultEmail,
				StudentContactAddress1, StudentContactAddress2, StudentContactAddress3, StudentContactSuburb, StudentContactPostCode, StudentContactState,
				StudentContactPhoneActual, StudentContactMobilePhone, StudentContactOccupPhone,StudentContactRelation,
				StudentContactSpouseID, StudentContactSpouseTitle, StudentContactSpousePreferred, StudentContactSpouseSurname,
				StudentContactSpouseMobilePhone, StudentContactSpouseOccupPhone,StudentContactSpouseDefaultEmail, StudentContactType,StudentContactSpouseRelation
			FROM
				SynergyOne.dbo.vStudentContactAllAddress
			WHERE
				StudentID = '$externalUserId'
				AND (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
				AND FileSemester = " . $_SESSION['FileSemester'] . "
				AND FileYear = " . $_SESSION['FileYear'];

		return $this->getDatabase()->getAll($query);
	}

	function getCurrentSemester() {
		#Get current FileSemester and FileYear
		$query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1";

		if ($semester = $this->getDatabase()->getRow($query)) {
			$_SESSION['FileSemester'] = $semester['FileSemester'];
			$_SESSION['FileYear'] = $semester['FileYear'];

			return true;
		} else {
			return false;
		}
	}

	function getSubjectList($synergyID, $userID) {
		if (empty($synergyID)) {
			return false;
		}
		if (!isset($_SESSION['FileSemester']) || !isset($_SESSION['FileYear'])) {
			return false;
		}

		if ($this->getGroup()->isStudent($userID)) {
			$sql = "SELECT StudentClasses.ClassCode, SubjectClasses.ClassCampus, SubjectClasses.Description as name
					FROM StudentClasses, SubjectClasses
					WHERE
					StudentClasses.FileType = SubjectClasses.FileType AND
					StudentClasses.FileYear = SubjectClasses.FileYear AND
					StudentClasses.FileSemester = SubjectClasses.FileSemester AND
					StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
					StudentClasses.ClassCode = SubjectClasses.ClassCode AND
					StudentClasses.FileSemester=" . $_SESSION['FileSemester'] . " AND
					StudentClasses.FileYear=" . $_SESSION['FileYear'] . " AND
					StudentClasses.ID=$synergyID
					AND StudentClasses.StartDate <= getdate()
					AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)";
		} else {

			$sql = "SELECT DISTINCT(c1.ClassCode), c1.ClassCampus, c2.description as name
					FROM SubjectClassStaff c1, SubjectClasses c2
					WHERE
					c1.FileType = c2.FileType AND
					c1.FileYear = c2.FileYear AND
					c1.FileSemester = c2.FileSemester AND
					c1.ClassCampus = c2.ClassCampus AND
					c1.ClassCode = c2.ClassCode AND
					c1.FileSemester = " . $_SESSION['FileSemester'] . " AND
					c1.FileYear = " . $_SESSION['FileYear'] . " AND
					c1.StaffID = $synergyID";
		}

		$this->getDatabase()->query($sql);
		while ($subject = $this->getDatabase()->getNextArray()) {
			$subjectList[] = array('code' => $subject['ClassCode'], 'name' => $subject['name'], 'campusCode' => $subject['ClassCampus']);
		}

		if (is_array($subjectList)) {
			return $subjectList;
		} else {
			return false;
		}
	}

	function getEnrolledStudents($synergyID) {
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
				vStudents.StudentID, NetworkLogin, vStudents.StudentTitle, vStudents.StudentPreferred, vStudents.StudentSurname, StudentEmail, StudentOccupEmail,
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
				StudentContactID,
				StudentContactTitle,
				StudentContactPreferred,
				StudentContactSurname,
				StudentContactDefaultEmail,
				StudentContactSpouseID,
				StudentContactSpouseTitle,
				StudentContactSpousePreferred,
				StudentContactSpouseSurname,
				StudentContactSpouseDefaultEmail,
				StudentYearLevel,
				StudentCampus
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
				StaffCampus, StaffCategoryType, StaffCategory
			FROM SynergyOne.dbo.vStaff vStaff
			LEFT JOIN Community ON Community.ID = StaffID
			WHERE ActiveFlag = 1 AND StaffRegistrationType != 'STDNT'
		";

		return $this->getDatabase()->getAll($query);
	}

	/**
	 * Contact list for staff directory
	 * Exclude people who aren't active.
	 * Exclude people who are on leave (category description for this is 'Staff on leave')
	 * TODO: Make exclusion on category configurable, currently excluding
	 * @return mixed
	 */
	public function getStaffContactListExtended() {
		$query = "
			SELECT
				vStaff.StaffID, vStaff.StaffTitle, vStaff.StaffPreferredName, vStaff.StaffSurname,
				vStaff.StaffOccupPhone, vStaff.StaffOccupEmail, vStaff.StaffExtension,
				vStaff.StaffCampusDescription, vStaff.StaffCategoryType,
				JobPositions.StaffDepartmentDescription AS StaffDepartment, JobPositions.JobPositionDescription
			FROM SynergyOne.dbo.vStaff vStaff
			LEFT JOIN SynergyOne.dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
			WHERE ActiveFlag = 1
			AND StaffCategoryDescription != 'Staff on leave'
			AND StaffCategory NOT IN ('SC')
			AND ( JobPositions.StaffJobPositionStartDate < GETDATE()
			OR    JobPositions.StaffJobPositionStartDate IS NULL )
			AND ( JobPositions.StaffJobPositionEndDate > GETDATE()
			OR    JobPositions.StaffJobPositionEndDate IS NULL )
			ORDER BY StaffSurname
		";

		return $this->getDatabase()->getAll($query);
	}

	function getCampusFromTimetable($staffID) {
		$query = "SELECT DISTINCT( ClassCampus ) as campus FROM timetable WHERE StaffID = '$staffID'";

		return $this->getDatabase()->getAll($query);

	}

	/**
	 * Determine the boarder type based on a value
	 * Returns a human-readable string
	 *
	 * @param $boarderValue
	 * @return string
	 */
	public function determineBoarderType($boarderValue) {
		$boarders = array('B', 'W');

		if (!strlen($boarderValue)) {
			return '';
		} else {
			if (in_array($boarderValue, $boarders)) {
				return 'Boarder';
			}
		}

		return 'Day';
	}

	public function getHousemasterByHouseId($houseID) {
		return false;
	}

	/**
	 * Retrieve all the options from luForm table
	 *
	 * @return mixed
	 */
	public function retrieveLuForm() {
		$query = "
			SELECT Code, Description
			FROM SynergyOne.dbo.luForm
			WHERE ActiveFlag = 1
		";

		return $this->getDatabase()->getAll($query);
	}

	/* Getters/Setters
	  */

	/**
	 * Set database
	 *
	 * @param $database
	 */
	private function setDatabase($database) {
		$this->database = $database;
	}

	/**
	 * Get database
	 *
	 * @return mixed
	 */
	private function getDatabase() {
		return $this->database;
	}

	/**
	 * @param Group $groupHandler
	 */
	public function setGroup($groupHandler) {
		$this->groupHandler = $groupHandler;
	}

	/**
	 * @return Group
	 */
	public function getGroup() {
		return $this->groupHandler;
	}
}
