<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Report_Activity extends Report_Abstract implements Report_IReport
{

    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $filter;

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
    private $filterable = array('keyword', 'user', 'action', 'after', 'before');
    private $defaultOrderBy = 'time';

    /**
     * @var Database
     */
    protected $db;

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
        $this->db = Database::getInstance();
        $this->getPage()->setFile('activity_t', 'adminv2/report/activity.html');
        $this->getPage()->setBlock('activity_t', 'activity', 'activity_block');
        $this->getPage()->setTitle('Activity Report');
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
        return "Activity";
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
            $orderDir = 'DESC';
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


        // explode across comma to get keypairs
        $filterSplit = explode('&', $filterString);
        $resultFilter = array();

        foreach ($filterSplit as $split) {
            $thisFilter = explode('=', $split);
            if (count($thisFilter) == 2) {
                $resultFilter[$thisFilter[0]] = $thisFilter[1];
            }
        }

        $resultFilter['after'] = $resultFilter['after'] ?: date('Y-m-d', strtotime('-30 days'));

        $this->filter = $resultFilter;

        $sqlFilter = array();
        $params = array();

        foreach ($resultFilter as $key => $value) {
            if (in_array($key, $this->filterable)) {
                // custom filter by user
                if ($key == 'user') {
                    $sqlFilter[] = "(username LIKE :user OR firstname LIKE :user OR lastname LIKE :user)";
                    $params['user'] = $value;
                } else if ($key == 'action') {
                    $sqlFilter[] = "cl.action = :action";
                    $params['action'] = $value;
                } // custom filter by keyword
                else if ($key == 'keyword') {
                    $sqlFilter[] =
                        "(
   c.name         LIKE :keyword
OR f.description  LIKE :keyword
OR f.realname     LIKE :keyword
OR cfile.name     LIKE :keyword
OR l.description  LIKE :keyword
OR l.url          LIKE :keyword
OR clink.name     LIKE :keyword
OR mf.description LIKE :keyword
OR mf.realname    LIKE :keyword
OR ml.name        LIKE :keyword
)";
                    $params['keyword'] = '%' . $this->db->escapeForLike($value) . '%';
                } else if ($key == 'after') {
                    if ($date = strtotime($value)) {
                        $date = date('Y-m-d H:i:s', $date);
                        $sqlFilter[] = "`time` >= :after";
                        $params['after'] = $date;
                    }
                } else if ($key == 'before') {
                    if ($date = strtotime($value)) {
                        $date = date('Y-m-d H:i:s', $date);
                        $sqlFilter[] = "`time` <= :before";
                        $params['before'] = $date;
                    }
                } // default filter key
                else {
                    $sqlFilter[] = $this->db->quoteIdentifier($key) . " LIKE :$key";
                    $params[$key] = '%' . $this->db->escapeForLike($value) . '%';
                }
            }
        }

        $sqlFilterString = count($sqlFilter) ?
            sprintf(" WHERE (%s)", implode(') AND (', $sqlFilter)) :
            '';

        $joins = "
            LEFT JOIN `user` u    ON u.id = cl.user
            LEFT JOIN folder c  ON cl.objectID = c.id  AND cl.object = 'folder'
            LEFT JOIN folder cfolder ON c.parent = cfolder.id
            LEFT JOIN `file` f    ON cl.objectID = f.id  AND cl.object = 'file'
            LEFT JOIN folder cfile ON f.category = cfile.id
            LEFT JOIN `link` l    ON cl.objectID = l.id  AND cl.object = 'link'
            LEFT JOIN folder clink ON l.category = clink.id
            LEFT JOIN myFile mf   ON cl.objectID = mf.id AND cl.object = 'myFile'
            LEFT JOIN myFolder ml ON cl.objectID = ml.id AND cl.object = 'myFolder'
            LEFT JOIN textBox t   ON cl.objectID = t.textBoxId AND cl.object = 'content'
        ";

        // Get count separately from data
        $countStatement = $this->db->prepare("SELECT COUNT(*) FROM cmsLog cl $joins $sqlFilterString");
        foreach ($params as $name => $value) {
            $countStatement->bindValue($name, $value);
        }
        $countStatement->execute();
        $this->dataCount = $countStatement->fetchColumn();
        if (!$this->dataCount) {
            return 0;
        }

        $dataQuery = "SELECT
            cl.id AS cl_id, cl.object, cl.objectId, cl.action, cl.user AS user_id, cl.time,
            u.username, u.title, u.firstname, u.lastname,
            c.id AS c_id, c.name AS c_name,
            cfolder.id AS cf_id, cfolder.name AS cf_name,
            f.id AS f_id, f.category AS f_cid, f.realname AS f_realname, f.description AS f_description, cfile.name AS f_catname,
            l.id AS l_id, l.category AS l_cid, l.url AS l_url, l.description AS l_description,  clink.name AS l_catname,
            mf.id AS mf_id, mf.owner AS mf_owner, mf.realname AS mf_realname, mf.description AS mf_description,
            ml.id AS ml_id, ml.owner AS ml_owner, ml.name AS ml_name,
            t.heading as textbox_heading, t.categoryID as textbox_cid
            FROM cmsLog cl
            $joins
            $sqlFilterString
            ORDER BY $orderBy $orderDir";
        if ($limit > 0) {
            $dataQuery .= ' LIMIT :limit';
        }
        if ($offset > 0) {
            $dataQuery .= ' OFFSET :offset';
        }
        $statement = $this->db->prepare($dataQuery);
        foreach ($params as $name => $value) {
            $statement->bindValue($name, $value);
        }
        if ($limit > 0) {
            $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        }
        if ($offset > 0) {
            $statement->bindValue('offset', $offset, PDO::PARAM_INT);
        }
        $statement->execute();

        // get limited records from the resource, rather than in bulk
        $this->dataSet = $statement;

        return $this->dataCount;
    }


    /**
     * @throws Exception
     */
    public function render(): void
    {
        // the group and the dataset must both not be null
        if ($this->getIndexID() == null || empty($this->dataSet)) {
            error(19);
        }

        $page = $this->getPage();

        // render users
        if (!empty($this->dataSet)) {

            $page->setVar(
                array(
                    'COUNTABLE_COUNT' => $this->dataCount,
                    'REPORT_TITLE'    => "Activity",
                    'SORT_BASE'       => "activity/{$this->getIndexID()}",
                    'COUNTABLE'       => 'events'
                )
            );

            foreach ($this->dataSet as $log) {

                $actionString = '';
                $locationPrefix = 'in';
                $moveSuffix = '';

                switch ($log['action']) {
                    case 'modify':
                        $actionString .= 'Modified ';
                        break;
                    case 'move':
                        $actionString .= 'Moved ';
                        $locationPrefix = 'to';
                        $moveSuffix = ' to <a href=/homepage/' . $log['cf_id'] . '>' . $log['cf_name'] . '</a>';
                        break;
                    case 'create' :
                        $actionString .= 'Added ';
                        break;
                    case 'delete' :
                        $actionString .= 'Deleted ';
                        break;
                    case 'access' :
                        $actionString .= 'Accessed ';
                        break;
                }

                switch ($log['object']) {
                    case 'folder':
                        $actionString .= 'folder <a href="/homepage/' . $log['c_id'] . '">' . Scrub::text($log['c_name']) . $moveSuffix . ' </a>';
                        break;
                    case 'file':
                        $actionString .= 'file <a href="/send.php?id=' . $log['f_id'] . '">' . Scrub::text($log['f_description']) . '</a> ' . $locationPrefix . ' <a href=/homepage/' . $log['f_cid'] . '>' . $log['f_catname'] . '</a>';
                        break;
                    case 'link':
                        $actionString .= 'link <a href="/redir.php?id=' . $log['l_id'] . '">' . Scrub::text($log['l_description']) . '</a> ' . $locationPrefix . ' <a href=/homepage/' . $log['l_cid'] . '>' . $log['l_catname'] . '</a>';
                        break;
                    case 'myfile':
                        $actionString .= 'myFile <a href="/my-files/' . $log['mf_id'] . '">' . Scrub::text($log['mf_description']) . '</a>';
                        break;
                    case 'myfolder':
                        $actionString .= 'myFolder <a href="/my-files/' . $log['ml_id'] . '">' . Scrub::text($log['ml_name']) . '</a>';
                        break;
                    case 'content':
                        $actionString .= 'textbox <a href="/homepage/' . $log['textbox_cid'] . '">' . Scrub::text($log['textbox_heading']) . '</a>';
                        break;
                    default:
                        $actionString .= 'unknown resource';
                        break;
                }

                $userHandler = new User();
                $nameShards = array(
                    'title'     => $log['title'],
                    'firstname' => $log['firstname'],
                    'lastname'  => $log['lastname']
                );
                $displayName = $userHandler->formatName($nameShards);

                $page->setVar(
                    array(
                        'UID'      => $log['user_id'],
                        'DATE'     => $log['time'],
                        'USERNAME' => Scrub::text($displayName),
                        'ACTION'   => $actionString,
                        'IP'       => $log['ip']
                    )
                );

                $page->parse('activity_block', 'activity', true);
            }

            $page->parse('RESULT', 'activity_t', true);

            // set up filtering
            $this->displayFilterBlock();

        } else {
            $page->setVar(
                array(
                    'RESULT'                => 'No results.',
                    'COUNTABLE_COUNT'       => $this->dataCount,
                    'REPORT_TITLE'          => $this->getReportTitle(),
                    'previous_button_block' => '',
                    'next_button_block'     => '',
                    'page_number_block'     => ''
                )
            );
        }
    }

    public function export()
    {
        $exporter = new Report_Exporter($this->getReportTitle());
        $exporter->setHeadings($this->csvFields);

        // we need to modify the dataset to substitute resourceIDs with their actual names / descriptions
        $exporter->setData($this->dataSet, array($this, 'dataManipulator'));
        $exporter->export('CSV');
    }

    public function dataManipulator($datum)
    {
        switch ($datum['object']) {
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
            case 'content':
                $datum['objectId'] = $datum['textbox_heading'];
                break;
            default:
                $datum['objectId'] = 'unknown resource';
        }

        return $datum;
    }

    public function displayFilterBlock()
    {
        $page = $this->getPage();

        foreach ($this->filterable as $field) {

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
