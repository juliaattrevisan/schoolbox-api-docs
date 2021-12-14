<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace External\Canvas\Importer;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\Base;
use Alaress\Database\Broker\Mysql\Broker;
use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy;
use PDO;

/**
 * Class CanvasDb
 *
 * Establish a db connection to the local canvas db cache
 * We suck down canvas data into this db, and then later,
 * we read from it to put it into the schoolbox db itself
 */
class CanvasDb
{

    /**
     * @var string
     */
    public string $dbHost = SQL_host;


    /**
     * @var Base|null
     */
    private ?Base $connector = null;


    /**
     * The local canvas database is a concatenated instance identifier
     * after a hardcoded prefix; it's a whole separate db than the usual
     * schoolbox one
     *
     * @return string
     */
    private function getCanvasDbName(): string
    {
        return 'canvas_data_' . INSTANCE_ID;
    }


    /**
     * Returns a connection to the Canvas staging database
     *
     * @return IConnector
     * @throws Database\Exception
     */
    public function build(): IConnector
    {
        /* Only spawn this once
         */
        if (null === $this->connector) {
            $connector = new Base(new Broker(), new Transactor(new Strategy\Nested()), new Database\Quoter\Mysql\Quoter());

            $connectionOptions = [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_PERSISTENT => true,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            ];

            $connector->connect(
                $this->dbHost,
                $this->getCanvasDbName(),
                SQL_user,
                SQL_passwd,
                $connectionOptions
            );

            $this->connector = $connector;
        }

        return $this->connector;
    }
}
