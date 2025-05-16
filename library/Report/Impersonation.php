<?php

use Alaress\Database\Connector\IConnector;
use Schoolbox\Database\Factory;
use Schoolbox\Error\Mapper as ErrorMapper;

/**
 *
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
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
        'created_at' => 'created_at',
        'impersonator' => 'impersonator',
        'impersonated' => 'impersonated',
        'ip' => 'ip',
    );

    // csv mappings
    private $csvFields = array(
        'created_at' => 'Date',
        'impersonator_id' => 'Impersonator ID',
        'impersonator_username' => 'Impersonator Username',
        'impersonator_firstname' => 'Impersonator First Name',
        'impersonator_lastname'  => 'Impersonator Last Name',
        'impersonated_id' => 'Impersonated ID',
        'impersonated_username' => 'Impersonated Username',
        'impersonated_firstname' => 'Impersonated First Name',
        'impersonated_lastname' => 'Impersonated Last Name',
        'ip' => 'Originating IP',
    );

    // fields that we will permit sorting on
    private $sortable = array('created_at');

    // default sorting if none provided
    private $defaultOrderBy = 'created_at';

    // fields we permit filtering by
    private $filterable = array(
        'impersonator' => 'username, real name or partial',
        'impersonated' => 'username, real name or partial',
        'originating_ip' => 'x.x.x.x',
        'after'        => 'eg. 2012-01-06, yesterday',
        'before'       => 'eg. July 1, 3 days ago'
    );

    /**
     * @var IConnector
     */
    protected IConnector $db;

    public function __construct($page)
    {
        $this->setGroupHandler(new Group());

        if (!$this->checkPermissions()) {
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
        } else {
            $this->setPage($page);
            $this->setUserHandler(new User());
            $this->setup();
        }
    }

    protected function setup()
    {
        // set blocks
        $this->db = $this->getPage()->getDi()->get('databaseInternal');
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
        $this->fields = array_merge($this->fields, $fields);
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
                if ($key == 'impersonator') {
                    $sqlFilter[] = "impersonator.username LIKE :impersonator
                                        OR impersonator.firstname LIKE :impersonator
                                        OR impersonator.lastname LIKE :impersonator";
                    $params['impersonator'] = '%' . $this->db->getQuoter()->escapeForLike($value) . '%';
                } elseif ($key == 'impersonated') {
                    $sqlFilter[] = "impersonated.username LIKE :impersonated
                                        OR impersonated.firstname LIKE :impersonated
                                        OR impersonated.lastname LIKE :impersonated";
                    $params['impersonated'] = '%' . $this->db->getQuoter()->escapeForLike($value) . '%';
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
                } elseif ($key == 'originating_ip') {
                    $sqlFilter[] = "ip LIKE :originating_ip";
                    $params['originating_ip'] = '%' . $this->db->getQuoter()->escapeForLike($value) . '%';
                } else {
                    $sqlFilter[] = $this->db->getQuoter()->quoteIdentifier($key) . " LIKE :$key";
                    $params[$key] = '%' . $this->db->getQuoter()->escapeForLike($value) . '%';
                }
            }
        }

        $sqlFilterString = count($sqlFilter) ?
            sprintf(" WHERE (%s)", implode(') AND (', $sqlFilter)) :
            '';

        $joins = "LEFT JOIN user AS impersonator ON impersonator.username = user_assume_log.user
                  LEFT JOIN user AS impersonated ON impersonated.username = user_assume_log.assumed";

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
                        user_assume_log.created_at AS created_at,
                        ip,
                        impersonator.id AS impersonator_id,
                        impersonator.username AS impersonator_username,
                        impersonator.title AS impersonator_title,
                        impersonator.firstname AS impersonator_firstname,
                        impersonator.preferred_name AS impersonator_preferred_name,
                        impersonator.lastname AS impersonator_lastname,
                        impersonated.id AS impersonated_id,
                        impersonated.username AS impersonated_username,
                        impersonated.title AS impersonated_title,
                        impersonated.firstname AS impersonated_firstname,
                        impersonated.preferred_name AS impersonated_preferred_name,
                        impersonated.lastname AS impersonated_lastname
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
            throw ErrorMapper::getInstance()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
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

                $impersonatorNameShards = array(
                    'title'          => $log['impersonator_title'],
                    'firstname'      => $log['impersonator_firstname'],
                    'preferred_name' => $log['impersonator_preferred_name'],
                    'lastname'       => $log['impersonator_lastname'],
                );

                $impersonatedNameShards = array(
                    'title'          => $log['impersonated_title'],
                    'firstname'      => $log['impersonated_firstname'],
                    'preferred_name' => $log['impersonated_preferred_name'],
                    'lastname'       => $log['impersonated_lastname'],
                );

                $page->setVar(
                    array(
                        'DATE'               => Scrub::text($log['created_at']),
                        'IMPERSONATOR_ID'    => Scrub::text($log['impersonator_id']),
                        'IMPERSONATOR'       => Scrub::text($this->getUserHandler()->formatName($impersonatorNameShards)),
                        'IMPERSONATED_ID'    => Scrub::text($log['impersonated_id']),
                        'IMPERSONATED'       => Scrub::text($this->getUserHandler()->formatName($impersonatedNameShards)),
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
                'FILTER_NAME'        => ucwords(str_replace("_", " ", $field)),
                'FILTER_PLACEHOLDER' => $pholder,
                'FILTER_VALUE'       => $this->filter[$field] ?? '',
            );

            $page->setVar($output);
            $page->parse('search_options_block', 'search_options', true);
        }

        $page->parse('filter_block', 'filter', false);
    }
}
