<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Schoolbox\Database\Factory as DatabaseFactory;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Denbigh_User implements External_UserInterface
{
    /** @var IConnector $externalDatabase */
    private $externalDatabase;


    /**
     * External_Denbigh_Class constructor.
     */
    public function __construct()
    {
        /** @noinspection PhpDeprecationInspection */
        $this->externalDatabase = DatabaseFactory::getInstanceExternal();
    }

    /**
     * {@inheritDoc}
     * @param array $externalUserIds
     *
     * @return array
     */
    public function getParentEmails(array $externalUserIds): array
    {
        $id_list   = implode(',', array_map('intval', $externalUserIds));
        $parentIds = array_filter(explode(',', $id_list));

        if (count($parentIds) == 0) {
            return [];
        }

        $predicate = PredicateBuilder::getArrayBindings($parentIds, 'externalId');

        /** @noinspection SqlDialectInspection */
        $parentIterator = $this->externalDatabase->getQueryIterator(
            "
                SELECT        Family.email
                FROM          Schoolbox_Students
                LEFT JOIN     Family ON Schoolbox_Students.familyDenbighID = Family.familyDenbighID
                WHERE         Schoolbox_Students.denbighID IN ({$predicate["placeholders"]})
            ",
            $predicate["bindings"]
        );

        $emailList = [];

        foreach ($parentIterator as $parent) {
            $emailList = array_merge(
                $emailList,
                array_map('trim', preg_split('/[;,\s\r\n]/', $parent['email']))
            );
        }

        return $emailList;
    }

    /**
     * {@inheritDoc}
     */
    public function getHousemasterByHouseId(string $houseID): ?string
    {
        $house = $this->externalDatabase->executeQuery(
            "
                SELECT    Houses.housemaster_id
                FROM      Houses
                WHERE     Houses.id = :houseId
            ",
            ['houseId' => $houseID]
        )->fetch();

        return $house['housemaster_id'] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getParent(string $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        /** @noinspection SqlDialectInspection */
        $parentQuery = $this->externalDatabase->executeQuery(
            "
                SELECT      pe.denbighID,
                            pe.surname,
                            pe.given_names,
                            pe.preferred_name,
                            pe.title,
                            pe.gender,
                            pe.dateOfBirth,
                            pa.relationship,
                            a.telephoneHome
                FROM        People
                LEFT JOIN   Parents ON Parents.peopleDenbighID = People.denbighID
                LEFT JOIN   Parents pa ON pa.familyDenbighID = Parents.familyDenbighID
                LEFT JOIN   People pe ON pe.denbighID = pa.peopleDenbighID
                LEFT JOIN   Addresses a ON a.familyDenbighID = pa.familyDenbighID AND
                            a.addressType = 'Residential Address'
                WHERE       People.denbighID = :externalId
                ORDER BY    a.telephoneHome IS NOT NULL DESC,
                            a.addressDenbighID DESC,
                            pe.denbighID = :externalId2 DESC
            ",
            [
                'externalId'  => $externalUserId,
                'externalId2' => $externalUserId,
            ]
        );

        $parentList = $parentQuery->fetchAll();

        $adaption = [
            'Title'                      => '',
            'Preferred'                  => '',
            'Surname'                    => '',
            'Email'                      => '',
            'MobilePhone'                => '',
            'HomePhone'                  => '',
            'SilentPhoneFlag'            => '',
            'DirectoryIncludeFlag'       => '',
            'SpouseID'                   => '',
            'SpouseTitle'                => '',
            'SpousePreferred'            => '',
            'SpouseSurname'              => '',
            'SpouseEmail'                => '',
            'SpouseMobilePhone'          => '',
            'SpouseDirectoryIncludeFlag' => '',
        ];

        if (!empty($parentList[0])) {
            $adaption['Title']                = $parentList[0]['title'];
            $adaption['Preferred']            = $parentList[0]['preferred_name'];
            $adaption['Surname']              = $parentList[0]['surname'];
            $adaption['HomePhone']            = $parentList[0]['telephoneHome'];
            $adaption['SilentPhoneFlag']      = false;
            $adaption['DirectoryIncludeFlag'] = '';
        }

        if (!empty($parentList[1]) && $parentList[1]['denbighID'] != $externalUserId) {
            $adaption['SpouseID']                   = $parentList[1]['denbighID'];
            $adaption['SpouseTitle']                = $parentList[1]['title'];
            $adaption['SpousePreferred']            = $parentList[1]['preferred_name'];
            $adaption['SpouseSurname']              = $parentList[1]['surname'];
            $adaption['SpouseDirectoryIncludeFlag'] = '';
        }

        return $adaption;
    }

    /**
     * {@inheritDoc}
     */
    public function getStudent(string $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        /** @noinspection SqlDialectInspection */
        $studentQuery = $this->externalDatabase->executeQuery(
            "
                SELECT      pe.denbighID,
                            pe.surname,
                            pe.given_names,
                            pe.preferred_name,
                            pe.title,
                            pe.gender,
                            UNIX_TIMESTAMP(pe.dateOfBirth),
                            stu.tutorGroup, stu.house, stu.academicYear,
                            pe_sta.denbighID AS tutor_denbighID,
                            pe_sta.surname AS tutor_surname,
                            pe_sta.preferred_name AS tutor_preferred_name,
                            pe_sta.title AS tutor_title,
                            pe_pa.denbighID AS parent_denbighID,
                            pe_pa.surname AS parent_surname,
                            pe_pa.preferred_name AS parent_preferred_name,
                            pe_pa.title AS parent_title,
                            fa.EmergencyContact1,
                            fa.EmergencyRelationship1,
                            fa.EmergencyPhone1,
                            fa.EmergencyEmail1,
                            stu.boardingStatus,
                            0 AS countIndividualEducationPlans,
                            '' AS CourtOrderType,
                            '' AS StudentEnrolmentDate,
                            '' AS StudentLeavingDate,
                            '' AS StudentIBFlag
                FROM        People pe
                LEFT JOIN   Students stu ON stu.peopleDenbighID = pe.denbighID
                LEFT JOIN   Staff sta ON sta.tutorGroup = stu.tutorGroup
                LEFT JOIN   People pe_sta ON pe_sta.denbighID = sta.peopleDenbighID
                LEFT JOIN   Parents pa ON pa.familyDenbighID = stu.familyDenbighID
                LEFT JOIN   People pe_pa ON pe_pa.denbighID = pa.peopleDenbighID
                LEFT JOIN   Family fa ON stu.familyDenbighID = fa.familyDenbighID
                WHERE       pe.denbighID = :externalId
                LIMIT       1
            ",
            ['externalId' => $externalUserId]
        );

        $student = $studentQuery->fetch();

        $adaption = [
            'StudentID'                => '',
            'StudentGiven1'            => '',
            'StudentSurname'           => '',
            'StudentGender'            => '',
            'StudentBirthDate'         => '0',
            'StudentTutorName'         => '',
            'StudentHeadOfHouse'       => '',
            'StudentFormStaffName'     => '',
            'StudentTutorDescription'  => '',
            'TeacherFormID'            => '',
            'StudentForm'              => '',
            'TeacherTutorID'           => '',
            'StudentHouseDescription'  => '',
            'StudentHouse'             => '',
            'StudentYearLevel'         => '',
            'StudentEmergencyName'     => '',
            'StudentEmergencyPhone'    => '',
            'StudentEmergencyPhoneAlt' => '',
            'StudentMedicalAlert'      => '',
            'StudentMobilePhone'       => '',
            'EnglishSecondFlag'        => '',
            'StudentSpecialNeedsFlag'  => 0,
            'AlertMessage'             => '',
            'StudentBoarder'           => '',
            'Comment'                  => '',
            'StudentIBFlag'            => '',
            'StudentStudiesCode'       => '',
        ];

        if (!empty($student)) {
            $adaption['StudentID']                = $student['denbighID'];
            $adaption['StudentGiven1']            = $student['given_names'];
            $adaption['StudentSurname']           = $student['surname'];
            $adaption['StudentGender']            = $student['gender'];
            $adaption['StudentBirthDate']         = $student['dateOfBirth'];
            $adaption['TeacherTutorID']           = $student['tutor_denbighID'];
            $adaption['StudentTutorDescription']  = $student['tutorGroup'];
            $adaption['StudentHouseDescription']  = $student['house'];
            $adaption['StudentYearLevel']         = $student['academicYear'];
            $adaption['StudentEmergencyPhone']    = $student['EmergencyPhone1'];
            $adaption['StudentEmergencyPhoneAlt'] = $student['EmergencyEmail1'];
            $adaption['StudentBoarder']           = $student['boardingStatus'];

            $adaption['StudentTutorName'] =
                "{$student['tutor_title']} " .
                "{$student['tutor_preferred_name']} " .
                "{$student['tutor_surname']}";

            $adaption['StudentEmergencyName'] =
                "{$student['EmergencyContact1']} " .
                "({$student['EmergencyRelationship1']})";
        }

        return $adaption;
    }

    /**
     * {@inheritDoc}
     */
    public function getStaff(string $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        /** @noinspection SqlDialectInspection */
        $staff = $this->externalDatabase->executeQuery(
            "
                SELECT    p.denbighID,
                          p.surname,
                          p.given_names,
                          p.preferred_name,
                          p.title,
                          p.gender,
                          p.dateOfBirth,
                          s.staffCode,
                          '' AS StaffHouseDescription
                FROM      People p
                LEFT JOIN Staff s ON s.peopleDenbighID = p.denbighID
                WHERE     p.denbighID = :externalId
            ",
            ['externalId' => $externalUserId]
        )->fetch();

        $adaption = [
            'SchoolStaffCode'          => '',
            'StaffBuilding'            => '',
            'StaffRoom'                => '',
            'StaffExtension'           => '',
            'StaffMobilePhone'         => '',
            'StaffDefaultMobilePhone'  => '',
            'StaffCampusDescription'   => '',
            'StaffCategoryDescription' => '',
            'StaffDepartment'          => '',
            'StaffHouseDescription'    => '',
        ];

        if (!empty($staff)) {
            $adaption['SchoolStaffCode'] = $staff['staffCode'];
        }

        return $adaption;
    }

    /**
     * {@inheritDoc}
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        $ids = implode(',', $externalUserId);

        /** @noinspection SqlDialectInspection */
        $contactIterator = $this->externalDatabase->getQueryIterator(
            "
                SELECT        pe_p.denbighID,
                              pe_p.surname,
                              pe_p.given_names,
                              pe_p.preferred_name,
                              pe_p.title,
                              pe_p.email,
                              pa_p.familyDenbighID,
                              a.telephoneHome
                FROM          People pe_s
                LEFT JOIN     Students s ON s.peopleDenbighID = pe_s.denbighID
                LEFT JOIN     Parents pa_p ON pa_p.familyDenbighID = s.familyDenbighID AND
                              pa_p.type != 'Non Resident'
                LEFT JOIN     People pe_p ON pe_p.denbighID = pa_p.peopleDenbighID
                LEFT JOIN     Addresses a ON a.familyDenbighID = s.familyDenbighID
                WHERE         pe_s.denbighID IN ($ids) AND
                              pe_p.id IS NOT NULL -- check the row actually exists in `People` table
                GROUP BY      pe_p.denbighID
            "
        );

        $contactAdaptionList = [];

        foreach ($contactIterator as $contact) {
            $contactAdaptionList[] = [
                'StudentContactID'               => $contact['denbighID'],
                'StudentContactTitle'            => $contact['title'],
                'StudentContactPreferred'        => $contact['preferred_name'],
                'StudentContactSurname'          => $contact['surname'],
                'StudentContactDefaultEmail'     => $contact['email'],
                'StudentContactPhoneActual'      => $contact['telephoneHome'],
                'StudentContactSilentPhoneFlag'  => '0',
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

        if ($user->getRole()->isStaff()) {
            return $this->externalDatabase->executeQuery(
                "
                    SELECT      cl.classCode AS code,
                                co.subjectName AS name
                    FROM        People p
                    RIGHT JOIN  Staff s ON s.peopleDenbighID = p.denbighID
                    RIGHT JOIN  Classes cl ON cl.staffCode = s.staffCode
                    RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                    WHERE       p.denbighID = :externalId
                    ORDER BY    cl.classCode ASC,
                                co.subjectName ASC
                ",
                ['externalId' => $externalId]
            )->fetchAll();
        }

        if ($user->getRole()->isStudent()) {
            return $this->externalDatabase->executeQuery(
                "
                        SELECT      cl.classCode AS code,
                                    co.subjectName AS name
                        FROM        People p
                        RIGHT JOIN  Enrolments e ON e.studentID = p.denbighID
                        RIGHT JOIN  Classes cl ON cl.classCode = e.classCode
                        RIGHT JOIN  Courses co ON co.courseCode = cl.courseCode
                        WHERE       p.denbighID = :externalId
                        GROUP BY    code
                        ORDER BY    cl.classCode ASC,
                                    co.subjectName ASC
                    ",
                ['externalId' => $externalId]
            )->fetchAll();
        }

        return [];
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        return $this->externalDatabase->executeQuery(
            "
                SELECT    s.peopleDenbighID AS RelatedID
                FROM      People pe
                JOIN Parents pa ON pa.peopleDenbighID = pe.denbighID
                JOIN Students s ON s.familyDenbighID = pa.familyDenbighID
                WHERE     pe.denbighID = :externalId
            ",
            ['externalId' => $externalUserId]
        )->fetchAll();
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
     * @throws \Alaress\Database\Exception
     */
    public function getStudentUserAccountInfo(): array
    {
        /** @noinspection SqlDialectInspection */
        return $this->externalDatabase->executeQuery(
            "
                SELECT  username,
                        academicYear,
                        firstname,
                        surname,
                        denbighID,
                        email,
                        house,
                        cateringHouse,
                        boardingStatus
                FROM    Schoolbox_Students
            "
        )->fetchAll();
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getParentUserAccountInfo(): array
    {
        return $this->externalDatabase->executeQuery(
            "
                SELECT    peo.title,
                          preferred_name,
                          peo.surname,
                          adr.telephoneHome,
                          peo.denbighID,
                          par.familyDenbighID,
                          peo.email,
                          par.loginID
                FROM      Addresses adr
                LEFT JOIN Parents par ON par.familyDenbighID = adr.familyDenbighID
                LEFT JOIN People peo ON peo.denbighID = par.peopleDenbighID
                WHERE     peo.id IS NOT NULL
                GROUP BY  peo.denbighID
                ORDER BY  familyDenbighID ASC,
                          denbighID ASC
            "
        )->fetchAll();
    }


    /**
     * {@inheritDoc}
     * @throws \Alaress\Database\Exception
     */
    public function getStaffUserAccountInfo(): array
    {
        return $this->externalDatabase->executeQuery(
            "
                SELECT    People.denbighID,
                          surname,
                          preferred_name,
                          title,
                          staffCode,
                          email
                FROM      Staff
                LEFT JOIN People ON Staff.peopleDenbighID = People.denbighID
            "
        )->fetchAll();
    }

    /**
     * {@inheritDoc}
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        if ($boarderValue === null) {
            return '';
        }
        $boarders = [
            'Boarder'        => 'Boarder',
            'Weekly Boarder' => 'Weekly Boarder',
            'W'              => 'Weekly Boarder',
            'D'              => 'Day Boarder',
            'B'              => 'Full Boarder',
        ];

        return $boarders[$boarderValue] ?? '';
    }

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
    public function getHousemasterEmails(array $externalUserIds): array
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
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        return [];
    }
}
