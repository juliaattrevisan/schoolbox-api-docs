<?php

/**
 * This is a lazy trait to connect to Synergetic and Schoolbox databases
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Database\Test;

use Alaress\Database\Connector\Sqlsrv\Connector as ConnectorSqlsrv;
use Alaress\Database\Connector\Mysql\Connector as ConnectorMysql;
use Alaress\Database\Factory;
use Database;

trait TestConnectorTrait
{
    /** @var ConnectorMysql $schoolbox */
    private $schoolbox;

    /** @var ConnectorSqlsrv $synergetic68 */
    private $synergetic68;

    /** @var ConnectorSqlsrv $synergetic67 */
    private $synergetic67;

    /**
     * Connect to schoolbox database
     */
    private function connectSchoolbox()
    {
        // We have to load up a connection using the old database class, otherwise the ConfigMapper is going to fail when
        // it tries to work out if we need to be in debug mode.
        //
        // TODO: Remove this once ConfigMapper has been migrated to these new database classes

        $database = Database::getInstance();
        $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true]);

        $this->schoolbox = Factory::CreateMysql();
        $this->schoolbox->connect(
            '192.168.36.10',
            'schoolbox',
            'root',
            'tnp879'
        );
    }

    /**
     * Connect to Synergetic V67 database
     */
    private function connectSynergetic67()
    {
        // We have to load up a connection using the old database class, otherwise the ConfigMapper is going to fail when
        // it tries to work out if we need to be in debug mode.
        //
        // TODO: Remove this once ConfigMapper has been migrated to these new database classes

        $database = Database::getInstance();
        $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true]);

        $this->synergetic67 = Factory::CreateSqlsrv();
        $this->synergetic67->connect(
            '172.31.6.236',
            'SynergyOne',
            'sa',
            'Synergetic#1'
        );
    }

    /**
     * Connect to Synergetic V68 database
     */
    private function connectSynergetic68()
    {
        // We have to load up a connection using the old database class, otherwise the ConfigMapper is going to fail when
        // it tries to work out if we need to be in debug mode.
        //
        // TODO: Remove this once ConfigMapper has been migrated to these new database classes

        $database = Database::getInstance();
        $database->connect(SQL_dsn, SQL_user, SQL_passwd, ['debug' => true]);

        $this->synergetic68 = Factory::CreateSqlsrv();
        $this->synergetic68->connect(
            '172.31.15.10',
            'Synergetic_AUVIC_ALARESS_PRD',
            'sa',
            'Synergetic#1'
        );
    }
}