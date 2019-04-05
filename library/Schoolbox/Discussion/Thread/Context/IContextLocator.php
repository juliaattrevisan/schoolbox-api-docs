<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
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
interface IContextLocator
{
//    /**
//     * Is this the correct context for objects of this type?
//     *
//     * @param string $contextType
//     * @return bool
//     */
//    public function isContextFor(string $contextType): bool;


    /**
     * Find an item by its ID, which this context knows how to associate with a
     * thread.
     *
     * @param $id
     * @return IContext
     */
    public function findById($id): IContext;


//    /**
//     * Does this strategy know of any associations between this thread? If so,
//     * return them.
//     *
//     * @param ThreadModel $thread
//     * @return iterable|IAssociable[] A collection of objects that are
//     *                                associated with this thread.
//     */
//    public function findAssociations(ThreadModel $thread): iterable;


    /**
     * Associates the given object with the thread.
     *
     * @param ThreadModel $thread
     * @param IContext    $object
     */
    public function associate(ThreadModel $thread, IContext $object): void;


    /**
     * Is this thread the correct association for this context?
     *
     * @param ThreadModel $thread
     * @param IContext    $context
     * @return bool
     */
    public function isContextForThread(ThreadModel $thread, IContext $context): bool;
}
