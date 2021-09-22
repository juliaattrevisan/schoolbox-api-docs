<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Context;

use InvalidArgumentException;
use Schoolbox\Discussion\Thread;
use Schoolbox\Mapper;
use Schoolbox\Mapper\Exception as MapperException;
use UnexpectedValueException;

/**
 * Base implementation of a thread locator.
 */
abstract class BaseThreadLocator implements IThreadLocator
{
    /**
     * The mapper for the discussion thread's context.
     *
     * Its model must implement `IThreadLocation`: this will be checked in the
     * constructor.
     */
    private Mapper $contextMapper;


    /**
     * The mapper for the discussion threads themselves.
     */
    private Thread\Mapper $threadMapper;


    /**
     * The column within the context mapper's table which contains the
     * discussion thread foreign key.
     */
    private string $contextMapperThreadColumn;


    /**
     * Constructor
     *
     * @param Thread\Mapper $threadMapper
     * @param Mapper        $contextMapper
     * @param string        $contextMapperThreadColumn
     * @throws InvalidArgumentException if `$contextMapper`'s model doesn't
     *                                  implement `IThreadLocator`.
     */
    public function __construct(
        Thread\Mapper $threadMapper,
        Mapper $contextMapper,
        string $contextMapperThreadColumn
    ) {
        $this->assertContextMapper($contextMapper);
        $this->threadMapper              = $threadMapper;
        $this->contextMapper             = $contextMapper;
        $this->contextMapperThreadColumn = $contextMapperThreadColumn;
    }


    /**
     * @inheritDoc
     *
     * @param int $id
     * @return IThreadLocation
     * @throws MapperException         if fetch failed
     * @throws MapperException\Deleted if object was deleted
     */
    public function findById($id): IThreadLocation
    {
        // Don't need to check again, as `assertContextMapper` has already
        // verified this mapper's return type
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->contextMapper->findById($id);
    }


    /**
     * @param Thread\Model $thread
     * @return iterable|IThreadLocation[]
     */
    public function findAssociations(Thread\Model $thread): iterable
    {
        return $this->contextMapper->findByColumn($this->contextMapperThreadColumn, $thread->getId());
    }


    /**
     * @inheritDoc
     *
     * @param Thread\Model    $thread
     * @param IThreadLocation $object
     * @throws UnexpectedValueException if either $thread or $object is
     *                                  associated with something else
     * @throws MapperException          if something goes wrong whilst saving
     */
    public function associate(Thread\Model $thread, IThreadLocation $object): void
    {
        $this->assertContextModel($object);

        /* Check whether $object is already associated with $thread
         * If it is, then there's nothing to do; leave
         */
        if ($object->getDiscussionThread() && $object->getDiscussionThread()->getId() === $thread->getId()) {
            return;
        }

        /* Don't allow the thread to be associated with more than one object
         *
         * Check whether $thread already got associated with something else
         * If it is, throw a 409 Conflict back, because now you are doing
         * something that breaks our 1:1 rules for threads
         */
        if ($this->threadMapper->isThreadAlreadyAssociated($thread)) {
            throw new UnexpectedValueException('$thread is already associated with an object', 409);
        }

        /* Don't allow the object to be associated with a different thread
         *
         * Now check whether $object has a free spot for a new $thread
         * If something is already here, also throw a 409 Conflict back
         */
        if ($object->getDiscussionThread()) {
            throw new UnexpectedValueException('$object is already associated with a thread', 409);
        }

        /* If $thread is a whole new instance, save it first, then associate it
         * Otherwise, it was pre-saved from elsewhere and we can associate it directly
         */
        if (null === $thread->getId()) {
            $this->threadMapper->save($thread);
        }

        /* Then, associate it, since we know that we can
         */
        $object->setDiscussionThread($thread);
        $this->contextMapper->save($object);
    }


    /**
     * @inheritDoc
     *
     * @param Thread\Model    $thread
     * @param IThreadLocation $context
     * @return bool
     */
    public function isContextForThread(Thread\Model $thread, IThreadLocation $context): bool
    {
        $contextThread = $context->getDiscussionThread();
        return $contextThread && $thread->getId() === $contextThread->getId();
    }


    /**
     * @inheritDoc
     *
     * @param Thread\Model    $thread
     * @param IThreadLocation $context
     * @throws MapperException
     */
    public function deleteThread(Thread\Model $thread, IThreadLocation $context): void
    {
        $this->assertContextModel($context);
        $this->threadMapper->delete($thread);
        $commentMapper = $this->threadMapper->getJoinMapper('comments');
        // Explicitly associate the notifier to trigger notifications within the
        // context of a thread location
        $commentMapper->hasModuleNotifier($context->getCommentNotifier());
        $commentMapper->deleteByColumn('discussion_thread_id', $thread->getId());
    }


    /**
     * Assert that `$contextMapper`'s model implements `IThreadLocation`, and
     * throws an `InvalidArgumentException` if it doesn't.
     *
     * @param Mapper $contextMapper
     * @throws InvalidArgumentException
     */
    private function assertContextMapper(Mapper $contextMapper): void
    {
        if (is_a($contextMapper->getModel(), IThreadLocation::class, true)) {
            return;
        }
        throw new InvalidArgumentException(
            sprintf(
                'Model for context mapper %s must implement %s',
                get_class($contextMapper),
                IThreadLocation::class
            )
        );
    }


    /**
     * Assert that `$context` is the same class as the context mapper's model,
     * and throws an `InvalidArgumentException` if it isn't.
     *
     * @param IThreadLocation $context
     * @throws InvalidArgumentException
     */
    private function assertContextModel(IThreadLocation $context): void
    {
        if (is_a($context, $this->contextMapper->getModel())) {
            return;
        }
        throw new InvalidArgumentException(
            sprintf(
                'Context for this model %s must implement %s',
                get_class($context),
                IThreadLocation::class
            )
        );
    }
}
