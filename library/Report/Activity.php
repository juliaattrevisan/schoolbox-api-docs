<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Report_Activity extends Report_Abstract implements Report_IReport {

    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $pageNum;
    private $perPage;
    private $filter;
    private $dataResource;

    // visible field mapping: index => humanreadable
    private $fields = array(
        'time'   => 'date',
        'action' => 'action',
        'user'   => 'user',
    );

    // csv mappings
    private $csvFields = array(
        'time'      => 'date',
        'user_id'   => 'user id',
        'firstname' => 'firstname',
        'lastname'  => 'lastname',
        'action'    => 'action',
        'object'    => 'resource type',
        'objectId'  => 'resource'
    );

    // fields that we will permit sorting on
    private $sortable = array('time', 'action', 'user');
    private $filterable = array('keyword', 'user');
    private $defaultOrderBy = 'time';

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
        $this->getPage()->setFile('activity_t', 'adminv2/report/activity.html');
        $this->getPage()->setBlock('activity_t', 'activity', 'activity_block');
        $this->getPage()->setTitle('Activity Report');
    }

    public function setIndexID($groupID) {
        $this->groupID = $groupID;
    }

    public function getIndexID() {
        return $this->groupID;
    }

    public function getReportTitle() {
        return "Activity";
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

    public function populateDataset($offset, $limit, $orderBy = '', $orderDir = '') {

        if($orderDir == '') {
            $orderDir = 'DESC';
        }

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
            if(in_array($key, $this->filterable)) {
                $key = mysql_real_escape_string($key);
                $value = mysql_real_escape_string($value);
                $sqlFilter .= $sqlFilter == "" ? " WHERE" : " AND";
                // custom filter by user
                if($key == 'user') {
                    $sqlFilter .= " ( username LIKE '%$value%' OR firstname LIKE '%$value%' OR lastname LIKE '%$value%' ) ";
                }
                // custom filter by keyword
                elseif($key == 'keyword') {
                    $sqlFilter .=
" (
   c.name         LIKE '%$value%'
OR f.description  LIKE '%$value%'
OR f.realname     LIKE '%$value%'
OR l.description  LIKE '%$value%'
OR l.url          LIKE '%$value%'
OR mf.description LIKE '%$value%'
OR mf.realname    LIKE '%$value%'
OR ml.name        LIKE '%$value%'
) ";
                }
                // default filter key
                else {
                    $sqlFilter .= " $key LIKE '%$value%'";
                }
            }
        }

        $handler = new DeprecatedModel();

                $query =
"SELECT
cl.id AS cl_id, cl.object, cl.objectId, cl.action, cl.user AS user_id, cl.time,
u.username, u.title, u.firstname, u.lastname,
c.id AS c_id, c.name AS c_name,
f.id AS f_id, f.category AS f_cid, f.realname AS f_realname, f.description AS f_description,
l.id AS l_id, l.category AS l_cid, l.url AS l_url, l.description AS l_description,
mf.id AS mf_id, mf.owner AS mf_owner, mf.realname AS mf_realname, mf.description AS mf_description,
ml.id AS ml_id, ml.owner AS ml_owner, ml.name AS ml_name
FROM cmsLog cl
LEFT JOIN `user` u    ON u.id = cl.user
LEFT JOIN category c  ON cl.objectID = c.id  AND cl.object = 'folder'
LEFT JOIN `file` f    ON cl.objectID = f.id  AND cl.object = 'file'
LEFT JOIN `link` l    ON cl.objectID = l.id  AND cl.object = 'link'
LEFT JOIN myFile mf   ON cl.objectID = mf.id AND cl.object = 'myFile'
LEFT JOIN myFolder ml ON cl.objectID = ml.id AND cl.object = 'myFolder'
$sqlFilter
ORDER BY $orderBy $orderDir";

        $this->dataResource = $handler->db->query($query);
        $this->dataCount = mysql_num_rows($this->dataResource);

        if(!$this->dataCount) {
            return 0;
        }

        // get limited records from the resource, rather than in bulk
        $this->dataSet = $this->getRecordsFromResource($this->dataResource, $offset, $limit);

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
                'REPORT_TITLE'      => "Activity",
                'SORT_BASE'       => "activity/{$this->getIndexID()}",
                'COUNTABLE'       => 'events'
            ));

            foreach ($this->dataSet as $log) {

                $actionString = "";

                switch($log['action']) {
                    case 'modify':
                        $actionString .= 'Modified ';
                        break;
                    case 'create' :
                        $actionString .= 'Added ';
                        break;
                    case 'delete' :
                        $actionString .= 'Deleted ';
                        break;
                }

                switch($log['object']) {
                    case 'folder':
                        $actionString .= 'folder <a href="/homepage/' . $log['c_id'] . '">' . $log['c_name'] . '</a>';
                        break;
                    case 'file':
                        $actionString .= 'file <a href="/resources/' . $log['f_cid'] . '">' . $log['f_description'] . '</a>';
                        break;
                    case 'link':
                        $actionString .= 'link <a href="/resources/' . $log['l_cid'] . '">' . $log['l_description'] . '</a>';
                        break;
                    case 'myfile':
                        $actionString .= 'myFile <a href="/cms/portfolio/' . $log['mf_id'] . '">' . $log['mf_description'] . '</a>';
                        break;
                    case 'myfolder':
                        $actionString .= 'myFolder <a href="/cms/portfolio/' . $log['ml_id'] . '">' . $log['ml_name'] . '</a>';
                        break;
                    default:
                        $actionString .= 'unknown resource';

                }

                $userHandler = new User();
                $nameShards = array(
                    'title'     => $log['title'],
                    'firstname' => $log['firstname'],
                    'lastname'  => $log['lastname']);
                $displayName = $userHandler->formatName($nameShards);

                $page->setVar(array(
                    'UID'       => $log['user_id'],
                    'DATE'      => $log['time'],
                    'USERNAME'  => $displayName,
                    'ACTION'    => $actionString,
                    'IP'        => $log['ip']
                ));

                $page->setVar('DARK', ++$j%2?'dark':'');

                $page->parse('activity_block', 'activity', true);
            }

            $page->parse('RESULT', 'activity_t', true);

            // set up filtering
            $this->displayFilterBlock();

        } else {
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

        // we need to modify the dataset to substitute resourceIDs with their actual names / descriptions
        $exporter->setData($this->dataResource, array($this, 'dataManipulator'));
        $exporter->export('CSV');
    }

    public function dataManipulator($datum) {
        switch($datum['object']) {
            case 'folder':
                $datum['objectId'] = $datum['c_name'];
                break;
            case 'file':
                $datum['objectId'] = $datum['f_description'];
                break;
            case 'link':
                $datum['objectId'] = $datum['l_description'];
                break;
            case 'myfile':
                $datum['objectId'] = $datum['mf_description'];
                break;
            case 'myfolder':
                $datum['objectId'] = $datum['ml_name'];
                break;
            default:
                $datum['objectId'] = 'unknown resource';
        }

        return $datum;
    }

    public function displayFilterBlock() {
        $page = $this->getPage();

        foreach($this->filterable as $field) {

            $output = array(
                'FILTER_ID'          => $field,
                'FILTER_NAME'        => $field,
                'FILTER_PLACEHOLDER' => '', // not used
                'FILTER_VALUE'       => $this->filter[$field],
            );

            $page->setVar($output);
            $page->parse('search_options_block', 'search_options', true);
        }

        $page->parse('filter_block', 'filter', false);
    }
}
