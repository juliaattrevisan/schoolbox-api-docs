<?php

/* King's Synergetic Classes */
use Alaress\DebugBar\Instance as DebugBar;

class Synchronise_Synergetic_Kings_KingsUser extends External_Synergetic_User {
	private $database;
        private $groupHandler;

	public function __construct() {
		parent::__construct();
		 $this->setDatabase(MSSQL::getInstance());
	}
	
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
					vStudents.StudentBoarder,
					vStudents.StudentBoardingHouse,
					NULLIF (CASE WHEN lub.Code = '' THEN '' ELSE lub.Description END, '') AS StudentBoardingHouseDescription 
				FROM SynergyOne.dbo.vStudents vStudents
					LEFT JOIN CommunityAlerts ON CommunityAlerts.ID = StudentID
					LEFT JOIN luForm ON vStudents.StudentForm = luForm.Code
					LEFT JOIN luTutor ON vStudents.StudentTutor = luTutor.Code
					LEFT JOIN luYearLevel ON vStudents.StudentCampus = luYearLevel.Campus AND vStudents.StudentYearLevel = luYearLevel.Code
					LEFT JOIN luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
					LEFT JOIN luBoardingHouse lub ON vStudents.StudentBoardingHouse = lub.Code
				WHERE
					vStudents.StudentID = '$synID'
					AND vStudents.FileSemester='" . $this->getDatabase()->quote($this->getCurrentSemester()) . "'
					AND vStudents.FileYear='" . $this->getDatabase()->quote($this->getCurrentYear()) . "'
		";
		$student = $this->getDatabase()->getRow($query);

		if (is_array($student)) {
			if ($student['StudentBirthDate']
				&& preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches)
			) {
				$student['StudentBirthDate'] = strtotime($matches[2] . ' ' . $matches[1] . ' ' . $matches[3]);
			}
			else {
				$student['StudentBirthDate'] = false;
			}
			return $student;
		}

		return false;
	}

        public function getParentUserAccountInfo() {
                $query = "
SELECT DISTINCT
sc.StudentContactID, sc.StudentContactTitle, sc.StudentContactPreferred, sc.StudentContactSurname, sc.StudentContactDefaultEmail,
scn.NetworkLogin,
sc.StudentYearLevel, sc.StudentCampus, sc.StudentGender
FROM SynergyOne.dbo.vStudentContactAllAddress sc
INNER JOIN dbo.uvStudentContactNetworkAccounts scn on scn.pk_id = sc.StudentContactID
WHERE (sc.StudentContactType = 'SC1' or sc.StudentContactType = 'SC2' or sc.StudentContactType = 'SC3')
and sc.FileSemester = '" . $this->getDatabase()->quote($this->getCurrentSemester()) . "' and sc.FileYear = '" . $this->getDatabase()->quote($this->getCurrentYear()) . "' and sc.StudentCampus <> 'T'

UNION

SELECT DISTINCT
sc.StudentContactSpouseID as StudentContactID, sc.StudentContactSpouseTitle as StudentContactTitle, 
sc.StudentContactSpousePreferred as StudentContactPreferred, 
sc.StudentContactSpouseSurname as StudentContactSurname, sc.StudentContactSpouseDefaultEmail as StudentContactDefaultEmail, scn.NetworkLogin,
sc.StudentYearLevel, sc.StudentCampus, sc.StudentGender
FROM SynergyOne.dbo.vStudentContactAllAddress sc
INNER JOIN dbo.uvStudentContactNetworkAccounts scn on scn.pk_id = sc.StudentContactSpouseID
WHERE (sc.StudentContactType = 'SC1' or sc.StudentContactType = 'SC2' or sc.StudentContactType = 'SC3') and sc.StudentContactPrimaryOnlyFlag <> 1 and sc.StudentContactSpouseID is not null
and sc.FileSemester = '" . $this->getDatabase()->quote($this->getCurrentSemester()) . "' and sc.FileYear = '" . $this->getDatabase()->quote($this->getCurrentYear()) . "' and sc.StudentCampus <> 'T'
                ";

                return $this->getDatabase()->getAll($query);
        }

		public function getStudentUserAccountInfo() {
			$query = "
				SELECT
					vStudents.StudentID, NetworkLogin, vStudents.StudentTitle, vStudents.StudentPreferred, vStudents.StudentSurname, StudentGender,
					StudentEmail, StudentOccupEmail,
					vStudents.StudentYearLevel, vStudents.StudentHouse, StudentCampus
				FROM SynergyOne.dbo.vStudents vStudents
				LEFT JOIN Community ON Community.ID = StudentID
				WHERE vStudents.FileSemester = '" . $this->getDatabase()->quote($this->getCurrentSemester()) . "'
				AND vStudents.FileYear = '" . $this->getDatabase()->quote($this->getCurrentYear()) . "'
			";
		
			return $this->getDatabase()->getAll($query);
		}

	private function setDatabase($database) {
                $this->database = $database;
        }

        /**
         * Get database
         *
         * @return MSSQL
         */
        private function getDatabase() {
                return $this->database;
        }


}
