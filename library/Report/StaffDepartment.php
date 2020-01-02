<?php

/**
 * Report_Staff_Department
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Report_StaffDepartment extends Report_Abstract
{

    /**
     * Constructor
     *
     * @param $page
     * @throws InvalidArgumentException
     * @throws LogicException
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
    protected function checkPermissions(): bool
    {
        return $this->getGroupHandler()->isSuperuser() || $this->getGroupHandler()->isStaff() || $this->getGroupHandler()->isParent();
    }


    /**
     * Set up blocks and stuff
     */
    protected function setup(): void
    {
        $this->getPage()->setFile(TEMPLATE_content, 'modules/report/staffDepartment.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'link', 'link_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'nolink', 'nolink_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'staff', 'staff_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'department', 'department_block');
        $this->getPage()->setTitle('Staff Contact info');

        $this->getPage()->setVar([
            'nolink_block' => '',
            'link_block' => '',
            'staff_block' => '',
            'department_block' => '',
        ]);
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
    protected function mergeStaffRecords($mergedRecords, $record): array
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
    public function render(): void
    {
        // Agglutinate multiple JobPositionDescription
        $allStaff = array_reduce(
            $this->getAllRecords(),
            [$this, 'mergeStaffRecords'],
            []
        );

        if (is_array($allStaff) && count($allStaff)) {
            $previousDepartment = '';

            foreach ($allStaff as $key => $currentStaff) {
                $department = trim(Scrub::text($currentStaff['StaffDepartment']));
                $role = trim(Scrub::text(implode(', ', $currentStaff['JobPositionDescription'])));

                // Remove staff with unknown departments


                if ($department === '' || $role === '' || strtolower($department) === 'not selected') {
                    unset($allStaff[$key]);
                } else {
                    $allStaff[$key]['StaffDepartment'] = $department;
                }
            }

            $sort = array();

            foreach ($allStaff as $k=>$v) {
                $sort['StaffDepartment'][$k] = $v['StaffDepartment'];
                $sort['StaffSurname'][$k] = $v['StaffSurname'];
                $sort['StaffPreferredName'][$k] = $v['StaffPreferredName'];
            }

            array_multisort(
                $sort['StaffDepartment'], SORT_ASC,
                $sort['StaffSurname'], SORT_ASC,
                $sort['StaffPreferredName'], SORT_ASC,
                $allStaff
            );

            foreach ($allStaff as $currentStaff) {
                if ($previousDepartment !== $currentStaff['StaffDepartment']) {
                    if ($previousDepartment !== '') {
                        $this->getPage()->parse('department_block', 'department', true);
                        $this->getPage()->setVar('staff_block', '');
                    }

                    $previousDepartment = $currentStaff['StaffDepartment'];
                }

                $extensionText = Scrub::text($currentStaff['StaffExtension']) === '' ? '' : '(x' . Scrub::text($currentStaff['StaffExtension']) . ')';

                // Find the match schoolbox user so we can link to their profile page

                try {
                    $userId = \Schoolbox\UserMapper::getInstance()->findByExternalId($currentStaff['StaffID'])->getId();
                } catch (Exception $e) {
                    $userId = -1;
                }

                $this->getPage()->setVar([
                    'STAFF_STAFFID'    => Scrub::text($currentStaff['StaffID']),
                    'STAFF_EXTERNALID' => $userId,
                    'STAFF_STAFFCODE'  => Scrub::text($currentStaff['SchoolStaffCode']),
                    'STAFF_TITLE'      => Scrub::text($currentStaff['StaffTitle']),
                    'STAFF_FIRSTNAME'  => Scrub::text($currentStaff['StaffPreferredName']),
                    'STAFF_SURNAME'    => Scrub::text($currentStaff['StaffSurname']),
                    'STAFF_ROLE'       => Scrub::text(implode(', ', $currentStaff['JobPositionDescription'])),
                    'STAFF_DEPARTMENT' => $currentStaff['StaffDepartment'],
                    'STAFF_CAMPUS'     => Scrub::text($currentStaff['StaffCampusDescription']),
                    'STAFF_EXTENSION'  => $extensionText,
                    'STAFF_MOBILE'     => Scrub::text($currentStaff['StaffDefaultMobilePhone']),
                    'STAFF_VOICEMAIL'  => Scrub::text($currentStaff['StaffVoiceMail']),
                    'STAFF_PHONE'      => Scrub::text($currentStaff['StaffOccupPhone']),
                    'STAFF_EMAIL'      => Scrub::text($currentStaff['StaffOccupEmail']),
                ]);

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


    /**
     * @param Report_Exporter $exporter
     * @param string          $format
     */
    public function export(Report_Exporter $exporter, $format = 'csv'): void
    {
        // function stub to appease interpreter
        // can be implemented if export required.
    }
}



