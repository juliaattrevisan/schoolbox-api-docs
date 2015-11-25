<?php
/**
 * Offline stub implementation of sync connector.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\External\Connector\Offline\Base;

use Schoolbox\External\Connector\SyncInterface;

class Sync implements SyncInterface
{
    /**
     * {@inheritdoc}
     */
    public function findAllStudents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function findAllParents()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function findAllStaff()
    {
        return [];
    }
}
