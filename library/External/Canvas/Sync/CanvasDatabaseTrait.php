<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au
 */
declare(strict_types=1);

namespace External\Canvas\Sync;

use Alaress\Database;
use Alaress\Database\Connector\IConnector;
use Alaress\Database\Connector\Base;
use Alaress\Database\Broker\Mysql\Broker;
use Alaress\Database\Transactor\Base as Transactor;
use Alaress\Database\Transactor\Strategy;
use PDO;

/**
 * Trait for canvas staging database.
 */
trait CanvasDatabaseTrait
{
    public $dbHost = SQL_host;

    public function getCanvasDbName(): string
    {
        return 'canvas_data_' . INSTANCE_ID;
    }


    /**
     * Returns a connection to the Canvas staging database
     *
     * @return IConnector
     * @throws Database\Exception
     */
    private function buildCanvasDbConnection(): IConnector
    {
        $connector = new Base(new Broker(), new Transactor(new Strategy\Nested()));

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

        return $connector;
    }
}
