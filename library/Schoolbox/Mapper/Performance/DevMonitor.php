<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Mapper\Performance;

use Alaress\Database\Predicate\Builder;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Schoolbox\Mapper;

class DevMonitor extends AbstractLogger implements IMonitor
{
    /** @var array */
    protected $indexByIndex = [];

    /** @var array */
    protected $indexByColumn = [];

    /** @var array */
    protected $indexByColumnComposite = [];

    /** @var DebugBar */
    protected $debugBar;


    /**
     * @param DebugBar $debugBar
     */
    public function __construct(DebugBar $debugBar)
    {
        $this->debugBar = $debugBar;
    }


    /**
     * @param Mapper $mapper
     * @return void
     */
    public function cacheIndices(Mapper $mapper)
    {
        $t = $mapper->getTable();

        if (empty($t)) {
            // happens on external "mappers", it seems
            return;
        }

        $db   = $mapper->getDatabase();
        $stmt = $db->query("SHOW INDEX FROM $t");

        while ($r = $stmt->fetch(\PDO::FETCH_NUM)) {
            list (
                $table, $nonUnique, $keyName, $seqInIndex, $columnName,
                // $collation, $cardinality, $subPart, $packed, $null, $indexType, $comment, $indexComment
            ) = array_slice($r, 0, 5);

            $this->indexByIndex[$table][$keyName][$seqInIndex - 1] = $columnName;
            $this->indexByColumn[$table][$columnName][] = $keyName;
        }

        foreach ($this->indexByIndex as $table => $keys) {
            foreach ($keys as $keyName => $columns) {
                $this->indexByColumnComposite[$table][implode('-', $columns)] = $keyName;
            }
        }
        return;
    }


    /**
     * @param Builder $predicate
     * @param string  $selectFrom
     * @return void
     */
    public function assessPredicate(Builder $predicate, $selectFrom)
    {
        $where = $predicate->getWhere();

        $checks  = [];
        $aliases = [];

        if (preg_match_all('#`([a-z_]+)`(?: AS)? `([a-z_]+)`#', $selectFrom, $matches)) {
            for ($i = 0; $i < count($matches[0]); ++$i) {
                $aliases[$matches[2][$i]] = $matches[1][$i];
            }
            true;
        }

        foreach ($where as $clause) {
            if (!preg_match('#`([a-z_]+)`\.`([a-z_]+)`#i', $clause, $matches)) {
                // this *shouldn't* happen
                continue;
            }

            list ($table, $column) = array_slice($matches, 1);

            $t = array_key_exists($table, $aliases) ? $aliases[$table] : $table;

            // dedupe
            $checks[$t][$column] = $column;
        }

        $output = '';

        foreach ($checks as $table => $columns) {
            if (!array_key_exists($table, $this->indexByColumn)) {
                // this *shouldn't* happen
                continue;
            }

            $impl = implode('-', $columns);
            if (array_key_exists($impl, $this->indexByColumnComposite[$table])) {
                // exact index match
                $msg = count($columns) > 1
                    ? "Exact composite index match for $table($impl)"
                    : "Index match for $table($impl)"
                ;

                $this->getLog()->debug($msg);
                continue;
            }

            // test for full overlap of single keys
            if (array_intersect_key($checks[$table], $this->indexByColumn[$table]) == $checks[$table]) {
                $impl = implode(', ', $checks[$table]);
                $this->getLog()->info("Matched all indices for $table($impl)");
                continue;
            }

            // didn't get full / composite match: try for partials
            foreach ($columns as $column) {
                if (array_key_exists($column, $this->indexByColumn[$table])) {
                    $impl = implode(', ', $checks[$table]);
                    $this->getLog()->warning("Partial index match for `$column` within $table($impl)");
                } else {
                    $this->getLog()->error("Searched on unindexed field: $table.$column");
                }
            }
        }

        return;
    }


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return void
     */
    public function startExecuteSelectTimer(Mapper $mapper, Builder $predicateBuilder)
    {
        $query = $this->deriveQuery($mapper, $predicateBuilder);
        $key   = sha1($query);

        $this->getTimer()->startMeasure($key, $query);
    }


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return void
     */
    public function stopExecuteSelectTimer(Mapper $mapper, Builder $predicateBuilder)
    {
        $key = sha1($this->deriveQuery($mapper, $predicateBuilder));

        $this->getTimer()->stopMeasure($key);
    }


    /**
     * @param Mapper  $mapper
     * @param Builder $predicateBuilder
     * @return string
     */
    private function deriveQuery(Mapper $mapper, Builder $predicateBuilder)
    {
        $query  = "SELECT ... FROM {$mapper->getTable()} ... ";
        $params = $predicateBuilder->getParams();

        if (count($params)) {
            $query .= "WHERE " . implode(' AND ', $predicateBuilder->getWhere());
            $query  = str_replace(array_map(function ($v) {
                return ":$v";
            }, array_keys($params)), $params, $query);
        }

        return str_replace('`', '', $query);
    }


    /**
     * @return TimeDataCollector
     */
    protected function getTimer()
    {
        return $this->debugBar['time'];
    }


    /**
     * @return LoggerInterface
     */
    protected function getLog()
    {
        return $this->debugBar['messages'];
    }


    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
        $this->getLog()->log($level, $message, $context);
    }
}
