<?php
/**
 * Schoolpro implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use MSSQL;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\Sync\Iterator;

class Sync implements SyncInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /**
     * Sync constructor
     */
    public function __construct()
    {
        $this->database = MSSQL::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        // This might work!
        $vars = array(
            'sbGetStudentAccounts',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
            )
        );
        return new Iterator\Student(
            $this->database->getProcedureIterator($vars[0], $vars[1])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        // This might work!
        // It probably returns both parents in one row though.
        $vars = array(
            'sbGetGuardianAccounts',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
            )
        );
        return new Iterator\Guardian(
            $this->database->getProcedureIterator($vars[0], $vars[1])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        // This might work!
        $vars = array(
            'sbGetStaffAccounts',
            array(
                array('@pUserName', 'alaress', SQLVARCHAR, false, false, -1),
                array('@pUserPass', 'alaress', SQLVARCHAR, false, false, -1),
            )
        );
        return new Iterator\Staff(
            $this->database->getProcedureIterator($vars[0], $vars[1])
        );
    }
} 