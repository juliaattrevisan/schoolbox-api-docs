<?php

/**
 * MSSQL Database Connector
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Connector\Sqlsrv;

use Alaress\Database\Broker\Sqlsrv\Broker;
use Alaress\Database\Connector\Base;
use Alaress\Database\Connector\IConnector;
use Serializable;

class Connector extends Base implements Serializable, IConnector
{
    /** @var Broker */
    protected $broker;

    /**
     * @inheritdoc
     */
    public function connect($hostname, $database, $username, $password, $options = [])
    {
        $this->options = $options;
        $this->username = $username;
        $this->password = $password;
        $this->hostname = $hostname;
        $this->database = $database;

        $this->broker = new Broker($this);
        return $this->broker->connect($options);
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Type specific deserialize function
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function unserialize($serialized)
    {
        parent::unserialize($serialized);

        $this->broker = new Broker($this);
        $this->broker->connect();
    }

    // ----------------------------------------------------------------------------------------------------------------
    // Type specific query preparation function
    // ----------------------------------------------------------------------------------------------------------------

    /**
     * @inheritdoc
     */
    public function prepareQuery($sql, $parameters = [])
    {
        $statement = $this->broker->prepareQuery($sql);

        if (is_array($parameters)) {
            $statement->bindInputs($parameters);
        }

        return $statement;
    }
}