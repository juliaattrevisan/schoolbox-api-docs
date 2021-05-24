<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Storage\GC\Check;

use Database;
use PDO;

abstract class DbCheck implements Check
{
    /** @var PDO */
    protected $db;

    /**
     * @param Database $db
     */
    public function __construct(Database $db)
    {
        $this->db = $db;
    }


    /**
     * Return the SQL condition that this check uses to regard a file as 'stale'.
     * Do not include the WHERE keyword.
     *
     * @return string
     */
    abstract protected function getStaleCondition();


    /**
     * @param array $previous
     * @return array
     */
    public function getStale(array $previous)
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
