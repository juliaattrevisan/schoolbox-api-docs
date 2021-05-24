<?php

/**
 * Collector for calls to a MSSQL database.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\DebugBar\DataCollector;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class MssqlDatabaseCollector extends DataCollector implements Renderable, AssetProvider
{
    protected $widget_name = 'externalDatabase';

    /**
     * The MSSQL database connection
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
     * @param resource $connection A MSSQL database connection
     */
    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    /**
     * Runs query, and logs its results.
     *
     * @param string $query
     *
     * @return resource Query resource, or boolean success|failure
     */
    public function query($query)
    {
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
     * {@inheritdoc}
     */
    protected function getResult($query)
    {
        return mssql_query($query, $this->connection);
    }

    /**
     * {@inheritdoc}
     */
    protected function getRowCount($result)
    {
        if ($result === true) {
            return mssql_rows_affected($this->connection);
        } elseif (is_resource($result)) {
            return mssql_num_rows($result);
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorCode()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getErrorMessage()
    {
        return mssql_get_last_message();
    }

    /**
     * Gets whether result from getResult() is successful
     *
     * @param  \mysqli_result|bool $result
     * @return bool
     */
    protected function isSuccessful($result)
    {
        return $result === true || $result instanceof \mysqli_result;
    }

    /**
     * {@inheritDoc}
     */
    public function collect()
    {
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
    public function getName()
    {
        return $this->widget_name;
    }

    /**
     * {@inheritDoc}
     */
    public function getWidgets()
    {
        return array(
            $this->widget_name => array(
                "widget"  => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map"     => $this->widget_name,
                "default" => "[]"
            ),
            $this->widget_name . ":badge" => array(
                "map"     => $this->widget_name . ".nb_statements",
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
