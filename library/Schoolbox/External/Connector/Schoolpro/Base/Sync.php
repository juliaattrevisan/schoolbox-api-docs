<?php
/**
 * Schoolpro implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use Alaress\Database\Connector\IConnector;
use Schoolbox\External\Connector\SyncInterface;
use Schoolbox\IConfigLoader;

class Sync implements SyncInterface
{
    /** @var IConnector $databaseExternal */
    protected $databaseExternal;

    /** @var IConfigLoader $config */
    protected $config;

    /**
     * @inheritdoc
     */
    public function __construct($config, $databaseExternal, $timetableExternal)
    {
        $this->config = $config;
        $this->databaseExternal = $databaseExternal;
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        return new StudentIterator(
            $this->databaseExternal->executeStoredProcedureIterator(
                'sbGetStudentAccounts',
                ['pUserName' => 'alaress', 'pUserPass' => 'alaress']
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        return new GuardianIterator(
            $this->databaseExternal->executeStoredProcedureIterator(
                'sbGetGuardianAccounts',
                ['pUserName' => 'alaress', 'pUserPass' => 'alaress']
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        return new StaffIterator(
            $this->databaseExternal->executeStoredProcedureIterator(
                'sbGetStaffAccounts',
                ['pUserName' => 'alaress', 'pUserPass' => 'alaress']
            )
        );
    }
}
