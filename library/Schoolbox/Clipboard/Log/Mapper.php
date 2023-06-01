<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Clipboard\Log;

use Alaress\Database\Predicate\InvalidColumnException;
use Schoolbox\GenericCollection;

/**
 * @method Model createModel($idValue = null)
 * @method GenericCollection|Model[] findAll($options = [])
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * Anything older than now minus EXPIRY_THRESHOLD days gets deleted
     */
    private const EXPIRY_THRESHOLD = 7;


    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('clipboard_import_log');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('run_id', self::TYPE_STRING);
        $this->uses('script', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('finish_at', self::TYPE_DATETIME);
        $this->uses('status', self::TYPE_STRING);
        $this->uses('error_message', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * Find the most recent log stats
     *
     * @return GenericCollection
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnderflowException
     * @throws \UnexpectedValueException
     */
    public function status(): GenericCollection
    {
        /* Only look for the KNOWN_SCRIPTS that ran today and not any other day
         */
        $pb = $this->getPredicateBuilder()
            ->in('script', Model::KNOWN_SCRIPTS);

        $where = implode(' AND ', $pb->getWhere());

        $query = "
            SELECT *
            FROM {$this->getTable()}
            WHERE run_id = (
                SELECT run_id
                FROM {$this->getTable()}
                ORDER BY id DESC
                LIMIT 1
            )
            AND {$where}
            ORDER BY start_at
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());

        $stack = $this->createCollection();

        while ($row = $statement->fetch()) {
            $stack->add($this->hydrate($row));
        }

        return $stack;
    }


    /**
     * This is used to determine the next Partial Mode start timestamp
     * This mode always spans the tuple (last import time, current time)
     *
     * First Usage.
     * If the clipboard import has never run, the table will be empty;
     * this method returns null and should be interpreted as "start of day"
     *
     * @return \Alaress_Date_Format
     * @throws InvalidColumnException
     */
    public function findNextPartialDate(): \Alaress_Date_Format
    {
        $lastRunLog = $this->findLastCompleted();

        /* Never run? Presume start of today
         */
        if (null === $lastRunLog) {
            return \Alaress_Date_Format::now()->startOfDay();
        }

        return $lastRunLog->getFinishAt();
    }


    /**
     * Fetches the most recently completed log
     *
     * @return Model|null
     * @throws InvalidColumnException
     */
    public function findLastCompleted(): ?Model
    {
        /* Find the import that most recently successfully completed
         * Broken imports won't have a finish_at
         */
        $pb = $this->getPredicateBuilder()
            ->equals('status', Model::STATUS_COMPLETE)
            ->notNull('finish_at');

        return $this->findByPredicates($pb, [
            'order' => [
                'finish_at' => 'desc'
            ]])->first();
    }


    /**
     * Find all the logs with Duration metadata pre-calculated
     *
     * @return array
     */
    public function findLogsWithDuration(): array
    {
        $logs = $this->findAll([
            'order' => [
                'start_at' => 'desc'
            ]
        ]);

        $stack = [];

        foreach ($logs as $log) {
            $duration = null;
            $durationHms = null;

            if ($log->getFinishAt()) {
                $duration = $log->getFinishAt()->diffInSeconds($log->getStartAt());
                $durationHms = (new \Alaress\Date\Interval($duration))->hms();
            }

            $stack[] = [
                'instance' => $log,
                'duration' => [
                    'sec' => $duration,
                    'hms' => $durationHms
                ]
            ];
        }

        return $stack;
    }


    /**
     * A macro to set up and save a new record
     *
     * @param string $runId
     * @param string $scriptName
     * @return Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function start(string $runId, string $scriptName): Model
    {
        $importLog = $this->createModel()
            ->setRunId($runId)
            ->setScript($scriptName)
            ->setStatus(Model::STATUS_RUNNING)
            ->setStartAt(\Alaress_Date_Format::newFromTimestamp(time()));

        $this->save($importLog);

        return $importLog;
    }


    /**
     * Update with Complete status with the time it ended
     *
     * @param Model $importLog
     * @return Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function finish(Model $importLog): Model
    {
        $importLog
            ->setStatus(Model::STATUS_COMPLETE)
            ->setFinishAt(\Alaress_Date_Format::newFromTimestamp(time()));

        $this->save($importLog);

        return $importLog;
    }


    /**
     * Update with Error status and error message, for diagnostics
     *
     * @param Model  $importLog
     * @param string $errorMessage
     * @return Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function error(Model $importLog, string $errorMessage): Model
    {
        $importLog
            ->setStatus(Model::STATUS_ERROR)
            ->setErrorMessage($errorMessage);

        $this->save($importLog);

        return $importLog;
    }


    /**
     * Anything older than EXPIRY_THRESHOLD days gets...pruned away
     *
     * @return void
     * @throws \PDOException
     */
    public function prune(): void
    {
        $threshold = \Alaress_Date_Format::now()->addDays(-1 * self::EXPIRY_THRESHOLD)->getDatabase();

        $query = "
            DELETE FROM {$this->getTable()}
            WHERE created_at < :threshold
        ";

        $statement = $this->getDatabase()->prepare($query);

        $statement->execute([
            'threshold' => $threshold
        ]);
    }
}
