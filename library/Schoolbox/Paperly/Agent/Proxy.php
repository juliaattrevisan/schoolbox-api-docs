<?php

namespace Schoolbox\Paperly\Agent;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Exception as AlaressDatabaseException;
use Alaress\Database\Statement\Query\IQuery;
use Alaress\Database\Statement\Query\Base;
use OutOfBoundsException;

class Proxy
{
    /* Max number of results allowed
     */
    private const MAX_RESULTS = 100;

    /* HMAC algorithm for hashing the request
     */
    public const PAPERLY_PROXY_HMAC_ALGO = 'sha256';

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
        //Reject requests that contains semicolons as we only accept one query at a time
        if (str_contains($sql, ';')) {
            throw new OutOfBoundsException(
                'Invalid query statement. Query should not contain semicolons(;).'
            );
        }

        /* Ignoring the leading non-alphabetical characters such as open-bracket
         * in case the user wants to use brackets
         */
        $regex = "/[^a-zA-Z]*(?<sqlOperator>\w+)/";
        preg_match($regex, $sql, $matches);

        /* Trying to discern the type of sql query the user wants to execute
         */
        return match (
            strtoupper(
                trim($matches['sqlOperator'])
            )
        ) {
            'SELECT' => true,
            'UPDATE', 'INSERT' => false,
            default => throw new OutOfBoundsException(
                'Invalid SQL statement. Please use a valid SQL operator.'
            ),
        };
    }

    /**
     * Retrieving results from the database
     *
     * @param IQuery|Base $stmt
     * @return array
     * @throws AlaressDatabaseException
     */
    public function retrieveResults(IQuery|Base $stmt): array
    {
        $results = [];
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($data) > self::MAX_RESULTS) {
            throw new AlaressDatabaseException(
                sprintf(
                    "Maximum number of results exceeded. Please limit the number of results in query to be below %d.",
                    self::MAX_RESULTS
                )
            );
        }

        foreach ($data as $row) {
            $results[] = $row;
        }

        return $results;
    }

    /**
     * Validate that the request has not been tampered with during transmission
     *
     * @param string     $hash
     * @param string     $sql
     * @param array|null $params
     * @param string     $secret
     * @return bool
     */
    public function isValidRequest(string $hash, string $sql, ?array $params, string $secret): bool
    {
        $encodedParams = isset($params) ? json_encode($params) : '';
        return hash_hmac(self::PAPERLY_PROXY_HMAC_ALGO, $sql . $encodedParams, $secret) === $hash;
    }
}
