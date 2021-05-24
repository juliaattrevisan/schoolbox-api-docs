<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Config;

use Alaress\Database;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Application\CliTool;
use PDOException;
use Aura\Di\Exception\ServiceNotFound;

/**
 * A strictly-typed helper to insert config items that rely on PHP code.
 *
 * eg when we need to serialise the value, or rely on PHP constants
 *
 * Expected to be used by update scripts.
 */
abstract class InsertHelper extends CliTool\App
{

    /** @var IQuery */
    private $insertStatement;


    /**
     * Hands off execution over to the App
     *
     * @throws PDOException
     * @throws ServiceNotFound
     */
    public function run(): void
    {
        /** @var Database\Connector\IConnector $database */
        $database              = $this->getDi()->get('databaseInternal');
        $this->insertStatement = $database->prepareQuery($this->getInsertStatementSql());

        $database->beginTransaction();
        try {
            $this->insertConfigItems();
            $database->commit();
        } catch (Database\Exception $e) {
            $this->getLogger()->error($e->getMessage(), ['exception' => $e]);
            $database->rollBack();
        }
    }


    /**
     * Returns the insert statement SQL.
     *
     * We cannot put this in the abstract as during the update process, the schema and code may be out of sync.
     * The schema-specific code must exist in the extending class.
     *
     * @return string
     */
    abstract protected function getInsertStatementSql(): string;


    /**
     * Inserts one or more config items.
     *
     * We cannot put this in the abstract as during the update process, the schema and code may be out of sync.
     * The schema-specific values must exist in the extending class.
     *
     * @throws Database\Exception
     */
    abstract protected function insertConfigItems(): void;


    /**
     * Inserts a single config item into the database
     *
     * @param array $values
     * @throws Database\Exception
     */
    protected function insertConfigItem(array $values): void
    {
        $this->insertStatement->execute($values);
    }
}
