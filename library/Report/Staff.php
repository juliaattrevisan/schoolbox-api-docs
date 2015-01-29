<?php

/**
 * Report_Staff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Staff extends Report_Abstract
{

    /**
     * Constructor
     *
     * @param $page
     */
    public function __construct($page)
    {
        $this->setGroupHandler(new Group());

        if (!$this->checkPermissions()) {
            error(19);
        } else {
            $this->setPage($page);

            $this->setExternalUserHandler(new External_User());
            $this->setup();

            $this->setAllRecords($this->getExternalUserHandler()->getStaffContactListExtended());
        }
    }

    /**
     * Check permissions. Only staff and superusers can view this page
     *
     * @return bool
     */
    protected function checkPermissions()
    {
        if ($this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff() || $this->getGroupHandler()->isParent()) {
            return true;
        }

        return false;
    }

    /**
     * Set up blocks and stuff
     */
    protected function setup()
    {
        $this->getPage()->setFile(TEMPLATE_content, 'modules/report/staff.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'staff', 'staff_block');
        $this->getPage()->setTitle('Staff Contact info');

        $this->getPage()->setVar(
            array(
                'staff_block' => '',
            )
        );
    }

    /**
     * Merge staff records method, used as an array_reduce() callback to merge
     * job position descriptions.
     *
     * @param $mergedRecords
     * @param $record
     *
     * @return array
     */
    protected function mergeStaffRecords($mergedRecords, $record)
    {
        $staffId = $record['StaffID'];
        if (!array_key_exists($staffId, $mergedRecords)) {
            $mergedRecords[$staffId] = $record;
            $mergedRecords[$staffId]['JobPositionDescription'] = array();
        }
        $mergedRecords[$staffId]['JobPositionDescription'][] = $record['JobPositionDescription'];
        return $mergedRecords;
    }

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
                        'STAFF_STAFFID'    => $currentStaff['StaffID'],
                        'STAFF_STAFFCODE'  => $currentStaff['SchoolStaffCode'],
                        'STAFF_TITLE'      => $currentStaff['StaffTitle'],
                        'STAFF_FIRSTNAME'  => $currentStaff['StaffPreferredName'],
                        'STAFF_SURNAME'    => $currentStaff['StaffSurname'],
                        'STAFF_ROLE'       => implode(', ', $currentStaff['JobPositionDescription']),
                        'STAFF_DEPARTMENT' => $currentStaff['StaffDepartmentDescription'],
                        'STAFF_CAMPUS'     => $currentStaff['StaffCampusDescription'],
                        'STAFF_EXTENSION'  => $currentStaff['StaffExtension'],
                        'STAFF_MOBILE'     => $currentStaff['StaffDefaultMobilePhone'],
                        'STAFF_VOICEMAIL'  => $currentStaff['StaffVoiceMail'],
                        'STAFF_PHONE'      => $currentStaff['StaffOccupPhone'],
                        'STAFF_EMAIL'      => $currentStaff['StaffOccupEmail'],
                    )
                );
                $this->getPage()->parse('staff_block', 'staff', true);
            }
        }
    }

    public function export(Report_Exporter $exporter, $format = 'csv')
    {
        // function stub to appease interpreter
        // can be implemented if export required.
    }
}



