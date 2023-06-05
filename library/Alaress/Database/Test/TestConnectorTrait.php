<?php

/**
 * This is a lazy trait to connect to Synergetic and Schoolbox databases
 *
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Database\Test;

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Factory;

trait TestConnectorTrait
{
    /** @var IConnector $schoolbox */
    private $schoolbox;

    /** @var IConnector $synergetic68 */
    private $synergetic68;

//    /** @var IConnector $synergetic67 */
//    private $synergetic67;

    /** @var IConnector $db2Test */
    private $db2Test;

    /** @var IConnector $db2Test */
    protected $mssqlTest;

    /**
     * Connect to DB2 test database
     *
     * @return bool Was connection successful?
     */
    private function connectDb2Test()
    {
        $factory = new Factory();

        $this->db2Test = $factory->createDb2();
        return $this->db2Test->connect(
            'ec2-54-153-145-255.ap-southeast-2.compute.amazonaws.com:50000',
            'SBOXTEST',
            'sbox',
            'tnp879'
        );
    }

    /**
     * Connect to schoolbox database
     *
     * @return bool Was connection successful?
     */
    private function connectSchoolbox()
    {
        $factory = new Factory();

        $this->schoolbox = $factory->createMysql();
        return $this->schoolbox->connect(
            'localhost',
            'schoolbox',
            'schoolboxvagrant',
            'abc123'
        );
    }

    /**
     * Connect to MSSQL test database using Sqlsrv
     *
     * @return bool Was connection successful?
     */
    private function connectSqlsrvTest()
    {
        $factory = new Factory();

        $this->mssqlTest = $factory->createSqlsrv();
        return $this->mssqlTest->connect(
            '172.31.15.10',
            'Synergetic_AUVIC_ALARESS_PRD',
            'sa',
            'Synergetic#1'
        );
    }
}
