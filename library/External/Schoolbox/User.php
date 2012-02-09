<?php
/************************************************************
*                                                           *
* 		Copyright (c) 2008 Lektec Consulting				*
*                                                           *
*	This file provides access to Denbigh user info		*
*                                                           *
************************************************************/

class External_Schoolbox_User implements External_UserInterface {
    const RELATION_TYPE_PARENT = 1;

	private $database;

	private $group;

    private $user;

	public function __construct () {
		$this->setdatabase( Database::getInstance() );

        $this->setUser( new User );
	}

    public function getParent ($externalUserId) {

    }

    public function getParentSync ($externalUserId) {

    }

	/**
	 * Get a student by id
	 *
	 * @param $externalUserId
	 * @return array
	 */
	public function getStudent($externalUserId) {
		$statement = $this->getDatabase()->prepare("
			SELECT
				user.id AS StudentID,
				user.firstname AS StudentGiven1,
				user.lastname AS StudentSurname,
				UNIX_TIMESTAMP(dob) AS StudentBirthDate,
				'' AS StudentTutorName,
				'' AS StudentHeadOfHouse,
				'' AS HeadOfHouseID,
				'' AS StudentFormStaffName,
				'' AS StudentTutorDescription,
				'' AS TeacherFormID,
				'' AS StudentForm,
				'' AS TeacherTutorID,
				'' AS StudentTutor,
				'' AS FormDescription,
				'' AS StudentHouseDescription,
				user_profile.house AS StudentHouse,
				level AS StudentYearLevel,
				'' AS StudentLockerBook,
				'' AS StudentEmergencyName,
				'' AS StudentEmergencyPhone,
				'' AS StudentEmergencyPhoneAlt,
				'' AS StudentMedicalAlert,
				'' AS StudentSpecialNeedsFlag,
				'' AS AlertMessage,
				'' AS StudentBoarder
			FROM `user`
			LEFT JOIN user_profile ON user.id = user_profile.user_id
			WHERE user.synergy_id = :externalUserId
		");

		$statement->execute(array(
			':externalUserId' => $externalUserId
		));

		return $statement->fetch(PDO::FETCH_ASSOC);
	}

	function getStaff ($externalUserId) {

	}

    public function getStudentContactListExtended ($yearLevel) {

    }


	function getStudentContactList ($externalUserId) {

        $statement = $this->getDatabase()->query("
      			SELECT u.*
      			FROM user_relation ur
      			LEFT JOIN user u ON u.id = ur.user_id
      			WHERE ur.relation_id = {$externalUserId}
      			  AND ur.relation_type = " . External_Schoolbox_User::RELATION_TYPE_PARENT . "
      		");

        $contactList = $statement->fetchAll(PDO::FETCH_ASSOC);

        $contactAdaptionList = array();

        if ( !empty($contactList) ) {
            foreach ( $contactList as $contact ) {
                $contactAdaption = array(
                    'StudentContactID' => $contact['id'],
                    'StudentContactTitle' => $contact['title'],
                    'StudentContactPreferred' => $contact['firstname'],
                    'StudentContactSurname' => $contact['lastname'],
                    'StudentContactDefaultEmail' => $contact['alt_email'],
                    'StudentContactAddress1' => '',
                    'StudentContactSuburb' => '',
                    'StudentContactPostCode' => '',
                    'StudentContactState' => '',
                    'StudentContactPhoneActual' => '',
                    'StudentContactMobilePhone' => '',
                    'StudentContactOccupPhone' => '',
                    'StudentContactSpouseID' => '',
                    'StudentContactSpouseTitle' => '',
                    'StudentContactSpousePreferred' => '',
                    'StudentContactSpouseSurname' => '',
                    'StudentContactSpouseMobilePhon' => '',
                    'StudentContactSpouseOccupPhone' => '',
                    'StudentContactSpouseDefaultEma' => '',
                    'StudentContactType' => 'SC1',
                );

                $contactAdaptionList[] = $contactAdaption;
            }
        }

        return $contactAdaptionList;
	}

	function getCurrentSemester () {

	}

	function getSubjectList ($externalUserId, $userId) {
		$statement = $this->getDatabase()->prepare("
			SELECT c.name, c.code
			FROM user u
			LEFT JOIN class_user cu ON cu.user_id = u.id
			LEFT JOIN class c ON c.id = cu.class_id
			WHERE u.synergy_id = :externalUserId
		");

		$statement->execute( array(
			':externalUserId' => $externalUserId,
		) );

		$classList = $statement->fetchAll(PDO::FETCH_ASSOC);

		return $classList;
	}

	function getEnrolledStudents ($externalUserId) {
        $statement = $this->getDatabase()->prepare("
      			SELECT c.synergy_id as RelatedID
      			FROM user_relation ur
      			LEFT JOIN user p ON p.id = ur.user_id
      			LEFT JOIN user c ON c.id = ur.relation_id
      			WHERE p.synergy_id = :externalUserId
      			  AND ur.relation_type = :relationType
      		");

        $statement->execute( array(
            ':externalUserId' => $externalUserId,
            ':relationType' => self::RELATION_TYPE_PARENT
        ) );

        $childList = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $childList;
	}

    function getEmail ($externalUserId) {

    }

    public function getStudentUserAccountInfo () {

    }

	public function getParentUserAccountInfo () {

	}

    public function getStaffUserAccountInfo () {

    }

    /**
     * Returns a local path to the user's portrait
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait ($externalUserId) {

        $portraitPath = realpath( Config::get('portrait_dir') ) . "/$externalUserId.jpg";

        if ( is_readable($portraitPath) ) {
            return $portraitPath;
        }
        else {
            return false;
        }
    }

	/**
	 * @param Database $database
	 * @return void
	 */
	private function setDatabase (Database $database) {
		$this->database = $database;
	}

	/**
	 * @return Database
	 */
	private function getDatabase () {
		return $this->database;
	}

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}