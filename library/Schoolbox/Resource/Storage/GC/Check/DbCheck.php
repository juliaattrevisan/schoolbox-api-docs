<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\GC\Check;

use Alaress\Database\Connector\IConnector;

abstract class DbCheck implements Check
{
    /**
     * Database connection
     */
    protected IConnector $db;

    /**
     * @param IConnector $db
     */
    public function __construct(IConnector $db)
    {
        $this->db = $db;
    }

    /**
     * Return the SQL condition that this check uses to regard a file as 'stale'.
     * Do not include the WHERE keyword.
     *
     * @return string
     */
    abstract protected function getStaleCondition(): string;

    /**
     * @param array<string> $previous
     * @return array<string>
     */
    public function getStale(array $previous): array
    {
        $cond   = $this->getStaleCondition();
        $prev   = implode("', '", $previous);
        $stmt   = $this->db->query("SELECT `hash` FROM `storage` WHERE $cond AND `hash` IN ('$prev')");
        $retain = [];

        while ($hash = $stmt->fetchColumn()) {
            $retain[] = $hash;
        }

        return $retain;
    }
}
