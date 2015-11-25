<?php
/**
 * Interface for external database sync classes.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright 2015 Alaress Pty Ltd
 * @link      http://www.alaress.com.au
 */

namespace Schoolbox\External\Connector;

use Schoolbox\External\Normalise\User;

interface SyncInterface
{
    /**
     * Gets a collection of normalized student objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Student[]
     */
    public function findAllStudents();

    /**
     * Gets a collection of normalized staff objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Staff[]
     */
    public function findAllStaff();

    /**
     * Gets a collection of normalized parent objects.
     *
     * This method should return an iterator instead of an array, for memory
     * efficiency reasons.
     *
     * @return User\Guardian[]
     */
    public function findAllParents();
}