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
     * The reader from which rows are read.
     *
     * @var Reader
     */
    protected $reader;

    /**
     * The log object to which log entries are written
     *
     * @var LoggerInterface
     */
    protected $log;

    /**
     * Are updates actually performed?
     *
     * @var bool
     */
    protected $performUpdate;

    /**
     * Documentation function for expected format
     *
     * @return array
     */
    public static function getFormat()
    {
        return [];
    }

    /**
     * Constructor
     *
     * @param Reader          $reader
     * @param LoggerInterface $log
     * @param bool            $performUpdate
     */
    public function __construct(Reader $reader, LoggerInterface $log, $performUpdate = false)
    {
        $this->reader = $reader;
        $this->log    = $log;
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
     * Reads the rows to insert from the reader, and writes to the database.
     */
    abstract public function save();
}
