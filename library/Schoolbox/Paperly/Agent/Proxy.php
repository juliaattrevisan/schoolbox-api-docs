<?php

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\Query\Base;
use OutOfBoundsException;

class Proxy
{
    public function __construct(
        private IConnector $externalDb,
    ) {
    }

    /**
     * Query execution
     *
     * @param string     $sql
     * @param array|null $params
     * @return IQuery|Base
     */
    public function executeQuery(string $sql, ?array $params = []): IQuery|Base
    {
        $statement = $this->externalDb->prepareQuery($sql, $params);
        $stmt = $statement->execute();

        if ($stmt === false) {
            throw new AlaressDatabaseException(
                print_r($this->externalDb->errorInfo(), true)
            );
        }

        return $statement;
    }

    /**
     * Determine whether results will need to be fetched after execution
     *
     * @param string $sql
     * @return bool
     * @throws OutOfBoundsException
     */
    public function shouldReturnResults(string $sql): bool
    {
        /* Trying to discern the type of sql query the user wants to execute
         */
        return match (
            strtoupper(
                explode(' ', trim($sql))[0]
            )
        ) {
            'SELECT' => true,
            'UPDATE', 'INSERT' => false,
            default => throw new OutOfBoundsException(
                'Invalid SQL statement. Please use a valid SQL operator.'
            ),
        };
    }
}
