<?php

/**
 * SOCS import log mapper.
 */

declare(strict_types=1);

namespace Schoolbox\Socs\Log;

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
    private const int EXPIRY_THRESHOLD = 7;

    protected function mapping(): void
    {
        $this->hasTable('socs_import_log');
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
     * Create and persist a new log entry at the start of a run.
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
     * Mark a run as successfully completed.
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
     * Mark a run as errored with an error message for diagnostics.
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
     * Delete old log rows beyond the expiry threshold.
     */
    public function prune(): void
    {
        $threshold = \Alaress_Date_Format::now()->addDays(-1 * self::EXPIRY_THRESHOLD)->getDatabase();

        $query = "
            DELETE FROM {$this->getTable()}
            WHERE created_at < :threshold
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([':threshold' => $threshold]);
    }
}
