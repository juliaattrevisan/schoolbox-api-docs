<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\User;

class External_Schoolbox_User implements External_UserInterface
{
    /** User relationship types */
    const RELATION_TYPE_PARENT = 1;

    /** @var IConnector $internalDatabase */
    private $internalDatabase;

    /** @var ConfigMapper $configMapper */
    private $configMapper;

    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        $this->configMapper = ConfigMapper::getInstance();

        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }

    /**
     * {@inheritDoc}
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = [];
        $id_list   = array_filter((array)$synergyIds);

        if (empty($id_list)) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($id_list, 'contactId');

        $parentIterator = $this->internalDatabase->getQueryIterator(
            "
                SELECT      parent.synergy_id AS StudentContactID,
                            parent.alt_email AS StudentContactEmail
                FROM        `user`
                INNER JOIN  user_relation ON user.id = user_relation.relation_id
                INNER JOIN  `user` parent ON parent.id = user_relation.user_id AND parent.deleted_at IS NULL
                WHERE       user.synergy_id IN ({$predicate["placeholders"]}) AND
                            user_relation.relation_type = :relation_type AND
                            `user`.deleted_at IS NULL
            ",
            array_merge(
                $predicate['bindings'],
                ['relation_type' => self::RELATION_TYPE_PARENT]
            )
        );

        foreach ($parentIterator as $parent) {
            $emailList[$parent['StudentContactID']] = $parent['StudentContactEmail'];
        }

        return $emailList;
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return array
     */
    public function getStudent($externalUserId)
    {
        return $this->internalDatabase->executeQuery(
            "
                SELECT    user.id AS StudentID,
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
                          '' AS StudentBoarder,
                          '' AS Comment,
                          0 AS countIndividualEducationPlans
                FROM      `user`
                LEFT JOIN user_profile ON user.id = user_profile.user_id
                WHERE     user.synergy_id = :externalUserId
                AND       `user`.deleted_at IS NULL
            ",
            ['externalUserId' => $externalUserId]
        )->fetch();
    }

    /**
     * {@inheritDoc}
     * @param string $externalUserId
     *
     * @return array
     */
    public function getStudentContactList($externalUserId)
    {
        return $this->internalDatabase->executeQuery(
            "
                SELECT      parent.synergy_id AS StudentContactID,
                            parent.title AS StudentContactTitle,
                            parent.firstname AS StudentContactPreferred,
                            parent.lastname AS StudentContactSurname,
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
                FROM        `user` child
                INNER JOIN  user_relation ur ON child.id = ur.relation_id
                INNER JOIN  `user` parent ON ur.user_id = parent.id AND parent.deleted_at IS NULL
                LEFT JOIN   user_profile pp ON `parent`.id = pp.user_id
                WHERE       child.synergy_id = :externalUserId AND 
                            child.deleted_at IS NULL
            ",
            ['externalUserId' => $externalUserId]
        )->fetchAll();
    }

    /**
     * {@inheritDoc}
     * @param $externalUserId
     *
     * @return array
     */
    public function getLeaveStudentContactList($externalUserId)
    {
        $contactAdaptionList = [];

        $iterateContacts = $this->internalDatabase->getQueryIterator(
            "
                SELECT      u.*
                FROM        user_relation ur
                LEFT JOIN   `user` u ON u.id = ur.user_id AND u.deleted_at IS NULL
                WHERE       ur.relation_id = :externalUserId
                AND         ur.relation_type = :typeParent
            ",
            [
                'externalUserId' => $externalUserId,
                'typeParent'     => External_Schoolbox_User::RELATION_TYPE_PARENT,
            ]
        );

        foreach ($iterateContacts as $contact) {
            $contactAdaptionList[] = [
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
            ];
        }

        return $contactAdaptionList;
    }

    /**
     * {@inheritDoc}
     * @param User $user
     *
     * @return array
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        $params = ['externalUserId' => $externalId];

        $classQuery = $this->internalDatabase->executeQuery(
            "
                SELECT      c.name, 
                            c.code
                FROM        user u
                INNER JOIN  class_user cu ON cu.user_id = u.id
                INNER JOIN  class c ON c.id = cu.class_id
                WHERE       u.synergy_id = :externalUserId
                AND         u.deleted_at IS NULL
                ORDER BY    c.code ASC, c.name ASC
            ",
            $params
        );

        $classList = $classQuery->fetchAll();

        $subjectQuery = $this->internalDatabase->executeQuery(
            "
                SELECT      c.name,
                            c.code
                FROM        user u
                INNER JOIN  class_period cp ON cp.teacher_id = u.id
                INNER JOIN  class c ON c.id = cp.class_id
                WHERE       u.synergy_id = :externalUserId
                AND         u.deleted_at IS NULL
                ORDER BY    c.code ASC, 
                            c.name ASC
            ",
            $params
        );

        return array_values(
            array_unique(
                array_merge(
                    $classList,
                    $subjectQuery->fetchAll()
                ),
                SORT_REGULAR
            )
        );
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return array
     */
    public function getEnrolledStudents($externalUserId)
    {
        return $this->internalDatabase->executeQuery(
            "
                SELECT      c.synergy_id AS RelatedID
                FROM        user_relation ur
                INNER JOIN  user p ON p.id = ur.user_id AND p.deleted_at IS NULL
                INNER JOIN  user c ON c.id = ur.relation_id AND c.deleted_at IS NULL
                WHERE       p.synergy_id = :externalUserId AND
                            ur.relation_type = :relationType
            ",
            [
                'externalUserId' => $externalUserId,
                'relationType'   => self::RELATION_TYPE_PARENT,
            ]
        )->fetchAll();
    }

    /**
     * {@inheritDoc}
     * @param string $boarderValue
     *
     * @return string
     */
    public function determineBoarderType($boarderValue)
    {
        $boarders = ['B', 'W'];

        if (!strlen($boarderValue)) {
            return '';
        } elseif (in_array($boarderValue, $boarders)) {
            return 'Boarder';
        }

        return 'Day';
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return bool|string
     */
    public function getPortrait($externalUserId)
    {
        $portraitPath = realpath($this->configMapper->get('portrait_dir')) . "/{$externalUserId}.jpg";

        if (is_readable($portraitPath)) {
            return $portraitPath;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return int
     */
    public function getCurrentSemester($externalUserId = null)
    {
        return date('m') < 6 ? 1 : 2;
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return string
     */
    public function getCurrentYear($externalUserId = null)
    {
        return date('Y');
    }

    // Useless functions

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getStaffContactListExtended()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @param int $staffID
     *
     * @return array
     */
    public function getCampusFromTimetable($staffID)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @param int $houseId
     */
    public function getHousemasterByHouseId($houseID)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     * @param array $externalUserIds
     */
    public function getHousemasterEmails($externalUserIds)
    {
        /** @noinspection PhpUnusedParameterInspection */
        return [];
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getStudentForms()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return array
     */
    public function getParent($externalUserId)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return array
     */
    public function getParentSync($externalUserId)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @return string
     */
    public function getEmail($externalUserId)
    {
        return '';
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getStudentUserAccountInfo()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getParentUserAccountInfo()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @return array
     */
    public function getStaffUserAccountInfo()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @param int $externalUserId
     *
     * @return array
     */
    public function getStaff($externalUserId)
    {
        return [];
    }

    /**
     * {@inheritDoc}
     * @param string $yearlevel
     * @param string $name
     * @param string $boardingStatus
     * @param string $form
     *
     * @return array
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        return [];
    }
}
