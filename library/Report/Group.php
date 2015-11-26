<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Group extends Report_Abstract implements Report_IReport
{
    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $dataResource;
    private $emailObj;

    // csv field mapping: index => humanreadable
    private $fields = array(
        'title'      => 'title',
        'firstname'  => 'firstname',
        'lastname'   => 'lastname',
        'username'   => 'username',
        'synergy_id' => 'external id',
        'campus'     => 'campus',
        'year'       => 'year',
        'alt_email'  => 'email'
    );

    // fields that we will permit sorting on
    private $sortable = array('firstname', 'lastname', 'username', 'synergy_id', 'campus', 'year', 'alt_email');
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
        $this->getPage()->setVar(array('group_block' => ''));
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
        if($orderDir == '') {
            $orderDir = 'ASC';
        }

        // need fields from which to retrieve data
        if(empty($this->fields))
            return false;

        // fallback to default if unset
        if($orderBy == '' || !in_array($orderBy, $this->sortable))
            $orderBy = $this->getDefaultOrderBy();

        // filter data passed in by report
        $filterString = $this->getIndexID();

        // bypass handlers because I need access to the mysql pointer resource
        $query = "
            SELECT `u`.*,
            GROUP_CONCAT(`c`.`name` ORDER BY `uc`.`defaultCampus` DESC, `c`.`name` ASC SEPARATOR ', ') AS `campus`
            FROM `user` `u`
            LEFT JOIN `userCampus` `uc` ON `u`.`id`=`uc`.`uid`
            LEFT JOIN `luCampus` `c` ON `uc`.`campus`=`c`.`id`
            WHERE `u`.`group_id` = '$filterString'
            GROUP BY `u`.`id`
            ORDER BY $orderBy $orderDir
        ";

        $handler = DeprecatedDatabase::getInstance();

        $this->dataResource = $handler->query($query);
        $this->dataCount = mysqli_num_rows($this->dataResource);

        if(!$this->dataCount) {
            return 0;
        }

        // get limited records from the resource, rather than in bulk
        $this->dataSet = $this->getRecordsFromResource($this->dataResource, $offset, $limit);

        return $this->dataCount;
    }

    public function render()
    {
        // the group and the dataset must both not be null
        if($this->getIndexID() == null || empty($this->dataSet))
            error(19);

        $page = $this->getPage();

        $groupHandler = new Group();
        $groupName = $groupHandler->getGroupName($this->getIndexID());

        // render users
        if (!empty($this->dataSet)) {

            $page->setVar(array(
                'COUNTABLE_COUNT' => $this->dataCount,
                'REPORT_TITLE'    => Scrub::text($groupName),
                'SORT_BASE'       => "group/{$this->getIndexID()}",
                'COUNTABLE'       => 'members',
            ));

            foreach ($this->dataSet as $user) {
                // Format email address
                $address = Scrub::text($this->emailObj->formatEmailFromArray($user));
                $email = $address ? "<a href=\"{$_SESSION['mailhandle']}$address\">$address</a>" : '';

                $page->setVar(array(
                    'UID'             => Scrub::text($user['id']),
                    'USER_TITLE'      => Scrub::text($user['title']),
                    'USER_FIRSTNAME'  => Scrub::text($user['firstname']),
                    'USER_SURNAME'    => Scrub::text($user['lastname']),
                    'USER_USERNAME'   => Scrub::text($user['username']),
                    'USER_SYNERGY_ID' => Scrub::text($user['synergy_id']),
                    'USER_EMAIL'      => $email,
                    'USER_YEAR'       => empty($user['year']) ? '' : "Year {$user['year']}",
                    'USER_CAMPUS'     => Scrub::text($user['campus']),
                ));

                if(!$_SESSION['su'])
                    $page->setVar('modify_block');
                else
                    $page->parse('modify_block', 'modify');

                $page->parse('user_block', 'user', true);
            }

            $page->parse('RESULT', 'user_t', true);

        } else {
            $page->setVar(array(
                'RESULT'                => 'No results.',
                'USER_DISP_COUNT'       => $this->dataCount,
                'USER_GROUP'            => Scrub::text($groupName),
                'previous_button_block' => '',
                'next_button_block'     => '',
                'page_number_block'     => ''
            ));
        }

    }

    public function export()
    {
        $exporter = new Report_Exporter($this->getReportTitle());
        $exporter->setHeadings($this->getFields());
        $exporter->setData($this->dataResource, array($this, 'dataManipulator'));

        $exporter->export('CSV');
    }

    public function dataManipulator($datum) {
        $address = $this->emailObj->formatEmailFromArray($datum);
        $datum['alt_email'] = $address;
        return $datum;
    }

    public function displayFilterBlock()
    {
        // this report does not utilise filtering
        return;
    }
}
