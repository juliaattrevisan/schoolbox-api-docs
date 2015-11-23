<?php

/************************************************************
 *                                                           *
 *        Copyright (c) 2013 Alaress Pty Ltd                *
 *                                                           *
 *    This file provides access to Denbigh user info        *
 *                                                           *
 ************************************************************/
class External_Schoolbox_User implements External_UserInterface
{
    const RELATION_TYPE_PARENT = 1;

    private $database;

    private $group;

    private $user;

    public function __construct()
    {
        $this->setdatabase(Database::getInstance());
        $this->setUser(new User);
    }

    public function getParent($externalUserId)
    {

    }


    public function getParentSync($externalUserId)
    {

    }

    /**
     * Get all the parents of the $synergyIds
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = array();
        $id_list   = [];
        foreach ($synergyIds as $id) {
            if (strlen($id)) {
                $id_list[] = $this->getDatabase()->quote($id);
            }
        };
        if (empty($id_list)) {
            return array();
        }
        $id_list = implode(',', $id_list);
        $statement = $this->getDatabase()->prepare(
            "
            SELECT
                parent.synergy_id AS StudentContactID,
                parent.alt_email AS StudentContactEmail
            FROM `user`
            INNER JOIN user_relation ON user.id = user_relation.relation_id
            INNER JOIN `user` parent ON parent.id = user_relation.user_id
            WHERE user.synergy_id IN ($id_list)
        "
        );
        $statement->execute();
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $emailList[$row['StudentContactID']] = $row['StudentContactEmail'];
        }
        return $emailList;
    }

    /**
     * Get a student by id
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getStudent($externalUserId)
    {
        $statement = $this->getDatabase()->prepare(
            "
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
                            '' AS StudentStudiesCode,
                            '' AS AlertMessage,
                            '' AS StudentBoarder
                        FROM `user`
                        LEFT JOIN user_profile ON user.id = user_profile.user_id
                        WHERE user.synergy_id = :externalUserId
                    "
        );
        $statement->execute(
            array(
                ':externalUserId' => $externalUserId
            )
        );
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get details for a student's contacts
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getStudentContactList($externalUserId)
    {
        // Updated query:
        // no longer relies on user_profile being populated to fetch parent ID
        $statement = $this->getDatabase()->prepare(
            "
                        SELECT
                            parent.synergy_id AS StudentContactID,
                            '' AS StudentContactTitle,
                            '' AS StudentContactPreferred,
                            '' AS StudentContactSurname,
                            pp.guardian1Name AS StudentContactPreferred,
                            '' AS StudentContactSurname,
                            pp.guardian1Email AS StudentContactDefaultEmail,
                            pp.guardian1Address AS StudentContactAddress1,
                            pp.guardian1Suburb AS StudentContactSuburb,
                            pp.guardian1Postcode AS StudentContactPostCode,
                            '' AS StudentContactState,
                            pp.guardian1HomePhone AS StudentContactPhoneActual,
                            pp.guardian1MobilePhone AS StudentContactMobilePhone,
                            pp.guardian1WorkPhone AS StudentContactOccupPhone,
                            '' AS StudentContactSpouseID,
                            '' AS StudentContactSpouseTitle,
                            '' AS StudentContactSpousePreferred,
                            '' AS StudentContactSpouseSurname,
                            pp.guardian2Name AS StudentContactSpousePreferred,
                            '' AS StudentContactSpouseSurname,
                            pp.guardian2Email AS StudentContactSpouseDefaultEmail,
                            pp.guardian2Address AS StudentContactSpouseAddress1,
                            pp.guardian2Suburb AS StudentContactSpouseSuburb,
                            pp.guardian2Postcode AS StudentContactSpousePostCode,
                            '' AS StudentContactSpouseState,
                            pp.guardian2HomePhone AS StudentContactSpousePhoneActual,
                            pp.guardian2MobilePhone AS StudentContactSpouseMobilePhone,
                            pp.guardian2WorkPhone AS StudentContactSpouseOccupPhone
                        FROM `user` child
                        INNER JOIN user_relation ur ON child.id = ur.relation_id
                        INNER JOIN `user` parent ON ur.user_id = parent.id
                        LEFT JOIN user_profile pp ON `parent`.id = pp.user_id
                        WHERE child.synergy_id = :externalUserId
                    "
        );
        $statement->execute(
            array(
                ':externalUserId' => $externalUserId
            )
        );
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    function getStaff($externalUserId)
    {
    }

    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {

    }


    /**
     * Alex's weird usage
     *
     * @param $externalUserId
     *
     * @return array
     */
    public function getLeaveStudentContactList($externalUserId)
    {
        $statement           = $this->getDatabase()->query(
            "
                SELECT u.*
                FROM user_relation ur
                LEFT JOIN user u ON u.id = ur.user_id
                WHERE ur.relation_id = {$externalUserId}
                  AND ur.relation_type = " . External_Schoolbox_User::RELATION_TYPE_PARENT . "
            "
        );
        $contactList         = $statement->fetchAll(PDO::FETCH_ASSOC);
        $contactAdaptionList = array();
        if (!empty($contactList)) {
            foreach ($contactList as $contact) {
                $contactAdaption       = array(
                    'StudentContactID'               => $contact['id'],
                    'StudentContactTitle'            => $contact['title'],
                    'StudentContactPreferred'        => $contact['firstname'],
                    'StudentContactSurname'          => $contact['lastname'],
                    'StudentContactDefaultEmail'     => $contact['alt_email'],
                    'StudentContactAddress1'         => '',
                    'StudentContactSuburb'           => '',
                    'StudentContactPostCode'         => '',
                    'StudentContactState'            => '',
                    'StudentContactPhoneActual'      => '',
                    'StudentContactMobilePhone'      => '',
                    'StudentContactOccupPhone'       => '',
                    'StudentContactSpouseID'         => '',
                    'StudentContactSpouseTitle'      => '',
                    'StudentContactSpousePreferred'  => '',
                    'StudentContactSpouseSurname'    => '',
                    'StudentContactSpouseMobilePhon' => '',
                    'StudentContactSpouseOccupPhone' => '',
                    'StudentContactSpouseDefaultEma' => '',
                    'StudentContactType'             => 'SC1',
                );
                $contactAdaptionList[] = $contactAdaption;
            }
        }
        return $contactAdaptionList;
    }

    function getCurrentSemester($externalUserId = null)
    {
        return date('m') < 6 ? 1 : 2;
    }

    public function getCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    function getSubjectList($externalUserId, $userId)
    {
        if (empty($externalUserId)) {
            return false;
        }
        $params = array(':externalUserId' => $externalUserId,);
        // Subjects this user is a student in
        $statement = $this->getDatabase()->prepare(
            "
                        SELECT c.name, c.code
                        FROM user u
                        INNER JOIN class_user cu ON cu.user_id = u.id
                        INNER JOIN class c ON c.id = cu.class_id
                        WHERE u.synergy_id = :externalUserId
                        ORDER BY c.code ASC, c.name ASC
                    "
        );
        $statement->execute($params);
        $classList = $statement->fetchAll(PDO::FETCH_ASSOC);
        // Subjects this user is a teacher in
        $statement = $this->getDatabase()->prepare(
            "
                        SELECT c.name, c.code
                        FROM user u
                        INNER JOIN class_period cp ON cp.teacher_id = u.id
                        INNER JOIN class c ON c.id = cp.class_id
                        WHERE u.synergy_id = :externalUserId
                        ORDER BY c.code ASC, c.name ASC
                    "
        );
        $statement->execute($params);
        $classList = array_merge($classList, $statement->fetchAll(PDO::FETCH_ASSOC));
        return array_values(array_unique($classList, SORT_REGULAR));
    }

    function getEnrolledStudents($externalUserId)
    {
        $statement = $this->getDatabase()->prepare(
            "
                              SELECT c.synergy_id AS RelatedID
                              FROM user_relation ur
                              LEFT JOIN user p ON p.id = ur.user_id
                              LEFT JOIN user c ON c.id = ur.relation_id
                              WHERE p.synergy_id = :externalUserId
                                AND ur.relation_type = :relationType
                          "
        );
        $statement->execute(
            array(
                ':externalUserId' => $externalUserId,
                ':relationType'   => self::RELATION_TYPE_PARENT
            )
        );
        $childList = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $childList;
    }

    function getEmail($externalUserId)
    {

    }

    public function getStudentUserAccountInfo()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getStudentUserAccountIterator()
    {
        // Will probably never exist for Schoolbox connector
        return new Schoolbox\Sync\Iterator\Student(new ArrayIterator());
    }

    public function getParentUserAccountInfo()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getParentUserAccountIterator()
    {
        // Will probably never exist for Schoolbox connector
        return new Schoolbox\Sync\Iterator\Guardian(new ArrayIterator());
    }

    public function getStaffUserAccountInfo()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getStaffUserAccountIterator()
    {
        // Will probably never exist for Schoolbox connector
        return new Schoolbox\Sync\Iterator\Staff(new ArrayIterator());
    }

    /**
     * Determine the boarder type based on a value
     * Returns a human-readable string
     *
     * @param $boarderValue
     *
     * @return string
     */
    public function determineBoarderType($boarderValue)
    {
        $boarders = array('B', 'W');
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
     *
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {

        $portraitPath = realpath(Config::get('portrait_dir')) . "/$externalUserId.jpg";

        if (is_readable($portraitPath)) {
            return $portraitPath;
        } else {
            return false;
        }
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
     * @param Database $database
     *
     * @return void
     */
    private function setDatabase(Database $database)
    {
        $this->database = $database;
    }

    /**
     * @return Database
     */
    private function getDatabase()
    {
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

    public function getHousemasterByHouseId($houseID)
    {
        return false;
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
