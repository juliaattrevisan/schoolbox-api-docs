<?php

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Report_Impersonation extends Report_Abstract implements Report_IReport
{
    private $groupID = null;
    private $dataSet;
    private $dataCount;
    private $filter;

    // visible field mapping: index => human readable
    private $fields = array(
        'user_assume_log.created_at' => 'user_assume_log.created_at',
        'user'       => 'user',
        'ip'         => 'ip',
    );

    // csv mappings
    private $csvFields = array(
        'date'  => 'date',
        'username'   => 'username',
        'user_id'    => 'user id',
        'firstname'  => 'firstname',
        'lastname'   => 'lastname',
        'ip'         => 'originating ip address',
    );

    // fields that we will permit sorting on
    private $sortable = array('user_assume_log.created_at', 'user', 'ip');

    // default sorting if none provided
    private $defaultOrderBy = 'user_assume_log.created_at';

    // fields we permit filtering by
    private $filterable = array(
        'user'       => 'username, real name or partial',
        'ip'         => 'x.x.x.x',
        'after'      => 'eg. 2012-01-06, yesterday',
        'before'     => 'eg. July 1, 3 days ago'
    );

    /**
     * @var IConnector
     */
    protected IConnector $db;

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
        $this->db = Factory::getInstanceInternal();
        $this->getPage()->setFile('activity_t', 'adminv2/report/impersonation.html');
        $this->getPage()->setBlock('activity_t', 'activity', 'activity_block');
        $this->getPage()->setTitle('Impersonation Report');
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
        return "Impersonation";
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

    public function getFilterFields()
    {
        return array_keys($this->filterable);
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
        $filterSplit = explode(Report_Factory::PARAM_SEPARATOR, $filterString);
        $resultFilter = array();

        foreach ($filterSplit as $split) {
            $thisFilter = explode('=', $split);
            if (count($thisFilter) == 2) {
                $resultFilter[$thisFilter[0]] = urldecode(urldecode($thisFilter[1]));
            }
        }

        $resultFilter['after'] = $resultFilter['after'] ?? date('Y-m-d', strtotime('-30 days'));

        $this->filter = $resultFilter;

        $sqlFilter = array();
        $params = array();
        foreach ($resultFilter as $key => $value) {
            if (in_array($key, $this->getFilterFields())) {
                // custom filter by user
                if ($key == 'user') {
                    $sqlFilter[] = "user.username LIKE :value
                                        OR user.firstname LIKE :value
                                        OR user.lastname LIKE :value";
                    $params['value'] = '%' . $this->db->getQuoter()->escapeForLike($value) . '%';
                } elseif ($key == 'after') {
                    if ($date = strtotime($value)) {
                        $date = date('Y-m-d H:i:s', $date);
                        $sqlFilter[] = "user_assume_log.created_at >= :after";
                        $params['after'] = $date;
                    }
                } elseif ($key == 'before') {
                    if ($date = strtotime($value)) {
                        $date = date('Y-m-d H:i:s', $date);
                        $sqlFilter[] = "user_assume_log.created_at <= :before";
                        $params['before'] = $date;
                    }
                } else {
                    $sqlFilter[] = $this->db->getQuoter()->quoteIdentifier($key) . " LIKE :$key";
                    $params[$key] = '%' . $this->db->getQuoter()->escapeForLike($value) . '%';
                }
            }
        }

        $sqlFilterString = count($sqlFilter) ?
            sprintf(" WHERE (%s)", implode(') AND (', $sqlFilter)) :
            '';

        $joins = "LEFT JOIN user ON user.username = user_assume_log.user AND user.deleted_at IS NULL";

        // Get count separately from data
        $countStatement = $this->db->prepare("SELECT COUNT(*) FROM user_assume_log $joins $sqlFilterString");
        foreach ($params as $name => $value) {
            $countStatement->bindValue($name, $value);
        }
        $countStatement->execute();
        $this->dataCount = $countStatement->fetchColumn();
        if (!$this->dataCount) {
            return 0;
        }

        $dataQuery = "SELECT
                        user_assume_log.user,
                        user_assume_log.created_at,
                        ip,
                        user.id AS user_id,
                        title,
                        firstname,
                        preferred_name,
                        lastname
                      FROM user_assume_log
                      $joins
                      $sqlFilterString
                      ORDER BY $orderBy $orderDir
                      ";
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
                    'REPORT_TITLE'    => Scrub::text($this->getReportTitle()),
                    'SORT_BASE'       => "Impersonation/{$this->getIndexID()}",
                    'COUNTABLE'       => 'events'
                )
            );

            foreach ($this->dataSet as $log) {
                $page->setVar(
                    [
                        'log_user_block'    => '',
                        'log_no_user_block' => '',
                    ]
                );

                $userHandler = new User();

                $nameShards = array(
                    'title'          => $log['title'],
                    'firstname'      => $log['firstname'],
                    'preferred_name' => $log['preferred_name'],
                    'lastname'       => $log['lastname']
                );

                $page->setVar(
                    array(
                        'UID'                => Scrub::text($log['user_id']),
                        'DATE'               => Scrub::text($log['created_at']),
                        // 'USERNAME'           => Scrub::text($log['username']),
                        'DISPLAYNAME'        => Scrub::text($userHandler->formatName($nameShards)),
                        'IP'                 => Scrub::text($log['ip']),
                    )
                );

                $page->parse('activity_block', 'activity', true);
            }

            $page->parse('RESULT', 'activity_t', true);

            // set up filtering
            $this->displayFilterBlock();
        } else {
            // else empty data set
            $page->setVar(
                array(
                    'RESULT'                => 'No results.',
                    'COUNTABLE_COUNT'       => $this->dataCount,
                    'REPORT_TITLE'          => Scrub::text($this->getReportTitle()),
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
        $exporter->setData($this->dataSet);
        $exporter->export('CSV');
    }

    public function displayFilterBlock()
    {
        $page = $this->getPage();

        foreach ($this->filterable as $field => $pholder) {
            $output = array(
                'FILTER_ID'          => $field,
                'FILTER_NAME'        => ucwords($field),
                'FILTER_PLACEHOLDER' => $pholder,
                'FILTER_VALUE'       => $this->filter[$field] ?? '',
            );

            $page->setVar($output);
            $page->parse('search_options_block', 'search_options', true);
        }

        $page->parse('filter_block', 'filter', false);
    }
}
