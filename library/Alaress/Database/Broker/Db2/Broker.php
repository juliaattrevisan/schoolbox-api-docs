<?php

/**
 * IBM DB2 Database connection broker
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Broker\Db2;

use Alaress\Database\Broker\Base;
use Alaress\Database\Exception;
use Alaress\Database\Iterator\Db2\Iterator;
use Alaress\Database\Statement\FunctionCall\Db2\FunctionCall;
use Alaress\Database\Statement\StoredProcedure\Db2\StoredProcedure;
use Alaress\Database\Wrapper\Db2\PDO;
use Alaress\DebugBar\Instance as DebugBar;
use DebugBar\DataCollector\DataCollectorInterface;

class Broker extends Base
{
    // These return DB2 specific statement objects

    /**
     * @inheritdoc
     */
    public static function isAvailable()
    {
        // DB2 is loaded as a PHP extension, not a PDO module
        return extension_loaded('ibm_db2');
    }

    /**
     * @inheritdoc
     */
    public function prepareFunctionCall($function)
    {
        return new FunctionCall($this, $function);
    }

    /**
     * @inheritdoc
     */
    public function prepareStoredProcedure($procedure)
    {
        return new StoredProcedure($this, $procedure);
    }

    /**
     * @inheritdoc
     */
    public function getIterator($statement, $pdoStatement)
    {
        return new Iterator($this, $statement, $pdoStatement);
    }

    /**
     * @inheritdoc
     */
    public function getDriver(): string
    {
        return 'ibm';
    }

    /**
     * @inheritdoc
     */
    public function getDsn()
    {
        $portString = '';

        if (!empty($this->connector->getPort())) {
            $portString = "PORT={$this->connector->getPort()};";
        }

        return "DATABASE={$this->connector->getDatabase()};HOSTNAME={$this->connector->getHostname()};{$portString}PROTOCOL=TCPIP;UID={$this->connector->getUsername()};PWD={$this->connector->getPassword()};";
    }

    /**
     * @inheritdoc
     *
     * DB2 Specific connection function as this uses the hideous PDO wrapper
     */
    public function connect($options = [])
    {
        // Close any existing connection (this seems a bit flaky- why is there no close function?)
        unset($this->pdo);

        if ($options['schema'] ?? null) {
            $this->postConnectQueries = ['SET SCHEMA ' . $options['schema']];
        }

        try {
            // Create a new PDO wrapper instance using the DSN string and supplied username/password parameters

            $this->pdo = new PDO($this->getDsn(), $this->connector->getUsername(), $this->connector->getPassword());

            // These will only get called if the child class overrides the values- don't *think*
            // we need these to be parameters as they're really just there to get the database in a
            // state where our functions operate as expected


            foreach ($this->databaseAttributes as $attribute => $value) {
                $this->pdo->setAttribute($attribute, $value);
            }

            foreach ($this->postConnectQueries as $sql) {
                $this->pdo->exec($sql);
            }

            if (DebugBar::isEnabled()) {
                $this->installCollector();

                /** @var DataCollectorInterface $collector */
                $collector = $this->getCollector();

                if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                    DebugBar::getInstance()->addCollector($collector);
                }
            }
        } catch (Exception $e) {
            // Something went wrong during connection

            throw new Exception("Error connecting to database.\nPDO Wrapper Error: {$e->getMessage()}");
        }

        return true;
    }

    /**
     * Attempt to set the connection's timezone.
     *
     * @param string $timezone A valid timezone.
     * @return bool
     */
    public function setTimezone($timezone)
    {
        return true;
    }
}
