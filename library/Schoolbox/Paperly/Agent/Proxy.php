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
    private const int MAX_RESULTS = 10000;

    /* HMAC algorithm for hashing the request
     */
    public const string PAPERLY_PROXY_HMAC_ALGO = 'sha256';

    public const string IMAGE_WILDCARD = 'image/';

    /* Compatible SQL Operators
     */
    private const string SQL_INSERT = 'INSERT';
    private const string SQL_SELECT = 'SELECT';
    private const string SQL_UPDATE = 'UPDATE';

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
        /* Appending SCOPE_IDENTITY() to retrieve the last inserted ID
         * We are purposely inserting the SELECT statement in the same
         * execution to be able to return the last inserted ID immediately after
         * the insert operation.
         * This also introduces te semi-colon, but we are ok cause we've already identified
         * the SQL operator earlier.
         *
         * Note: This is specific to MS SQL Server databases, unlikely, but if this is ever used with
         * other database types, this part will need to be adjusted accordingly.
         */
        if (self::SQL_INSERT === $this->getSqlOperator($sql)) {
            $sql .= '; SELECT SCOPE_IDENTITY() AS InsertedID;';
        }

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
     * Get the SQL operator from the query
     *
     * @param string $sql
     * @return string
     * @throws OutOfBoundsException
     */
    private function getSqlOperator(string $sql): string
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

        /* Validating if we were able to extract the SQL operator
         */
        if (!isset($matches['sqlOperator'])) {
            throw new OutOfBoundsException(
                'Invalid SQL statement. Please use a valid SQL operator.'
            );
        }

        return strtoupper(
            trim($matches['sqlOperator'])
        );
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
        return match ($this->getSqlOperator($sql)) {
            self::SQL_SELECT, self::SQL_INSERT => true,
            self::SQL_UPDATE => false,
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
            $row = array_map(fn($item) => $this->convertImageToBase64($item), $row);
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
        return hash_equals(
            hash_hmac(
                self::PAPERLY_PROXY_HMAC_ALGO,
                json_encode(['sql' => $sql, 'params' => $params]),
                $secret
            ),
            $hash
        );
    }

    /**
     * Check if the data need to be encoded and return the base64 encoded data back
     *
     * @param mixed $stream
     * @return mixed
     */
    private function convertImageToBase64(mixed $stream): mixed
    {
        if (!is_string($stream)) {
            return $stream;
        }
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($stream);
        /* If the data is image stream then encode it
         */
        if (str_starts_with($mimeType, self::IMAGE_WILDCARD)) {
            return base64_encode($stream);
        }
        return $stream;
    }
}
