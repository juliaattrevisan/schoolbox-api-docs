<?php
/**
 * Synchronise_External_Nhs_Strategy_Definition
 *
 * This class is currently a contractual obligation for external sync types. It
 * doesn't do anything for NHS (yet).
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
class Synchronise_External_Nhs_Strategy_Definition extends Synchronise_External_Strategy_Definition {

    /**
     * Defines the fields found within each strategy. The file location for each is expected to be defined in
     * Cfg::get('external_sync_STRATEGY_csv')
     */
    public function __construct() {
        $this->setDefinition(
            'student',
            array(
                'StudentID',
                'NetworkLogin',
                'StudentSurname',
                'StudentGiven',
                'StudentPreferred',
                'StudentMiddleName',
                'StudentGender',
                'StudentClassGroup',
                'StudentHouse',
                'StudentYearLevel',
                'StudentEmail',
                'StudentPhone',
                'StudentVceNumber',
                null,
                'StudentBirthDate',
            )
        );
        $this->setDefinition(
            'staff',
            array(
                'StaffID',
                'NetworkLogin',
                'StaffSurname',
                'StaffGivenName',
                'StaffPreferredName',
                'StaffMiddleName',
                'StaffGender',
                null,
                null,
                null,
                'OccupEmail',
                'StaffPhone',
                'StaffTeacherNumber',
                null,
                'StaffBirthDate',
                'StaffSubAddress',
                'StaffAddress',
                'StaffSuburb',
                'StaffState',
                'StaffPostcode',
                'StaffCountry',
                'StaffHomePhone',
                null,
                null,
                null,
                null,
                null,
                'StaffFullName',
            )
        );
        $this->setDefinition(
            'parent',
            array(
                'StudentContactID',
                'NetworkLogin',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'StudentContactDefaultEmail',
                null,
                null,
                null,
                null,
                null,
                'StudentContactAddress',
                'StudentContactSuburb',
                'StudentContactState',
                'StudentContactPostcode',
                'StudentContactCountry',
                'StudentContactPhone',
                'StudentContactName',
                'StudentContactWorkPhone',
                'StudentContactSpouseName',
                'StudentContactSpouseWorkPhone',
                'StudentContactIdentifier',
                'StudentContactCombinedName',
                'StudentContactSpouseDefaultEmail',
                'StudentYearLevel',
                'StudentCampus',
            )
        );

        // Class, timetable and enrolment have the first row as the column list.
        $this->setDefinition('class', null);
        $this->setDefinition('enrolment', null);
        $this->setDefinition('timetable', null);
    }

}
