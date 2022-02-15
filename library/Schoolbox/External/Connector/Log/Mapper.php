<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Log;

/**
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{

    /**
     * Anything older than now minus EXPIRY_THRESHOLD days gets deleted
     */
    private const EXPIRY_THRESHOLD = 1;


    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('sis_import_log');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('script', self::TYPE_STRING);
        $this->uses('start_at', self::TYPE_DATETIME);
        $this->uses('finish_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * Find the most recent log stats
     *
     * @return array
     * @throws \Schoolbox\Mapper\Exception
     */
    public function status(): array
    {
        /* Only look for the KNOWN_SCRIPTS that ran today and not any other day
         */
        $pb = $this->getPredicateBuilder()
            ->in('script', Model::KNOWN_SCRIPTS)
            ->like('start_at', date('Y-m-d') . '%');

        $where = implode(' AND ', $pb->getWhere());

        $query = "
            SELECT *
            FROM sis_import_log
            WHERE id IN (
                SELECT MAX(id) from sis_import_log
                GROUP BY script
            )
            AND {$where} 
            ORDER BY start_at
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute($pb->getParams());

        $stack = [];

        /* These rows might have zero, one, or two scripts in it
         *
         * If there are two rows in this configuration: [sis, calendar]
         * then you're looking at a complete suds import
         *
         * If there are two rows in this configuration: [calendar, sis]
         * You're looking at the second half of a previous run alongside
         * the first half of the current run. In this case, you could
         * ignore the first row and say the current run is incomplete...
         * except you don't know if the calendar was previously in use,
         * but is -no longer- in use, at which point things get wierd
         */
        while ($row = $statement->fetch()) {
           $stack[$row['script']] = $this->hydrate($row);
        }

        return $stack;
    }


    /**
     * A macro to set up and save a new record
     *
     * @param string $scriptName
     * @return Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function start(string $scriptName): Model
    {
        $importLog = $this->createModel()
            ->setScript($scriptName)
            ->setStartAt(\Alaress_Date_Format::now());

        $this->save($importLog);

        return $importLog;
    }


    /**
     * A macro to set up and save a new record
     *
     * @param Model $importLog
     * @return Model
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function finish(Model $importLog): Model
    {
        $importLog->setFinishAt(\Alaress_Date_Format::now());

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
