<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Report_Group extends Report_Abstract implements Report_IReport
{
    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $dataResource;
    private $emailObj;

    // csv field mapping: index => humanreadable
    private $fields = [
        'id'                  => 'Schoolbox User ID',
        'username'            => 'Username',
        'synergy_id'          => 'External ID',
        'title'               => 'Title',
        'firstname'           => 'First Name',
        'lastname'            => 'Surname',
        'position_title'      => 'Position Title',
        'role'                => 'Role',
        'campus'              => 'Campus',
        'alt_email'           => 'Alt Email',
        'year'                => 'Year',
        'house'               => 'House',
        'res_house'           => 'Residential House',
        'eportfolio_enabled'  => 'E-Portfolio?',
        'hide_contacts'       => 'Hide Contact Details?',
        'hide_timetable'      => 'Hide Timetable?',
        'local_email'         => 'Email Address From Username?',
        'mail_handler'        => 'Use External Mail Client?',
        'webmail'             => 'Enable Webmail Tab?',
        'display_grade_graph' => 'Display Graph on Student Grade Card?',
        'enabled'             => 'Account Enabled?',
        // Skip superuser field
    ];


    // fields that we will permit sorting on
    private $sortable = ['firstname', 'lastname', 'username', 'synergy_id', 'campus', 'year', 'alt_email'];
    private $defaultOrderBy = 'lastname';

    public function __construct($page)
    {
        $this->setGroupHandler(new Group());

        if (!$this->checkPermissions()) {
            error(19);

        } else {
            $this->setPage($page);
            $this->setUserHandler(new User());
            $this->setup();
        }
    }

    protected function setup()
    {
        // set blocks
        $this->getPage()->setFile('user_t', 'adminv2/report/group.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'group', 'group_block');

        $this->getPage()->setBlock('user_t', 'user', 'user_block');
        $this->getPage()->setBlock('user', 'modify', 'modify_block');
        $this->getPage()->setVar(['group_block' => '']);
        $this->getPage()->setTitle('Group Report');


        $this->emailObj = new Email();
    }

    public function setIndexID($groupID)
    {
        $this->groupID = $groupID;
    }

    public function getIndexID()
    {
        return $this->groupID;
    }

    public function getReportTitle()
    {
        $rtn = $this->getGroupHandler()->getGroupName($this->getIndexID());
        return $rtn ? $rtn : 'Group';
    }

    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    public function getSortableFields()
    {
        return $this->sortable;
    }

    public function addFields($fields)
    {
        array_merge($this->fields, $fields);
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getDefaultOrderBy()
    {
        return $this->defaultOrderBy;
    }

    public function populateDataset($offset, $limit, $orderBy = '', $orderDir = '')
    {
        if ($orderDir == '') {
            $orderDir = 'ASC';
        }

        // need fields from which to retrieve data
        if (empty($this->fields)) {
            return false;
        }

        // fallback to default if unset
        if ($orderBy == '' || !in_array($orderBy, $this->sortable)) {
            $orderBy = $this->getDefaultOrderBy();
        }

        // filter data passed in by report
        $filterString = $this->getIndexID();

        // bypass handlers because I need access to the mysql pointer resource
        $query = "
            SELECT `u`.*,
            GROUP_CONCAT(DISTINCT `c`.`name` ORDER BY `c`.`name` ASC SEPARATOR ', ') AS `campus`,
            `r`.`name` AS `role`,
            `h`.`house` AS `house`,
            `rh`.`name` AS `res_house`,
            GROUP_CONCAT(DISTINCT `child`.`synergy_id` ORDER BY `child`.`synergy_id` ASC SEPARATOR ', ') AS `profile_child_ids`,
            p.dob AS profile_dob,
            p.guardian1HomePhone AS profile_home_phone,
            p.guardian1MobilePhone AS profile_mobile_phone,
            p.guardian1WorkPhone profile_work_phone
            FROM `user` `u`
            LEFT JOIN `userCampus` `uc` ON `u`.`id`=`uc`.`uid`
            LEFT JOIN `luCampus` `c` ON `uc`.`campus`=`c`.`id`
            LEFT JOIN `groups` `r` ON `u`.`group_id`=`r`.`id`
            LEFT JOIN `house` `h` ON `u`.`house`=`h`.`id`
            LEFT JOIN `luResidentialHouse` `rh` ON `u`.`residential_house_id`=`rh`.`id`
            LEFT JOIN `user_profile` `p` ON `u`.`id`=`p`.`user_id`
            LEFT JOIN `user_relation` `ur` ON `u`.`id`=`ur`.`user_id`
            LEFT JOIN `user` `child` ON `ur`.`relation_id`=`child`.`id`
            WHERE `u`.`group_id` = '$filterString'
            AND   `u`.`deleted_at` IS NULL
            GROUP BY `u`.`id`
            ORDER BY $orderBy $orderDir
        ";

        $handler = DeprecatedDatabase::getInstance();

        $this->dataResource = $handler->query($query);
        $this->dataCount    = $this->dataResource->rowCount();

        if (!$this->dataCount) {
            return 0;
        }

        if ($limit == 0) {
            $this->dataSet = $this->dataResource;
        } else {
            // get limited records from the resource, rather than in bulk
            $this->dataSet = new LimitIterator(new IteratorIterator($this->dataResource), $offset, $limit);
        }

        return $this->dataCount;
    }

    public function render()
    {
        // the group and the dataset must both not be null
        if ($this->getIndexID() == null || empty($this->dataSet)) {
            error(19);
        }

        $page = $this->getPage();

        $groupHandler = new Group();
        $groupName    = $groupHandler->getGroupName($this->getIndexID());

        // render users
        $isUserOutput = false;
        foreach ($this->dataSet as $user) {
            if (!$isUserOutput) {
                $isUserOutput = true;
                // Output this for first user only.
                $page->setVar([
                    'COUNTABLE_COUNT' => $this->dataCount,
                    'REPORT_TITLE'    => Scrub::text($groupName),
                    'SORT_BASE'       => "group/{$this->getIndexID()}",
                    'COUNTABLE'       => 'members',
                ]);
            }
            // Format email address
            $address = Scrub::text($this->emailObj->formatEmailFromArray($user));
            $email   = $address ? "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>" : '';

            $page->setVar([
                'UID'             => Scrub::text($user['id']),
                'USER_TITLE'      => Scrub::text($user['title']),
                'USER_FIRSTNAME'  => Scrub::text($user['firstname']),
                'USER_SURNAME'    => Scrub::text($user['lastname']),
                'USER_USERNAME'   => Scrub::text($user['username']),
                'USER_SYNERGY_ID' => Scrub::text($user['synergy_id']),
                'USER_EMAIL'      => $email,
                'USER_YEAR'       => empty($user['year']) && (string)$user['year'] != '0' ? '' : "Year {$user['year']}",
                'USER_CAMPUS'     => Scrub::text($user['campus']),
            ]);

            if (!$_SESSION['su']) {
                $page->setVar('modify_block');
            } else {
                $page->parse('modify_block', 'modify');
            }

            $page->parse('user_block', 'user', true);
        }

        if ($isUserOutput) {
            $page->parse('RESULT', 'user_t', true);
        } else {
            $page->setVar([
                'RESULT'                => 'No results.',
                'USER_DISP_COUNT'       => $this->dataCount,
                'USER_GROUP'            => Scrub::text($groupName),
                'previous_button_block' => '',
                'next_button_block'     => '',
                'page_number_block'     => '',
            ]);
        }
    }

    public function export()
    {
        $exporter = new Report_Exporter($this->getReportTitle());
        $exporter->setHeadings($this->getFields());
        $exporter->setData($this->dataResource, [$this, 'dataManipulator']);

        $exporter->export('CSV');
    }

    public function dataManipulator($datum)
    {
        $address            = $this->emailObj->formatEmailFromArray($datum);
        $datum['alt_email'] = $address;
        return $datum;
    }

    public function displayFilterBlock()
    {
        // this report does not utilise filtering
        return;
    }
}
