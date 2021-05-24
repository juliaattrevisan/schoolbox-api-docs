<?php

/**
 * Schoolpro implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\External\Connector\Schoolpro\Base;

use Schoolbox\External\Connector\Base;
use Schoolbox\External\Connector\ISync;

class Sync extends Base implements ISync
{
    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        return new StudentIterator(
            $this->databaseExternal->getStoredProcedureIterator(
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
            $this->databaseExternal->getStoredProcedureIterator(
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
            $this->databaseExternal->getStoredProcedureIterator(
                'sbGetStaffAccounts',
                ['pUserName' => 'alaress', 'pUserPass' => 'alaress']
            )
        );
    }
}
