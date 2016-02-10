<?php

/**
 * Report_Staff_Department
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_StaffDepartment extends Report_Abstract
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
        $this->getPage()->setFile(TEMPLATE_content, 'modules/report/staffDepartment.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'link', 'link_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'nolink', 'nolink_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'staff', 'staff_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'department', 'department_block');
        $this->getPage()->setTitle('Staff Contact info');

        $this->getPage()->setVar(
            array(
                'nolink_block' => '',
                'link_block' => '',
                'staff_block' => '',
                'department_block' => '',
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

            $previousDepartment = "";

            foreach ($allStaff as $key => $currentStaff) {
                $description = trim(Scrub::text($currentStaff['StaffDepartmentDescription']));

                // Remove staff with unknown departments

                if ($description == '' || strtolower($description) == 'not selected') {
                    unset($allStaff[$key]);
                } else {
                    $allStaff[$key]['StaffDepartmentDescription'] = $description;
                }
            }

            $sort = array();

            foreach ($allStaff as $k=>$v) {
                $sort['StaffDepartmentDescription'][$k] = $v['StaffDepartmentDescription'];
                $sort['StaffSurname'][$k] = $v['StaffSurname'];
                $sort['StaffPreferredName'][$k] = $v['StaffPreferredName'];
            }

            array_multisort(
                $sort['StaffDepartmentDescription'], SORT_ASC,
                $sort['StaffSurname'], SORT_ASC,
                $sort['StaffPreferredName'], SORT_ASC,
                $allStaff
            );

            foreach ($allStaff as $currentStaff) {

                if ($previousDepartment != $currentStaff['StaffDepartmentDescription']) {
                    if ($previousDepartment != '') {
                        $this->getPage()->parse('department_block', 'department', true);
                        $this->getPage()->setVar('staff_block', '');
                    }
                    $previousDepartment = $currentStaff['StaffDepartmentDescription'];
                }

                $extensionText = Scrub::text($currentStaff['StaffExtension']) == '' ? '' : '(x' . Scrub::text($currentStaff['StaffExtension']) . ')';

                // Find the match schoolbox user so we can link to their profile page

                try {
                    $userId = \Schoolbox\UserMapper::getInstance()->findByExternalId($currentStaff['StaffID'])->getId();
                } catch (Exception $e) {
                    $userId = -1;
                }

                $this->getPage()->setVar(
                    array(
                        'STAFF_STAFFID'    => Scrub::text($currentStaff['StaffID']),
                        'STAFF_EXTERNALID' => $userId,
                        'STAFF_STAFFCODE'  => Scrub::text($currentStaff['SchoolStaffCode']),
                        'STAFF_TITLE'      => Scrub::text($currentStaff['StaffTitle']),
                        'STAFF_FIRSTNAME'  => Scrub::text($currentStaff['StaffPreferredName']),
                        'STAFF_SURNAME'    => Scrub::text($currentStaff['StaffSurname']),
                        'STAFF_ROLE'       => Scrub::text(implode(', ', $currentStaff['JobPositionDescription'])),
                        'STAFF_DEPARTMENT' => $currentStaff['StaffDepartmentDescription'],
                        'STAFF_CAMPUS'     => Scrub::text($currentStaff['StaffCampusDescription']),
                        'STAFF_EXTENSION'  => $extensionText,
                        'STAFF_MOBILE'     => Scrub::text($currentStaff['StaffDefaultMobilePhone']),
                        'STAFF_VOICEMAIL'  => Scrub::text($currentStaff['StaffVoiceMail']),
                        'STAFF_PHONE'      => Scrub::text($currentStaff['StaffOccupPhone']),
                        'STAFF_EMAIL'      => Scrub::text($currentStaff['StaffOccupEmail']),
                    )
                );

                if ($user_id != -1) {
                    $this->getPage()->parse('link_block', 'link', true);
                } else {
                    $this->getPage()->parse('nolink_block', 'nolink', true);
                }

                $this->getPage()->parse('staff_block', 'staff', true);

                $this->getPage()->setVar('link_block', '');
                $this->getPage()->setVar('nolink_block', '');
            }

            $this->getPage()->parse('department_block', 'department', true);
        }
    }

    public function export(Report_Exporter $exporter, $format = 'csv')
    {
        // function stub to appease interpreter
        // can be implemented if export required.
    }
}



