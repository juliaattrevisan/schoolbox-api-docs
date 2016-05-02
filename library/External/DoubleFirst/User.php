<?php

/**
 * DoubleFirst External User Object
 *
 * @author	  Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link		http://www.alaress.com.au/
 *
 *         ,--.--._
 * ------" _, \___)
 *    2    / _/____)
 *    X    \//(____)
 * ------\     (__)
 *        `-----"
 *
 * VLContactTelephone
 * --------------------------------------------------------------------------------------------------------------------
 * ContactID
 * HomeTelephoneNumber
 * WorkTelephoneNumber
 * Fax
 * Mobile
 *
 * VLContactAddress
 * --------------------------------------------------------------------------------------------------------------------
 * ContactID
 * AddressId
 * SubDwelling
 * Dwelling
 * County
 * PostCode
 * Country
 *
 * VLContactDetails
 * --------------------------------------------------------------------------------------------------------------------
 * ContactID
 * IsJointParent
 *
 * VLContactEmail
 * --------------------------------------------------------------------------------------------------------------------
 * ContactId
 * HomeEmailAddress
 * WorkEmailAddress
 *
 * Unfortunately DoubleFirst doesn't seem to believe in database normalization- thus there is a single giant lookup
 * table (Sigh). Below is my deciphering of this table (keep in mind this is most likely going to be school specific)
 * so not sure how portable this connector is going to be (DoubleSigh).
 *
 * LookupID 1221 (Email address type)
 *
 * LookupDetailsID      Description                     Occurrences
 *
 * 0                    Blank
 * 001                  Email (hers)                    (873 records)
 * 002                  Email (his)                     (637 records)
 * 1                    Home                            (128 records)
 * 2                    Work                            (10 records)
 * 3                    Email (students)                (1 record)
 * D321                 Staff Email                     (2 records)
 *
 * LookupID 2904 (Telephone type)
 *
 * LookupDetailsID      Description
 *
 * 0                    Blank
 * 001                  Business
 * 002                  Business 2
 * 003                  Mobile (hers)
 * 004                  Mobile (hers) 2
 * 005                  Mobile (his)
 * 006                  Mobile 2
 * 007                  Work (hers)
 * 008                  Work (his)
 * 009                  Work 2
 * 010                  SMS (his)
 * 011                  SMS (hers)
 * 012                  SMS
 * F                    Fax
 * H                    Home
 * M                    Mobile
 * W                    Work
 *
 * Lookup ID 3012 (Name Prefix)
 *
 * LookupDetailsID      Description
 *
 * 00                   Blank
 * 01                   Mr
 * 0100                 Mr & Mrs
 * 0101                 Dr & Mrs
 * 0102                 Rev & Mrs
 * 0103                 Mr & Ms
 * 0104                 Mr & Miss
 * 02                   Mrs
 * 03                   Ms
 * 04                   Miss
 * 05                   Rev
 * 06                   Dr
 * 07                   Prof
 * 08                   Mr & Dr
 * Oth                  Other
 *
 * Lookup ID 2102 (Address Type)
 *
 * 000
 * C                    Current
 * P                    Previous
 *
 * Lookup ID 2108 (Country)
 *
 * Lookup ID 2100 (Relationship Type)
 *
 * 000
 * 001	                Son
 * 002	                Aunt - EM Contact 2
 * 003	                Aunt - EM Contact 3
 * 004	                Aunt & Uncle - EM Contact 1
 * 005	                Aunt & Uncle - EM Contact 2
 * 006	                Aunt - EM Contact 1
 * 007	                Brother - EM Contact 1
 * 008	                Brother - EM Contact 2
 * 009	                Cousin - EM Contact 1
 * 010	                Family friend - EM Contact 1
 * 011	                Family friend - EM Contact 2
 * 012	                Family friend - EM Contact 3
 * 013	                Father - EM Contact 1
 * 014	                Father - EM Contact 1 Post
 * 015	                Father - EM Contact 2
 * 016	                Father - EM Contact 2 Post
 * 017	                Father Post
 * 018	                Godfather - EM Contact 2
 * 019	                Godmother - EM Contact 1
 * 020	                Godmother - EM Contact 2
 * 021	                Grandfather - EM Contact 1
 * 022	                Grandfather - EM Contact 2
 * 023	                Grandmother - EM Contact 1
 * 024	                Grandmother - EM Contact 2
 * 025	                Grandmother - EM Contact 3
 * 026	                Grandmother & Guardian
 * 027	                Grandmother & Guardian Post
 * 028	                Grandparents - EM Contact 1
 * 029	                Grandparents - EM Contact 2
 * 030	                Guardian (separated)
 * 031	                Guardians
 * 032	                Guardians Post
 * 033	                Him/Herself
 * 034	                Mother - EM Contact 1
 * 035	                Mother - EM Contact 1 Post
 * 036	                Mother   Post
 * 037	                Mother  Post
 * 038	                Mother Post
 * 039	                Neighbour - EM Contact 1
 * 040	                Neighbour - EM Contact 2
 * 041	                Paediatrician
 * 042	                Parents Post
 * 043	                Pastor/Church Minister - EM 2
 * 044	                Relative - EM Contact 1
 * 045	                Sister - EM Contact 1
 * 046	                Sister - EM Contact 2
 * 047	                Sister in Law - EM Contact 2
 * 048	                Uncle - EM Contact 1
 * 049	                Uncle - EM Contact 2
 * 050	                Unknown - EM Contact 1
 * 051	                Unknown - EM Contact 2
 * 052	                Father - EM Contact 3
 * 053	                Grandparents - EM Contact 3
 * 054	                Carer - EM Contact 2
 * 055	                Uncle - EM Contact 3
 * 056	                Aunt - EM Contact 4
 * 057	                Cousin - EM Contact 2
 * 058	                Family friend - EM Contact 4
 * 059	                Babysitter - EM Contact 1
 * 060	                Uncle - EM Contact 4
 * 061	                Step Dad - EM Contact 1
 * 062	                Mother & Step Father
 * 063	                Mentor - Yr 12
 * 064	                Grandparents - EM Contact 4
 * 066	                Daughter
 * 067	                Grandfather EM Contact 3
 * 068	                Grandmother - EM Contact 4
 * 069	                Cousin - EM Contact 3
 * 65	                Wife
 * BF	                Boyfriend
 * Bfa	                Biological Father - no/little contact
 * Bmo	                Biological Mother - no/little contact
 * Bro3	                Brother - EM Contact 4
 * Car	                Carer
 * Doc	                Doctor
 * Fam	                Other Family Member
 * FG	                Female Guardian
 * Fos	                Foster Parent
 * GPME	                Mentor/Grandparent EM 1
 * hus	                Husband
 * MEAU	                Mentor/Aunt EM Contact 1
 * meff	                Mentor/Family Friend EM Contact
 * MG	                Male Guardian
 * newp	                Newsletter - postal
 * news	                Newsletter - email
 * Oth	                Other Contact
 * Paf	                Father
 * Pam	                Mother
 * PARE	                Parents
 * Rel	                Other Relative
 * Rlg	                Religious/Spiritual Contact
 * Sis	                Sister - EM Contact 3
 * Sis2	                Sister - EM Contact 4
 * STE	                Step Mother  - EM Contact 1
 * STF	                Step Father - Em Contact 3
 * Stp	                Step Parent
 * Swr	                Social Worker
 *
 */

use Alaress\DebugBar\Instance as DebugBar;

class External_Doublefirst_User implements External_UserInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /** @var Group */
    private $groupHandler;

    /** Constructor */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());

        if (DebugBar::isEnabled())
        {
            $this->getDatabase()->installCollector();

            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();

            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }

        $this->setGroup(new Group());
    }

    /**
     * Update a particular custom attribute for a specific user
     *
     * @param $synergyId
     * @param $attributeId
     * @param $newValue
     */
    public function updateCustomAttribute($synergyId, $attributeId, $newValue)
    {
    }

    /**
     * Retrieves the user's Doublefirst image
     *
     * @param Integer $externalUserId
     *
     * @return Bool|String
     */
    public function getPortrait($externalUserId)
    {
        if (!is_numeric($externalUserId)) {
            return false;
        }
        $query = "
            SELECT vPhotos.LastUpdated
            FROM PupilPhoto
            WHERE PupilID = '" . $this->getDatabase()->quote($externalUserId) . "'
        ";

        if ($photo = $this->getDatabase()->getRow($query))
        {
            $photoPath = realpath(Config::get('portrait_dir')) . '/' . $externalUserId . '.jpg';

            //TODO: delete photos on rollover
            /* If the cached file doesn't exist, or the synergetic image is more recent than our
                      * last known cached version, regenerate cached version.
                      *
                      * Separate the queries out, fetching the photo data only if it's newer than the cached
                      * version, or if the cache version doesn't already exist.
                      */
            $cacheTime    = @filemtime($photoPath);
            $modifiedTime = strtotime($photo['ModifiedDate']);

            if (!file_exists($photoPath) || ($cacheTime < $modifiedTime))
            {
                $query = "
                    SELECT Photo
                    FROM PupilPhoto
                    WHERE PupilID = '" . $this->getDatabase()->quote($externalUserId) . "'
                ";
                $photo = $this->getDatabase()->getRow($query);

                $photoHandle = fopen($photoPath, "w");
                fwrite($photoHandle, $photo['Photo']);
                fclose($photoHandle);
            }

            return $photoPath;
        }

        return false;
    }

    /**
     * Gets the contact info of the person who is a Student's Contact. The ID
     * passed is the Doublefirst of the Contact, not the Student.
     *
     * @param $externalId
     *
     * @return mixed
     */
    public function getParent($externalId)
    {
        $query  = "
            SELECT
                luTitle.Description as Title,
                FirstName as Preferred,
                LastName as Surname,
                ceiHers.EmailID as Email,
                ceiHersSecondary.EmailID as OccupEmail,
                '' as DefaultEmailCode,
                ctiHome.TelephoneNumber AS HomePhone,
                0 as SilentPhoneFlag,
                0 as SilentMobilePhoneFlag,
                ctiMobile.TelephoneNumber as MobilePhone,
                1 as DirectoryIncludeFlag,
                
                '' as SpouseID,
                '' as SpouseTitle,
                '' as SpousePreferred,
                '' as SpouseSurname,
                ceiHis.EmailID as SpouseEmail,
                ceiHisSecondary.EmailID as SpouseOccupEmail,
                '' as SpouseDefaultEmailCode,
                '' as SpouseMobilePhone,
                '' as SpouseDirectoryIncludeFlag
            
            FROM        ContactMaster                 cm
            
            LEFT JOIN   LookupDetails                 luTitle ON luTitle.LookupID = 3012 AND luTitle.LookupDetailsID = cm.Prefix
            
            LEFT JOIN   ContactTelephoneInformation   ctiHome ON ctiHome.ContactID = cm.ContactID and ctiHome.TelephoneType = 'H' 
            LEFT JOIN   ContactTelephoneInformation   ctiMobile ON ctiMobile.ContactID = cm.ContactID and ctiMobile.TelephoneType = 'M' 
            LEFT JOIN   ContactTelephoneInformation   ctiWork ON ctiWork.ContactID = cm.ContactID and ctiWork.TelephoneType = 'W' 
            
            LEFT JOIN   ContactEmailInformation       ceiHers ON ceiHers.ContactID = cm.ContactID and ceiHers.EmailType = '001' and ceiHers.PrimaryEmail = 1
            LEFT JOIN   ContactEmailInformation       ceiHersSecondary ON ceiHersSecondary.ContactID = cm.ContactID and ceiHersSecondary.EmailType = '001' and ceiHersSecondary.PrimaryEmail = 0
            
            LEFT JOIN   ContactEmailInformation       ceiHis ON ceiHis.ContactID = cm.ContactID and ceiHis.EmailType = '002' and ceiHis.PrimaryEmail = 1
            LEFT JOIN   ContactEmailInformation       ceiHisSecondary ON ceiHisSecondary.ContactID = cm.ContactID and ceiHisSecondary.EmailType = '002' and ceiHisSecondary.PrimaryEmail = 0
            
            WHERE cm.ContactID = '" . $this->database->quote($externalId) . "'";

        $parent = $this->getDatabase()->getRow($query);

        if ($parent['DefaultEmailCode'] == 'O') {
            $parent['Email'] = $parent['OccupEmail'];
        }

        if ($parent['SpouseDefaultEmailCode'] == 'O') {
            $parent['SpouseEmail'] = $parent['SpouseOccupEmail'];
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
     * @param $synID
     *
     * @return mixed
     */
    public function getParentSync($externalId)
    {
        if (is_array($externalId))
            $assignation = " IN ('" . implode("','", array_map([$this->database, 'quote'], $externalId)) . "')";
        else
            $assignation = " =  '" . $this->database->quote($externalId) . "'";

        $query  =
        "
            SELECT
                Prefix as Title,
                FirstName as Preferred,
                LastName as Surname,
                ceiHers.EmailID as Email,
                '' as OccupEmail,
                '' as DefaultEmailCode,
                ctiHome.TelephoneNumber AS HomePhone,
                0 as SilentPhoneFlag,
                0 as SilentMobilePhoneFlag,
                ctiMobile.TelephoneNumber as MobilePhone,
                1 as DirectoryIncludeFlag,
                
                '' as SpouseID,
                '' as SpouseTitle,
                '' as SpousePreferred,
                '' as SpouseSurname,
                ceiHis.EmailID as SpouseEmail,
                '' as SpouseOccupEmail,
                '' as SpouseDefaultEmailCode,
                '' as SpouseMobilePhone,
                '' as SpouseDirectoryIncludeFlag
            
            FROM        ContactMaster                 cm
            LEFT JOIN   ContactTelephoneInformation   ctiHome ON ctiHome.ContactID = cm.ContactID and ctiHome.TelephoneType = 'H' 
            LEFT JOIN   ContactTelephoneInformation   ctiMobile ON ctiMobile.ContactID = cm.ContactID and ctiMobile.TelephoneType = 'M' 
            LEFT JOIN   ContactTelephoneInformation   ctiWork ON ctiWork.ContactID = cm.ContactID and ctiWork.TelephoneType = 'W' 
            LEFT JOIN   ContactEmailInformation       ceiHers ON ceiHers.ContactID = cm.ContactID and ceiHers.EmailType = '001'
            LEFT JOIN   ContactEmailInformation       ceiHis ON ceiHis.ContactID = cm.ContactID and ceiHis.EmailType = '002'

            WHERE cm.ContactID {$assignation}
        ";

        if (is_array($externalId))
            return $this->getDatabase()->getAll($query);

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
        $id_list   = implode(',', array_map('intval', $synergyIds));

        if (empty($id_list))
            return array();

        $query = "
            SELECT
                apc.ContactID as RelatedID,
                CASE WHEN ceiHome.PrimaryEmail = 1 THEN 'P' ELSE 'O' END as UseEmail,             
                ceiWork.EmailID as OccupEmail,
                ceiHome.EmailID as Email,
                CASE WHEN ceiHome.PrimaryEmail = 1 THEN ceiHome.EmailID ELSE ceiWork.EmailID END as DefaultEmail
            FROM AllPupilContacts apc                
            LEFT JOIN ContactEmailInformation ceiHome ON ceiHome.ContactID = apc.ContactID AND ceiHome.EmailType = '1'
            LEFT JOIN ContactEmailInformation ceiWork ON ceiWork.ContactID = apc.ContactID AND ceiWork.EmailType = '2'
            WHERE apc.PupilID IN ($id_list)";

        $this->getDatabase()->query($query);

        while ($row = $this->getDatabase()->getNextArray()) {
            switch (strtoupper($row['UseEmail'])) {
                case 'O': //Use occupation email address
                    $emailList[$row['RelatedID']] = $row['OccupEmail'];
                    break;
                case 'P': //Use personal email address
                    $emailList[$row['RelatedID']] = $row['Email'];
                    break;
                case 'D': //Use default email address
                    if(trim($row['DefaultEmail']) != '') {
                        $defaultEmail = explode(';',$row['DefaultEmail']);
                        foreach($defaultEmail as $key=>$email){
                            $emailList[$row['RelatedID'].'-'.$key] = $email;
                        }
                    }
                    break;
                default:
                    break;
            }
        }

        return $emailList;
    }

    /**
     * Get a students info from Doublefirst
     *
     * @param  $synID
     *
     * @return array|bool
     */
    public function getStudent($externalId)
    {
        // We will retrieve a list of all possible primary emergency contacts- they all seem to contain 'EM Contact 1' in the description

        $ecQuery = "
            SELECT LookupDetailsID 
            FROM LookupDetails                
            WHERE LookupID = 2100 AND Description LIKE '%EM Contact 1%';
        ";

        $ecRelationship = $this->getDatabase()->getAll($ecQuery);
        $assignation = implode("','", array_map([$this->database, 'quote'], $ecRelationship));

        // The query is going to return multiple rows because there is potentially more than one emergency contact- so we are just taking the first
        // row. This is a total hack.

        $query = "
            SELECT TOP 1
            
                p.PupilID as StudentID,
                ForeName as StudentGiven1,
                Surname as StudentSurname,
                BirthDate as StudentBirthDate,
                
                '' as StudentTutorName,
                '' as StudentHeadOfHouse,
                '' as HeadOfHouseID,
                '' as StudentFormStaffName,
                '' as StudentTutorDescription,
                '' as TeacherFormID,
                FormID as StudentForm,
                '' as TeacherTutorID,
                '' as StudentTutor,
                Form as FormDescription,
                House as StudentHouseDescription,
                HouseID as StudentHouse,
                YearGroup as StudentYearLevel,
                '' as StudentLockerBook,
                ecm.ContactName as StudentEmergencyName,
                ecmPhoneMobile.TelephoneNumber as StudentEmergencyPhone,
                ecmPhoneHome.TelephoneNumber as StudentEmergencyPhoneAlt,
                m.Comments as StudentMedicalAlert,
                '' as StudentSpecialNeedsFlag,
                '' as StudentStudiesCode,
                UrgentNotes as AlertMessage,
                '' as StudentBoarder
            
            FROM V_PupilBasicInformation p
            LEFT JOIN PupilMedicalInformation m ON m.PupilID = p.PupilID
            LEFT JOIN PupilContacts ec ON ec.RelationshipToPupil IN ({$assignation})
            LEFT JOIN ContactMaster ecm on ecm.ContactID = ec.ContactID
            LEFT JOIN ContactTelephoneInformation ecmPhoneMobile ON ecmPhoneMobile.ContactID = ecm.ContactID AND TelephoneType = 'M'
            LEFT JOIN ContactTelephoneInformation ecmPhoneHome ON ecmPhoneHome.ContactID = ecm.ContactID AND TelephoneType = 'H'
            
            WHERE p.PupilID = '".$this->database->quote($externalId)."';
        ";

        $student = [];

        foreach ($this->getDatabase()->getAll($query) ?: array() as $row) {
            // fill in student record
            $student = $row;

            if ($student['StudentBirthDate']
                && preg_match('/([\w]+)\s+([\d]+)\s+([\d]+)/', $student['StudentBirthDate'], $matches)
            ) {
                $student['StudentBirthDate'] = strtotime($matches[2] . ' ' . $matches[1] . ' ' . $matches[3]);
            } else {
                $student['StudentBirthDate'] = false;
            }
        }

        return $student;
    }

    public function getStaff($externalId)
    {
        $query = "
            SELECT
                s.EmployeeID as SchoolStaffCode,
                '' AS StaffBuilding,
                '' as StaffRoom,
                '' as StaffExtension,
                tMobile.TelephoneNumber as StaffMobilePhone,
                '' as StaffCampusDescription,
                '' as StaffCategoryDescription,
                '' as StaffDepartment,
                '' as StaffVoiceMail
             FROM Staff s
             LEFT JOIN StaffTelephones tMobile on tMobile.StaffID = s.StaffID AND TelephoneType = 'M' 

             WHERE vStaff.StaffID = '" . $this->database->quote($externalId) . "'";

        $staff = $this->getDatabase()->getRow($query);
        return $staff;
    }

    /**
     * Gets a list of Contacts for all Students in a particular year level. The
     * year level is stored in Doublefirst.
     *
     * Used in reporting.
     *
     * Note that there is no StudentContactHomeCountryDesc in the Doublefirst views
     * so I changed it to use CountryCode instead.
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
            SELECT
                p.PupilID as StudentID,
                p.ForeName as StudentGiven1,
                p.Surname as StudentSurname,
                p.YearGroup as StudentYearLevel,
                p.House as StudentHouse,
                0 as StudentBoarder,
                p.Form as StudentForm,
                
                cm.ContactID as StudentContactID,
                luTitle.Description as StudentContactTitle,
                cm.FirstName as StudentContactPreferred,
                cm.ContactName as StudentContactLegalFullName,
                cm.LastName StudentContactSurname,
                '' as StudentContactDefaultEmail,
                1 as ValidAddressFlag,

                a.SubDwelling as StudentContactAddress1,
                a.Street as StudentContactAddress2,
                '' as StudentContactAddress3,
                a.Dwelling as StudentContactSuburb,
                a.Postcode as StudentContactPostCode,
                a.Country as StudentContactState,
                luCountry.Description as StudentContactCountryCode,
                
                a.SubDwelling as StudentContactHomeAddress1,
                a.Street as StudentContactHomeAddress2,
                '' as StudentContactHomeAddress3,
                a.Dwelling as StudentContactHomeSuburb,
                a.Postcode as StudentContactHomePostCode,
                a.County as StudentContactHomeState,
                luCountry.Description AS StudentContactHomeCountryCode,
                
                ctiHome.TelephoneNumber as StudentContactPhoneActual,
                ctiMobile.TelephoneNumber as StudentContactMobilePhone,
                ctiWork.TelephoneNumber as StudentContactOccupPhone,
                
                '' as StudentContactSpouseID,
                '' as StudentContactSpouseTitle,
                '' as StudentContactSpousePreferred,
                '' as StudentContactSpouseLegalFullName,
                '' as StudentContactSpouseSurname,
                '' as StudentContactSpouseMobilePhone,
                '' as StudentContactSpouseOccupPhone,
                '' as StudentContactSpouseDefaultEmail,
                
                'SC1' as StudentContactType,
                0 as StudentContactSilentPhoneFlag,
                0 as StudentContactSilentMobilePhoneFlag,
                1 as PrimaryDirectoryIncludeFlag,
                1 as SpouseDirectoryIncludeFlag,
                0 as SilentHomeAddressFlag,
                0 as StudentParentsSeparatedFlag
            
            FROM V_PupilBasicInformation p
                            
            JOIN        PupilContacts                 pc ON pc.PupilID = p.PupilID AND pc.IsContactParental = 1
            LEFT JOIN   ContactMaster                 cm ON cm.ContactID = pc.ContactID 
            LEFT JOIN   AddressMaster                 a ON a.AddressID = cm.AddressID AND a.AddressType = 'C'
            LEFT JOIN   LookupDetails                 luCountry ON luCountry.LookupID = 2108 AND luCountry.LookupDetailsID = a.Country
            LEFT JOIN   LookupDetails                 luTitle ON luTitle.LookupID = 3012 AND luTitle.LookupDetailsID = cm.Prefix
            LEFT JOIN   ContactTelephoneInformation   ctiHome ON ctiHome.ContactID = cm.ContactID and ctiHome.TelephoneType = 'H' 
            LEFT JOIN   ContactTelephoneInformation   ctiMobile ON ctiMobile.ContactID = cm.ContactID and ctiMobile.TelephoneType = 'M' 
            LEFT JOIN   ContactTelephoneInformation   ctiWork ON ctiWork.ContactID = cm.ContactID and ctiWork.TelephoneType = 'W' 
            LEFT JOIN   ContactEmailInformation       ceiHers ON ceiHers.ContactID = cm.ContactID and ceiHers.EmailType = '001' and ceiHers.PrimaryEmail = 1
            LEFT JOIN   ContactEmailInformation       ceiHersSecondary ON ceiHersSecondary.ContactID = cm.ContactID and ceiHersSecondary.EmailType = '001' and ceiHersSecondary.PrimaryEmail = 0
            LEFT JOIN   ContactEmailInformation       ceiHis ON ceiHis.ContactID = cm.ContactID and ceiHis.EmailType = '002' and ceiHis.PrimaryEmail = 1
            LEFT JOIN   ContactEmailInformation       ceiHisSecondary ON ceiHisSecondary.ContactID = cm.ContactID and ceiHisSecondary.EmailType = '002' and ceiHisSecondary.PrimaryEmail = 0
                                        
        ";

//        $query = "
//            SELECT
//                StudentID,
//                StudentGiven1,
//                StudentSurname,
//                StudentYearLevel,
//                House.Description AS StudentHouse,
//                StudentBoarder,
//                StudentForm,
//                StudentContactID,
//                StudentContactTitle,
//                StudentContactPreferred,
//                StudentContactLegalFullName,
//                StudentContactSurname,
//                StudentContactDefaultEmail,
//                vStudentContactAllAddress.ValidAddressFlag,
//                StudentContactAddress1,
//                StudentContactAddress2,
//                StudentContactAddress3,
//                StudentContactSuburb,
//                StudentContactPostCode,
//                StudentContactState,
//                StudentCountry.Description AS StudentContactCountryCode,
//                StudentContactHomeAddress1,
//                StudentContactHomeAddress2,
//                StudentContactHomeAddress3,
//                StudentContactHomeSuburb,
//                StudentContactHomePostCode,
//                StudentContactHomeState,
//                StudentCountry.Description AS StudentContactHomeCountryCode,
//                StudentContactPhoneActual,
//                StudentContactMobilePhone,
//                StudentContactOccupPhone,
//                StudentContactSpouseID,
//                StudentContactSpouseTitle,
//                StudentContactSpousePreferred,
//                StudentContactSpouseLegalFullName,
//                StudentContactSpouseSurname,
//                StudentContactSpouseMobilePhone,
//                StudentContactSpouseOccupPhone,
//                StudentContactSpouseDefaultEmail,
//                StudentContactType,
//                StudentContactSilentPhoneFlag,
//                Contact.SilentMobilePhoneFlag as StudentContactSilentMobilePhoneFlag,
//                Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
//                Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
//                ContactAddress.SilentHomeAddressFlag SilentHomeAddressFlag,
//                StudentParentsSeparatedFlag
//            FROM SynergyOne.dbo.vStudentContactAllAddress
//            LEFT JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
//            LEFT JOIN SynergyOne.dbo.Addresses ContactAddress ON Contact.AddressID = ContactAddress.AddressID
//            LEFT JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
//            LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus
//                AND StudentHouse = House.Code
//            LEFT JOIN SynergyOne.dbo.luYearLevel Year ON StudentYearLevel = Year.Code
//                AND StudentCampus = Year.Campus
//            LEFT JOIN SynergyOne.dbo.luCountry StudentCountry
//                ON StudentContactCountryCode = StudentCountry.Code
//            LEFT JOIN SynergyOne.dbo.luCountry StudentHomeCountry
//                ON StudentContactHomeCountryCode = StudentHomeCountry.Code
//            WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
//            AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
//            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'"
//        ;
//        /* Search for a particular year level
//         */
//        if (strlen($yearlevel)) {
//            if (is_numeric($yearlevel)) {
//                // forcibly cast to int in case it's a something non-int that's still numeric
//                $yearlevel = (int)$yearlevel;
//                $query .= "
//                AND (StudentYearLevel = '{$yearlevel}' OR Year.Description = '{$yearlevel}')";
//            } else {
//                // if year is NOT numeric, don't attempt to compare StudentYearLevel (will bug out)
//                $query .= "
//                AND Year.Description = '{$this->getDatabase()->quote($yearlevel)}'";
//            }
//        }
//        /* Search for a student name
//         */
//        if (strlen($name)) {
//            $name = $this->getDatabase()->quote($name);
//            $query .= "
//                AND (
//                    (StudentSurname LIKE '%{$name}%')
//                    OR
//                    (StudentGiven1 LIKE '%{$name}%')
//                )
//            ";
//        }
//        /* Search for a particular boarding status
//         *
//         * In Doublefirst, assume the following:
//         * B: Boarder
//         * D: Day student
//         * HS: Home stay
//         */
//        if ($boardingStatus == '') {
//            /* No restrictions on result set
//             */
//        } elseif ($boardingStatus == 'yes') {
//            $query .= "
//                AND StudentBoarder = 'B'
//            ";
//        } else {
//            $query .= "
//                AND StudentBoarder != 'B'
//            ";
//        }
//        /* Search for a particular form
//         */
//        if ($form) {
//            $form = $this->getDatabase()->quote($form);
//            $query .= "
//                AND StudentForm = '{$form}'
//            ";
//        }
//        $query .= "
//            ORDER BY StudentSurname, StudentID, StudentContactType
//        ";
//
//        $contactList = $this->getDatabase()->getAll($query) ?: array();
//        foreach ($contactList as &$row) {
//            if ($row['ValidAddressFlag'] == 0 || $row['SilentHomeAddressFlag'] == 1) {
//                $row['StudentContactAddress1']        = '';
//                $row['StudentContactAddress2']        = '';
//                $row['StudentContactAddress3']        = '';
//                $row['StudentContactSuburb']          = '';
//                $row['StudentContactPostCode']        = '';
//                $row['StudentContactState']           = '';
//                $row['StudentContactCountryCode']     = '';
//                $row['StudentContactHomeAddress1']    = '';
//                $row['StudentContactHomeAddress2']    = '';
//                $row['StudentContactHomeAddress3']    = '';
//                $row['StudentContactHomeSuburb']      = '';
//                $row['StudentContactHomePostCode']    = '';
//                $row['StudentContactHomeState']       = '';
//                $row['StudentContactHomeCountryCode'] = '';
//            }
//            $row['StudentContactHomeCountryCode']
//                = $row['StudentContactHomeCountryCode'] == 'Not Selected' ? '' : $row['StudentContactHomeCountryCode'];
//            $row['StudentContactCountryCode']
//                = $row['StudentContactCountryCode'] == 'Not Selected' ? '' : $row['StudentContactCountryCode'];
//        }
//        return $contactList;
    }

    /**
     * Gets a list of Contacts for all Student Boarders.
     *
     * Used in reporting.
     *
     * @return mixed
     */
    public function getStudentBoarderContactListExtended()
    {
//        $query = "
//            SELECT
//                StudentID,
//                StudentGiven1,
//                StudentSurname,
//                StudentYearLevel,
//                House.Description AS StudentHouse,
//                StudentContactID,
//                StudentContactTitle,
//                StudentContactPreferred,
//                StudentContactSurname,
//                StudentContactDefaultEmail,
//                StudentContactAddress1,
//                StudentContactAddress2,
//                StudentContactAddress3,
//                StudentContactSuburb,
//                StudentContactPostCode,
//                StudentContactState,
//                StudentContactCountryDesc,
//                StudentContactPhoneActual,
//                StudentContactMobilePhone,
//                StudentContactOccupPhone,
//                StudentContactSpouseID,
//                StudentContactSpouseTitle,
//                StudentContactSpousePreferred,
//                StudentContactSpouseSurname,
//                StudentContactSpouseMobilePhone,
//                StudentContactSpouseOccupPhone,
//                StudentContactSpouseDefaultEmail,
//                StudentContactType,
//                StudentContactSilentPhoneFlag,
//                Contact.SilentMobilePhoneFlag AS StudentContactSilentMobilePhoneFlag,
//                Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
//                Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
//                StudentParentsSeparatedFlag
//            FROM SynergyOne.dbo.vStudentContactAllAddress
//            LEFT JOIN SynergyOne.dbo.Community Contact ON StudentContactID = Contact.ID
//            LEFT JOIN SynergyOne.dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
//            LEFT JOIN SynergyOne.dbo.luHouse House ON StudentCampus = House.Campus AND StudentHouse = House.Code
//            WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
//            AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
//            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
//            AND StudentBoarder = 'B'
//            ORDER BY StudentSurname, StudentID, StudentContactType";
//        return $this->getDatabase()->getAll($query);
    }

    /**
     * Get a list of student contacts (parents) and their contact information
     *
     * @param $externalUserId
     *
     * @return mixed
     */
    public function getStudentContactList($externalUserId)
    {
//        $query       = "
//            SELECT
//                StudentContactID,
//                StudentContactTitle,
//                StudentContactPreferred,
//                StudentContactSurname,
//                StudentContactDefaultEmail,
//                ValidAddressFlag,
//                StudentContactAddress1,
//                StudentContactAddress2,
//                StudentContactAddress3,
//                StudentContactSuburb,
//                StudentContactPostCode,
//                StudentContactState,
//                StudentContactPhoneActual,
//                StudentContactMobilePhone,
//                StudentContactOccupPhone,
//                StudentContactRelation,
//                StudentContactSpouseID,
//                StudentContactSpouseTitle,
//                StudentContactSpousePreferred,
//                StudentContactSpouseSurname,
//                StudentContactSpouseMobilePhone,
//                StudentContactSpouseOccupPhone,
//                StudentContactSpouseDefaultEmail,
//                StudentContactType,StudentContactSpouseRelation
//            FROM
//                SynergyOne.dbo.vStudentContactAllAddress
//            WHERE
//                StudentID = '$externalUserId'
//                AND (StudentContactType = 'SC1' OR StudentContactType = 'SC2' OR StudentContactType = 'SC3')
//                AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
//                AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'"
//        ;
//        $contactList = $this->getDatabase()->getAll($query) ?: array();
//        foreach ($contactList as &$row) {
//            if ($row['ValidAddressFlag'] == 0) {
//                $row['StudentContactAddress1'] = '';
//                $row['StudentContactAddress2'] = '';
//                $row['StudentContactAddress3'] = '';
//                $row['StudentContactSuburb']   = '';
//                $row['StudentContactPostCode'] = '';
//                $row['StudentContactState']    = '';
//            }
//        }
//        return $contactList;
    }

    protected function findFileYearAndSemester($externalUserId = null)
    {
//        if ($externalUserId) {
//            $result = $this->getDatabase()->executeProcedure(
//                'spsConfigUserDefaultYearSemester',
//                array(
//                    array('@ID', $externalUserId, SQLINT4, false, false, -1),
//                )
//            );
//            if ($result['success']
//                && isset($result['result'][0]['UserFileSemester'])
//                && $result['result'][0]['UserFileSemester']
//                && isset($result['result'][0]['UserFileYear'])
//                && $result['result'][0]['UserFileSemester']
//            ) {
//                return array(
//                    'year'     => $result['result'][0]['UserFileYear'],
//                    'semester' => $result['result'][0]['UserFileSemester'],
//                );
//            }
//        }
//        // Get current FileSemester and FileYear
//        $query = "SELECT FileSemester, FileYear FROM FileSemesters WHERE SystemCurrentFlag = 1";
//        if ($semester = $this->getDatabase()->getRow($query)) {
//            return array(
//                'year'     => $semester['FileYear'],
//                'semester' => $semester['FileSemester'],
//            );
//        } else {
//            return array();
//        }
    }

    public function getCurrentSemester($externalUserId = null)
    {
//        $fileYearSemester = $this->findFileYearAndSemester($externalUserId);
//        if (!empty($fileYearSemester)) {
//            return $fileYearSemester['semester'];
//        }
//        return null;
    }

    public function getCurrentYear($externalUserId = null)
    {
//        $fileYearSemester = $this->findFileYearAndSemester($externalUserId);
//        if (!empty($fileYearSemester)) {
//            return $fileYearSemester['year'];
//        }
//        return null;
    }

    /**
     * Get a list of subjects and their types
     *
     * @param $synergyID
     * @param $userID
     *
     * @return array|bool
     */
    public function getSubjectList($synergyID, $userID)
    {
//        if (empty($synergyID)) {
//            return false;
//        }
//
//        if ($this->getGroup()->isStudent($userID)) {
//            $sql = "
//                SELECT
//                    StudentClasses.ClassCode,
//                    SubjectClasses.ClassCampus,
//                    SubjectClasses.Description as name,
//                    luFileType.Description as classType
//                FROM StudentClasses
//                INNER JOIN SubjectClasses ON
//                    StudentClasses.FileType = SubjectClasses.FileType AND
//                    StudentClasses.FileYear = SubjectClasses.FileYear AND
//                    StudentClasses.FileSemester = SubjectClasses.FileSemester AND
//                    StudentClasses.ClassCampus = SubjectClasses.ClassCampus AND
//                    StudentClasses.ClassCode = SubjectClasses.ClassCode AND
//                    StudentClasses.FileSemester = '" . $this->database->quote($this->getCurrentSemester($synergyID)) . "' AND
//                    StudentClasses.FileYear = '" . $this->database->quote($this->getCurrentYear($synergyID)) . "' AND
//                    StudentClasses.ID=$synergyID
//                LEFT JOIN luFileType ON StudentClasses.FileType = luFileType.Code
//                WHERE
//                    StudentClasses.StartDate <= getdate()
//                    AND (StudentClasses.StopDate >= getdate() OR StudentClasses.StopDate IS NULL)
//                ORDER BY SubjectClasses.FileType ASC, SubjectClasses.Description ASC
//            ";
//        } else {
//            $sql = "
//                SELECT
//                    DISTINCT(c1.ClassCode),
//                    c1.ClassCampus,
//                    c2.description as name,
//                    luFileType.Description as classType
//                FROM SubjectClassStaff c1
//                INNER JOIN SubjectClasses c2 ON
//                    c1.FileType = c2.FileType AND
//                    c1.FileYear = c2.FileYear AND
//                    c1.FileSemester = c2.FileSemester AND
//                    c1.ClassCampus = c2.ClassCampus AND
//                    c1.ClassCode = c2.ClassCode AND
//                    c1.FileSemester = '" . $this->database->quote($this->getCurrentSemester($synergyID)) . "' AND
//                    c1.FileYear = '" . $this->database->quote($this->getCurrentYear($synergyID)) . "' AND
//                    c1.StaffID = $synergyID
//                LEFT JOIN luFileType ON c2.FileType = luFileType.Code
//            ";
//        }
//        $this->getDatabase()->query($sql);
//        $subjectList = array();
//        while ($subject = $this->getDatabase()->getNextArray()) {
//            $subjectList[] = array(
//                'code'       => $subject['ClassCode'],
//                'name'       => $subject['name'],
//                'classType'  => $subject['classType'],
//                'campusCode' => $subject['ClassCampus']
//            );
//        }
//        if (is_array($subjectList)) {
//            return $subjectList;
//        } else {
//            return false;
//        }
    }

    /**
     * Get the students belonging to you
     *
     * @param $synergyID
     *
     * @return array|bool
     */
    public function getEnrolledStudents($synergyID)
    {
        //
        // Valid Relations:
        // SN/DR:       Son/Daughter
        // STSN/STDR:   Stepson/Stepdaugter
        // WD:          Ward
        // GRASN/GRADR: Grandson/Granddaughter
        // FOCD:        Foster child
        // HS:          Homestay
//        $query = "
//            SELECT Relationships.RelatedID
//            FROM SynergyOne.dbo.Constituencies Constituencies
//            LEFT JOIN SynergyOne.dbo.Relationships Relationships ON Relationships.RelatedID = Constituencies.ID
//            LEFT JOIN SynergyOne.dbo.Community Community ON Relationships.RelatedID = Community.ID
//            WHERE Relationships.ID = '{$synergyID}'
//            AND Constituencies.ConstitCode='@SC'
//            AND Relationships.Relationship IN ('SN','STSN','DR','STDR','WD','GRASN','GRADR','FOCD','HOPT','HOCD','HS','HOMES')
//            ORDER BY Community.BirthDate
//        ";
//        return $this->getDatabase()->getAll($query);
    }

    public function getEmail($id)
    {
//        $query = "SELECT Email FROM Community WHERE id = $id";
//        return $this->getDatabase()->getOne($query);
    }

    /**
     * Gets all Students' account info from Doublefirst
     *
     * @return array|bool
     */
    public function getStudentUserAccountInfo()
    {
//        $query = "
//            SELECT
//                vStudents.StudentID,
//                NetworkLogin,
//                vStudents.StudentTitle,
//                vStudents.StudentPreferred,
//                vStudents.StudentSurname,
//                StudentGender,
//                StudentEmail,
//                StudentOccupEmail,
//                vStudents.StudentYearLevel,
//                vStudents.StudentHouse,
//				vStudents.StudentBoarder,
//				vStudents.StudentBoardingHouse,
//                StudentCampus
//            FROM SynergyOne.dbo.vStudents vStudents
//            LEFT JOIN Community ON Community.ID = StudentID
//            WHERE
//            FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
//            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
//        ";
//        return $this->getDatabase()->getAll($query);
    }

    /**
     * Gets all Parents' account info from Doublefirst
     * Used for Bialik sync
     *
     * @return array|bool
     */
    public function getParentUserAccountInfo()
    {
//        $query = "
//            SELECT DISTINCT
//				cp.NetworkLogin AS StudentContactNetworkLogin,
//				s.StudentContactID,
//				s.StudentContactTitle,
//				s.StudentContactPreferred,
//				s.StudentContactSurname,
//				s.StudentContactDefaultEmail,
//				cs.NetworkLogin AS StudentContactSpouseNetworkLogin,
//				s.StudentContactSpouseID,
//				s.StudentContactSpouseTitle,
//				s.StudentContactSpousePreferred,
//				s.StudentContactSpouseSurname,
//				s.StudentContactSpouseDefaultEmail,
//				s.StudentYearLevel,
//				s.StudentCampus,
//				s.StudentGender
//			FROM SynergyOne.dbo.vStudentContactAllAddress s
//			LEFT JOIN SynergyOne.dbo.Community cp ON s.StudentContactID = cp.ID
//			LEFT JOIN SynergyOne.dbo.Community cs ON s.StudentContactSpouseID = cs.ID
//            WHERE (StudentContactType = 'SC1' OR StudentContactType = 'SC2')
//            AND FileSemester = '" . $this->database->quote($this->getCurrentSemester()) . "'
//            AND FileYear = '" . $this->database->quote($this->getCurrentYear()) . "'
//            ORDER BY StudentContactID
//        ";
//        return $this->getDatabase()->getAll($query);
    }

    /**
     * Gets all active Staff's account info from Doublefirst
     *
     * @return array|bool
     */
    public function getStaffUserAccountInfo()
    {
//        $query = "
//            SELECT
//                vStaff.StaffID,
//                NetworkLogin,
//                ActiveFlag,
//                vStaff.StaffTitle,
//                vStaff.StaffPreferredName,
//                vStaff.StaffSurname,
//                DefaultEmailCode,
//                OccupEmail,
//                Email,
//                vStaff.StaffCampus,
//                StaffCategoryType,
//                StaffCategory,
//                vStaff.StaffDepartment
//            FROM SynergyOne.dbo.vStaff vStaff
//            LEFT JOIN Community ON Community.ID = StaffID
//          WHERE ActiveFlag = 1 AND vStaff.StaffRegistrationType != 'STDNT'
//        ";
//        return $this->getDatabase()->getAll($query);
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
//        /* Restrict staff categories to provided list */
//        $staffCategory = explode(',',\Config::get('Doublefirst_staff_dir_restrict'));
//        $scWhere = '';
//        if(is_array($staffCategory) && $staffCategory[0] != '') {
//            $db = $this->getDatabase();
//            $staffCategoryString = implode(',', array_map(function ($var) use ($db) {
//                return "'".$db->quote($var)."'";
//            }
//                , $staffCategory));
//            $scWhere = "AND StaffCategory NOT IN ($staffCategoryString)";
//        }
//
//        // Check if the custom staff position table exists, and if so use this to override position titles [SB-10813]
//
//        $statement = $this->getDatabase()->query("SELECT * FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'uStaffPosition'");
//
//        if ($this->getDatabase()->countRows($statement) > 0) {
//
//            // If the custom table exists, override the 'JobPositionDescription' field
//
//            $query = "
//                SELECT
//                    vStaff.StaffID,
//                    vStaff.SchoolStaffCode,
//                    vStaff.StaffTitle,
//                    vStaff.StaffPreferredName,
//                    vStaff.StaffSurname,
//                    vStaff.StaffOccupPhone,
//                    vStaff.StaffOccupEmail,
//                    vStaff.StaffExtension,
//                    vStaff.StaffCampusDescription,
//                    vStaff.StaffCategory,
//                    vStaff.StaffCategoryType,
//                    vStaff.StaffDefaultMobilePhone,
//                    vStaff.StaffVoiceMail,
//                    StaffDepartmentDescription,
//                    uStaffPosition.JobTitle AS JobPositionDescription,
//                    JobPositions.StaffJobPositionStartDate,
//                    JobPositions.StaffJobPositionEndDate
//                FROM SynergyOne.dbo.vStaff vStaff
//                LEFT JOIN SynergyOne.dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
//                LEFT JOIN SynergyOne.dbo.uStaffPosition ON vStaff.StaffID = uStaffPosition.ID
//                WHERE vStaff.ActiveFlag = 1
//                AND StaffCategoryDescription != 'Staff on leave'
//                $scWhere
//                AND ( JobPositions.StaffJobPositionStartDate < GETDATE()
//                OR    JobPositions.StaffJobPositionStartDate IS NULL )
//                AND ( JobPositions.StaffJobPositionEndDate > GETDATE()
//                OR    JobPositions.StaffJobPositionEndDate IS NULL )
//                ORDER BY vStaff.StaffSurname
//            ";
//
//        } else {
//
//            $query = "
//                SELECT
//                vStaff.StaffID,
//                vStaff.SchoolStaffCode,
//                vStaff.StaffTitle,
//                vStaff.StaffPreferredName,
//                vStaff.StaffSurname,
//                vStaff.StaffOccupPhone,
//                vStaff.StaffOccupEmail,
//                vStaff.StaffExtension,
//                vStaff.StaffCampusDescription,
//                vStaff.StaffCategory,
//                vStaff.StaffCategoryType,
//                vStaff.StaffDefaultMobilePhone,
//                vStaff.StaffVoiceMail,
//                StaffDepartmentDescription,
//                JobPositions.JobPositionDescription,
//                JobPositions.StaffJobPositionStartDate,
//                JobPositions.StaffJobPositionEndDate
//            FROM SynergyOne.dbo.vStaff vStaff
//            LEFT JOIN SynergyOne.dbo.vStaffJobPositions JobPositions ON vStaff.StaffID = JobPositions.StaffID
//            WHERE vStaff.ActiveFlag = 1
//            AND StaffCategoryDescription != 'Staff on leave'
//            $scWhere
//            AND ( JobPositions.StaffJobPositionStartDate < GETDATE()
//            OR    JobPositions.StaffJobPositionStartDate IS NULL )
//            AND ( JobPositions.StaffJobPositionEndDate > GETDATE()
//            OR    JobPositions.StaffJobPositionEndDate IS NULL )
//            ORDER BY vStaff.StaffSurname
//        ";
//        }
//
//        return $this->getDatabase()->getAll($query);
    }


    /**
     * List of campuses a staff member is involved in based on timetable
     *
     * @param int $staffID
     * @return array|bool
     */
    public function getCampusFromTimetable($staffID)
    {
//        $staffID = (int)$staffID;
//        $query = "SELECT DISTINCT( ClassCampus ) AS campus FROM timetable WHERE StaffID = '$staffID' AND FileYear = ".date('Y');
//        return $this->getDatabase()->getAll($query);
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
//        if (strlen($boarderValue) === 0) {
//            return '';
//        }
//        $query = "
//            SELECT Description
//            FROM   luBoarder
//            WHERE  Code = '{$this->getDatabase()->quote($boarderValue)}'
//        ";
//        return $this->getDatabase()->getOne($query);
    }

    public function getHousemasterByHouseId($houseID)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getStudentForms()
    {
//        $query = "
//            SELECT Code, Description
//            FROM SynergyOne.dbo.luForm
//            WHERE ActiveFlag = 1
//        ";
//        return $this->getDatabase()->getAll($query);
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
