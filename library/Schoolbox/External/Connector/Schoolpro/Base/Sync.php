<?php
/**
 * Schoolpro implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\SyncInterface;

class Sync extends Base implements SyncInterface
{
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
