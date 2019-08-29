<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
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
        $student = $this->internalDatabase->executeQuery(
            "
                SELECT    external_id AS StudentID,
                          firstname AS StudentGiven1,
                          lastname AS StudentSurname,
                          UNIX_TIMESTAMP(date_of_birth) AS StudentBirthDate,
                          tutor_teacher_name AS StudentTutorName,
                          tutor_teacher_name AS StudentTutor,
                          tutor_group AS StudentTutorDescription,
                          tutor_teacher_id AS TeacherTutorID,
                          house_name AS StudentHouseDescription,
                          house_name AS StudentHouse,
                          head_of_house_name AS StudentHeadOfHouse,
                          head_of_house_id AS HeadOfHouseID,
                          form_teacher_name AS StudentFormStaffName,
                          form_teacher_id AS TeacherFormID,
                          form_name AS StudentForm,
                          form_name AS FormDescription,
                          year_level AS StudentYearLevel,
                          '' AS StudentLockerBook,
                          '' AS StudentEmergencyName,
                          '' AS StudentEmergencyPhone,
                          '' AS StudentEmergencyPhoneAlt,
                          alert_medical AS StudentMedicalAlert,
                          mobile AS StudentMobilePhone,
                          individual_learning_plans AS StudentSpecialNeedsFlag,
                          esl AS EnglishSecondFlag,
                          '' AS StudentStudiesCode,
                          alert_message AS AlertMessage,
                          alert_legal AS CourtOrderType,
                          boarding_status AS StudentBoarder,
                          alert_comment AS Comment,
                          '' AS StudentEnrolmentDate,
                          '' AS StudentLeavingDate,
                          ib AS StudentIBFlag,
                          0 AS countIndividualEducationPlans
                FROM      `sis_user_profile` sup
                JOIN      `sis_user_profile_student` sups ON sup.id = sups.sis_user_profile_id
                WHERE      sup.external_id = :externalUserId
                AND        sup.deleted_at IS NULL AND sups.deleted_at IS NULL
            ",
            ['externalUserId' => $externalUserId]
        )->fetch();

        return $student ?: [];
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
    public function getAllYearLevels(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(string $externalUserId): array
    {
        $parent = $this->internalDatabase->executeQuery(
            "
                SELECT   title AS Title,
                         firstname AS Preferred,
                         lastname AS Surname,
                         email AS Email,
                         '' AS OccupEmail,
                         '' AS DefaultEmailCode,
                         phone_home AS HomePhone,
                         '' AS SilentPhoneFlag,
                         mobile AS MobilePhone,
                         '' AS DirectoryIncludeFlag,
                         '' AS SpouseID,
                         '' AS SpouseTitle,
                         '' AS SpousePreferred,
                         '' AS SpouseSurname,
                         '' AS SpouseEmail,
                         '' AS SpouseOccupEmail,
                         '' AS SpouseDefaultEmailCode,
                         '' AS SpouseMobilePhone,
                         '' AS SpouseDirectoryIncludeFlag
                FROM      `sis_user_profile` sup
                JOIN      `sis_user_profile_parent` supp ON sup.id = supp.sis_user_profile_id
                WHERE      sup.external_id = :externalUserId
                AND        sup.deleted_at IS NULL AND supp.deleted_at IS NULL
            ",
            ['externalUserId' => $externalUserId]
        )->fetch();

        return $parent ?: [];
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
        $staff = $this->internalDatabase->executeQuery(
            "
            SELECT    external_id AS SchoolStaffCode,
                      building AS StaffBuilding,
                      room AS StaffRoom,
                      phone AS StaffExtension,
                      '' AS StaffMobilePhone,
                      '' AS StaffDefaultMobilePhone,
                      campus_name AS StaffCampusDescription,
                      category AS StaffCategoryDescription,
                      department AS StaffDepartment,
                      '' AS StaffVoiceMail,
                      '' AS StaffHouseDescription
            FROM      `sis_user_profile` sup
            JOIN      `sis_user_profile_staff` sups ON sup.id = sups.sis_user_profile_id
            WHERE      sup.external_id = :externalUserId
            AND        sup.deleted_at IS NULL AND sups.deleted_at IS NULL
        ",
            ['externalUserId' => $externalUserId]
        )->fetch();

        return $staff ?: [];
    }

    /**
     * {@inheritDoc}
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }
}
