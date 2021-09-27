<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Context;

use Schoolbox\Discussion\Thread\Model as ThreadModel;

/**
 * Definition of a thread context.
 *
 * Contains strategies for:
 * * finding a thread context, given its ID
 * * associating a thread with a context
 * * determining if a thread is associated with a given context
 */
interface IThreadLocator
{
    /**
     * Find an item by its ID, which this context knows how to associate with a
     * thread.
     *
     * @param int $id
     * @return IThreadLocation
     */
    public function findById($id): IThreadLocation;


    /**
     * Associates the given object with the thread.
     *
     * @param ThreadModel $thread
     * @param IThreadLocation $object
     * @throws \UnexpectedValueException
     */
    public function associate(ThreadModel $thread, IThreadLocation $object): void;


    /**
     * Is this thread the correct association for this context?
     *
     * @param ThreadModel $thread
     * @param IThreadLocation $context
     * @return bool
     */
    public function isContextForThread(ThreadModel $thread, IThreadLocation $context): bool;


    /**
     * Delete thread and its comments
     *
     * @param ThreadModel $thread
     * @param IThreadLocation $context
     */
    public function deleteThread(ThreadModel $thread, IThreadLocation $context): void;
}
