<?php
/**
 * Collector for calls to the deprecated database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\DebugBar\DataCollector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class DeprecatedDatabaseCollector extends DataCollector implements Renderable, AssetProvider {

    protected $widget_name = 'oldDatabase';

    /**
     * The MySQL database connection, created by mysqli_connect
     *
     * @var \mysqli
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
        $start  = microtime(true);
        $result = $this->getResult($query);
        $statement = array(
            'sql'           => $query,
            'prepared_stmt' => $query,
            'params'        => array(),
            'is_success'    => $this->isSuccessful($result),
            'row_count'     => $this->getRowCount($result),
            'error_code'    => $this->getErrorCode(),
            'error_message' => $this->getErrorMessage(),
        );

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
     * Runs query and returns result
     *
     * @param  string $query
     * @return resource|bool
     */
    protected function getResult($query) {
        return mysqli_query($this->connection, $query);
    }

    /**
     * Gets row count from result
     *
     * @param  resource|bool $result
     * @return int
     */
    protected function getRowCount($result) {
        if ($result === true) {
            return mysqli_affected_rows($this->connection);
        }
        elseif ($result instanceof \mysqli_result) {
            return mysqli_num_rows($result);
        }
        return null;
    }

    /**
     * Gets error code from last query
     *
     * @return int
     */
    protected function getErrorCode() {
        return mysqli_errno($this->connection);
    }

    /**
     * Gets error from last query
     *
     * @return string
     */
    protected function getErrorMessage() {
        return mysqli_error($this->connection);
    }

    /**
     * Gets whether result from getResult() is successful
     *
     * @param  \mysqli_result|bool $result
     * @return bool
     */
    protected function isSuccessful($result) {
        return $result === true || $result instanceof \mysqli_result;
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
        return $this->widget_name;
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets() {
        return array(
            $this->widget_name => array(
                "widget"  => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map"     => $this->widget_name,
                "default" => "[]"
            ),
            $this->widget_name.":badge" => array(
                "map"     => $this->widget_name.".nb_statements",
                "default" => 0
            )
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getAssets()
    {
        return [
            'css' => 'widgets/sqlqueries/widget.css',
            'js' => 'widgets/sqlqueries/widget.js',
        ];
    }
}
