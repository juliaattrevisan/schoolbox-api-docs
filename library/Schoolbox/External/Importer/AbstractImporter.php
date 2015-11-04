<?php
/**
 * Base CSV importer for into the Schoolbox connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Importer;

use Alaress\Csv\Reader;
use Psr\Log\LoggerInterface;

abstract class AbstractImporter
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setup();
    }

    /**
     * Custom setup function. Set up mappers, handlers, etc. here instead of in
     * __construct().
     */
    protected function setup()
    {
        // noop
    }

    /**
     * Reads all rows from the CSV reader, and loads them into an associative
     * array structure.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     *
     * @return array
     */
    abstract protected function parse(Reader $reader, LoggerInterface $log);

    /**
     * Reads the rows to insert from the CSV, and writes to the database.
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     */
    abstract public function save(Reader $reader, LoggerInterface $log);
}
