<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Schoolbox\Discussion\Thread\Model as ThreadModel;
use Schoolbox\User;

/**
 * Strategies for validating whether a thread:
 * * is associated with a particular type of item
 * * if associated, whether the current user has permission to act upon it
 */
interface IStrategy
{
    /**
     * Does this strategy know how to find associations between this thread?
     * If so, return them.
     *
     * @param ThreadModel $thread
     * @return iterable
     */
    public function findAssociations(ThreadModel $thread): iterable;


    /**
     * Does the given user have permission to perform the action, based on the
     * object that is associated with the thread?
     *
     * Implies `retrieveAssociations` returned something.
     *
     * @param mixed     $object The object that is related to the thread
     * @param User|null $user
     * @return bool
     */
    public function hasPermission($object, ?User $user): bool;
}
