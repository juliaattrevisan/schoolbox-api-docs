<?php

/**
 * Collector for calls to a database connector
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Database;

use Alaress\Database\Broker\IBroker;
use Alaress\Database\Statement\IStatement;
use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

class Collector extends DataCollector implements Renderable, AssetProvider
{
    /** @var string $widget_name */
    protected $widget_name = "Alaress/Database - ";

    /** @var IBroker $broker */
    protected $broker;

    /** @var array $statements List of statements run */
    protected $statements = array();

    /**
     * Constructor
     *
     * @param IBroker $broker
     */
    public function __construct($broker)
    {
        $this->broker = $broker;
        $this->widget_name .= str_replace(".", "", $broker->getHostname());
    }

    /**
     * Runs query, and logs its results.
     *
     * @param IStatement $statement
     * @return bool
     * @throws \Exception Any exception that the statement throws in execution
     *                    gets thrown here.
     */
    public function execute($statement)
    {
        $start = microtime(true);

        // Catch and throw exception later
        $queryException = null;
        try {
            $result = $statement->getPDOStatement()->execute();
        } catch (\Exception $e) {
            $queryException = $e;
            $result = false;
        }

        $backtrace = debug_backtrace();

        $sql = '';
        $prefix = 'Alaress\Database';

        // Used this trying to find where queries came from

        foreach ($backtrace as $trace) {
            if (substr($trace['class'] ?? '', 0, strlen($prefix)) != $prefix) {
                $sql .= sprintf(
                    "Statement Class: %s\nLine %d: %s::%s()\n\n",
                    get_class($statement),
                    $trace['line'] ?? '<unknown>',
                    $trace['file'] ?? '<unknown>',
                    $trace['function'],
                );

                break;
            }
        }

        $sql .= $statement->getSql();

        $statementSummary = [
            'sql' => $sql,
            'prepared_stmt' => $statement->getSql(),
            'params' => [],
            'is_success' => $result,
            'row_count' => $statement->rowCount(),
            'error_code' => '',
            'error_message' => '',
        ];

        $duration = microtime(true) - $start;
        $memory = memory_get_peak_usage(true);

        $statementSummary['connection'] = $this->broker->getDatabase();
        $statementSummary['duration'] = $duration;
        $statementSummary['duration_str'] = $this->formatDuration($duration);
        $statementSummary['memory'] = $memory;
        $statementSummary['memory_str'] = $this->formatBytes($memory);

        if ($queryException instanceof \Exception) {
            $statementSummary['error_code'] = $queryException->getCode();
            $statementSummary['error_message'] = $queryException->getMessage();
        }

        $this->statements[] = $statementSummary;

        if ($queryException instanceof \Exception) {
            throw $queryException;
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function collect()
    {
        $result = array(
            'nb_statements' => count($this->statements),
            'nb_failed_statements' => 0,
            'accumulated_duration' => 0,
            'peak_memory_usage' => 0,
            'statements' => $this->statements
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
        $result['peak_memory_usage_str'] = $this->formatBytes($result['peak_memory_usage']);

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
                "widget" => "PhpDebugBar.Widgets.SQLQueriesWidget",
                "map" => $this->widget_name,
                "default" => "[]"
            ),
            $this->widget_name . ":badge" => array(
                "map" => $this->widget_name . ".nb_statements",
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
