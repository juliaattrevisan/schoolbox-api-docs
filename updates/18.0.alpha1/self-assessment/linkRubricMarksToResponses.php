<?php
/**
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Psr\Log\LoggerInterface;

class migrateReturnsToMarks
{
    /** @var Database */
    private $db;

    /** @var LoggerInterface */
    private $log;

    /**
     * @param Database        $db
     * @param LoggerInterface $log
     */
    public function __construct(Database $db, LoggerInterface $log)
    {
        $this->db  = $db;
        $this->log = $log;
    }

    /**
     * @return Database
     */
    public function linkByDate()
    {


    }

    public function linkOneToOne()
    {
        $ids = getUniqueMarkRecords();

    }

    public function linkOneToMulti() {

    }


    public function getUniqueMarkRecords(): array
    {


    }

    public function createMarkRecord()
    {

    }


}