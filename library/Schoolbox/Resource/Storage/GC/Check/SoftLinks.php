<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\GC\Check;

use Database;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;

class SoftLinks implements Check
{

    /** @var Database */
    protected $db;

    /** @var string */
    protected $dbName;

    /** @var PDOStatement */
    protected $scanStmt;

    /** @var PDOStatement */
    protected $softStmt;

    /** @var PDOStatement[] */
    protected $hashTestStmts;

    /** @var array */
    protected $sources;

    /** @var LoggerInterface */
    protected $log;

    /**
     * This is the list of tables that contain text-type fields, but WILL NOT contain storage hashes anywhere within those fields
     *
     * Note: it speeds things up to not be scanning all these tables unnecessarily,
     * but if their schema changed for any reason in future to include possible hash references,
     * it would need to be known that they should be removed from this list.
     *
     * @var array
     */
    protected $blacklist = [
        'assess_luQuestionType', 'folder', 'class', 'component_config_definition', 'component_config_instance', 'error', 'file', 'myFile', 'myFolder', 'msg_source',
        'user_profile', 'dropbox', 'etherpad_pad', 'leave_request', 'user_assume_log', 'submission_mark_type', 'storage', 'homepageTemplate', 'luCampus', 'luResidentialHouse',
        'lu_leave_request_host_type', 'lu_resource_moderation_type', 'user', 'eportfolio_coverphoto', 'reportcard_comment', 'eotc_metadata', 'eotc_status', 'eotc_config',
        'plagscan_document', 'video_queue', 'scheduleq', 'rubric_descriptor', 'assess_question_category', 'calendar_connector', 'submission_return', 'wiki',
        'course_builder_config',
        'lu_pastoral_type',
        'lu_pastoral_severity',
        'pastoral_access',
        'pastoral_tags',
        'pastoral_attachment',
        'term',
        'user_password_reset_request',
        'blog_image',
        'tile',
        'ups_log',
        'eportfolio_component_blog_image',
    ];

    /**
     * @var int
     */
    private $limit;


    /**
     * @param Database        $db
     * @param string          $dbName
     * @param LoggerInterface $log
     * @param int             $limit
     * @param array           $blacklist
     */
    public function __construct(Database $db, $dbName, LoggerInterface $log = null, $limit = 0, $blacklist = [])
    {
        $this->db     = $db;
        $this->dbName = $dbName;
        $this->log    = $log ?: new Logger('null', [new NullHandler()]);
        $this->limit  = $limit;

        $this->cacheTables($blacklist);

    }


    /**
     * Set the list of tables that are not to be scanned
     *
     * @param array $blacklist
     * @return self
     */
    public function cacheTables(array $blacklist)
    {
        // fallback to default if nothing specified
        $blacklist = $blacklist ?: $this->blacklist;

        // set the blacklist
        $this->blacklist = $blacklist;

        // (re)cache our sources
        $this->sources = $this->fetchSourceMap();

        return $this;
    }


    /**
     * @return PDOStatement
     */
    protected function getScannableFieldsStatement()
    {
        // lazyload
        if (empty($this->scanStmt)) {
            $blacklist = implode("', '", $this->blacklist);

            // Unfortunately there are fields which are varchar and contain hash references.
            $this->scanStmt = $this->db->query("
                SELECT TABLE_NAME, COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE table_schema = '$this->dbName'
                AND (
                     data_type LIKE '%blob'
                  OR data_type LIKE '%text'
                  OR (
                        data_type = 'varchar'
                    AND character_maximum_length >= 40
                  )
                )
                AND table_name NOT IN ('$blacklist')
                ORDER BY TABLE_NAME, COLUMN_NAME
            ");
        }

        return $this->scanStmt;
    }


    /**
     * @return PDOStatement
     */
    protected function getSoftDeleteFieldsStatement()
    {
        if (empty($this->softStmt)) {
            $blacklist = implode("', '", $this->blacklist);

            $this->softStmt = $this->db->query("
                SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE
                FROM information_schema.COLUMNS
                WHERE table_schema = '$this->dbName'
                AND table_name NOT IN ('$blacklist')
                AND column_name LIKE 'delete%'
                ORDER BY TABLE_NAME, COLUMN_NAME;
            ");
        }

        return $this->softStmt;
    }


    /**
     * @param string $table
     * @return int
     */
    protected function fetchRowCount($table)
    {
        return (int)$this->db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    }


    /**
     * Fetch a table-indexed array of the fields we're operating on
     *
     * @return array
     */
    protected function fetchSourceMap()
    {
        $sources = [];
        $i       = 0;

        // cache the list of fields to scan
        while ([$table, $field] = $this->getScannableFieldsStatement()->fetch(PDO::FETCH_NUM)) {
            // index by table
            $sources[$table]['fields'][] = $field;
            $sources[$table]['delete'] = ''; // ensure this is set, even if blank
            ++$i;
        }

        $j = count($sources);

        $this->log->info("Scanning $i fields from $j tables");

        // cache the list of soft-delete fields to compensate for
        while ([$table, $field, $type] = $this->getSoftDeleteFieldsStatement()->fetch(PDO::FETCH_NUM)) {

            // we're only interested in tables we've already seen
            if (!array_key_exists($table, $sources)) {
                continue;
            }

            switch($type) {
                case 'datetime':
                case 'timestamp':
                    // confirm nullness of datetime-deleted
                    $shard = "AND `$field` IS NULL";
                    break;
                case 'int':
                case 'tinyint':
                    // confirm falseness of boolean-deleted
                    $shard = "AND `$field` IS FALSE";
                    break;
                case 'enum':
                    // *sigh*
                    $shard = "AND `$field` = 'false'";
                    break;
                default:
                    // not sure how you could get into this state, but accommodate it
                    $this->log->warning("Encountered soft-delete field of unexpected type: $table.$field ($type)");
                    $shard = '';
                    break;
            }

            // index by table
            $sources[$table]['delete'] = $shard;
        }

        return $sources;
    }


    /**
     * @return array
     */
    protected function getTableList()
    {
        return array_keys($this->sources);
    }


    /**
     * @param string $table
     * @return array
     */
    protected function getTableFields($table)
    {
        return $this->sources[$table]['fields'];
    }


    /**
     * @param string $table
     * @return string
     */
    protected function getTableSoftDeleteShard($table)
    {
        return $this->sources[$table]['delete'];
    }


    /**
     * Return whether or not the given hash is present on this table
     *
     * @param string $table
     * @param string $hash
     * @return bool
     */
    protected function tableContainsHash($table, $hash)
    {

        // prepare on-demand
        if (empty($this->hashTestStmts[$table])) {
            // retreive cache information
            $fields = $this->getTableFields($table);
            $delete = $this->getTableSoftDeleteShard($table);

            // compile query
            $like = implode('` LIKE :hash OR `', $fields);
            $this->hashTestStmts[$table] = $this->db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$like` LIKE :hash $delete");
        }

        // search for this hash
        $this->hashTestStmts[$table]->execute(['hash' => "%$hash%"]);

        // if fetchColumn > 0, this hash is "seen"
        return $this->hashTestStmts[$table]->fetchColumn() > 0;
    }


    /**
     * Takes an array of hashes, and returns the subset that this check regards as 'stale'
     *
     * @param array $test
     * @return array
     */
    public function getStale(array $test)
    {
        if ($this->limit) {
            $test = array_slice($test, 0, $this->limit);
        }

        $debug   = $this->log instanceof \Alaress\Lumberjack\Logger && $this->log->getThreshold() <= Logger::DEBUG;

        foreach ($this->getTableList() as $table) {

            $fields = $this->getTableFields($table);

            if (!count($test)) {
                $this->log->info("No more hashes to test; bailing out");
                break;
            }

            // stats
            $rowCount = $this->fetchRowCount($table);
            $progress = (int)(count($test) / 100) ?: 1;
            $i        = 0;
            $seen     = [];
            $hit      = false;

            if (!$rowCount) {
                $this->log->debug(sprintf("Skipping `%s` (`%s`) [no records]", $table, implode('`, `', $fields)));
                continue;
            }

            $this->log->debug(sprintf("Scanning `%s` (`%s`) [%s records]", $table, implode('`, `', $fields), $rowCount));

            foreach ($test as $k => $hash) {
                if ($this->tableContainsHash($table, $hash)) {
                    // remove from list
                    unset($test[$k]);
                    $seen[$hash] = true;
                    $hit = true;
                }

                if ($debug && !(++$i % $progress)) {
                    echo $hit ? "!" : "=";
                    $hit = false;
                }
            }

            if ($debug) {
                echo "\n";
            }

            if (count($seen)) {
                $this->log->info(sprintf("Detected %d hash%s to retain in `%s`", count($seen), count($seen) == 1 ? '' : 'es',  $table), ['char' => '+']);
            }


        }

        return array_values($test);
    }

}
