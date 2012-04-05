<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Login extends Report_Abstract implements Report_IReport {

    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $fullDataSet;
    private $pageNum;
    private $perPage;
    private $filter;

    // visible field mapping: index => humanreadable
    private $fields = array(
        'date'  => 'date',
        'login' => 'action',
        'user'  => 'user',
        'ip'    => 'ip'
    );

    // csv mappings
    private $csvFields = array(
        'date'      => 'date',
        'login'     => 'login success',
        'user'      => 'user id',
        'firstname' => 'firstname',
        'lastname'  => 'lastname',
        'ip'        => 'originating ip address'
    );

    // fields that we will permit sorting on
    private $sortable = array('date', 'user', 'ip');
    private $filterable = array('user', 'ip');
    private $defaultOrderBy = 'date';

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
        $this->getPage()->setFile('activity_t', 'adminv2/report/login.html');
        $this->getPage()->setBlock('activity_t', 'activity', 'activity_block');
        $this->getPage()->setTitle('Login Report');
    }

    public function setIndexID($groupID) {
        $this->groupID = $groupID;
    }

    public function getIndexID() {
        return $this->groupID;
    }

    public function getReportTitle() {
        return "Login";
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


        // filter data passed in by report
        $filterString = $this->getIndexID();

        // explode across comma to get keypairs
        $filterSplit = explode(',',$filterString);
        $resultFilter = array();

        foreach($filterSplit as $split) {
            $thisFilter = explode('=', $split);
            if(count($thisFilter) == 2)
                $resultFilter[$thisFilter[0]] = $thisFilter[1];
        }

        $this->filter = $resultFilter;

        $sqlFilter = "";

        foreach($resultFilter as $key => $value) {
            if(in_array($key, array_keys($this->getFields()))) {
                $key = mysql_real_escape_string($key);
                $value = mysql_real_escape_string($value);
                $sqlFilter .= $sqlFilter == "" ? " WHERE" : " AND";


                // custom filter by user
                if($key == 'user') {
                    $sqlFilter .= " ( user.username LIKE '%$value%' OR user.firstname LIKE '%$value%' OR user.lastname LIKE '%$value%' ) ";
                }
                // default filter
                else {
                    $sqlFilter .= " $key LIKE '%$value%'";
                }
            }
        }

        $handler = new DeprecatedModel();

        $query = "SELECT `date`, login, ip, user.id AS user, title, firstname, lastname
                  FROM login_log
                  LEFT JOIN `user` ON `user`.`username`=login_log.username
                  $sqlFilter
                  ORDER BY $orderBy $orderDir";

        $allResults = $handler->db->getAll($query);

        if(!$allResults)
            return 0;

        $this->dataCount = count($allResults);
        $this->fullDataSet = $allResults;
        $this->dataSet = array_slice($allResults, $offset, $limit);

        return $this->dataCount;
    }

    public function render() {
        // the group and the dataset must both not be null
        if($this->getIndexID() == null || empty($this->dataSet))
            error(19);

        $page = $this->getPage();

        // render users
        if (!empty($this->dataSet)) {

            $page->setVar(array(
                'COUNTABLE_COUNT' => $this->dataCount,
                'REPORT_TITLE'      => "Login",
                'SORT_BASE'       => "login/{$this->getIndexID()}",
                'COUNTABLE'       => 'events'
            ));

            foreach ($this->dataSet as $log) {

                $action = $log['login'] == 0 ? "Failed login" : "Logged in";

                $userHandler = new User();

                $nameShards = array(
                    'title'     => $log['title'],
                    'firstname' => $log['firstname'],
                    'lastname'  => $log['lastname']);

                $displayName = $userHandler->formatName($nameShards);

                $page->setVar(array(
                    'USER_ID'   => $log['user'],
                    'DATE'      => $log['date'],
                    'USERNAME'  => $displayName,
                    'ACTION'    => $action,
                    'IP'        => $log['ip']
                ));

                $page->setVar('DARK', ++$j%2?'dark':'');

                $page->parse('activity_block', 'activity', true);
            }

            $page->parse('RESULT', 'activity_t', true);

            // set up filtering
            $this->displayFilterBlock();

        }
        // else empty data set
        else {
            $page->setVar(array(
                'RESULT'                => 'No results.',
                'COUNTABLE_COUNT'       => $this->dataCount,
                'REPORT_TITLE'          => $this->getReportTitle(),
                'previous_button_block' => '',
                'next_button_block'     => '',
                'page_number_block'     => ''
            ));
        }
    }

    public function export() {
        $exporter = new Report_Exporter($this->getReportTitle());
        $exporter->setHeadings($this->csvFields);
        $exporter->setData($this->fullDataSet);
        $exporter->export('CSV');
    }

    public function displayFilterBlock() {
        $page = $this->getPage();

        foreach($this->filterable as $field) {

            $output = array(
                'FILTER_ID'    => $field,
                'FILTER_NAME'  => $field,
                'FILTER_VALUE' => $this->filter[$field],
            );

            $page->setVar($output);
            $page->parse('search_options_block', 'search_options', true);
        }

        $page->parse('filter_block', 'filter', false);
    }
}
