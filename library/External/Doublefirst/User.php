<?php

/**
 * DoubleFirst User Object
 *
 * @author        Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 *
 *         ,--.--._
 * ------" _, \___)
 *       2 / _/____)
 *       x \//(____)
 * ------\     (__)
 *        `-----"
 */

use Schoolbox\User;

class External_Doublefirst_User implements External_UserInterface
{

    /**
     * This identifies the CONVERT/CAST format for ISO8601 date
     * yyyy-mm-ddThh:mi:ss.mmm
     */
    const FORMAT_ISO8601 = 126;

    /**
     * @var MSSQL
     */
    private $database;

    /** @var Group */
    private $groupHandler;

    /** @var External_Doublefirst_Helper */
    private $helper;


    /** Constructor */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->setGroup(new Group());
        $this->helper = new External_Doublefirst_Helper();
    }

    /**
     * Retrieves the user's Doublefirst image
     *
     * @param Integer $externalUserId
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {
        // Start by searching for a student photo

        $query =
            "
            SELECT  LastUpdated
            FROM    PupilPhoto
            WHERE   PupilID = '" . $this->getDatabase()->quote($externalUserId) . "'
        ";

        if ($photo = $this->getDatabase()->getRow($query)) {
            $photoPath = $this->helper->getPhotoPath($externalUserId);

            // TODO: Delete photos on rollover

            /* If the cached file doesn't exist, or the synergetic image is more recent than our
             * last known cached version, regenerate cached version.
             *
             * Separate the queries out, fetching the photo data only if it's newer than the cached
             * version, or if the cache version doesn't already exist.
             */

            $cacheTime = @filemtime($photoPath);
            $modifiedTime = strtotime($photo['LastUpdated']);

            if (!file_exists($photoPath) || ($cacheTime < $modifiedTime)) {
                $query = "
                    SELECT  Photo
                    FROM    PupilPhoto
                    WHERE   PupilID = '" . $this->getDatabase()->quote($externalUserId) . "'
                ";
                $photo = $this->getDatabase()->getRow($query);

                $photoHandle = fopen($photoPath, "wb");
                fwrite($photoHandle, $photo['Photo']);
                fclose($photoHandle);
            }

            return $photoPath;
        } else {
            // Couldn't find student photo, try staff

            $query =
                "
                SELECT  LastUpdated
                FROM    StaffPersonalDetails
                WHERE   StaffID = '" . $this->getDatabase()->quote($externalUserId) . "'
            ";

            if ($photo = $this->getDatabase()->getRow($query)) {
                $photoPath = $this->helper->getPhotoPath($externalUserId);

                // TODO: Delete photos on rollover

                /* If the cached file doesn't exist, or the synergetic image is more recent than our
                 * last known cached version, regenerate cached version.
                 *
                 * Separate the queries out, fetching the photo data only if it's newer than the cached
                 * version, or if the cache version doesn't already exist.
                 */

                $cacheTime = @filemtime($photoPath);
                $modifiedTime = strtotime($photo['LastUpdated']);

                if (!file_exists($photoPath) || ($cacheTime < $modifiedTime)) {
                    $query = "
                    SELECT  StaffPhoto AS Photo
                    FROM    StaffPersonalDetails
                    WHERE   StaffID = '" . $this->getDatabase()->quote($externalUserId) . "'
                ";
                    $photo = $this->getDatabase()->getRow($query);

                    $photoHandle = fopen($photoPath, "wb");
                    fwrite($photoHandle, $photo['Photo']);
                    fclose($photoHandle);
                }

                return $photoPath;
            }

        }
        return false;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the Doublefirst of the Contact, not the Student.
     *
     * @param $externalUserId
     * @return mixed
     */
    public function getParent($externalUserId)
    {
        $externalUserId = $this->database->quote($externalUserId);

        $query =
            "
            SELECT TOP 1
                c.Title                     AS Title,
                cm.Greeting                 AS Preferred,
                c.Surname                   AS Surname,
                ceh.EmailID         AS Email,
                cew.EmailID         AS OccupEmail,        
                ''                          AS DefaultEmailCode,
                ct.HomeTelephoneNumber      AS HomePhone,
                
                LTRIM(
                  RTRIM(
                    COALESCE(
                      NULLIF(ct.Mobile, ''), 
                      NULLIF(ctPhoneMobile.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                      NULLIF(ctPhoneMobile2.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                      NULLIF(ctPhoneMobile3.TelephoneNumber + ' (His)', ' (His)'), 
                      NULLIF(ctPhoneMobile4.TelephoneNumber + ' (His)', ' (His)'), 
                      NULLIF(ctPhoneMobile5.TelephoneNumber, '')
                    ) 
                  )
                ) AS MobilePhone, 
                0                           AS SilentPhoneFlag,
                0                           AS SilentMobilePhoneFlag,
                1                           AS DirectoryIncludeFlag,
                ''                          AS SpouseID,
                ''                          AS SpouseTitle,
                cm.FirstName2               AS SpousePreferred,
                ''                          AS SpouseSurname,
                ''                          AS SpouseEmail,
                ''                          AS SpouseOccupEmail,
                ''                          AS SpouseDefaultEmailCode,
                ''                          AS SpouseMobilePhone,
                0                           AS SpouseDirectoryIncludeFlag
            
            FROM        VLPupilContact      pc
            LEFT JOIN   VLContact           c on c.ContactID = pc.ContactID
            LEFT JOIN   ContactMaster       cm on c.ContactID = cm.ContactID
            LEFT JOIN   ContactEmailInformation      ceh on ceh.ContactID = pc.ContactID AND ceh.EmailType = '001' AND ceh.PrimaryEmail = 1
            LEFT JOIN   ContactEmailInformation      cew on cew.ContactID = pc.ContactID AND cew.EmailType = '002' AND cew.PrimaryEmail = 1
            LEFT JOIN   VLContactTelephone  ct on ct.ContactID = pc.ContactID 
                        
            LEFT JOIN ContactTelephoneInformation ctPhoneMobile    ON ctPhoneMobile.ContactID = pc.ContactID AND ctPhoneMobile.TelephoneType = 'M'
            LEFT JOIN ContactTelephoneInformation ctPhoneMobile2   ON ctPhoneMobile2.ContactID = pc.ContactID AND ctPhoneMobile2.TelephoneType = '003'
            LEFT JOIN ContactTelephoneInformation ctPhoneMobile3   ON ctPhoneMobile3.ContactID = pc.ContactID AND ctPhoneMobile3.TelephoneType = '004'
            LEFT JOIN ContactTelephoneInformation ctPhoneMobile4   ON ctPhoneMobile4.ContactID = pc.ContactID AND ctPhoneMobile4.TelephoneType = '005'
            LEFT JOIN ContactTelephoneInformation ctPhoneMobile5   ON ctPhoneMobile5.ContactID = pc.ContactID AND ctPhoneMobile5.TelephoneType = '006'
            
            WHERE       pc.IsContactParental = 1 
            AND         pc.ContactID = '{$externalUserId}'    
            ORDER BY    pc.Priority
        ";

        $parent = $this->getDatabase()->getRow($query);

        // HACK: Apparently the DoubleFirst database schema is setup to believe that all couples share the same surname, so
        // the partners surname is either mashed into their first name (in which case we assume anything after the
        // first space is their surname), or mashed into a single surname field (in which case we'll assume anything
        // after "&" is the spouses surname, and anything before is the primarys surname)

        // Start by checking for "&"

        $spouseNameArray = explode("&", trim($parent["Surname"]), 1);

        if (count($spouseNameArray) > 1) {
            $spouseNameArray["Surname"] = $spouseNameArray[0];
            $spouseNameArray["SpouseSurname"] = $spouseNameArray[1];
        } else {
            // That didn't work, try looking for spaces in spouse name

            $spouseNameArray = explode(" ", trim($parent["SpousePreferred"]), 1);

            if (count($spouseNameArray) > 1) {
                $spouseNameArray["SpouseSurname"] = $spouseNameArray[1];
            } else {
                $spouseNameArray["SpouseSurname"] = $parent["Surname"];
            }
        }

        return $parent;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the external ID of the Contact, not the Student. Anyone who is
     * not supposed to be having their details publicised (via the
     * DirectoryIncludeFlag) are not included in the result set.
     * Accept array of external IDs, or just an int
     *
     * @param $externalUserId
     * @return mixed
     */
    public function getParentSync($externalUserId)
    {
        if (is_array($externalUserId)) {
            $assignation = " IN ('" . implode("','", array_map([$this->database, 'quote'], $externalUserId)) . "')";
        } else {
            $assignation = " =  '" . $this->database->quote($externalUserId) . "'";
        }

        $externalUserId = $this->database->quote($externalUserId);

        $query = "
            SELECT
                c.Title                     AS Title,
                cm.Greeting                 AS Preferred,
                c.Surname                   AS Surname,
                ce.HomeEmailAddress         AS Email,
                ce.WorkEmailAddress         AS OccupEmail,
                ''                          AS DefaultEmailCode,
                ct.HomeTelephoneNumber      AS HomePhone,
                ct.Mobile                   AS MobilePhone,
                0                           AS SilentPhoneFlag,
                0                           AS SilentMobilePhoneFlag,
                1                           AS DirectoryIncludeFlag,
                ''                          AS SpouseID,
                ''                          AS SpouseTitle,
                cm.FirstName2               AS SpousePreferred,
                ''                          AS SpouseSurname,
                ''                          AS SpouseEmail,
                ''                          AS SpouseOccupEmail,
                ''                          AS SpouseDefaultEmailCode,
                ''                          AS SpouseMobilePhone,
                0                           AS SpouseDirectoryIncludeFlag
            FROM        VLPupilContact      pc
            LEFT JOIN   VLContact           c on c.ContactID = pc.ContactID
            LEFT JOIN   ContactMaster       cm on c.ContactID = cm.ContactID
            LEFT JOIN   VLContactEmail      ce on ce.ContactID = pc.ContactID
            LEFT JOIN   VLContactTelephone  ct on ct.ContactID = pc.ContactID 
            WHERE       pc.IsContactParental = 1 
            AND         pc.ContactID {$assignation} 
            ORDER BY    pc.Priority
        ";

        if (is_array($externalUserId)) {
            return $this->getDatabase()->getAll($query);
        }

        return $this->getDatabase()->getRow($query);
    }

    /**
     * Get all the parents emails for a class, or group of students
     *
     * @param array $synergyIds
     *
     * @return array
     */
    public function getParentEmails($synergyIds)
    {
        $emailList = array();
        $id_list = implode(',', array_map('intval', $synergyIds));

        if (empty($id_list)) {
            return array();
        }

        $query =
            "
            SELECT DISTINCT   pc.ContactID            AS RelatedID,
                              cei.EmailID             AS DefaultEmail
            FROM              VLPupilContact          pc
            LEFT JOIN         ContactEmailInformation cei ON cei.ContactID = pc.ContactID
            WHERE             pc.PupilID IN ($id_list) 
            AND 	          pc.IsContactParental = 1
            AND               pc.ContactID <> ''
            AND               cei.EmailID <> ''
        ";

        $statement = $this->getDatabase()->query($query);

        while ($row = $statement->fetch()) {
            if (!isset($emailList[$row['RelatedID']])) {
                $emailList[$row['RelatedID']] = $row['DefaultEmail'];
            }
        }

        return $emailList;
    }

    /**
     * Get a students info from Doublefirst
     *
     * @param  $externalUserId
     * @return array|bool
     */
    public function getStudent($externalUserId)
    {
        // We will retrieve a list of all possible primary emergency contacts- they all seem to contain 'EM Contact 1' in the description

        $ecQuery =
            "
            SELECT LookupDetailsID 
            FROM LookupDetails                
            WHERE LookupID = 2100 AND Description LIKE '%EM Contact 1%';
        ";

        $ecRelationship = $this->getDatabase()->getAll($ecQuery);
        $relationships = [];

        foreach ($ecRelationship as $relat) {
            $relationships[] = $relat["LookupDetailsID"];
        }
        $assignation = implode("','", array_map([$this->database, 'quote'], $relationships));

        // TODO: The query is going to return multiple rows because there is potentially more than one emergency contact- so we are just
        // taking the first row. This is a total hack.

        $query = "
            SELECT TOP 1
            
                p.PupilID                                           AS StudentID,
                p.AdmissionNo                                       AS AdmissionNo,
                ForeName                                            AS StudentGiven1,
                Surname                                             AS StudentSurname,
                CONVERT(nvarchar(30), BirthDate, ".self::FORMAT_ISO8601." )              AS StudentBirthDate,                
                ''                                                  AS StudentTutorName,
                ''                                                  AS StudentHeadOfHouse,
                ''                                                  AS HeadOfHouseID,
                ''                                                  AS StudentFormStaffName,
                ''                                                  AS StudentTutorDescription,
                ''                                                  AS TeacherFormID,
                Form                                                AS StudentForm,
                ''                                                  AS TeacherTutorID,
                ''                                                  AS StudentTutor,
                Form                                                AS FormDescription,
                House                                               AS StudentHouseDescription,
                HouseID                                             AS StudentHouse,
                YearGroup                                           AS StudentYearLevel,
                ''                                                  AS StudentLockerBook,
                ecm.ContactName                                     AS StudentEmergencyName,
                LTRIM(
                  RTRIM(
                    COALESCE(
                      NULLIF(ecmPhoneMobile.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                      NULLIF(ecmPhoneMobile2.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                      NULLIF(ecmPhoneMobile3.TelephoneNumber + ' (His)', ' (His)'), 
                      NULLIF(ecmPhoneMobile4.TelephoneNumber + ' (His)', ' (His)'), 
                      NULLIF(ecmPhoneMobile5.TelephoneNumber, '')                      
                    ) 
                  )
                )                                                   AS StudentEmergencyPhone, 
                ecmPhoneHome.TelephoneNumber                        AS StudentEmergencyPhoneAlt,
                m.Comments                                          AS StudentMedicalAlert,
                ''                                                  AS StudentSpecialNeedsFlag,
                ''                                                  AS StudentStudiesCode,
                UrgentNotes                                         AS AlertMessage,
                ''                                                  AS StudentBoarder,
                ''                                                  AS Comment,
                pca.CustodyAlert                                    AS CustodyAlert,
                pca.CustodyNotes                                    AS CustodyNotes,
                pcad.Description                                    AS CustodyResponsiblePerson,
                0 AS countIndividualEducationPlans
            FROM V_PupilBasicInformation                            p
            LEFT JOIN PupilCareAuthority pca                        ON pca.PupilID = p.PupilID
            LEFT JOIN LookupDetails pcad                            ON pcad.LookupID = 3025 AND LookupDetailsID = pca.ResponsibleForCustody
            LEFT JOIN PupilMedicalInformation m                     ON m.PupilID = p.PupilID                
            LEFT JOIN PupilContacts ec                              ON ec.RelationshipToPupil IN ('{$assignation}') AND ec.PupilID = p.PupilID
            LEFT JOIN ContactMaster ecm                             ON ecm.ContactID = ec.ContactID
            LEFT JOIN ContactTelephoneInformation ecmPhoneMobile    ON ecmPhoneMobile.ContactID = ecm.ContactID AND ecmPhoneMobile.TelephoneType = 'M'
            LEFT JOIN ContactTelephoneInformation ecmPhoneMobile2   ON ecmPhoneMobile2.ContactID = ecm.ContactID AND ecmPhoneMobile2.TelephoneType = '003'
            LEFT JOIN ContactTelephoneInformation ecmPhoneMobile3   ON ecmPhoneMobile3.ContactID = ecm.ContactID AND ecmPhoneMobile3.TelephoneType = '004'
            LEFT JOIN ContactTelephoneInformation ecmPhoneMobile4   ON ecmPhoneMobile4.ContactID = ecm.ContactID AND ecmPhoneMobile4.TelephoneType = '005'
            LEFT JOIN ContactTelephoneInformation ecmPhoneMobile5   ON ecmPhoneMobile5.ContactID = ecm.ContactID AND ecmPhoneMobile5.TelephoneType = '006'
            LEFT JOIN ContactTelephoneInformation ecmPhoneHome      ON ecmPhoneHome.ContactID = ecm.ContactID AND ecmPhoneHome.TelephoneType = 'H'
            WHERE p.PupilID = '" . $this->database->quote($externalUserId) . "';
        ";

        $student = [];

        foreach ($this->getDatabase()->getAll($query) ?: array() AS $row) {
            // fill in student record
            $student = $row;

            if ($student['StudentBirthDate'] && $birthDate = strtotime($student['StudentBirthDate'])) {
                $student['StudentBirthDate'] = $birthDate;
            } else {
                $student['StudentBirthDate'] = false;
            }
        }

        return $student;
    }

    /**
     * Retrieve selected staff members details
     *
     * @param $externalUserId
     * @return array|bool
     */
    public function getStaff($externalUserId)
    {
        $query =
            "
            SELECT          s.EmployeeID AS SchoolStaffCode,
                            '' AS StaffBuilding,
                            '' AS StaffRoom,
                            '' AS StaffExtension,
                            tMobile.TelephoneNumber AS StaffMobilePhone,
                            tMobile.TelephoneNumber AS StaffDefaultMobilePhone,
                            '' AS StaffCampusDescription,
                            cat.Description AS StaffCategoryDescription,
                            '' AS StaffDepartment,
                            '' AS StaffVoiceMail,
                            '' AS StaffHouseDescription
            FROM            StaffPersonalDetails s
		    JOIN            CurrentStaff cs ON cs.StaffID = s.StaffID
            LEFT JOIN       LookupDetails cat ON cat.LookupID = 1203 AND cat.LookupDetailsID = s.StaffCategory
            LEFT JOIN       StaffTelephones tMobile on tMobile.StaffID = s.StaffID AND TelephoneType = 'M' 
            WHERE           s.StaffId = '{$this->database->quote($externalUserId)}'
        ";

        return $this->getDatabase()->getRow($query);
    }

    /**
     * Gets a list of Contacts for all Students in a particular year level. The
     * year level is stored in Doublefirst.
     *
     * Used in reporting.
     *
     * @param $yearlevel
     * @param $name
     * @param $boardingStatus
     * @param $form
     *
     * @return mixed
     */
    public function getStudentContactListExtended($yearlevel, $name, $boardingStatus, $form)
    {
        $query =
            "
            SELECT      p.PupilID AS StudentID,
                        p.ForeName AS StudentPreferred,
                        p.Surname AS StudentSurname,
                        p.YearGroup AS StudentYearLevel,
                        p.House AS StudentHouse,
                        pcs.BoarderStatus AS StudentBoarder,
                        p.Form AS StudentForm,
                        
                        c.ContactID AS StudentContactID,
                        c.Title AS StudentContactTitle,
                        cm.Greeting AS StudentContactPreferred,
                        c.contactName AS StudentContactLegalFullName,
                        c.Surname StudentContactSurname,
                        ceh.EmailID AS StudentContactDefaultEmail,
                        1 AS ValidAddressFlag,
        
                        a.SubDwelling AS StudentContactAddress1,
                        a.Street AS StudentContactAddress2,
                        a.Locality AS StudentContactAddress3,
                        a.Dwelling AS StudentContactSuburb,
                        a.Postcode AS StudentContactPostCode,
                        a.County AS StudentContactState,
                        a.Country AS StudentContactCountryCode,
                        
                        a.SubDwelling AS StudentContactHomeAddress1,
                        a.Street AS StudentContactHomeAddress2,
                        a.Locality AS StudentContactHomeAddress3,
                        a.Dwelling AS StudentContactHomeSuburb,
                        a.PostCode AS StudentContactHomePostCode,
                        a.County AS StudentContactHomeState,
                        a.Country AS StudentContactHomeCountryCode,
                        
                        LTRIM(
                          RTRIM(
                            COALESCE(
                              NULLIF(cp.Mobile, ''), 
                              NULLIF(cmPhoneMobile.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                              NULLIF(cmPhoneMobile2.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                              NULLIF(cmPhoneMobile3.TelephoneNumber + ' (His)', ' (His)'), 
                              NULLIF(cmPhoneMobile4.TelephoneNumber + ' (His)', ' (His)'), 
                              NULLIF(cmPhoneMobile5.TelephoneNumber, '')
                            )
                          )
                        ) AS StudentContactMobilePhone, 
                        
                        LTRIM(
                          RTRIM(
                            COALESCE(
                              NULLIF(cp.Mobile, ''), 
                              NULLIF(cmPhoneWork.TelephoneNumber + '', ''), 
                              NULLIF(cmPhoneWork2.TelephoneNumber + '', ''), 
                              NULLIF(cmPhoneWork3.TelephoneNumber + '', ''), 
                              NULLIF(cmPhoneWork4.TelephoneNumber + ' (Hers Work)', ' (Hers Work)'), 
                              NULLIF(cmPhoneWork5.TelephoneNumber + ' (His Work)', ' (His Work)'), 
                              NULLIF(cmPhoneWork6.TelephoneNumber + '', '')
                            )
                          )
                        ) AS StudentContactOccupPhone, 
                        
                        pc.RelationshipToPupil AS StudentContactRelation,
                        '' AS StudentContactSpouseID,
                        '' AS StudentContactSpouseTitle,
                        '' AS StudentContactSpousePreferred,
                        '' AS StudentContactSpouseSurname,

                        LTRIM(
                          RTRIM(
                            COALESCE(
                              NULLIF(cmPhoneMobile3.TelephoneNumber + ' (His)', ' (His)'), 
                              NULLIF(cmPhoneMobile4.TelephoneNumber + ' (His)', ' (His)')
                            )
                          )
                        ) AS StudentContactSpouseMobilePhone, 
    
                        LTRIM(
                          RTRIM(
                            COALESCE(
                              NULLIF(cmPhoneWork5.TelephoneNumber + ' (His Work)', ' (His Work)'), 
                              NULLIF(cmPhoneWork6.TelephoneNumber + '', '')
                            ) 
                          )
                        ) AS StudentContactSpouseOccupPhone, 


                        cew.EmailID AS StudentContactSpouseDefaultEmail,
                        'SC1' AS StudentContactType,
                        0 AS StudentContactSilentPhoneFlag,
                        0 AS StudentContactSilentMobilePhoneFlag,
                        1 AS PrimaryDirectoryIncludeFlag,
                        1 AS SpouseDirectoryIncludeFlag,
                        0 AS SilentHomeAddressFlag,
                        0 AS StudentParentsSeparatedFlag
            
            FROM      V_PupilBasicInformation p
            JOIN      VLPupilContact                pc ON pc.PupilID = p.PupilID AND pc.IsContactParental = 1 
            LEFT JOIN PupilCurrentSchool            pcs ON pcs.PupilID = p.PupilID
            LEFT JOIN VLContactAddress              a ON a.ContactID = pc.ContactID AND a.AddressType = 'Current'
            LEFT JOIN VLContact                     c ON c.ContactID = pc.ContactID
            LEFT JOIN ContactMaster                 cm ON cm.ContactID = pc.ContactID
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile    ON cmPhoneMobile.ContactID = pc.ContactID AND cmPhoneMobile.TelephoneType = 'M'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile2   ON cmPhoneMobile2.ContactID = pc.ContactID AND cmPhoneMobile2.TelephoneType = '003'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile3   ON cmPhoneMobile3.ContactID = pc.ContactID AND cmPhoneMobile3.TelephoneType = '004'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile4   ON cmPhoneMobile4.ContactID = pc.ContactID AND cmPhoneMobile4.TelephoneType = '005'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile5   ON cmPhoneMobile5.ContactID = pc.ContactID AND cmPhoneMobile5.TelephoneType = '006'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork    ON cmPhoneWork.ContactID = pc.ContactID AND cmPhoneWork.TelephoneType = 'W'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork2   ON cmPhoneWork2.ContactID = pc.ContactID AND cmPhoneWork2.TelephoneType = '001'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork3   ON cmPhoneWork3.ContactID = pc.ContactID AND cmPhoneWork3.TelephoneType = '002'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork4   ON cmPhoneWork4.ContactID = pc.ContactID AND cmPhoneWork4.TelephoneType = '007'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork5   ON cmPhoneWork5.ContactID = pc.ContactID AND cmPhoneWork5.TelephoneType = '008'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork6   ON cmPhoneWork6.ContactID = pc.ContactID AND cmPhoneWork6.TelephoneType = '009'
            LEFT JOIN VLContactTelephone            cp ON cp.ContactID = pc.ContactID 
            LEFT JOIN VLContactEmail                ce ON ce.ContactID = pc.ContactID
            LEFT JOIN ContactEmailInformation ceh  ON ceh.contactid = pc.contactid and ceh.emailtype IN ('001','1') and ceh.primaryemail = 1
            LEFT JOIN ContactEmailInformation cew  ON cew.contactid = pc.contactid and cew.emailtype IN ('002','2') and cew.primaryemail = 1 
            WHERE
        ";

        $whereAnd = "";

        // Search for a particular year level

        if (strlen($yearlevel)) {
            $query .= " {$whereAnd} Year.Description = '{$this->getDatabase()->quote($yearlevel)}' ";
            $whereAnd = " AND ";
        }

        // Search for a student name

        if (strlen($name)) {

            $splitNames = explode(' ', $name);

            foreach ($splitNames as $splitName) {
                $splitName = $this->getDatabase()->quote($splitName);
                $query .= " {$whereAnd} (StudentSurname LIKE '%{$splitName}%' OR StudentGiven1 LIKE '%{$splitName}%') ";
                $whereAnd = " AND ";
            }
        }

        // Search for a particular boarding status

        if ($boardingStatus == '') {
            // Noop
        } elseif ($boardingStatus == 'yes') {
            $codes = "";
            foreach ($this->helper->getBoardingStatuses() as $status) {
                $status = $this->getDatabase()->smartQuote($status);
                if ($codes != "") {
                    $codes .= ",";
                }
                $codes .= $status;
            }
            $query .= " {$whereAnd} pcs.BoarderStatus IN ({$codes})";
            $whereAnd = " AND ";
        } else {
            $codes = "";
            foreach ($this->helper->getBoardingStatuses() as $status) {
                $status = $this->getDatabase()->smartQuote($status);
                if ($codes != "") {
                    $codes .= ",";
                }
                $codes .= $status;
            }
            $query .= " {$whereAnd} pcs.BoarderStatus NOT IN ({$codes})";
            $whereAnd = " AND ";
        }

        if ($form) {
            $form = $this->getDatabase()->quote($form);
            $query .= "{$whereAnd} p.Form = '{$form}'";
        }

        $query .= " ORDER BY StudentSurname, StudentID, StudentContactType, pc.Priority";
        $contactList = $this->getDatabase()->getAll($query) ?: array();

        foreach ($contactList as &$row) {

            // Titles get mashed together (eg. 'Mr & Mrs'), split it out based on ampersand

            $titleArray = explode("&", $row['StudentContactTitle']);
            if (count($titleArray) > 1) {
                $row['StudentContactTitle'] = $titleArray[0];
                $row['StudentContactSpouseTitle'] = $titleArray[1];
            }

            // Same thing for surnames

            $titleArray = explode("&", $row['StudentContactSurname']);
            if (count($titleArray) > 1) {
                $row['StudentContactSurname'] = $titleArray[0];
                $row['StudentContactSpouseSurname'] = $titleArray[1];
            }
        }

        // Hack to get rid of duplicate phone numbers

        foreach ($contactList as $k => $c) {
            if ($c["StudentContactSpouseOccupPhone"] == $c["StudentContactOccupPhone"]) {
                $contactList[$k]["StudentContactSpouseOccupPhone"] = '';
            }

            if ($c["StudentContactSpouseMobilePhone"] == $c["StudentContactMobilePhone"]) {
                $contactList[$k]["StudentContactSpouseMobilePhone"] = '';
            }
        }

        return $contactList;
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     *
     * @param string $externalUserId
     * @return mixed
     */
    public function getStudentContactList($externalUserId)
    {
        $query =
            "
            SELECT    c.ContactID AS StudentContactID,
                      c.Title AS StudentContactTitle,
                      cm.Greeting AS StudentContactPreferred,
                      c.Surname StudentContactSurname,
                      ceh.EmailID AS StudentContactDefaultEmail,
                      cp.HomeTelephoneNumber AS StudentContactPhoneActual,
                      0 AS StudentContactSilentPhoneFlag,
                      
                      LTRIM(
                        RTRIM(
                          COALESCE(
                            NULLIF(cp.Mobile, ''), 
                            NULLIF(cmPhoneMobile.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                            NULLIF(cmPhoneMobile2.TelephoneNumber + ' (Hers)', ' (Hers)'), 
                            NULLIF(cmPhoneMobile3.TelephoneNumber + ' (His)', ' (His)'), 
                            NULLIF(cmPhoneMobile4.TelephoneNumber + ' (His)', ' (His)'), 
                            NULLIF(cmPhoneMobile5.TelephoneNumber, '')
                          )
                        )
                      ) AS StudentContactMobilePhone, 

                      LTRIM(
                        RTRIM(
                          COALESCE(
                            NULLIF(cp.Mobile, ''), 
                            NULLIF(cmPhoneWork.TelephoneNumber + '', ''), 
                            NULLIF(cmPhoneWork2.TelephoneNumber + '', ''), 
                            NULLIF(cmPhoneWork3.TelephoneNumber + '', ''), 
                            NULLIF(cmPhoneWork4.TelephoneNumber + ' (Hers Work)', ' (Hers Work)'), 
                            NULLIF(cmPhoneWork5.TelephoneNumber + ' (His Work)', ' (His Work)'), 
                            NULLIF(cmPhoneWork6.TelephoneNumber + '', '')
                          )
                        )
                      ) AS StudentContactOccupPhone, 
                      
                      pc.RelationshipToPupil AS StudentContactRelation,
                      '' AS StudentContactSpouseID,
                      '' AS StudentContactSpouseTitle,
                      '' AS StudentContactSpousePreferred,
                      '' AS StudentContactSpouseSurname,

                      LTRIM(
                        RTRIM(
                          COALESCE(
                            NULLIF(cmPhoneMobile3.TelephoneNumber + ' (His)', ' (His)'), 
                            NULLIF(cmPhoneMobile4.TelephoneNumber + ' (His)', ' (His)')
                          )
                        )
                      ) AS StudentContactSpouseMobilePhone, 

                      LTRIM(
                        RTRIM(
                          COALESCE(
                            NULLIF(cmPhoneWork5.TelephoneNumber + ' (His Work)', ' (His Work)'), 
                            NULLIF(cmPhoneWork6.TelephoneNumber + '', '')
                          )
                        )
                      ) AS StudentContactSpouseOccupPhone, 

                      cew.EmailID AS StudentContactSpouseDefaultEmail,
                      '' AS StudentContactSpouseRelation
            
            FROM      V_PupilBasicInformation p
            JOIN      VLPupilContact                pc ON pc.PupilID = p.PupilID AND pc.IsContactParental = 1
            LEFT JOIN PupilCurrentSchool            pcs ON pcs.PupilID = p.PupilID
            LEFT JOIN VLContactAddress              a ON a.ContactID = pc.ContactID
            LEFT JOIN VLContact                     c ON c.ContactID = pc.ContactID
            LEFT JOIN ContactMaster                 cm on cm.ContactID = pc.ContactID
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile    ON cmPhoneMobile.ContactID = pc.ContactID AND cmPhoneMobile.TelephoneType = 'M'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile2   ON cmPhoneMobile2.ContactID = pc.ContactID AND cmPhoneMobile2.TelephoneType = '003'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile3   ON cmPhoneMobile3.ContactID = pc.ContactID AND cmPhoneMobile3.TelephoneType = '004'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile4   ON cmPhoneMobile4.ContactID = pc.ContactID AND cmPhoneMobile4.TelephoneType = '005'
            LEFT JOIN ContactTelephoneInformation cmPhoneMobile5   ON cmPhoneMobile5.ContactID = pc.ContactID AND cmPhoneMobile5.TelephoneType = '006'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork    ON cmPhoneWork.ContactID = pc.ContactID AND cmPhoneWork.TelephoneType = 'W'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork2   ON cmPhoneWork2.ContactID = pc.ContactID AND cmPhoneWork2.TelephoneType = '001'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork3   ON cmPhoneWork3.ContactID = pc.ContactID AND cmPhoneWork3.TelephoneType = '002'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork4   ON cmPhoneWork4.ContactID = pc.ContactID AND cmPhoneWork4.TelephoneType = '007'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork5   ON cmPhoneWork5.ContactID = pc.ContactID AND cmPhoneWork5.TelephoneType = '008'
            LEFT JOIN ContactTelephoneInformation cmPhoneWork6   ON cmPhoneWork6.ContactID = pc.ContactID AND cmPhoneWork6.TelephoneType = '009'
            LEFT JOIN   VLContactTelephone            cp ON cp.ContactID = pc.ContactID 
            LEFT JOIN   VLContactEmail                ce ON ce.ContactID = pc.ContactID
            LEFT JOIN ContactEmailInformation ceh  ON ceh.contactid = pc.contactid and ceh.emailtype IN ('001','1') and ceh.primaryemail = 1
            LEFT JOIN ContactEmailInformation cew  ON cew.contactid = pc.contactid and cew.emailtype IN ('002','2') and cew.primaryemail = 1 
            
            WHERE p.PupilID = '{$externalUserId}'
            ORDER BY pc.Priority
        ";

        $contactList = $this->getDatabase()->getAll($query);

        // Hack to get rid of duplicate phone numbers

        foreach ($contactList as $k => $c) {
            if ($c["StudentContactSpouseOccupPhone"] == $c["StudentContactOccupPhone"]) {
                $contactList[$k]["StudentContactSpouseOccupPhone"] = '';
            }

            if ($c["StudentContactSpouseMobilePhone"] == $c["StudentContactMobilePhone"]) {
                $contactList[$k]["StudentContactSpouseMobilePhone"] = '';
            }
        }

        return $contactList;
    }

    /**
     * Retrieve the current Doublefirst semester- there is no such concept in their DB so back to the old 6 month trick
     *
     * @param null $externalUserId
     * @return int
     */
    public function getCurrentSemester($externalUserId = null)
    {
        return date('m') < 6 ? 1 : 2;
    }

    /**
     * Get current academic year
     *
     * @param null $externalUserId
     * @return array|bool
     */
    public function getCurrentYear($externalUserId = null)
    {
        return $this->getDatabase()->getOne("SELECT CurrentAcademicYear FROM ClusterMaster");
    }

    /**
     * Get a list of subjects for a given student
     *
     * @param User $user
     * @return array
     */
    public function getSubjectList(User $user)
    {
        $externalId = $user->getExternalId();

        if (empty($externalId)) {
            return [];
        }

        $year = $this->getCurrentYear();

        if ($user->getRole()->isStudent()) {
            // Get student subjects

            $sql =
                "
              SELECT DISTINCT     ssb.BlockName + ' - ' + ssbr.RoomName AS Room, 
                                  sm.Name AS name, 
                                  ygsd.SubjectDivisionName AS ClassCode, 
                                  '' AS StaffID, 
                                  '' AS StaffTitle, 
                                  '' AS StaffPreferredName, 
                                  '' AS StaffSurname 
                                  
              FROM                TimetableClassRoom tcr 
              LEFT JOIN           YearGroupSubjectDivision ygsd ON ygsd.DivisionID = tcr.DivisionID AND ygsd.SubjectID = tcr.SubjectID AND ygsd.YearGroup = tcr.YearGroup AND ygsd.AcademicYear = tcr.AcademicYear 
              LEFT JOIN           SchoolSiteBlock ssb ON ssb.SiteID = tcr.SiteID AND ssb.BlockID = tcr.BlockID 
              LEFT JOIN           SchoolSiteBlockRooms ssbr ON ssbr.SiteID = tcr.SiteID AND ssbr.BlockID = tcr.BlockID AND ssbr.RoomID = tcr.RoomID 
              LEFT JOIN           BatchMaster bm ON bm.YearGroup = tcr.YearGroup AND bm.SubjectID = tcr.SubjectID AND bm.DivisionID = tcr.DivisionID 
              JOIN                PupilSubjects ps ON ps.YearGroup = tcr.YearGroup AND ps.SubjectID = tcr.SubjectID AND ps.DivisionID = tcr.DivisionID AND ps.SchoolYear = tcr.AcademicYear	
              JOIN                TimetableMatrix tm ON tm.MatrixID = tcr.MatrixID AND tm.AcademicYear = tcr.AcademicYear AND tm.YearGroup = tcr.YearGroup 
              JOIN                TimetableMaster tn ON tn.TimetableName = tm.TimetableName AND tn.IsWorking = 1 
              JOIN                SubjectMaster sm ON sm.SubjectID = tcr.SubjectID 
              WHERE               tcr.AcademicYear = '{$year}' AND 
                                  ps.PupilID = '{$externalId}' 
            ";
        } else if ($user->getRole()->isStaff()) {
            // Get staff subjects

            $sql =
                "
                SELECT DISTINCT
                ygsd.SubjectDivisionName AS ClassCode,
                '' AS ClassCampus,
                sm.Name AS name,
                '' AS classType
                
                FROM TimetableClassRoom tcr
    
                LEFT JOIN YearGroupSubjectDivision ygsd ON
                ygsd.DivisionID = tcr.DivisionID AND
                ygsd.SubjectID = tcr.SubjectID AND
                ygsd.YearGroup = tcr.YearGroup AND
                ygsd.AcademicYear = tcr.AcademicYear
                
                LEFT JOIN BatchMaster bm ON
                bm.YearGroup = tcr.YearGroup AND
                bm.SubjectID = tcr.SubjectID AND
                bm.DivisionID = tcr.DivisionID
    
                JOIN TimetableMatrix tm ON
                tm.MatrixID = tcr.MatrixID AND
                tm.AcademicYear = tcr.AcademicYear AND
                tm.YearGroup = tcr.YearGroup
    
                JOIN TimetableClassStaff tcs ON
                tcs.YearGroup = tcr.YearGroup AND
                tcs.SubjectID = tcr.SubjectID AND
                tcs.DivisionID = tcr.DivisionID AND
                tcs.AcademicYear = tcr.AcademicYear AND
                tcs.MatrixID = tm.MatrixID

                JOIN SubjectMaster sm ON
                sm.SubjectID = tcr.SubjectID
    
                JOIN TimetableMaster tn ON
                tn.TimetableName = tm.TimetableName AND
                tn.IsWorking = 1
                
                WHERE tcr.AcademicYear = '{$year}'
                AND tcs.StaffID = '{$externalId}' ORDER BY name
            ";
        } else {
            return [];
        }

        $statement = $this->getDatabase()->query($sql);
        $subjectList = array();

        while ($subject = $statement->fetch()) {
            $subjectList[] = array(
                'code' => $subject['ClassCode'],
                'name' => $subject['name'],
                'classType' => $subject['classType'],
                'campusCode' => $subject['ClassCampus']
            );
        }

        if (is_array($subjectList)) {
            return $subjectList;
        } else {
            return [];
        }
    }

    /**
     * Get the students belonging to specified person
     *
     * @param $externalUserId
     * @return array|bool
     */
    public function getEnrolledStudents($externalUserId)
    {
        $query =
            "
            SELECT  pcs.PupilID as RelatedID 
            FROM    PupilCurrentSchool pcs 
            JOIN    VLPupilContact vpc ON vpc.PupilID = pcs.PupilID 
            WHERE   ReasonForLeaving IN ('', '00', '0000') AND 
                    vpc.RelationshipToPupil IN (
                      'Parents', 
                      'Mother', 
                      'Father', 
                      'Guardians', 
                      'Male Guardian', 
                      'Female Guardian', 
                      'Foster Parent', 
                      'Father Post', 
                      'Mother & Step Father', 
                      'Parents Post', 
                      'Step Parent', 
                      'Grandmother & Guardian', 
                      'Biological Father - no/little contact', 
                      'Biological Mother - no/little contact'
                    ) AND
                    vpc.ContactId = '{$externalUserId}'
        ";

        return $this->getDatabase()->getAll($query);
    }

    /**
     * Retrieve email address
     *
     * @param $id
     * @return array|bool
     */
    public function getEmail($id)
    {
        $query =
            "
          SELECT  TOP 1 EmailID 
          FROM    ContactEmailInformation 
          WHERE   ContactID = '{$id}' AND 
                  PrimaryEmail = 1;
        ";

        return $this->getDatabase()->getOne($query);
    }

    /**
     * Gets all Students' account info from Doublefirst
     *
     * @return array|bool
     */
    public function getStudentUserAccountInfo()
    {
        $query = "
            SELECT      PupilID           AS StudentID,
                        ''                AS NetworkLogin,
                        ''                AS StudentTitle,
                        PreferredForeName AS StudentPreferred,
                        Surname           AS StudentSurname,
                        LEFT(Gender, 1)   AS StudentGender,
                        ''                AS StudentEmail,
                        ''                AS StudentOccupEmail,
                        YearGroup         AS StudentYearLevel,
                        HouseID           AS StudentHouse,
                        0                 AS StudentBoarder,
                        ''                AS StudentBoardingHouse,
                        ''                AS StudentCampus
            FROM        V_PupilBasicInformation
                        
        ";
        return $this->getDatabase()->getAll($query);
    }

    /**
     * Gets all Parents' account info from Doublefirst
     *
     * @return array|bool
     */
    public function getParentUserAccountInfo()
    {
        $query =
            "
            SELECT DISTINCT
                ''                          AS StudentContactNetworkLogin,
                c.ContactID                 AS StudentContactID,
                c.Title                     AS StudentContactTitle,
                cm.Greeting                 AS StudentContactPreferred,
                c.Surname                   AS StudentContactSurname,
                ce.HomeEmailAddress         AS StudentContactDefaultEmail,
                ''                          AS StudentContactSpouseNetworkLogin,
                ''                          AS StudentContactSpouseID,
                ''                          AS StudentContactSpouseTitle,
                ''                          AS StudentContactSpousePreferred,
                ''                          AS StudentContactSpouseSurname,
                ''                          AS StudentContactSpouseDefaultEmail,
                pbi.YearGroup               AS StudentYearLevel,
                ''                          AS StudentCampus,
                LEFT(pbi.Gender, 1)         AS StudentGender
            FROM        VLPupilContact      pc
            LEFT JOIN   VLContact           c ON c.ContactID = pc.ContactID
            LEFT JOIN   ContactMaster       cm ON c.ContactID = cm.ContactID
            LEFT JOIN   VLContactEmail      ce ON ce.ContactID = pc.ContactID
            LEFT JOIN   VLContactTelephone  ct ON ct.ContactID = pc.ContactID 
            JOIN        V_PupilBasicInformation pbi ON pbi.PupilID = pc.PupilID
            WHERE       pc.IsContactParental = 1 
        ";

        return $this->getDatabase()->getAll($query);
    }

    /**
     * Gets all active Staff's account info from Doublefirst
     *
     * @return array|bool
     */
    public function getStaffUserAccountInfo()
    {
        $query =
            "
            SELECT      spd.StaffID,
                        spd.UserID            AS NetworkLogin,
                        1 AS ActiveFlag,
                        title.Description     AS StaffTitle,
                        CASE WHEN spd.PreferredForeName <> '' AND spd.PreferredForeName IS NOT NULL THEN spd.PreferredForeName ELSE spd.ForeName END AS StaffPreferredName,
                        spd.SurName           AS StaffSurname,
                        'o'                   AS DefaultEmailCode,
                        CASE WHEN seiw.EmailAddress IS NOT NULL THEN seiw.EmailAddress ELSE seiwb.EmailAddress END AS OccupEmail,
                        ''                    AS Email,
                        ''                    AS StaffCampus,
                        CASE WHEN IsStaffPartTime = 1 THEN 'Part Time' ELSE 'Full Time' END AS StaffCategoryType,
                        CASE WHEN IsTeacher = 1 THEN 'Teaching' ELSE 'Non Teaching' END AS StaffCategory,
                        ''                    AS StaffDepartment
            FROM        StaffPersonalDetails spd
            JOIN        CurrentStaff cs ON cs.StaffID = spd.StaffID
            LEFT JOIN   StaffTelephones stw ON stw.StaffID = spd.StaffID AND stw.TelephoneType = 'W'            
            LEFT JOIN   StaffEmailInformation seiw ON seiw.StaffID = spd.StaffID and seiw.PrimaryEmail = 1 AND seiw.EmailType = 'D321'
            LEFT JOIN   StaffEmailInformation seiwb ON seiwb.StaffID = spd.StaffID and seiwb.PrimaryEmail = 1 AND seiwb.EmailType = '2'                        
            LEFT JOIN   LookupDetails cat ON cat.LookupID = '1203' AND cat.LookupDetailsID = spd.StaffCategory
            LEFT JOIN   LookupDetails title ON title.LookupID = '3012' AND title.LookupDetailsID = spd.Title
            ORDER BY    spd.SurName, 
                        spd.ForeName
        ";

        return $this->getDatabase()->getAll($query);

    }

    /**
     * Contact list for staff directory
     * Exclude people who aren't active.
     * Exclude people who are on leave (category description for this is 'Staff on leave')
     * TODO: Make exclusion on category configurable, currently excluding
     *
     * @return mixed
     */
    public function getStaffContactListExtended()
    {
        $query =
            "
            SELECT      spd.StaffID,
                        spd.EmployeeID        AS SchoolStaffCode,
                        title.Description     AS StaffTitle,
                        CASE WHEN spd.PreferredForeName <> '' THEN spd.PreferredForeName ELSE spd.ForeName END AS StaffPreferredName,
                        spd.SurName           AS StaffSurname,
                        stw.TelephoneNumber   AS StaffOccupPhone,
                        CASE WHEN seiw.EmailAddress IS NOT NULL THEN seiw.EmailAddress ELSE seiwb.EmailAddress END AS StaffOccupEmail,
                        ''                    AS StaffExtension,
                        ''                    AS StaffCampusDescription,
                        CASE WHEN IsTeacher = 1 THEN 'Teaching' ELSE 'Non Teaching' END AS StaffCategory,
                        CASE WHEN IsStaffPartTime = 1 THEN 'Part Time' ELSE 'Full Time' END AS StaffCategoryType,
                        ''                    AS StaffDefaultMobilePhone,
                        ''                    AS StaffVoiceMail,
                        ''                    AS StaffDepartmentDescription,
                        cat.Description       AS JobPositionDescription,
                        ''                    AS StaffJobPositionStartDate,
                        ''                    AS StaffJobPositionEndDate
            FROM        StaffPersonalDetails spd
	        JOIN        CurrentStaff cs ON cs.StaffID = spd.StaffID
            LEFT JOIN   StaffTelephones stw ON stw.StaffID = spd.StaffID AND stw.TelephoneType = 'W'
            LEFT JOIN   StaffEmailInformation seiw ON seiw.StaffID = spd.StaffID and seiw.PrimaryEmail = 1 AND seiw.EmailType = 'D321'
            LEFT JOIN   StaffEmailInformation seiwb ON seiwb.StaffID = spd.StaffID and seiwb.PrimaryEmail = 1 AND seiwb.EmailType = '2'                        
            LEFT JOIN   LookupDetails cat ON cat.LookupID = '1203' AND cat.LookupDetailsID = spd.StaffCategory
            LEFT JOIN   LookupDetails title ON title.LookupID = '3012' AND title.LookupDetailsID = spd.Title
            ORDER BY    spd.SurName, 
                        spd.ForeName
        ";

        return $this->getDatabase()->getAll($query);
    }

    /**
     * List of campuses a staff member is involved in based on timetable. No such thing in Doublefirst.
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
        return [];
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
        if (in_array($this->helper->getBoardingStatuses(), $boarderValue)) {
            return "Boarder";
        } else {
            return "Day Student";
        }
    }

    /**
     * @param $houseID
     * @return bool
     */
    public function getHousemasterByHouseId($houseID)
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function getHousemasterEmails($externalUserIds)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentForms()
    {
        $query =
            "
            SELECT DISTINCT   Form AS Code,
                              Form AS Description
            FROM              V_PupilBasicInformation
            
            UNION
	        
	        SELECT            '' AS Code, 
	                          '' AS Description
	                          
	        ORDER BY	      Description 	    
        ";

        return $this->getDatabase()->getAll($query);
    }

    /* Getters/Setters
      */

    /**
     * Set database
     *
     * @param $database
     */
    private function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * Get database
     *
     * @return MSSQL
     */
    private function getDatabase()
    {
        return $this->database;
    }

    /**
     * @param Group $groupHandler
     */
    public function setGroup($groupHandler)
    {
        $this->groupHandler = $groupHandler;
    }

    /**
     * @return Group
     */
    public function getGroup()
    {
        return $this->groupHandler;
    }
}
