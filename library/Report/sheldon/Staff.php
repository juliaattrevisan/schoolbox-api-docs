<?php

/**
 * Report_Staff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Sheldon_Staff extends \Report_Staff
{

    /**
     * Render data
     */
    public function render()
    {
        // Agglutinate multiple JobPositionDescription
        $allStaff = array_reduce(
            $this->getAllRecords(),
            array($this, 'mergeStaffRecords'),
            array()
        );

        if (is_array($allStaff) && count($allStaff)) {
            foreach ($allStaff as $currentStaff) {
                $this->getPage()->setVar(
                    array(
                        'STAFF_STAFFID'    => Scrub::text($currentStaff['StaffID']),
                        'STAFF_STAFFCODE'  => Scrub::text($currentStaff['SchoolStaffCode']),
                        'STAFF_TITLE'      => Scrub::text($currentStaff['StaffTitle']),
                        'STAFF_FIRSTNAME'  => Scrub::text($currentStaff['StaffPreferredName']),
                        'STAFF_SURNAME'    => Scrub::text($currentStaff['StaffSurname']),
                        'STAFF_ROLE'       => Scrub::text(implode(', ', $currentStaff['JobPositionDescription'])),
                        'STAFF_DEPARTMENT' => Scrub::text($currentStaff['StaffDepartmentDescription']),
                        'STAFF_CAMPUS'     => Scrub::text($currentStaff['StaffCampusDescription']),
                        'STAFF_CATEGORY'   => Scrub::text($currentStaff['StaffCategoryType']),
                        'STAFF_EXTENSION'  => Scrub::text($currentStaff['StaffExtension']),
                        'STAFF_MOBILE'     => Scrub::text($currentStaff['StaffDefaultMobilePhone']),
                        'STAFF_VOICEMAIL'  => Scrub::text($currentStaff['StaffVoiceMail']),
                        'STAFF_PHONE'      => Scrub::text($currentStaff['StaffOccupPhone']),
                        'STAFF_EMAIL'      => Scrub::text($currentStaff['StaffOccupEmail']),
                    )
                );
                $this->getPage()->parse('staff_block', 'staff', true);
            }
        }
    }
}



