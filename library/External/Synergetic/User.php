<?php
/**
 * @author    The Whole Company <support@alaress.com.au>
 * @copyright Copyright 2011-2017 (Hopefully RIP after that), Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Mapper\PredicateBuilder;
use Schoolbox\User;

class External_Synergetic_User implements External_UserInterface
{
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setupDatabase();
    }

    /**
     * @inheritdoc
     */
    public function getPortrait($externalUserId)
    {
        // Make sure a valid external ID was passed

        if (!$this->isExternalIdValid($externalUserId)) {
            return false;
        }

        // Get path on Schoolbox server to cached photos. These stupidly have a .JPG extension regardless of what
        // they really are- so don't pay much attention that the type

        $photoPath = realpath($this->config->get('portrait_dir')) . "/{$externalUserId}/.jpg";

        // Check if the configuration options have SIS photo imporing disabled

        if ($this->config->get('photo_sis_import') == 0) {

            // Not allowed to import photos. If one exists locally we can use it, otherwise we will just fail

            if (file_exists($photoPath)) {
                return $photoPath;
            } else {
                return false;
            }
        }

        $queryPhoto = $this->getConnector()->executeQuery(
            "SELECT vPhotos.ModifiedDate FROM dbo.vPhotos vPhotos WHERE vPhotos.ID = :externalId",
            ['externalId' => $externalUserId]
        );

        // Check if we found a photo in the SIS

        if ($photo = $queryPhoto->fetch()) {

            // Check

            if (is_readable($photoPath)) {
                $cacheExpired = filemtime($photoPath) < strtotime($photo['ModifiedDate']);
            } else {
                $cacheExpired = true;
            }

            if ($cacheExpired) {

                // The cache has expired, overwrite it with the one from the SIS

                $photoHandle = fopen($photoPath, "w");
                fwrite($photoHandle, hex2bin($photo['Photo']));
                fclose($photoHandle);
            }

            return $photoPath;
        }

        // There was no photo in the SIS, so we don't want to display the cached image either- delete it from
        // disk so it doesn't magically appear later after they'd specifically deleted it from Synergetic

        if (file_exists($photoPath)) {
            unlink($photoPath);
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getParent($synID)
    {
        if (!$this->isExternalIdValid($synID)) {
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

        $parentQuery = $this->getConnector()->executeQuery($query, ['externalId' => $synID]);

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
    public function getParentSync($synID)
    {
        if (!$this->isExternalIdValid($synID)) {
            return [];
        }

        // Coalesce non array inputs into an array just to make this easier

        if (!is_array($synID)) {
            $synID = [$synID];
        }

        $externalIdBindings = PredicateBuilder::getArrayBindings($synID, 'externalId');

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

        if (count($synID) > 1) {
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
    public function getParentEmails($synergyIds)
    {
        if (!$this->isExternalIdValid($synergyIds)) {
            return [];
        }

        // Coalesce non array inputs into an array just to make this easier

        if (!is_array($synergyIds)) {
            $synergyIds = [$synergyIds];
        }

        $externalIdBindings = PredicateBuilder::getArrayBindings($synergyIds, 'externalId');

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

        $iteratorParents = $this->getConnector()->executeQueryIterator($query, $externalIdBindings['bindings']);

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
    public function getStudent($synID)
    {
        if (!$this->isExternalIdValid($synID)) {
            return [];
        }

        $query =
        "
            SELECT      vStudents.StudentID,
                        vStudents.StudentGiven1,
                        vStudents.StudentSurname,
                        vStudents.StudentBirthDate,
                        vStudents.StudentTutorName,
                        vStudents.StudentHeadOfHouse,
                        House.HeadOfHouseID,
                        vStudents.StudentFormStaffName,
                        vStudents.StudentTutorDescription,
                        luForm.ID AS TeacherFormID,
                        vStudents.StudentForm,
                        luTutor.ID AS TeacherTutorID,
                        vStudents.StudentTutor,
                        luForm.Description AS FormDescription,
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
                        vStudents.StudentStudiesCode,
                        AlertMessage,
                        vStudents.StudentBoarder,
                        vStudents.StudentsComment as Comment
            FROM        dbo.vStudents vStudents
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
            LEFT JOIN   luForm ON vStudents.StudentForm = luForm.Code
            LEFT JOIN   luTutor ON vStudents.StudentTutor = luTutor.Code
            LEFT JOIN   luYearLevel ON 
                            vStudents.StudentCampus = luYearLevel.Campus AND 
                            vStudents.StudentYearLevel = luYearLevel.Code
            LEFT JOIN   luHouse House ON 
                            StudentCampus = House.Campus AND 
                            StudentHouse = House.Code
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudents.FileSemester AND
                            Semester.FileYear = vStudents.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       vStudents.StudentID = :externalId
        ";

        $student = [];

        $iteratorStudents = $this->getConnector()->executeQueryIterator($query, ['externalId' => $synID]);

        foreach ($iteratorStudents as $row) {

            // There might be multiple records, if this is the first one populate the bulk of the details

            if (empty($student)) {

                $student = $row;

                if ($student['StudentBirthDate'] && preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches)) {
                    $student['StudentBirthDate'] = strtotime("{$matches[2]} {$matches[1]} {$matches[3]}");
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
     */
    public function getStaff($synID)
    {
        if (!$this->isExternalIdValid($synID)) {
            return [];
        }

        $query =
        "
            SELECT      SchoolStaffCode,
                        luBuilding.Description AS StaffBuilding,
                        StaffRoom,
                        StaffExtension,
                        StaffMobilePhone,
                        StaffCampusDescription,
                        StaffCategoryDescription,
                        luDepartment.Description AS StaffDepartment,
                        StaffVoiceMail
             FROM       dbo.vStaff vStaff
             LEFT JOIN  dbo.luBuilding ON StaffBuilding = luBuilding.Code
             LEFT JOIN  dbo.luDepartment ON StaffDepartment = luDepartment.Code
             WHERE      vStaff.StaffID = :externalId
        ";

        return $this->getConnector()->executeQuery($query, ['externalId', $synID])->fetch();
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        // We have to dynamically bind filter parameters here based on the users input

        $filters = '';
        $bindings = [];

        // Search for a particular year level

        if (!empty($yearLevel)) {

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

        if (!empty($name)) {

            $splitNames = explode(' ', $name);

            $count = 1;

            foreach ($splitNames as $splitName) {

                $keyGiven = "nameGiven{$count}";
                $bindings[$keyGiven] = "%{$splitName}%";

                $keySurname = "nameSurname{$count}";
                $bindings[$keySurname] = "%{$splitName}%";

                $filters .= " AND (StudentSurname LIKE :{$keySurname} OR StudentGiven1 LIKE :{$keyGiven})";

                $count++;
            }
        }

        // Boarder search ('B' = Boarder, 'D' = Day Student, 'HS' = Home Stay)

        if ($boardingStatus == 'yes') {
            $filters .= " AND StudentBoarder = 'B'";
        } else {
            $filters .= " AND StudentBoarder != 'B'";
        }

        // Search for a particular form

        if (!empty($form)) {
            $bindings['form'] = $form;
            $filters .= " AND StudentForm = :form";
        }

        // Now can build the query string

        $query = "
            SELECT      StudentID,
                        StudentGiven1,
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
                        StudentContactDefaultEmail,
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
                        StudentContactPhoneActual,
                        StudentContactMobilePhone,
                        StudentContactOccupPhone,
                        StudentContactSpouseID,
                        StudentContactSpouseTitle,
                        StudentContactSpousePreferred,
                        StudentContactSpouseLegalFullName,
                        StudentContactSpouseSurname,
                        StudentContactSpouseMobilePhone,
                        StudentContactSpouseOccupPhone,
                        StudentContactSpouseDefaultEmail,
                        StudentContactType,
                        StudentContactSilentPhoneFlag,
                        Contact.SilentMobilePhoneFlag AS StudentContactSilentMobilePhoneFlag,
                        Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
                        Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                        ContactAddress.SilentHomeAddressFlag SilentHomeAddressFlag,
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
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudentContactAllAddress.FileSemester AND
                            Semester.FileYear = vStudentContactAllAddress.FileYear AND
                            Semester.SystemCurrentFlag = 1
            WHERE       StudentContactType IN ('SC1', 'SC2', 'SC3')
                        {$filters}
            ORDER BY    StudentSurname, 
                        StudentID, 
                        StudentContactType
                        
        ";

        // Now that we have created the query, time to shove it across to the database with
        // the appropriate parameters

        $iteratorContacts = $this->getConnector()->executeQueryIterator($query, $bindings);

        $contactList = [];

        foreach ($iteratorContacts as $contact) {

            // Remove invalid and silent contact details

            if ($contact['ValidAddressFlag'] == 0 || $contact['SilentHomeAddressFlag'] == 1) {
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
            WHERE       StudentContactType IN ('SC1', 'SC2', 'SC3') AND 
                        StudentBoarder = 'B'
            ORDER BY    StudentSurname, 
                        StudentID, 
                        StudentContactType
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getStudentContactList($externalUserId)
    {
        if (!$this->isExternalIdValid($externalUserId)) {
            return [];
        }

        $query =
        "
            SELECT          a.StudentContactID,
                            a.StudentContactTitle,
                            a.StudentContactPreferred,
                            a.StudentContactSurname,
                            a.StudentContactDefaultEmail,
                            a.ValidAddressFlag,
                            a.StudentContactAddress1,
                            a.StudentContactAddress2,
                            a.StudentContactAddress3,
                            a.StudentContactSuburb,
                            a.StudentContactPostCode,
                            a.StudentContactState,
                            a.StudentContactPhoneActual,
                            a.StudentContactMobilePhone,
                            a.StudentContactOccupPhone,
                            a.StudentContactRelation,
                            a.StudentContactSpouseID,
                            a.StudentContactSpouseTitle,
                            a.StudentContactSpousePreferred,
                            a.StudentContactSpouseSurname,
                            a.StudentContactSpouseMobilePhone,
                            a.StudentContactSpouseOccupPhone,
                            a.StudentContactSpouseDefaultEmail,
                            a.StudentContactType,
                            a.StudentContactSpouseRelation,
                            r.UseEmail AS StudentContactUseEmail,
                            rs.UseEmail AS StudentContactSpouseUseEmail                
            FROM            dbo.vStudentContactAllAddress a
            LEFT JOIN       Relationships r ON r.id = a.StudentID AND r.RelatedID = a.StudentContactID
            LEFT JOIN       Relationships rs ON rs.id = a.StudentID AND rs.RelatedID = a.StudentContactSpouseID            
            JOIN            FileSemesters Semester ON
                                Semester.FileSemester = vStudentContactAllAddress.FileSemester AND
                                Semester.FileYear = vStudentContactAllAddress.FileYear AND
                                Semester.SystemCurrentFlag = 1
            WHERE           StudentID = :externalId AND
                            StudentContactType IN ('SC1', 'SC2', 'SC3')
        ";

        $iteratorContacts = $this->getConnector()->executeQueryIterator($query, ['externalId' => $externalUserId]);

        $contactList = [];

        foreach ($iteratorContacts as $contact) {
            if ($contact['ValidAddressFlag'] == 0) {
                $contact['StudentContactAddress1'] = '';
                $contact['StudentContactAddress2'] = '';
                $contact['StudentContactAddress3'] = '';
                $contact['StudentContactSuburb'] = '';
                $contact['StudentContactPostCode'] = '';
                $contact['StudentContactState'] = '';
            }

            $contactList[] = $contact;
        }

        return $this->clearInvalidValues($contactList);
    }

    /**
     * @inheritdoc
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }

        if ($user->getRole()->isStudent()) {

            // Retrieve student subjects

            $sql =
            "
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
                ORDER BY          SubjectClasses.FileType ASC, 
                                  SubjectClasses.Description ASC
            ";

        } else if ($user->getRole()->isStaff()) {

            // Retrieve staff subjects

            $sql =
            "
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
                                      Semester.FileSemester = SubjectClasses.FileSemester AND
                                      Semester.FileYear = SubjectClasses.FileYear AND
                                      Semester.SystemCurrentFlag = 1
                LEFT JOIN         luFileType ON c2.FileType = luFileType.Code
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
     */
    public function getEnrolledStudents($externalId)
    {
        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }

        // Get student contact types from config, and wrangle into a query

        if (empty($this->config->get('external_synergetic_student_relationships')) === 0) {

            // No relationships were found, use the default values we had before introducing this feature

            $relationships = ['SN','STSN','DR','STDR','WD','GRASN','GRADR','FOCD','HOPT','HOCD','HS','HOMES','HSS'];
        } else {
            $relationships = explode(',', $this->config->get('external_synergetic_student_relationships'));
        }

        $relationshipBindings = PredicateBuilder::getArrayBindings($relationships, 'relationship');

        $query = "
            SELECT      Relationships.RelatedID
            FROM        dbo.Constituencies Constituencies
            LEFT JOIN   dbo.Relationships Relationships ON Relationships.RelatedID = Constituencies.ID
            LEFT JOIN   dbo.Community Community ON Relationships.RelatedID = Community.ID
            WHERE       Relationships.ID = :id AND
                        Constituencies.ConstitCode = '@SC' AND
                        Relationships.Relationship IN ({$relationshipBindings['placeholders']})
            ORDER BY    Community.BirthDate
        ";

        return $this->getConnector()->executeQuery($query, $relationshipBindings['bindings'])->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getEmail($externalId)
    {
        if (!$this->isExternalIdValid($externalId)) {
            return false;
        }

        return $this->getConnector()->executeQuery(
            "SELECT Email FROM Community WHERE id = :externalId",
            ['externalId' => $externalId]
        )->fetch();
    }

    /**
     * @inheritdoc
     */
    public function getStudentUserAccountInfo()
    {
        $query =
            "
            SELECT      vStudents.StudentID,
                        NetworkLogin,
                        vStudents.StudentTitle,
                        vStudents.StudentPreferred,
                        vStudents.StudentSurname,
                        StudentGender,
                        StudentEmail,
                        StudentOccupEmail,
                        vStudents.StudentYearLevel,
                        vStudents.StudentHouse,
                        vStudents.StudentBoarder,
                        vStudents.StudentBoardingHouse,
                        StudentCampus
            FROM        dbo.vStudents vStudents
            JOIN        FileSemesters Semester ON
                            Semester.FileSemester = vStudents.FileSemester AND
                            Semester.FileYear = vStudents.FileYear AND
                            Semester.SystemCurrentFlag = 1
            LEFT JOIN   Community ON Community.ID = StudentID
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getParentUserAccountInfo()
    {
        // Get student contact types from config, and wrangle into a query

        if (empty($this->config->get('external_synergetic_student_contact_types')) === 0) {

            // No relationships were found, use the default values we had before introducing this feature

            $studentContactTypes = ['SC1','SC2'];
        } else {
            $studentContactTypes = explode(',', $this->config->get('external_synergetic_student_contact_types'));
        }

        $contactTypes = PredicateBuilder::getArrayBindings($studentContactTypes, 'types');

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
                                Semester.FileSemester = vStudentContactAllAddress.FileSemester AND
                                Semester.FileYear = vStudentContactAllAddress.FileYear AND
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
     */
    public function getStaffUserAccountInfo()
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
                        vStaff.StaffRegistrationType != 'STDNT'
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getStaffContactListExtended()
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

        if ($queryTable->rowCount() > 0) {
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
                        StaffDepartmentDescription,
                        JobPositions.StaffJobPositionStartDate,
                        JobPositions.StaffJobPositionEndDate,
                        Community.OccupDesc AS JobPositionDescriptionAlternate,                
                        {$select}
            FROM        dbo.vStaff vStaff
            JOIN        dbo.Community ON Community.ID = vStaff.StaffID
            LEFT JOIN   dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
                        {$join}
            WHERE       vStaff.ActiveFlag = 1 AND
                        StaffCategoryDescription != 'Staff on leave' AND
                        (JobPositions.StaffJobPositionStartDate < GETDATE() OR JobPositions.StaffJobPositionStartDate IS NULL) AND
                        (JobPositions.StaffJobPositionEndDate > GETDATE() OR JobPositions.StaffJobPositionEndDate IS NULL)
                        {$filter}
            ORDER BY    vStaff.StaffSurname
        ";

        return $this->getConnector()->executeQuery($query)->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function getCampusFromTimetable($externalId)
    {
        if (!$this->isExternalIdValid($externalId)) {
            return [];
        }

        return $this->getConnector()->executeQuery(
            "SELECT DISTINCT ClassCampus AS campus FROM timetable WHERE StaffID = :id AND FileYear = :fileYear",
            [
                'id' => $externalId,
                'fileYear' => date('Y')
            ]
        )->fetchAll();
    }

    /**
     * @inheritdoc
     */
    public function determineBoarderType($code)
    {
        if (empty($code)) {
            return '';
        }

        return $this->getConnector()->executeQuery(
            "SELECT Description FROM luBoarder WHERE Code = :code",
            ['code' => $code]
        )->fetchColumn();
    }


    /**
     * @inheritdoc
     */
    public function getHousemasterByHouseId($houseID)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getStudentForms()
    {
        $iteratorForms = $this->getConnector()->executeQueryIterator(
            "SELECT Code, Description FROM dbo.luForm WHERE ActiveFlag = 1"
        );

        $forms = [];

        foreach ($iteratorForms as $form) {
            $forms[] = $this->clearInvalidValues($form);
        }

        return $forms;
    }

    /**
     * This is a helper function to clear the 'Not Selected' placeholder value that
     * Synergetic loves to add as an option in every lookup table
     *
     * @param array $array
     * @param string $placeholder
     * @return array
     */
    private function clearInvalidValues($array, $placeholder = 'Not Selected')
    {
        // Remove the 'Not Selected' Synergetic placeholder value

        return array_map(function($value) use ($placeholder) {
            if (is_array($value)) {
                return $this->clearInvalidValues($value);
            } else {
                return trim(str_replace($placeholder, '', $value));
            }
        }, $array);
    }

    /**
     * Retrieve current Synergetic file year and file semester
     * @param int|null $externalUserId If supplied, will returns the users current year/semester
     * @return array
     */
    protected function findFileYearAndSemester($externalUserId = null)
    {
        // If we are supplied a valid user ID use their current file year/semester values

        if ($externalUserId != null) {
            if ($this->isExternalIdValid($externalUserId)) {
                $statement = $this->getConnector()->executeStoredProcedure(
                    'spsConfigUserDefaultYearSemester',
                    ['ID' => $externalUserId]
                );

                if ($semester = $statement->fetch()) {
                    return [
                        'year'          => $semester['UserFileYear'],
                        'semester'      => $semester['UserFileSemester']
                    ];
                }
            }
        }

        // Otherwise, return the current system default values

        $querySemester = $this->getConnector()->executeQuery(
            "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1"
        );

        if ($semester = $querySemester->fetch())
        {
            return [
                'year'              => $semester['FileYear'],
                'semester'          => $semester['FileSemester'],
            ];

        } else {

            // If everything falls in a heap- return a blank array

            return [];
        }
    }

    /**
     * @inheritdoc
     */
    public function getCurrentSemester($externalUserId = null)
    {
        $fileYearSemester = $this->findFileYearAndSemester($externalUserId);

        if (!empty($fileYearSemester)) {
            return $fileYearSemester['semester'];
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    public function getCurrentYear($externalUserId = null)
    {
        $fileYearSemester = $this->findFileYearAndSemester($externalUserId);

        if (!empty($fileYearSemester)) {
            return $fileYearSemester['year'];
        }

        return null;
    }
}
