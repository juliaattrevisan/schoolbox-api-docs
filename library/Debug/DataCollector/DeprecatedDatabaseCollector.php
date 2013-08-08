<?php
/**
 * Collector for calls to the deprecated database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Debug\DataCollector;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class DeprecatedDatabaseCollector extends DataCollector implements Renderable {

    /**
     * The MySQL database connection, created by mysql_connect
     *
     * @var resource
     */
    protected $connection;

    /**
     * List of statements run
     *
     * @var array
     */
    protected $statements = array();

    /**
     * @param resource $connection A MySQL database connection
     */
    public function __construct($connection) {
        $this->connection = $connection;
    }

    /**
     * Runs query, and logs its results.
     *
     * @param string $query
     *
     * @return resource Query resource, or boolean success|failure
     */
    public function query($query) {
        $statement = array(
            'sql'           => $query,
            'prepared_stmt' => $query,
            'params'        => array(),
            'is_success'    => true,
        );
        $start  = microtime(true);
        $result = mysql_query($query, $this->connection);
        if ($result === true) {
            $statement['row_count'] = mysql_affected_rows($this->connection);
        }
        elseif (is_resource($result)) {
            $statement['row_count'] = mysql_num_rows($result);
        }
        else {
            $statement['is_success']    = false;
            $statement['error_code']    = mysql_errno($this->connection);
            $statement['error_message'] = mysql_error($this->connection);
        }
        $duration = microtime(true) - $start;
        $memory   = memory_get_peak_usage(true);
        $statement['duration']     = $duration;
        $statement['duration_str'] = $this->formatDuration($duration);
        $statement['memory']       = $memory;
        $statement['memory_str']   = $this->formatBytes($memory);
        $this->statements[] = $statement;
        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function collect() {

        $result = array(
            'nb_statements'        => count($this->statements),
            'nb_failed_statements' => 0,
            'accumulated_duration' => 0,
            'peak_memory_usage'    => 0,
            'statements'           => $this->statements
        );

        foreach ($this->statements as $statement) {
            if (!$statement['is_success']) {
                $result['nb_failed_statements']++;
            }
            $result['accumulated_duration'] += $statement['duration'];
            if ($statement['memory'] > $result['peak_memory_usage']) {
                $result['peak_memory_usage'] = $statement['memory'];
            }
        }

        $result['accumulated_duration_str'] = $this->formatDuration($result['accumulated_duration']);
        $result['peak_memory_usage_str']    = $this->formatBytes($result['peak_memory_usage']);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function getName() {
        return 'oldDatabase';
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets() {
        return array(
            "oldDatabase" => array(
                "widget"  => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map"     => "oldDatabase",
                "default" => "[]"
            ),
            "oldDatabase:badge" => array(
                "map"     => "oldDatabase.nb_statements",
                "default" => 0
            )
        );
    }
}
