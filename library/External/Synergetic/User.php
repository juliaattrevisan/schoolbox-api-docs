<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Predicate\Builder;
use Schoolbox\Resource\Storage;
use Schoolbox\User;

class External_Synergetic_User implements External_UserInterface
{
    use External_MssqlConnector;
    use External_Synergetic_ClearInvalidValuesTrait;
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    protected const FORMAT_ISO8601 = 126;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();
    }


    /**
     * @inheritdoc
     * @param User $user
     * @return Storage\Model|null
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Schoolbox\Mapper\Exception
     */
    public function getPortrait(User $user): ?Storage\Model
    {
        // Make sure a valid external ID was passed

        if (!$this->isExternalIdValid($user->getExternalId())) {
            return null;
        }

        // Do a query to get the photos modification date in the SIS. We don't pull the actual photo at this time

        $queryPhoto = $this->getConnector()->executeQuery(
            'SELECT TOP 1 vPhotos.ModifiedDate
            FROM dbo.vPhotos vPhotos 
            WHERE vPhotos.ID = :externalId
            ORDER BY ModifiedDate DESC',
            ['externalId' => $user->getExternalId()]
        );

        // Check if we found a photo in the SIS
        if ($photo = $queryPhoto->fetch()) {

            /* If this $user doesn't have an image, or if the SIS picture is more recent than the one we
             * currently have, shank it down and save it into brownhole
             *
             * (the latter occurs when the sis gets updated with a new picture and we need to update our storage)
             */
            if (
                null === $user->getPortrait() ||
                (
                    // This field is nullable in the database. Only replace an existing SB photo if date is set, and is later that the SB version
                    isset($photo['ModifiedDate']) &&
                    $user->getPortrait()->getCreatedAt()->lt(\Alaress_Date_Format::newFromTimestamp(strtotime($photo['ModifiedDate'])))
                )
            ) {

                // Do a query to retrieve the actual photo data from the database
                unset($queryPhoto);

                $queryPhoto = $this->getConnector()->executeQuery(
                    'SELECT TOP 1 vPhotos.Photo
                    FROM dbo.vPhotos vPhotos
                    WHERE vPhotos.ID = :externalId
                    ORDER BY ModifiedDate DESC',
                    ['externalId' => $user->getExternalId()]
                );

                $photo = $queryPhoto->fetch();

                /* Save it into brownhole
                 */
                $portrait = Storage\Mapper::getInstance()->acceptStream($user->getExternalId() . '.jpg', $photo['Photo']);

                /* Update user with latest version
                 */
                $user->setPortrait($portrait);

                \Schoolbox\UserMapper::getInstance()->save($user);
            }

            /* Then return the latest Storage\Model instance
             */
            return $user->getPortrait();
        }

        // There was no photo in the SIS, so we don't want to display the cached image either- delete it from
        // disk so it doesn't magically appear later after they'd specifically deleted it

        /* Unlink from user
         */
        $user->setPortrait(null);

        \Schoolbox\UserMapper::getInstance()->save($user);

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getParent(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $query =
        "
            SELECT      Contact.Title,
                        Contact.Preferred,
                        Contact.Surname,
                        CASE 
                          WHEN Contact.DefaultEmailCode = 'O' THEN Contact.OccupEmail
                          ELSE Contact.Email
                        END AS Email,
                        Contact.OccupEmail,
                        Contact.DefaultEmailCode,
                        Addresses.Phone AS HomePhone,
                        Addresses.SilentPhoneFlag,
                        Contact.SilentMobilePhoneFlag,
                        Contact.MobilePhone,
                        Contact.DirectoryIncludeFlag,
                        Contact.SpouseID,
                        Spouse.Title SpouseTitle,
                        Spouse.Preferred SpousePreferred,
                        Spouse.Surname SpouseSurname,
                        CASE 
                          WHEN Spouse.DefaultEmailCode = 'O' THEN Spouse.OccupEmail
                          ELSE Spouse.Email
                        END AS SpouseEmail,                        
                        Spouse.OccupEmail SpouseOccupEmail,
                        Spouse.DefaultEmailCode SpouseDefaultEmailCode,
                        Spouse.MobilePhone SpouseMobilePhone,
                        Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag
            FROM        dbo.Community Contact
            LEFT JOIN   dbo.Addresses Addresses ON Contact.AddressID = Addresses.AddressID
            LEFT JOIN   dbo.Community Spouse ON Contact.SpouseID = Spouse.ID
            WHERE       Contact.ID = :externalId
        ";

        $parentQuery = $this->getConnector()->executeQuery($query, ['externalId' => $externalUserId]);

        // Try and fetch the requested parent

        if ($parent = $parentQuery->fetch()) {
            return $this->clearInvalidValues($parent);
        }

        // Couldn't find the parent

        return [];
    }

    /**
     * @inheritdoc
     */
    public function getParentSync(array $externalUserIds): array
    {
        // This hasn't been tested in an actual sync script. But running in isolation it returns this following results.
        //
        //    array (size=1)
        //      0 =>
        //          array (size=8)
        //              'ID' => string '224' (length=3)
        //              'Title' => string 'Mr' (length=2)
        //              'Preferred' => string 'Tim' (length=3)
        //              'Surname' => string 'Thomas' (length=6)
        //              'Email' => string '' (length=0)
        //              'OccupEmail' => string '' (length=0)
        //              'DefaultEmailCode' => string '' (length=0)
        //              'DirectoryIncludeFlag' => string '0' (length=1)
        foreach ($externalUserIds as $idx => $externalUserId) {
            if (!$this->isExternalIdValid($externalUserId)) {
                unset($externalUserIds[$idx]);
            }
        }

        if (!count($externalUserIds)) {
            return [];
        }

        $externalIdBindings = Builder::getArrayBindings($externalUserIds, 'externalId');

        $query =
        "
            SELECT        ID, 
                          Title,
                          Preferred, 
                          Surname,
                          Email, 
                          OccupEmail, 
                          DefaultEmailCode,
                          DirectoryIncludeFlag
            FROM          dbo.Community
            WHERE         ID IN ({$externalIdBindings['placeholders']})
        ";

        $queryParents = $this->getConnector()->executeQuery($query, $externalIdBindings['bindings']);

        // If multiple parents were requested return the results in an array. This should be replaces with an
        // iterator, but that would require refactoring the old sync scripts which is a glorious waste of energy

        if (count($externalUserIds) > 1) {
            return $this->clearInvalidValues(
                $queryParents->fetchAll()
            );
        }

        // If there are multiple parents requested return the results in an array

        return  $this->clearInvalidValues(
            $queryParents->fetch()
        );
    }

    /**
     * @inheritdoc
     */
    public function getParentEmails(array $externalUserIds): array
    {
        // Coalesce non array inputs into an array just to make this easier

        if (!is_array($externalUserIds)) {
            $externalUserIds = [$externalUserIds];
        }

        // Make sure all of the ID's supplied were valid

        foreach ($externalUserIds as &$id) {
            if (!$this->isExternalIdValid($id)) {

                // This was not a valid ID, remove it from the array

                unset($id);
            }
        }

        // Check if we've got any valid IDs left

        if (count($externalUserIds) == 0) {
            return [];
        }

        $externalIdBindings = Builder::getArrayBindings($externalUserIds, 'externalId');

        $query =
        "
            SELECT DISTINCT RelatedID,
                        CASE 
                            WHEN UseEmail = 'O' THEN OccupEmail
                            WHEN UseEmail = 'P' THEN Email
                            WHEN UseEmail = 'D' THEN DefaultEmail
                            ELSE ''
                        END AS Email
            FROM        dbo.RelationShips r
            JOIN        dbo.vCommunityAddresses ON RelatedID = vCommunityAddresses.ID
            WHERE		CASE 
                            WHEN UseEmail = 'O' THEN OccupEmail
                            WHEN UseEmail = 'P' THEN Email
                            WHEN UseEmail = 'D' THEN DefaultEmail
                            ELSE ''
                        END <> '' AND
                        r.ID IN ({$externalIdBindings['placeholders']})
        ";

        $iteratorParents = $this->getConnector()->getQueryIterator($query, $externalIdBindings['bindings']);

        $emailList = [];

        foreach ($iteratorParents as $parent) {

            $emailArray = explode(';', $parent['Email']);

            if (count($emailArray) > 0) {
                foreach ($emailArray as $key => $email) {
                    $emailList["{$parent['RelatedID']}-{$key}"] = $email;
                }
            } else {
                $emailList[$parent['RelatedID']] = $parent['Email'];
            }
        }

        return $emailList;
    }

    /**
     * @inheritdoc
     */
    public function getStudent(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        if($this->config->get('external_learning_plans')) {
            $learningPlanQuery = "(
                            SELECT    COUNT(*) 
                            FROM      dbo.IndividualPlans IEP
                            WHERE     IEP.ID = vStudentsAll.ID AND
                            StartDate <= CURRENT_TIMESTAMP AND
                            (EndDate >= CURRENT_TIMESTAMP OR EndDate IS NULL)
                        ) AS countIndividualEducationPlans";
        } else {
            $learningPlanQuery = "0 AS countIndividualEducationPlans";
        }

        $query =
        "
            SELECT      vStudentsAll.StudentID,
                        vStudentsAll.StudentGiven1,
                        vStudentsAll.StudentSurname,
                        vStudentsAll.StudentGender,
                        CONVERT(nvarchar(30), vStudentsAll.StudentBirthDate, ".self::FORMAT_ISO8601." ) as StudentBirthDate,
                        vStudentsAll.StudentTutorName,
                        vStudentsAll.StudentHeadOfHouse,
                        House.HeadOfHouseID,
                        vStudentsAll.StudentFormStaffName,
                        vStudentsAll.StudentTutorDescription,
                        luForm.ID AS TeacherFormID,
                        vStudentsAll.StudentForm,
                        luTutor.ID AS TeacherTutorID,
                        vStudentsAll.StudentHouseDescription,
                        vStudentsAll.StudentHouse,
                        vStudentsAll.StudentYearLevel,
                        vStudentsAll.StudentYearLevelCoordinator,
                        luYearLevel.YearLevelCoordID,
                        vStudentsAll.StudentEmergencyName,
                        vStudentsAll.StudentEmergencyPhone,
                        vStudentsAll.StudentEmergencyPhoneAlt,
                        vStudentsAll.StudentEmergency2Name,
                        vStudentsAll.StudentEmergency2Phone,
                        vStudentsAll.StudentEmergency2PhoneAlt,
                        vStudentsAll.StudentEmergency3Name,
                        vStudentsAll.StudentEmergency3Phone,
                        vStudentsAll.StudentEmergency3PhoneAlt,
                        CASE WHEN vStudentsAll.StudentMedicalAlertFlag= 1 THEN vStudentsAll.StudentMedicalAlert ELSE '' END as StudentMedicalAlert,  
                        vStudentsAll.StudentMobilePhone,
                        vStudentsAll.StudentSpecialNeedsFlag,
                        vStudentsAll.EnglishSecondFlag,
                        vStudentsAll.StudentStudiesCode,
                        AlertMessage,
                        vStudentsAll.LegalCourtOrderType AS CourtOrderType,
                        vStudentsAll.StudentBoarder,
                        vStudentsAll.StudentsComment as Comment,   
                        '' AS StudentEnrolmentDate,
                        '' AS StudentLeavingDate,
                        vStudentsAll.StudentIBFlag,
                        $learningPlanQuery
            FROM        dbo.vStudentsAll vStudentsAll
            LEFT JOIN   CommunityAlerts ON 
                            CommunityAlerts.ID = StudentID AND 
                            (
                                CommunityAlerts.AlertDateFrom IS NULL OR 
                                CommunityAlerts.AlertDateFrom <= CAST(GETDATE() AS DATE)
                            ) AND 
                            (
                                CommunityAlerts.AlertDateTo IS NULL OR 
                                CommunityAlerts.AlertDateTo >= CAST(GETDATE() AS DATE)
                            )
            LEFT JOIN   luForm ON vStudentsAll.StudentForm = luForm.Code
            LEFT JOIN   luTutor ON vStudentsAll.StudentTutor = luTutor.Code
            LEFT JOIN   luYearLevel ON 
                            vStudentsAll.StudentCampus = luYearLevel.Campus AND 
                            vStudentsAll.StudentYearLevel = luYearLevel.Code
            LEFT JOIN   luHouse House ON 
                            StudentCampus = House.Campus AND 
                            StudentHouse = House.Code
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudentsAll.FileSemester AND
                            Semester.FileYear = vStudentsAll.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       vStudentsAll.StudentID = :externalId
        ";

        $student = [];

        $iteratorStudents = $this->getConnector()->getQueryIterator($query, ['externalId' => $externalUserId]);

        foreach ($iteratorStudents as $row) {

            // There might be multiple records, if this is the first one populate the bulk of the details

            if (empty($student)) {

                $student = $row;

                if ($student['StudentBirthDate'] && $birthDate = strtotime($student['StudentBirthDate'])) {
                    $student['StudentBirthDate'] = $birthDate;
                } else {
                    $student['StudentBirthDate'] = false;
                }

                $student['AlertMessage'] = [
                    $student['AlertMessage']
                ];

            } else {

                // Additional records will only different by their alert message, we just append these to the
                // existing student record

                $student['AlertMessage'][] = $row['AlertMessage'];
            }
        }

        // Implode all of the alert messages down to a single message seperate by line breaks

        if (!empty($student) && is_array($student['AlertMessage'])) {
            $student['AlertMessage'] = implode("\n", array_unique(array_filter($student['AlertMessage'])));
        }

        // Remove Synergetic's annoying 'Not Selected' default value for lookups

        return $this->clearInvalidValues($student);
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getStaff(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $query =
        "
            SELECT      SchoolStaffCode,
                        luBuilding.Description AS StaffBuilding,
                        StaffRoom,
                        luHouse.Description AS StaffHouseDescription,
                        StaffExtension,
                        StaffMobilePhone,
                        StaffDefaultMobilePhone,
                        StaffCampusDescription,
                        StaffCategoryDescription,
                        luDepartment.Description AS StaffDepartment,
                        StaffVoiceMail
             FROM       dbo.vStaff vStaff
             LEFT JOIN  dbo.luHouse ON luHouse.Code = vStaff.StaffHouse AND luHouse.Campus = vStaff.StaffCampus
             LEFT JOIN  dbo.luBuilding ON StaffBuilding = luBuilding.Code
             LEFT JOIN  dbo.luDepartment ON StaffDepartment = luDepartment.Code
             WHERE      vStaff.StaffID = :externalId
        ";

        return $this->getConnector()->executeQuery($query, ['externalId' => $externalUserId])->fetch() ?: [];
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended(string $yearLevel, string $name, string $boardingStatus, string $form): array
    {
        // We have to dynamically bind filter parameters here based on the users input

        $filters = '';
        $bindings = [];

        // Search for a particular year level

        if (strlen($yearLevel) > 0) {

            $bindings['yearDescription'] = $yearLevel;

            if (is_numeric($yearLevel)) {

                $bindings['yearLevel'] = $yearLevel;

                $filters .= " AND (
                    Year.Description = :yearDescription OR
                    StudentYearLevel = :yearLevel
                    )
                ";

            } else {

                // If the year is not numeric, don't attempt to compare to StudentYearLevel
                // or the query will bug out

                $filters .= " AND Year.Description = :yearDescription";
            }
        }

        if (strlen($name) > 0 ) {

            $splitNames = explode(' ', $name);

            $count = 1;

            foreach ($splitNames as $splitName) {

                $keyGiven = "nameGiven{$count}";
                $bindings[$keyGiven] = "%{$splitName}%";

                $keySurname = "nameSurname{$count}";
                $bindings[$keySurname] = "%{$splitName}%";

                $filters .= " AND (StudentSurname LIKE :{$keySurname} OR StudentPreferred LIKE :{$keyGiven})";

                $count++;
            }
        }

        // Boarder search ('B' = Boarder, 'D' = Day Student, 'HS' = Home Stay)

        if ($boardingStatus == 'yes') {
            $filters .= " AND StudentBoarder = 'B'";
        } elseif ($boardingStatus == 'no') {
            $filters .= " AND StudentBoarder <> 'B'";
        }

        // Search for a particular form

        if (strlen($form) > 0) {
            $bindings['form'] = $form;
            $filters .= " AND StudentForm = :form";
        }

        $contactTypes = $this->getStudentContactTypeBindings();

        // Now can build the query string

        $query = "
            SELECT DISTINCT vStudentContactAllAddress.StudentID,
                        StudentPreferred,
                        StudentSurname,
                        StudentYearLevel,
                        House.Description AS StudentHouse,
                        StudentBoarder,
                        StudentForm,
                        StudentContactID,
                        StudentContactTitle,
                        StudentContactPreferred,
                        StudentContactLegalFullName,
                        StudentContactSurname,
                        CASE
                            WHEN scp.ShareEmailFlag = 1 THEN StudentContactDefaultEmail
                            ELSE ''
                        END AS StudentContactDefaultEmail,
                        vStudentContactAllAddress.ValidAddressFlag,
                        StudentContactAddress1,
                        StudentContactAddress2,
                        StudentContactAddress3,
                        StudentContactSuburb,
                        StudentContactPostCode,
                        StudentContactState,
                        StudentCountry.Description AS StudentContactCountryCode,
                        StudentContactHomeAddress1,
                        StudentContactHomeAddress2,
                        StudentContactHomeAddress3,
                        StudentContactHomeSuburb,
                        StudentContactHomePostCode,
                        StudentContactHomeState,
                        StudentCountry.Description AS StudentContactHomeCountryCode,
                        CASE
                            WHEN scp.ShareHomePhoneFlag = 1 THEN StudentContactPhoneActual
                            ELSE ''
                        END AS StudentContactPhoneActual,
                        CASE
                            WHEN scp.ShareMobileFlag = 1 THEN StudentContactMobilePhone
                            ELSE ''
                        END AS StudentContactMobilePhone,
                        StudentContactOccupPhone,
                        StudentContactSpouseID,
                        StudentContactSpouseTitle,
                        StudentContactSpousePreferred,
                        StudentContactSpouseLegalFullName,
                        StudentContactSpouseSurname,
                        CASE
                            WHEN scpSpouse.ShareMobileFlag = 1 THEN StudentContactSpouseMobilePhone
                            ELSE ''
                        END AS StudentContactSpouseMobilePhone,
                        StudentContactSpouseOccupPhone,
                        CASE
                            WHEN scpSpouse.ShareEmailFlag = 1 THEN StudentContactSpouseDefaultEmail
                            ELSE ''
                        END AS StudentContactSpouseDefaultEmail,
                        StudentContactType,
                        StudentContactSilentPhoneFlag,
                        Contact.SilentMobilePhoneFlag AS StudentContactSilentMobilePhoneFlag,
                        Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
                        Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                        ContactAddress.SilentHomeAddressFlag SilentHomeAddressFlag,
                        scp.ShareHomeAddressFlag,
                        StudentParentsSeparatedFlag
            FROM        dbo.vStudentContactAllAddress
            LEFT JOIN   dbo.Community Contact ON StudentContactID = Contact.ID
            LEFT JOIN   dbo.Addresses ContactAddress ON Contact.AddressID = ContactAddress.AddressID
            LEFT JOIN   dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
            LEFT JOIN   dbo.luHouse House ON StudentCampus = House.Campus AND
                        StudentHouse = House.Code
            LEFT JOIN   dbo.luYearLevel Year ON StudentYearLevel = Year.Code AND
                        StudentCampus = Year.Campus
            LEFT JOIN   dbo.luCountry StudentCountry ON StudentContactCountryCode = StudentCountry.Code
            LEFT JOIN   dbo.luCountry StudentHomeCountry ON StudentContactHomeCountryCode = StudentHomeCountry.Code
            LEFT JOIN   dbo.StudentContactsPortal scp ON vStudentContactAllAddress.StudentID = scp.StudentID AND StudentContactID = scp.CommunityID
            LEFT JOIN   dbo.StudentContactsPortal scpSpouse ON vStudentContactAllAddress.StudentID = scpSpouse.StudentID AND StudentContactSpouseID = scpSpouse.CommunityID
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudentContactAllAddress.FileSemester AND
                            Semester.FileYear = vStudentContactAllAddress.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       StudentContactType IN ({$contactTypes['placeholders']})
                        {$filters}
            ORDER BY    StudentSurname, 
                        StudentID, 
                        StudentContactType
        ";

        // Now that we have created the query, time to shove it across to the database with
        // the appropriate parameters

        $iteratorContacts = $this->getConnector()->getQueryIterator($query, array_merge($bindings, $contactTypes['bindings']));

        $contactList = [];

        foreach ($iteratorContacts as $contact) {

            // Remove invalid and silent contact details

            if ($contact['ValidAddressFlag'] == 0 || $contact['SilentHomeAddressFlag'] == 1 || $contact['ShareHomeAddressFlag'] != 1) {
                $contact['StudentContactAddress1'] = '';
                $contact['StudentContactAddress2'] = '';
                $contact['StudentContactAddress3'] = '';
                $contact['StudentContactSuburb'] = '';
                $contact['StudentContactPostCode'] = '';
                $contact['StudentContactState'] = '';
                $contact['StudentContactCountryCode'] = '';
                $contact['StudentContactHomeAddress1'] = '';
                $contact['StudentContactHomeAddress2'] = '';
                $contact['StudentContactHomeAddress3'] = '';
                $contact['StudentContactHomeSuburb'] = '';
                $contact['StudentContactHomePostCode'] = '';
                $contact['StudentContactHomeState'] = '';
                $contact['StudentContactHomeCountryCode'] = '';
            }

            $contactList[] = $this->clearInvalidValues($contact);
        }

        // Return the cleaned up list of contacts

        return $this->clearInvalidValues($contactList);
    }

    /**
     * @inheritdoc
     */
    public function getStudentBoarderContactListExtended()
    {
        $contactTypes = $this->getStudentContactTypeBindings();

        $query =
        "
            SELECT      StudentID,
                        StudentGiven1,
                        StudentSurname,
                        StudentYearLevel,
                        House.Description AS StudentHouse,
                        StudentContactID,
                        StudentContactTitle,
                        StudentContactPreferred,
                        StudentContactSurname,
                        StudentContactDefaultEmail,
                        StudentContactAddress1,
                        StudentContactAddress2,
                        StudentContactAddress3,
                        StudentContactSuburb,
                        StudentContactPostCode,
                        StudentContactState,
                        StudentContactCountryDesc,
                        StudentContactPhoneActual,
                        StudentContactMobilePhone,
                        StudentContactOccupPhone,
                        StudentContactSpouseID,
                        StudentContactSpouseTitle,
                        StudentContactSpousePreferred,
                        StudentContactSpouseSurname,
                        StudentContactSpouseMobilePhone,
                        StudentContactSpouseOccupPhone,
                        StudentContactSpouseDefaultEmail,
                        StudentContactType,
                        StudentContactSilentPhoneFlag,
                        Contact.SilentMobilePhoneFlag AS StudentContactSilentMobilePhoneFlag,
                        Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
                        Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                        StudentParentsSeparatedFlag
            FROM        dbo.vStudentContactAllAddress
            LEFT JOIN   dbo.Community Contact ON StudentContactID = Contact.ID
            LEFT JOIN   dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
            LEFT JOIN   dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudentContactAllAddress.FileSemester AND
                            Semester.FileYear = vStudentContactAllAddress.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       StudentContactType IN ({$contactTypes['placeholders']}) AND 
                        StudentBoarder = 'B'
            ORDER BY    StudentSurname, 
                        StudentID, 
                        StudentContactType
        ";

        $this->getConnector()->getQueryIterator($query, $contactTypes['bindings']);

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList(array $externalUserId): array
    {
        if (empty($externalUserId)) {
            return [];
        }

        /**
         * Return valid external ID's only and convert it into a string delimited by comma (,) to be used in the
         * query below using an IN statement.
         */
        $validExternalIds = array_filter($externalUserId, function ($externalUserId) {
            return $this->isExternalIdValid($externalUserId);
        });

        $predicate = Builder::getArrayBindings($validExternalIds, 'studentIds');

        if (!$validExternalIds) {
            return [];
        }

        $contactTypes = $this->getStudentContactTypeBindings();

        $query =
        "
            SELECT          a.StudentContactID,
                            a.StudentContactTitle,
                            a.StudentContactPreferred,
                            a.StudentContactSurname,
                            a.StudentContactDefaultEmail,
                            a.StudentContactPhoneActual,
                            a.StudentContactSilentPhoneFlag,
                            a.StudentContactMobilePhone,
                            a.StudentContactOccupPhone,
                            a.StudentContactRelation,
                            a.StudentContactPrimaryOnlyFlag,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseID END AS StudentContactSpouseID,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseTitle END AS StudentContactSpouseTitle,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpousePreferred END AS StudentContactSpousePreferred,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseSurname END AS StudentContactSpouseSurname,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseMobilePhone END AS StudentContactSpouseMobilePhone,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseOccupPhone END AS StudentContactSpouseOccupPhone,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseDefaultEmail END AS StudentContactSpouseDefaultEmail,
                            a.StudentContactType,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE a.StudentContactSpouseRelation END AS StudentContactSpouseRelation,
                            r.UseEmail AS StudentContactUseEmail,
                            CASE WHEN a.StudentContactPrimaryOnlyFlag = 1 THEN '' ELSE rs.UseEmail END AS StudentContactSpouseUseEmail
            FROM            dbo.vStudentContactAllAddress a
            LEFT JOIN       Relationships r ON r.id = a.StudentID AND r.RelatedID = a.StudentContactID
            LEFT JOIN       Relationships rs ON rs.id = a.StudentID AND rs.RelatedID = a.StudentContactSpouseID            
            JOIN            FileSemesters Semester ON
                                Semester.FileSemester = a.FileSemester AND
                                Semester.FileYear = a.FileYear AND
                                Semester.SystemCurrentFlag = 1
            WHERE           StudentID IN ({$predicate['placeholders']}) AND
                            StudentContactType IN ({$contactTypes['placeholders']})
        ";

        $statement = $this->getConnector()->executeQuery(
            $query,
            array_merge(
                $contactTypes['bindings'],
                $predicate['bindings']
            )
        );

        $contacts = $this->clearInvalidValues($statement->fetchAll());
        $contactIdList = [];
        $contactReturn = [];
        //Dedupe contacts when mother and father are both contacts
        foreach ($contacts as $contact) {
            if (isset($contactIdList[$contact['StudentContactID']]) || isset($contactIdList[$contact['StudentContactSpouseID']])) {
                continue;
            }
            $contactReturn[] = $contact;
            if (!empty($contact['StudentContactID'])) {
                $contactIdList[$contact['StudentContactID']] = true;
            }
            if (!empty($contact['StudentContactSpouseID'])) {
                $contactIdList[$contact['StudentContactSpouseID']] = true;
            }
        }

        return $contactReturn;
    }


    /**
     * If Synergetic Filetypes were provided, filter based on the value given
     *
     * @return string|null
     */
    private function getAllowedFileTypes(): ?string
    {
        $allowedFileTypes = trim($this->config->get('external_synergetic_allowed_file_types') ?? '');

        if (!$allowedFileTypes) {
            return null;
        }

        /* Check that everything is in the correct format, removing empty values
         */
        $values = explode(',', $allowedFileTypes);
        $values = array_map('trim', $values);
        $values = array_filter($values);
        $values = "'" . implode("','", $values) . "'";

        return $values;
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getSubjectList(User $user): array
    {
        $externalId = $user->getExternalId();

        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }

        /* Filter by filetype
         */
        $allowedFileTypesSql = '';
        $allowedFileTypes = $this->getAllowedFileTypes();

        if ($user->getRole()->isStudent()) {
            if (null !== $allowedFileTypes) {
                $allowedFileTypesSql = " AND luFileType.Code IN ({$allowedFileTypes})";
            }

            // Retrieve student subjects
            $sql = "
                SELECT            StudentClasses.ClassCode as code,
                                  SubjectClasses.ClassCampus as campusCode,
                                  SubjectClasses.Description as name,
                                  luFileType.Description as classType
                FROM              StudentClasses
                INNER JOIN        SubjectClasses ON
                                  StudentClasses.FileType = SubjectClasses.FileType AND
                                  StudentClasses.FileYear = SubjectClasses.FileYear AND
                                  StudentClasses.FileSemester = SubjectClasses.FileSemester AND
                                  StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
                                  StudentClasses.ClassCode = SubjectClasses.ClassCode AND
                                  StudentClasses.ID = :externalId
                JOIN              FileSemesters Semester ON
                                      Semester.FileSemester = StudentClasses.FileSemester AND
                                      Semester.FileYear = StudentClasses.FileYear AND
                                      Semester.SystemCurrentFlag = 1
                LEFT JOIN         luFileType ON StudentClasses.FileType = luFileType.Code
                WHERE             StudentClasses.StartDate <= getdate() AND
                                  (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
                                  {$allowedFileTypesSql}
                ORDER BY          SubjectClasses.FileType ASC, 
                                  SubjectClasses.Description ASC
            ";
        } else if ($user->getRole()->isStaff()) {
            if (null !== $allowedFileTypes) {
                $allowedFileTypesSql = "WHERE luFileType.Code IN ({$allowedFileTypes})";
            }

            // Retrieve staff subjects
            $sql = "
                SELECT DISTINCT   (c1.ClassCode) AS code,
                                  c1.ClassCampus as campusCode,
                                  c2.description as name,
                                  luFileType.Description as classType
                FROM              SubjectClassStaff c1
                INNER JOIN        SubjectClasses c2 ON
                                        c1.FileType = c2.FileType AND
                                        c1.FileYear = c2.FileYear AND
                                        c1.FileSemester = c2.FileSemester AND
                                        c1.ClassCampus = c2.ClassCampus AND
                                        c1.ClassCode = c2.ClassCode AND
                                        c1.StaffID = :externalId
                JOIN              FileSemesters Semester ON
                                      Semester.FileSemester = c2.FileSemester AND
                                      Semester.FileYear = c2.FileYear AND
                                      Semester.SystemCurrentFlag = 1
                LEFT JOIN         luFileType ON c2.FileType = luFileType.Code
                {$allowedFileTypesSql}
                ORDER BY          luFileType.Description ASC, 
                                  c2.Description ASC
            ";
        } else {

            // If they're neither staff/student- they have no business being here

            return [];
        }

        $subjectList = $this->getConnector()->executeQuery($sql, ['externalId' => $externalId])->fetchAll();

        if (is_array($subjectList)) {

            // Perform natural sort on subjects so they appear in a more logical order
            // (unfortunately no current way to do this is MSSQL query without writing
            // a custom database function or doing a hacky length query)

            usort($subjectList, function ($a, $b) {
                return strnatcmp($a['name'], $b['name']);
            });

            return $subjectList;
        }

        // No subjects found
        return [];
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getEnrolledStudents(string $externalUserId): array
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }
        $relationships = $this->getRelationshipBindings();
        $contactTypes = $this->getStudentContactTypeBindings();
        $externalIds = Builder::getArrayBindings(array_fill(0, 3, $externalUserId), 'externalId');
        $externalIdPlaceholders = array_keys($externalIds['bindings']);

        $query = "
            SELECT   r.RelatedID
            FROM     Relationships r
            JOIN     vStudentContactAllAddress a ON r.RelatedID = a.StudentID
            JOIN     FileSemesters Semester ON
                     Semester.FileSemester = a.FileSemester AND
                     Semester.FileYear = a.FileYear
            WHERE    r.ID = {$externalIdPlaceholders[0]} AND
                     Semester.SystemCurrentFlag = 1 AND
                     r.Relationship IN ({$relationships['placeholders']}) AND
                     (a.StudentContactID = {$externalIdPlaceholders[1]} OR
                      (a.StudentContactSpouseID = {$externalIdPlaceholders[2]} AND a.StudentContactPrimaryOnlyFlag = 0)) AND
                     a.StudentContactType IN ({$contactTypes['placeholders']})
            ORDER BY a.StudentBirthDate;
        ";

        $students = $this->getConnector()->executeQuery(
            $query,
            array_merge(
                $relationships['bindings'],
                $externalIds['bindings'],
                $contactTypes['bindings']
            )
        )->fetchAll();

        return array_unique($students, SORT_REGULAR);
    }


    /**
     * @inheritdoc
     */
    public function getStudentUserAccountInfo(): array
    {
        $query =
            "
            SELECT      vStudentsAll.StudentID,
                        NetworkLogin,
                        vStudentsAll.StudentTitle,
                        vStudentsAll.StudentPreferred,
                        vStudentsAll.StudentSurname,
                        StudentGender,
                        StudentEmail,
                        StudentOccupEmail,
                        vStudentsAll.StudentYearLevel,
                        vStudentsAll.StudentHouse,
                        vStudentsAll.StudentBoarder,
                        vStudentsAll.StudentBoardingHouse,
                        StudentCampus
            FROM        dbo.vStudentsAll vStudentsAll
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudentsAll.FileSemester AND
                            Semester.FileYear = vStudentsAll.FileYear AND
                            Semester.SystemCurrentFlag = 1
            LEFT JOIN   Community ON Community.ID = StudentID WHERE 
			NOT (vStudentsAll.StudentStatusSynergyMeaning IN ('LEFT', 'LEAVING') 
			AND DATEDIFF(DAY, vStudentsAll.StudentLeavingDate, GETDATE()) > 0)
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getParentUserAccountInfo(): array
    {
        $contactTypes = $this->getStudentContactTypeBindings();

        $query =
        "
            SELECT DISTINCT cp.NetworkLogin AS StudentContactNetworkLogin,
                            s.StudentContactID,
                            s.StudentContactTitle,
                            s.StudentContactPreferred,
                            s.StudentContactSurname,
                            s.StudentContactDefaultEmail,
                            cs.NetworkLogin AS StudentContactSpouseNetworkLogin,
                            s.StudentContactSpouseID,
                            s.StudentContactSpouseTitle,
                            s.StudentContactSpousePreferred,
                            s.StudentContactSpouseSurname,
                            s.StudentContactSpouseDefaultEmail,
                            s.StudentYearLevel,
                            s.StudentCampus,
                            s.StudentGender
            FROM            dbo.vStudentContactAllAddress s
            JOIN            FileSemesters Semester ON
                                Semester.FileSemester = s.FileSemester AND
                                Semester.FileYear = s.FileYear AND
                                Semester.SystemCurrentFlag = 1
            LEFT JOIN       dbo.Community cp ON s.StudentContactID = cp.ID
            LEFT JOIN       dbo.Community cs ON s.StudentContactSpouseID = cs.ID
            WHERE           StudentContactType IN ({$contactTypes['placeholders']})
            ORDER BY        StudentContactID
        ";

        return $this->getConnector()->executeQuery($query, $contactTypes['bindings'])->fetchAll();
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getStaffUserAccountInfo(): array
    {
        $query =
            "
            SELECT      vStaff.StaffID,
                        NetworkLogin,
                        ActiveFlag,
                        vStaff.StaffTitle,
                        vStaff.StaffPreferredName,
                        vStaff.StaffSurname,
                        DefaultEmailCode,
                        OccupEmail,
                        Email,
                        vStaff.StaffCampus,
                        StaffCategoryType,
                        StaffCategory,
                        vStaff.StaffDepartment
            FROM        dbo.vStaff vStaff
            LEFT JOIN   Community ON Community.ID = StaffID
            WHERE       ActiveFlag = 1 AND 
                        vStaff.StaffRegistrationType <> 'STDNT'
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getStaffContactListExtended(): array
    {
        // Restrict staff categories to provided list

        $staffCategory = explode(',', $this->config->get('synergetic_staff_dir_restrict'));

        $filter = '';
        $bindings = [];

        if (is_array($staffCategory) && $staffCategory[0] != '')
        {
            $count = 1;

            $filterPlaceholder = "";

            foreach ($staffCategory as $category) {
                if ($filterPlaceholder != '') {
                    $filterPlaceholder .= ', ';
                }

                $filterPlaceholder .= ":staffCategory{$count}";
                $bindings["staffCategory{$count}"] = $category;

                $count++;
            }

            $filter = " AND StaffCategory NOT IN ($filterPlaceholder)";
        }

        // Check if the custom staff position table exists, and if so use this to override
        // position titles [SB-10813]

        $queryTable = $this->getConnector()->executeQuery(
            "SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'uStaffPosition'"
        );

        if ($queryTable->fetch()) {
            $select = 'uStaffPosition.JobTitle AS JobPositionDescription';
            $join = 'LEFT JOIN dbo.uStaffPosition ON vStaff.StaffID = uStaffPosition.ID';
        } else {
            $select = 'JobPositions.JobPositionDescription';
            $join = '';
        }

        // If the custom table exists, override the 'JobPositionDescription' field

        $query = "

            SELECT      vStaff.StaffID,
                        vStaff.SchoolStaffCode,
                        vStaff.StaffTitle,
                        vStaff.StaffPreferredName,
                        vStaff.StaffSurname,
                        vStaff.StaffOccupPhone,
                        vStaff.StaffOccupEmail,
                        vStaff.StaffExtension,
                        vStaff.StaffCampusDescription,
                        vStaff.StaffCategory,
                        vStaff.StaffCategoryType,
                        vStaff.StaffDefaultMobilePhone,
                        vStaff.StaffVoiceMail,
                        StaffDepartmentDescription as StaffDepartment,
                        JobPositions.StaffJobPositionStartDate,
                        JobPositions.StaffJobPositionEndDate,
                        Community.OccupDesc AS JobPositionDescriptionAlternate,                
                        {$select}
            FROM        dbo.vStaff vStaff
            JOIN        dbo.Community ON Community.ID = vStaff.StaffID
            LEFT JOIN   dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
                        {$join}
            WHERE       vStaff.ActiveFlag = 1 AND
                        StaffCategoryDescription <> 'Staff on leave' AND
                        (JobPositions.StaffJobPositionStartDate < GETDATE() OR JobPositions.StaffJobPositionStartDate IS NULL) AND
                        (JobPositions.StaffJobPositionEndDate > GETDATE() OR JobPositions.StaffJobPositionEndDate IS NULL)
                        {$filter}
            ORDER BY    vStaff.StaffSurname
        ";

        return $this->getConnector()->executeQuery($query, $bindings)->fetchAll();
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getCampusFromTimetable(string $staffID): array
    {
        // Was unable to hit this in testing, but running in isolation returned
        //
        //    /vagrant/library/External/Synergetic/User.php:440:
        //    array (size=1)
        //      0 =>
        //          array (size=1)
        //              'campus' => string 'S' (length=1)

        if (!$this->isExternalIdValid($staffID)) {
            return [];
        }

        return $this->getConnector()->executeQuery(
            "
                SELECT DISTINCT     ClassCampus AS campus 
                FROM                timetable 
                WHERE               StaffID = :id AND 
                                    FileYear = :fileYear AND
                                    ClassCampus <> ''
            ",
            [
                'id' => $staffID,
                'fileYear' => date('Y')
            ]
        )->fetchAll();
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function determineBoarderType(?string $boarderValue): string
    {
        if (empty($boarderValue)) {
            return '';
        }

        return $this->getConnector()->executeQuery(
            "SELECT Description FROM luBoarder WHERE Code = :code",
            ['code' => $boarderValue]
        )->fetchColumn() ?: '';
    }


    /**
     * @inheritdoc
     */
    public function getHousemasterByHouseId(string $houseID): ?string
    {
        return null;
    }


    /**
     * @inheritdoc
     * @throws \Alaress\Database\Exception
     */
    public function getHousemasterEmails(array $externalUserIds): array
    {
        // Make sure an array of valid external ID's was passed

        $ids = array_filter($externalUserIds, function ($externalUserId) {
            return self::isExternalIdValid($externalUserId);
        });

        if (count($ids) == 0) {
            return [];
        }

        $fileYearSemester = $this->findFileYearAndSemester();

        $predicateBuilder = $this->getConnector()
            ->buildPredicateBuilder('StudentYears')
            ->equals('StudentYears.FileYear', $fileYearSemester['year'])
            ->in('StudentYears.ID', $ids);

        $where = implode(' AND ', $predicateBuilder->getWhere());

        $sql = "
            SELECT DISTINCT OccupEmail FROM Community 
            JOIN luHouse ON HeadOfHouseID = Community.ID
            JOIN StudentYears ON StudentYears.House = luHouse.Code
            WHERE {$where}
        ";

        $bindings = $predicateBuilder->getParams();

        return $this->getConnector()
            ->executeQuery($sql, $bindings)
            ->fetchColumnArray('OccupEmail');
    }

    /**
     * @inheritdoc
     */
    public function getStudentForms(): array
    {
        $iteratorForms = $this->getConnector()->getQueryIterator(
            "SELECT Code, Description FROM dbo.luForm WHERE ActiveFlag = 1"
        );

        $forms = [];

        foreach ($iteratorForms as $form) {
            $forms[] = $this->clearInvalidValues($form);
        }

        return $forms;
    }

    // Everything below here is FileYear/FileSemester retrieval shenanigans, this should all disappear and be replaced by proper SQL joins

    // TODO: These calls don't seem to be getting cached according to the debug console, so caching them here.

    /** @var array $fileYearSemesterUser Cache of users file year/semester */
    private static $fileYearSemesterUser = [];

    /** @var array $fileYearSemesterDefault Cache of system file year/semester */
    private static $fileYearSemesterDefault;

    // TODO: These functions seem to be used prolifically to fill in SQL clauses- in these cases should be using appropriate SQL joins.

    /**
     * Return the FileYear and FileSemester for a user, if given; otherwise, the global value.
     *
     * @param string|null $externalUserId
     * @return array [ year, semester ]
     */
    protected function findFileYearAndSemester($externalUserId = null)
    {
        // If we are supplied a valid user ID use their current file year/semester values

        if ($externalUserId != null) {

            if (isset(self::$fileYearSemesterUser[$externalUserId])) {
                return self::$fileYearSemesterUser[$externalUserId];
            }

            if ($this->isExternalIdValid($externalUserId)) {
                $statement = $this->getConnector()->executeStoredProcedure(
                    'spsConfigUserDefaultYearSemester',
                    ['ID' => $externalUserId]
                );

                if ($semester = $statement->fetch()) {
                    self::$fileYearSemesterUser[$externalUserId] = [
                        'year' => $semester['UserFileYear'],
                        'semester' => $semester['UserFileSemester']
                    ];

                    return self::$fileYearSemesterUser[$externalUserId];
                }

                $statement->close();
            }
        }

        // Otherwise, return the current system default values

        if (!isset(self::$fileYearSemesterDefault)) {

            $querySemester = $this->getConnector()->executeQuery(
                "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1"
            );

            if ($semester = $querySemester->fetch()) {
                self::$fileYearSemesterDefault = [
                    'year' => $semester['FileYear'],
                    'semester' => $semester['FileSemester'],
                ];

                // Since we are not fully iterating we need to close the result set
                $querySemester->close();

            } else {

                // Since we are not fully iterating we need to close the result set
                $querySemester->close();

                // If everything falls in a heap- return a blank array

                return [];
            }
        }

        return self::$fileYearSemesterDefault;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentSemester(string $externalUserId = null): ?string
    {
        $fileSemester = $this->findFileYearAndSemester($externalUserId)['semester'] ?? null;

        return $fileSemester === null ? null : (string)$fileSemester;
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentYear(string $externalUserId = null): ?string
    {
        $fileYear = $this->findFileYearAndSemester($externalUserId)['year'] ?? null;

        return $fileYear === null ? null : (string)$fileYear;
    }

    /**
     * {@inheritdoc}
     *
     * @return array
     */
    public function getAllYearLevels(): array
    {
        $query = "SELECT DISTINCT Code, Description FROM  dbo.luYearLevel";
        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * Gets query bindings for student contact type from admin config
     * @return array
     */
    private function getStudentContactTypeBindings(): array
    {
        if ($this->config->get('external_synergetic_student_contact_types') === '') {
            // No relationships were found, use the default values we had before introducing this feature
            $studentContactTypes = ['SC1', 'SC2', 'SC3'];
        } else {
            $studentContactTypes = explode(',', $this->config->get('external_synergetic_student_contact_types'));
        }

        $contactTypes = Builder::getArrayBindings($studentContactTypes, 'types');
        return $contactTypes;
    }

    /**
     * Gets query bindings for relationship from admin config
     * @return array
     */
    private function getRelationshipBindings(): array
    {
        if ($this->config->get('external_synergetic_student_relationships') === '') {
            // No relationships were found, use the default values we had before introducing this feature
            $relationships = ['SN', 'STSN', 'DR', 'STDR', 'WD', 'GRASN', 'GRADR', 'FOCD', 'HOPT', 'HOCD', 'HS', 'HOMES', 'HSS'];
        } else {
            $relationships = explode(',', $this->config->get('external_synergetic_student_relationships'));
        }
        $relationshipBindings = Builder::getArrayBindings($relationships, 'relationship');
        return $relationshipBindings;
    }
}
