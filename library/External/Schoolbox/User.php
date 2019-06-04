<?php
/**
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\External\UserRelation;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Schoolbox_User implements External_UserInterface
{

    /** @var IConnector $internalDatabase */
    private $internalDatabase;


    /**
     * External_Schoolbox_User constructor.
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->internalDatabase = DatabaseFactory::getInstanceInternal();
    }

    /**
     * {@inheritDoc}
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $emailList = [];
        $id_list   = array_filter($externalUserIds);

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
                ['relation_type' => UserRelation::RELATION_TYPE_PARENT]
            )
        );

        foreach ($parentIterator as $parent) {
            $emailList[$parent['StudentContactID']] = $parent['StudentContactEmail'];
        }

        return $emailList;
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getStudent(string $externalUserId): array
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
                          '' AS StudentMobilePhone,
                          '' AS StudentSpecialNeedsFlag,
                          user_profile.esl_flag AS EnglishSecondFlag,
                          '' AS StudentStudiesCode,
                          '' AS AlertMessage,
                          '' AS CourtOrderType,
                          '' AS StudentBoarder,
                          '' AS Comment,
                          '' AS StudentEnrolmentDate,
                          '' AS StudentLeavingDate,
                          user_profile.ib_flag AS StudentIBFlag,
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
     * @throws \Alaress\Database\Exception
     */
    public function getStudentContactList(string $externalUserId): array
    {
        return $this->internalDatabase->executeQuery(
            "
                SELECT      parent.synergy_id AS StudentContactID,
                            parent.title AS StudentContactTitle,
                            parent.firstname AS StudentContactPreferred,
                            parent.lastname AS StudentContactSurname,
                            pp.guardian1Email AS StudentContactDefaultEmail,
                            pp.guardian1HomePhone AS StudentContactPhoneActual,
                            0 AS StudentContactSilentPhoneFlag,
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
                'typeParent'     => UserRelation::RELATION_TYPE_PARENT,
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
     * @throws \Alaress\Database\Exception
     */
    public function getSubjectList(User $user): array
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
     * @throws \Alaress\Database\Exception
     */
    public function getEnrolledStudents(string $externalUserId): array
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
                'relationType'   => UserRelation::RELATION_TYPE_PARENT,
            ]
        )->fetchAll();
    }

    /**
     * {@inheritDoc}
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        $boarders = ['B', 'W'];

        if (in_array($boarderValue, $boarders, true)) {
            return 'Boarder';
        }

        return 'Day';
    }

    /**
     * {@inheritDoc}
     *
     * @param User $user
     * @return Storage\Model|null
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        return $user->getPortrait();
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        return date('m') < 6 ? '1' : '2';
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        return date('Y');
    }

    // Useless functions

    /**
     * {@inheritDoc}
     */
    public function getStaffContactListExtended(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getHousemasterByHouseId(string $houseID): ?string
    {
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStudentForms(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * @return array
     */
    public function getAllYearLevels()
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(string $externalUserId): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getParentSync(array $externalUserIds): array
    {
        return [];
    }


    /**
     * {@inheritDoc}
     * @return array
     */
    public function getStudentUserAccountInfo(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getParentUserAccountInfo(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStaffUserAccountInfo(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStaff(string $externalUserId): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }
}
