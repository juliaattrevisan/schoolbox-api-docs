<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Group extends Report_Abstract implements Report_IReport {

    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $pageNum;
    private $perPage;

    // csv field mapping: index => humanreadable
    private $fields = array(
        'title'     => 'title',
        'firstname' => 'firstname',
        'lastname'  => 'lastname',
        'campus'    => 'campus',
        'year'      => 'year',
        'alt_email' => 'email'
    );

    // fields that we will permit sorting on
    private $sortable = array('firstname', 'lastname', 'campus', 'year', 'alt_email');
    private $defaultOrderBy = 'lastname';

    public function __construct($page) {
        $this->setGroupHandler(new Group());

        if (!$this->checkPermissions()) {
            error(19);

        } else {
            $this->setPage($page);
            $this->setUserHandler(new User());
            $this->setup();
        }
    }

    protected function setup() {
        // set blocks
        $this->getPage()->setFile('user_t', 'adminv2/report/group.html');
        $this->getPage()->setBlock(TEMPLATE_content, 'group', 'group_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'previous_button', 'previous_button_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'page_number', 'page_number_block');
        $this->getPage()->setBlock(TEMPLATE_content, 'next_button', 'next_button_block');
        $this->getPage()->setBlock('user_t', 'user', 'user_block');
        $this->getPage()->setBlock('user', 'modify', 'modify_block');
        $this->getPage()->setVar(array('group_block' => ''));
        $this->getPage()->setTitle('Group Report');

    }

    public function setIndexID($groupID) {
        $this->groupID = $groupID;
    }

    public function getIndexID() {
        return $this->groupID;
    }

    public function getReportTitle() {
        return $this->getGroupHandler()->getGroupName($this->getIndexID());
    }

    public function setFields($fields) {
        $this->fields = $fields;
    }

    public function getSortableFields() {
        return $this->sortable;
    }

    public function addFields($fields) {
        array_merge($this->fields, $fields);
    }

    public function getFields() {
        return $this->fields;
    }

    public function getDefaultOrderBy() {
        return $this->defaultOrderBy;
    }

    public function populateDataset($offset, $limit, $orderBy = '', $orderDir = 'ASC') {

        $this->pageNum = ($offset / $limit) + 1;
        $this->perPage = $limit;

        // need fields from which to retrieve data
        if(empty($this->fields))
            return false;

        // fallback to default if unset
        if($orderBy == '' || !in_array($orderBy, $this->sortable))
            $orderBy = $this->getDefaultOrderBy();

        $allResults = $this->getUserHandler()->getAllByField('group_id', $this->getIndexID(), $orderBy, $orderDir);

        $this->dataCount = count($allResults);
        $this->dataSet = array_slice($allResults, $offset, $limit);

        return $this->dataCount;
    }

    public function render() {
        // the group and the dataset must both not be null
        if($this->getIndexID() == null || empty($this->dataSet))
            error(19);

        $page = $this->getPage();

        $groupHandler = new Group();
        $groupName = $groupHandler->getGroupName($this->getIndexID());

        // render users
        if (!empty($this->dataSet)) {

            $page->setVar(array(
                'USER_DISP_COUNT' => $this->dataCount,
                'USER_GROUP'      => $groupName,
                'SORT_BASE'       => "group/{$this->getIndexID()}",
            ));

            foreach ($this->dataSet as $user) {
                // Format email address
                $emailObj = new Email();
                $address = $emailObj->formatEmailFromArray($user);
                $email = $address ? "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>" : '';

                $page->setVar(array(
                    'USER_ID'         => $user['id'],
                    'USER_TITLE'      => $user['title'],
                    'USER_FIRSTNAME'  => $user['firstname'],
                    'USER_SURNAME'    => $user['lastname'],
                    'USER_EMAIL'      => $email,
                    'USER_YEAR'       => empty($user['year']) ? '' : "Year {$user['year']}",
                    'USER_CAMPUS'     => stristr($user['group_name'], $user['campus_name'])?'':$user['campus_name'],
                ));

                if(!$_SESSION['su'])
                    $page->setVar('modify_block');
                else
                    $page->parse('modify_block', 'modify');

                $page->setVar('DARK', ++$j%2?'dark':'');

                $page->parse('user_block', 'user', true);
            }

            $page->parse('RESULT', 'user_t', true);

        } else {
            $page->setVar(array(
                'RESULT'                => 'No results.',
                'USER_DISP_COUNT'       => $this->dataCount,
                'USER_GROUP'            => $groupName,
                'previous_button_block' => '',
                'next_button_block'     => '',
                'page_number_block'     => ''
            ));
        }

    }

    public function export() {
        $exporter = new Report_Exporter($this->getReportTitle());
        $exporter->setHeadings($this->getFields());
        $exporter->setData($this->dataSet);
        $exporter->export('CSV');
    }
}
