<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_External_Nhs_Strategy_Definition extends Synchronise_External_Strategy_Definition {

    /**
     * Defines the fields found within each strategy. The file location for each is expected to be defined in
     * Cfg::get('external_sync_STRATEGY_csv')
     */
    public function __construct() {

//        $this->setDefinition('class',
//            array('code', 'name', 'staffID')
//        );
//
//        $this->setDefinition('enrolment',
//            array('classCode', 'studentID')
//        );
//
//        $this->setDefinition('parent',
//            array('parentID', 'title', 'firstName', 'lastName', 'networkLogin', '?', 'externalEmail')
//        );
//
//        $this->setDefinition('parentrelation',
//            array('studentID', 'parentID')
//        );
//
        $this->setDefinition(
            'staff',
            array(
                'StaffID',
                'NetworkLogin',
                'StaffSurname',
                'StaffPreferredName',
                'StaffPreferredName2',
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
                'StaffAddress1',
                'StaffAddress2',
                'StaffSuburb',
                'StaffState',
                'StaffPostcode',
                'StaffCountry',
                'StaffHomePhone',
                'StaffHomePhone',
                null,
                null,
                null,
                null,
                null,
                'StaffFullName',
            )
        );

//        $this->setDefinition(
//            'student',
//            array(
//                'StudentID',
//                'NetworkLogin',
//                'StudentSurname',
//                'StudentGiven',
//                'StudentPreferred',
//                'StudentMiddleName',
//                'StudentGender',
//                'StudentClassGroup',
//                'StudentHouse',
//                'StudentYearLevel',
//                'StudentEmail',
//                'StudentPhone',
//                'StudentVceNumber',
//                null,
//                'StudentBirthDate',
//            )
//        );

//        $this->setDefinition('timetable',
//            array('classCode', 'day', 'period', 'room')
//        );
    }

}
