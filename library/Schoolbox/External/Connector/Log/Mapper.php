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
}
