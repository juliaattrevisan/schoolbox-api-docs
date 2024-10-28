<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\External\Normalise\TupleStack;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

/**
 * Class External_Schoolbox_User
 */
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
            "SELECT     parent.external_id AS StudentContactID,
                            supp.email AS StudentContactEmail
                FROM        sis_user_profile child
                JOIN        sis_user_profile_relation supr ON child.id = child_profile_id
                JOIN        sis_user_profile parent ON supr.user_profile_id = parent.id
                JOIN        sis_user_profile_parent supp on parent.id = supp.sis_user_profile_id
                WHERE       child.external_id IN ({$predicate['placeholders']})
            ",
            $predicate['bindings']
        );

        foreach ($parentIterator as $parent) {
            $emailList[] = $parent['StudentContactEmail'];
        }

        return (new TupleStack())->normaliseValues($emailList);
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getStudent(string $externalUserId): array
    {
        $student = $this->internalDatabase->executeQuery(
            "SELECT   external_id AS StudentID,
                          firstname AS StudentGiven1,
                          lastname AS StudentSurname,
                          gender AS StudentGender,
                          UNIX_TIMESTAMP(date_of_birth) AS StudentBirthDate,
                          tutor_teacher_name AS StudentTutorName,
                          tutor_group AS StudentTutorDescription,
                          tutor_teacher_id AS TeacherTutorID,
                          house_name AS StudentHouseDescription,
                          house_name AS StudentHouse,
                          head_of_house_name AS StudentHeadOfHouse,
                          head_of_house_id AS HeadOfHouseID,
                          form_teacher_name AS StudentFormStaffName,
                          form_teacher_id AS TeacherFormID,
                          form_name AS StudentForm,
                          year_level AS StudentYearLevel,
                          sups.emergency_contact_name_1 AS StudentEmergencyName,
                          sups.emergency_contact_phone_1 AS StudentEmergencyPhone,
                          sups.emergency_contact_phone_alt_1 AS StudentEmergencyPhoneAlt,
                          sups.emergency_contact_name_2 AS StudentEmergency2Name,
                          sups.emergency_contact_phone_2 AS StudentEmergency2Phone,
                          sups.emergency_contact_phone_alt_2 AS StudentEmergency2PhoneAlt,
                          sups.emergency_contact_name_3 AS StudentEmergency3Name,
                          sups.emergency_contact_phone_3 AS StudentEmergency3Phone,
                          sups.emergency_contact_phone_alt_3 AS StudentEmergency3PhoneAlt,
                          alert_medical AS StudentMedicalAlert,
                          mobile AS StudentMobilePhone,
                          special_needs AS StudentSpecialNeedsFlag,
                          esl AS EnglishSecondFlag,
                          studies_code AS StudentStudiesCode,
                          alert_message AS AlertMessage,
                          alert_legal AS CourtOrderType,
                          boarding_status AS StudentBoarder,
                          alert_comment AS Comment,
                          year_level_coordinator_id AS YearLevelCoordID,
                          year_level_coordinator_name AS StudentYearLevelCoordinator,
                          '' AS StudentEnrolmentDate,
                          '' AS StudentLeavingDate,
                          '' AS ParentsSeparatedFlag,
                          ib AS StudentIBFlag,
                          '' AS IndigenousFlag,
                          '' AS StudentTSIFlag,
                          individual_learning_plans AS countIndividualEducationPlans
                FROM      `sis_user_profile` sup
                JOIN      `sis_user_profile_student` sups ON sup.id = sups.sis_user_profile_id
                WHERE      sup.external_id = :externalUserId
            ",
            ['externalUserId' => $externalUserId]
        )->fetch();

        /* Couldn't find this record via the $externalUserId
         * Exit early
         */
        if (!$student) {
            return [];
        }

        /* Forcibly obey External_UserInterface
         */
        $student['AlertMessage'] = array_unique(array_filter([$student['AlertMessage']]));

        /* Legal Alerts are distinguished by their CourtOrderType node.
         * If that CourtOrderType node was meaningful, then it's a Legal Alert and gets special treatment.
         * Otherwise it's a generic alert.
         */
        $courtOrderType = trim((string)($student['CourtOrderType'] ?? null));

        if ('' !== $courtOrderType) {
            /* A specially-crafted AlertLegal message about legal matters.
             */
            $student['AlertLegal'] = [sprintf(
                '%s - %s',
                \External_UserInterface::COURT_ORDER_PREFIX,
                $courtOrderType
            )];
        } else {
            $student['AlertLegal'] = [];
        }

        return $student;
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($externalUserId, 'studentIds');

        return $this->internalDatabase->executeQuery(
            "SELECT     parent.external_id AS StudentContactID,
                            parent.title AS StudentContactTitle,
                            parent.firstname AS StudentContactPreferred,
                            parent.lastname AS StudentContactSurname,
                            supp.email AS StudentContactDefaultEmail,
                            supp.phone_home AS StudentContactPhoneActual,
                            0 AS StudentContactSilentPhoneFlag,
                            supp.mobile AS StudentContactMobilePhone,
                            supp.phone_work AS StudentContactOccupPhone,
                            supr.type AS StudentContactRelation,
                            '' AS StudentContactSpouseID,
                            '' AS StudentContactSpouseTitle,
                            '' AS StudentContactSpousePreferred,
                            '' AS StudentContactSpouseSurname,
                            '' AS StudentContactSpouseDefaultEmail,
                            '' AS StudentContactSpousePhoneActual,
                            '' AS StudentContactSpouseMobilePhone,
                            '' AS StudentContactSpouseOccupPhone
                FROM        sis_user_profile child
                    JOIN    sis_user_profile_relation supr ON child.id = child_profile_id
                    JOIN    sis_user_profile parent ON supr.user_profile_id = parent.id
                    JOIN    sis_user_profile_parent supp on parent.id = supp.sis_user_profile_id
                WHERE       child.external_id IN ({$predicate['placeholders']})
            ",
            $predicate['bindings']
        )->fetchAll();
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getSubjectList(User $user): array
    {
        $externalId = trim((string)$user->getExternalId());

        /* This user doesn't have an external id; nothing to find
         */
        if ('' === $externalId) {
            return [];
        }

        $params = [
            'externalUserId' => $externalId
        ];

        /* Look inside sis_class_period for teachers officially teaching a class
         * There can be only one official teacher of a class in the classical grid
         */
        $officialTeachers = $this->internalDatabase->executeQuery(
            "SELECT     c.name,
                            c.code,
                            '' AS classType,
                            '' AS campusCode
                FROM        sis_class_period cp
                INNER JOIN  sis_class c ON c.id = cp.class_id
                WHERE       cp.teacher_external_id = :externalUserId
                ORDER BY    c.code ASC,
                            c.name ASC
            ",
            $params
        )->fetchAll();

        /* Look inside sis_class_user for extra teacher involvements
         * Teachers will be in here if schools are using the classical grid
         * or if every teacher involved in a class instance is involved in
         * all their instances. There can only be one official teacher of a class
         * in a classical grid, but any additional teachers were added into
         * sis_class_user (where the students live) as a workaround.
         */
        $additionalTeachers = $this->internalDatabase->executeQuery(
            "SELECT     c.name,
                            c.code,
                            '' AS classType,
                            '' AS campusCode
                FROM        sis_class_user cu
                INNER JOIN  sis_class c ON c.id = cu.class_id
                WHERE       cu.external_id = :externalUserId
                ORDER BY    c.code ASC, c.name ASC
            ",
            $params
        )->fetchAll();

        /* Schools using the SUDS Timetable system might have teachers in
         * the Overrides system, sis_event_user_overrides - go looking in
         * there for additional guys
         *
         * We only want teachers that, after a series of Override mutations,
         * still remain in the result set. Override mutations can add and
         * remove people in any combination in any order and in any quantity
         * so you have to do a bit of math to figure out the final answer.
         *
         * $futureThreshold is the length of time into the future to look
         * for involvements. You might not be a teacher of a class today,
         * but you might be some time later.
         *
         * $pastThreshold is the length of time into the past to look
         * for involvements. You might have been the teacher of a class
         * at some point in the past, but no longer
         *
         * $pastThreshold and $futureThreshold are both defaulted to Just Today
         * This means anyone who is *not* the usual teacher will lose their
         * access to homepages etc. via the My Subjects in the side menu at the
         * end of the day (midnight). This rule applies universally.
         *
         * We chose not to accommodate covering teachers having more access than
         * this; if a school wants that, add them to the homepage manually.
         */
        $overridesTeachers = [];
        $config = \Schoolbox\Config\Loader::getInstance();

        $pastThreshold = \Alaress_Date_Format::now()->startOfDay()->getUnixTimeStamp();
        $futureThreshold = \Alaress_Date_Format::now()->endOfDay()->getUnixTimeStamp();

        $events = (new \Schoolbox\External\Event\TimetableUsers(
            \Schoolbox\External\ClassEntryMapper::getInstance(),
            \Schoolbox\External\ClassInvolvementMapper::getInstance(),
            \Schoolbox\External\Event\Mapper::getInstance(),
            \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
            \Schoolbox\External\Profile\Staff\Mapper::getInstance()
        ))->findClassCodesForUser($user, $pastThreshold, $futureThreshold);

        /* Shank it into the required format
         */
        foreach ($events as $event) {
            $overridesTeachers[] = [
                'name' => $event['name'],
                'code' => $event['code'],
                'classType' => '',
                'campusCode' => ''
            ];
        }

        return array_values(
            array_unique(
                array_merge(
                    $officialTeachers,
                    $additionalTeachers,
                    $overridesTeachers
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
            'SELECT sup_child.external_id AS RelatedID
                FROM sis_user_profile sup
                    JOIN sis_user_profile_parent supp on sup.id = supp.sis_user_profile_id
                    JOIN sis_user_profile_relation supr ON supp.sis_user_profile_id =supr.user_profile_id
                    JOIN sis_user_profile sup_child ON supr.child_profile_id = sup_child.id
                WHERE sup.external_id = :externalUserId
            ',
            [
                'externalUserId' => $externalUserId,
            ]
        )->fetchAll();
    }


    /**
     * {@inheritDoc}
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        return $boarderValue ? 'Boarder' : '';
    }


    /**
     * Get portrait for the user from sis_user_profile, if newer than the existing photo.
     * Return existing portrait otherwise.
     *
     * @param User $user
     * @return Storage\Model|null
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        if (!$user->getExternalId()) {
            return $user->getPortrait();
        }

        $hash = $this->internalDatabase->executeQuery(
            'SELECT portrait_hash, updated_at
                FROM sis_user_profile
                WHERE external_id = :externalUserId
            ',
            [
                'externalUserId' => $user->getExternalId(),
            ]
        )->fetch();

        if (
            !$hash
            || ($user->getPortrait()
                && $user->getPortrait()->getCreatedAt()->gt(Alaress_Date_Format::newDatabaseDate($hash['updated_at'])))
        ) {
            return $user->getPortrait();
        }

        // using hash from sis_user_profile table

        $storageMapper = Storage\Mapper::getInstance();
        try {
            $portrait = $storageMapper->findByHash($hash['portrait_hash']);
            $user->setPortrait($portrait);
            \Schoolbox\UserMapper::getInstance()->save($user);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }

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
        $query = "
            SELECT
                external_id AS StaffID,
                username AS NetworkLogin,
                '' AS ActiveFlag,
                title AS StaffTitle,
                firstname AS StaffFirstname,
                preferred_firstname AS StaffPreferredName,
                lastname AS StaffSurname,
                email AS StaffOccupEmail,
                '' AS DefaultEmailCode,
                building AS StaffBuilding,
                room AS StaffRoom,
                position_title AS JobPositionDescription,
                phone AS StaffOccupPhone,
                '' AS StaffExtension,
                campus_name AS StaffCampusDescription,
                category AS StaffCategory,
                department AS StaffDepartment
            FROM `sis_user_profile` sup
            JOIN `sis_user_profile_staff` sups ON sup.id = sups.sis_user_profile_id
            WHERE 1=1
        ";

        /* Apply any restrictions that were specified in admin
         * Filter out any meaningless values
         *
         * Do I not have a configLoader here...?
         */
        $config = \Schoolbox\Config\Loader::getInstance();
        $params = [];

        /* Staff Category blacklist
         */
        $staffCategorySql = $this->buildRestrictionSql('staffCategory', ',', (string)$config->get('staff_dir_category_restrict'));

        if (null !== $staffCategorySql) {
            $params = array_merge($staffCategorySql['bindings'], $params);

            $query .= "
                AND (sups.category IS NULL OR sups.category NOT IN ({$staffCategorySql['placeholders']}))
            ";
        }

        /* Staff Position Title blacklist
         * Note: this is only from the sis tables, *not* user account's Position Title
         */
        $staffPositionTitleSql = $this->buildRestrictionSql('staffPositionTitle', "\n", (string)$config->get('staff_dir_position_title_restrict'));

        if (null !== $staffPositionTitleSql) {
            $params = array_merge($staffPositionTitleSql['bindings'], $params);

            $query .= "
                AND (sups.position_title IS NULL OR sups.position_title NOT IN ({$staffPositionTitleSql['placeholders']}))
            ";
        }

        return $this->internalDatabase->executeQuery($query, $params)->fetchAll();
    }


    /**
     * Build sql fragments for staff directory restrictions, compiling them
     * out of the config fields' csv strings.
     *
     * @param string $name
     * @param string $separator
     * @param string $csv
     * @return array|null
     */
    private function buildRestrictionSql(string $name, string $separator, string $csv): ?array
    {
        /* Exit early if csv is an empty string
         */
        if ('' === trim($csv)) {
            return null;
        }

        $blacklist = array_map('trim', explode($separator, $csv));

        return Builder::getArrayBindings($blacklist, $name);
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
    public function getHousemasterEmails(array $externalUserIds): array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getTutorEmails(array $externalUserIds): array
    {
        $emailList = [];
        $idList   = array_filter($externalUserIds);

        if (empty($idList)) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($idList, 'externalId');

        $tutorIterator = $this->internalDatabase->getQueryIterator(
            "SELECT sis_user_profile_staff.email AS TutorEmail
                FROM sis_user_profile_staff
                JOIN sis_user_profile staff ON staff.id = sis_user_profile_staff.sis_user_profile_id
                JOIN sis_user_profile_student ON sis_user_profile_student.tutor_teacher_id = staff.external_id
                JOIN sis_user_profile student ON student.id = sis_user_profile_student.sis_user_profile_id
                WHERE student.external_id IN ({$predicate['placeholders']})
            ",
            $predicate['bindings']
        );

        foreach ($tutorIterator as $tutor) {
            $emailList[] = $tutor['TutorEmail'];
        }

        return (new TupleStack())->normaliseValues($emailList);
    }


    /**
     * {@inheritDoc}
     */
    public function getStudentForms(): array
    {
        return $this->internalDatabase->executeQuery('SELECT DISTINCT form_name AS Code, form_name as Description FROM sis_user_profile_student')->fetchAll();
    }


    /**
     * {@inheritDoc}
     *
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function getAllYearLevels(): array
    {
        return $this->internalDatabase->executeQuery('SELECT DISTINCT year_level AS Code, year_level as Description FROM sis_user_profile_student')->fetchAll();
    }


    /**
     * {@inheritDoc}
     */
    public function getParent(string $externalUserId): array
    {
        $parent = $this->internalDatabase->executeQuery(
            "SELECT   title AS Title,
                         firstname AS Preferred,
                         lastname AS Surname,
                         email AS Email,
                         '' AS OccupEmail,
                         '' AS DefaultEmailCode,
                         phone_home AS HomePhone,
                         mobile AS MobilePhone,
                         phone_work AS WorkPhone,
                         1 AS DirectoryIncludeFlag,
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
            ",
            ['externalUserId' => $externalUserId]
        )->fetch();

        return $parent ?: [];
    }


    /**
     * {@inheritDoc}
     */
    public function getStaff(string $externalUserId): array
    {
        $staff = $this->internalDatabase->executeQuery(
            "SELECT    external_id AS SchoolStaffCode,
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
        $bindings = [];
        $yearLevelQuery = '';
        $nameQuery = '';
        $boardingQuery = '';
        $formQuery = '';

        if ($yearLevel) {
            $yearLevelQuery = ' AND sups.year_level = :year_level';
            $bindings[':year_level'] = $yearLevel;
        }

        if ($name) {
            $nameQuery = " AND CONCAT(child.firstname,' ',child.lastname) LIKE :name";
            $bindings[':name'] = '%' . $name . '%';
        }

        if ($boardingStatus) {
            $boardingQuery = ' AND sups.boarding_status = :boarding_status';
            $bindings[':boarding_status'] = $boardingStatus == 'yes' ? 1 : 0;
        }

        if ($form) {
            $formQuery = ' AND sups.form_name = :form';
            $bindings[':form'] = $form;
        }

        return $this->internalDatabase->executeQuery(
            "SELECT
                        1 AS PrimaryDirectoryIncludeFlag,
                        '' AS SpouseDirectoryIncludeFlag,
                        sups.boarding_status AS StudentBoarder,
                        '' AS StudentContactAddress1,
                        '' AS StudentContactAddress2,
                        '' AS StudentContactAddress3,
                        '' AS StudentContactCountryCode,
                        supp.email AS StudentContactDefaultEmail,
                        '' AS StudentContactHomeAddress1,
                        '' AS StudentContactHomeAddress2,
                        '' AS StudentContactHomeAddress3,
                        '' AS StudentContactHomeCountryCode,
                        '' AS StudentContactHomePostCode,
                        '' AS StudentContactHomeState,
                        '' AS StudentContactHomeSuburb,
                        parent.external_id AS StudentContactID,
                        '' AS StudentContactLegalFullName,
                        supp.mobile  AS StudentContactMobilePhone,
                        supp.phone_work AS StudentContactOccupPhone,
                        supp.phone_home AS StudentContactPhoneActual,
                        '' AS StudentContactPostCode,
                        parent.firstname AS StudentContactPreferred,
                        '' AS StudentContactSilentPhoneFlag,
                        '' AS StudentContactSpouseDefaultEmail,
                        '' AS StudentContactSpouseID,
                        '' AS StudentContactSpouseLegalFullName,
                        '' AS StudentContactSpouseMobilePhone,
                        '' AS StudentContactSpouseOccupPhone,
                        '' AS StudentContactSpousePreferred,
                        '' AS StudentContactSpouseSurname,
                        '' AS StudentContactSpouseTitle,
                        '' AS StudentContactState,
                        '' AS StudentContactSuburb,
                        parent.lastname AS StudentContactSurname,
                        parent.title AS StudentContactTitle,
                        supr.type AS StudentContactType,
                        sups.form_name AS StudentForm,
                        sups.house_name AS StudentHouse,
                        child.external_id AS StudentID,
                        '' AS StudentParentsSeparatedFlag,
                        child.firstname AS StudentPreferred,
                        child.lastname AS StudentSurname,
                        sups.year_level AS StudentYearLevel
            FROM      `sis_user_profile` child
            JOIN      `sis_user_profile_student` sups ON child.id = sups.sis_user_profile_id
            JOIN      sis_user_profile_relation supr ON child.id = child_profile_id
            JOIN      sis_user_profile parent ON supr.user_profile_id = parent.id
            JOIN      sis_user_profile_parent supp on parent.id = supp.sis_user_profile_id
            WHERE     1=1 {$yearLevelQuery} {$formQuery} {$nameQuery} {$boardingQuery}
        ",
            $bindings
        )->fetchAll();
    }


    /**
     * {@inheritDoc}
     * @return array
     * @throws \Alaress\Database\Exception
     */
    public function getPhotoRedactionList(): array
    {

        /* Get a list of students, their associated house and associated year level
         * alongside their redaction status. This requires UPS to have run successfully
         * to create the user accounts and year level/house assignations (if any exist)
         */
        $query = "
            SELECT
                u.id,
                u.firstname,
                u.lastname,
                lyl.name AS yearLevel,
                h.house,
                sup.external_id,
                sups.photo_redacted,
                s.uploaded_on AS redact_photo_modified_at
            FROM sis_user_profile_student sups
            INNER JOIN sis_user_profile sup ON sups.sis_user_profile_id = sup.id
            INNER JOIN `user` u ON sup.external_id = u.synergy_id
            LEFT JOIN lu_year_level lyl ON u.year_id = lyl.id
            LEFT JOIN house h ON u.house = h.id
            LEFT JOIN storage s ON u.portrait_hash = s.hash
        ";

        return $this->internalDatabase->executeQuery($query)->fetchAll();
    }
}
