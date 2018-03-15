<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 */


class Report_Bialik_Parent extends \Report_Parent
{
    /**
     * Constructor
     *
     * @param $page
     * @param $yearLevel
     * @param $searchName
     * @param $searchBoardingStatus
     * @param $searchForm
     */
    public function __construct($page, $yearLevel, $searchName, $searchBoardingStatus, $searchForm) {
        $this->setGroupHandler(new Group());

        // Must provide an int or be empty
        $yearLevel = trim($yearLevel);

        // Parents may only search in their student's year level
        if ($this->isYearsRestricted() && !( in_array($yearLevel, $this->getChildrensYears()) || $yearLevel == '')) {
            error(155);
        }

        $this->setYearLevel(trim($yearLevel));
        $this->setSearchName(trim($searchName));
        $this->setSearchBoardingStatus(trim($searchBoardingStatus));
        $this->setSearchForm(trim($searchForm));

        if (strlen($page->arg[0]) && !$this->checkPermissions()) {
            error(155);
        }
        else {
            $this->setPage($page);

            $this->setExternalUserHandler(new External_User());

            $this->setup();

            /* If all four options are empty, just show search form - don't show
             * all results (default is empty array)
             */
            if (strlen($this->getYearLevel().$this->getSearchName().$this->getSearchForm().$this->getSearchBoardingStatus())) {
                $this->setAllRecords(
                    $this->getStudentContactList(
                        $this->getYearLevel(),
                        $this->getSearchName(),
                        $this->getSearchBoardingStatus(),
                        $this->getSearchForm(),
                        $this->getExternalUserHandler()->getCurrentYear(),
                        $this->getExternalUserHandler()->getCurrentSemester()
                    )
                );
            }

            // check that something came back, otherwise inform the user that it was an invalid request
            if($this->getAllRecords() === false) {
                error(155);
            }

        }
    }

    /**
     * Custom query function for fetching student contact results from Synergetic for Bialik
     */
    protected function getStudentContactList($yearlevel, $name, $boardingStatus, $form, $fileYear, $fileSemester) {

        $db = new MSSQL();

        $query = "
            SELECT
                StudentID,
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
                Contact.DirectoryIncludeFlag PrimaryDirectoryIncludeFlag,
                Spouse.DirectoryIncludeFlag SpouseDirectoryIncludeFlag,
                StudentParentsSeparatedFlag,
                Contact.UserFlag1 ContactUserFlag1,
                Contact.UserFlag2 ContactUserFlag2,
                Contact.UserFlag3 ContactUserFlag3,
                Contact.UserFlag4 ContactUserFlag4,
                Contact.UserFlag5 ContactUserFlag5,
                Spouse.UserFlag1 SpouseUserFlag1,
                Spouse.UserFlag2 SpouseUserFlag2,
                Spouse.UserFlag3 SpouseUserFlag3,
                Spouse.UserFlag4 SpouseUserFlag4,
                Spouse.UserFlag5 SpouseUserFlag5,
                Addresses.UserFlag1 AddressUserFlag1,
                Addresses.UserFlag2 AddressUserFlag2,
                Addresses.UserFlag3 AddressUserFlag3,
                Addresses.UserFlag4 AddressUserFlag4,
                Addresses.UserFlag5 AddressUserFlag5
            FROM dbo.vStudentContactAllAddress
            LEFT JOIN dbo.Community Contact ON StudentContactID = Contact.ID
            LEFT JOIN dbo.Community Spouse ON StudentContactSpouseID = Spouse.ID
            LEFT JOIN dbo.Addresses Addresses ON Contact.AddressID = Addresses.AddressID
            LEFT JOIN dbo.luHouse House ON StudentCampus = House.Campus
                AND StudentHouse = House.Code
            LEFT JOIN dbo.luYearLevel Year ON StudentYearLevel = Year.Code
                AND StudentCampus = Year.Campus
            LEFT JOIN dbo.luCountry StudentCountry
                ON StudentContactCountryCode = StudentCountry.Code
            LEFT JOIN dbo.luCountry StudentHomeCountry
                ON StudentContactHomeCountryCode = StudentHomeCountry.Code
            WHERE StudentContactType IN ('SC1', 'SC2', 'SC3')
            AND FileSemester = '" . $db->quote($fileSemester) . "'
            AND FileYear = '" . $db->quote($fileYear) ."'";
        /* Search for a particular year level
         */
        if (strlen($yearlevel)) {
            if (is_numeric($yearlevel)) {
                // forcibly cast to int in case it's a something non-int that's still numeric
                $yearlevel = (int)$yearlevel;
                $query .= "
                AND (StudentYearLevel = '{$yearlevel}' OR Year.Description = '{$yearlevel}')";
            } else {
                // if year is NOT numeric, don't attempt to compare StudentYearLevel (will bug out)
                $query .= "
                AND Year.Description = '{$db->quote($yearlevel)}'";
            }
        }
        /* Search for a student name
         */
        if (strlen($name)) {
            $name = $db->quote($name);
            $query .= "
                AND (
                    (StudentSurname LIKE '%{$name}%')
                    OR
                    (StudentPreferred LIKE '%{$name}%')
                )
            ";
        }
        /* Search for a particular boarding status
         *
         * In Synergetic, assume the following:
         * B: Boarder
         * D: Day student
         * HS: Home stay
         */
        if ($boardingStatus == '') {
            /* No restrictions on result set
             */
        } elseif ($boardingStatus == 'yes') {
            $query .= "
                AND StudentBoarder = 'B'
            ";
        } else {
            $query .= "
                AND StudentBoarder != 'B'
            ";
        }
        /* Search for a particular form
         */
        if ($form) {
            $form = $db->quote($form);
            $query .= "
                AND StudentForm = '{$form}'
            ";
        }
        $query .= "
            ORDER BY StudentSurname, StudentID, StudentContactType
        ";
        $contactList = $db->getAll($query);

        foreach ($contactList as &$row) {
           if ($row['ValidAddressFlag'] == 0 || ($row['AddressUserFlag1'] == 1 && $row['AddressUserFlag3'] != 1)) {
                $row['StudentContactAddress1']        = '';
                $row['StudentContactAddress2']        = '';
                $row['StudentContactAddress3']        = '';
                $row['StudentContactSuburb']          = '';
                $row['StudentContactPostCode']        = '';
                $row['StudentContactState']           = '';
                $row['StudentContactCountryDesc']     = '';
                $row['StudentContactHomeAddress1']    = '';
                $row['StudentContactHomeAddress2']    = '';
                $row['StudentContactHomeAddress3']    = '';
                $row['StudentContactHomeSuburb']      = '';
                $row['StudentContactHomePostCode']    = '';
                $row['StudentContactHomeState']       = '';
                $row['StudentContactHomeCountryDesc'] = '';
            }
            $row['StudentContactHomeCountryCode']
                = $row['StudentContactHomeCountryCode'] == 'Not Selected' ? '' : $row['StudentContactHomeCountryCode'];
            $row['StudentContactCountryCode']
                = $row['StudentContactCountryCode'] == 'Not Selected' ? '' : $row['StudentContactCountryCode'];

            if($row['AddressUserFlag2'] == 1)
                $row['StudentContactPhoneActual'] = '';

            if($row['AddressUserFlag3'] == 1) {
                $row['StudentContactHomeAddress1']  = $row['StudentContactAddress1'];
                $row['StudentContactHomeAddress2']  = $row['StudentContactAddress2'];
                $row['StudentContactHomeAddress3']  = $row['StudentContactAddress3'];
                $row['StudentContactHomeSuburb']    = $row['StudentContactSuburb'];
                $row['StudentContactHomePostCode']  = $row['StudentContactPostCode'];
                $row['StudentContactHomeState']     = $row['StudentContactState'];
                $row['StudentContactHomeCountryCode']     = $row['StudentContactCountryCode'];
            }

            if($row['ContactUserFlag1'] == 1)
                $row['StudentContactDefaultEmail'] = '';

            if($row['ContactUserFlag2'] == 1)
                $row['StudentContactMobilePhone'] = '';

            if($row['SpouseUserFlag1'] == 1)
                $row['StudentContactSpouseDefaultEmail'] = '';

            if($row['SpouseUserFlag2'] == 1)
                $row['StudentContactSpouseMobilePhone'] = '';

            $row['StudentContactHomeCountryDesc'] = '';
            $row['StudentContactCountryDesc']     = '';
        }

        return $contactList;
    }
}
