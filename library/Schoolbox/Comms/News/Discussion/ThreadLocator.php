<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion;

use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread;

/**
 * Discussion threads context for comms articles.
 */
class ThreadLocator extends Thread\Context\BaseThreadLocator
{
    /**
     * The type for this object.
     *
     * @deprecated This was once used in an identifier somewhere: it is used
     *             no longer
     */
    public const TYPE = 'comms';


//    /**
//     * @var News\Mapper
//     */
//    private $mapper;
//
//
//    /**
//     * @var Thread\Mapper
//     */
//    private $threadMapper;


    /**
     * Constructor
     *
     * @param Thread\Mapper $threadMapper
     * @param News\Mapper   $mapper
     */
    public function __construct(Thread\Mapper $threadMapper, News\Mapper $mapper)
    {
        parent::__construct($threadMapper, $mapper, 'discussion_thread_id');
    }


    /**
     * @deprecated I don't think this is used anymore
     *
     * @param string $contextType
     * @return bool
     */
    public function isContextFor(string $contextType): bool
    {
        return $contextType === self::TYPE;
    }


//    /**
//     * @inheritDoc
//     *
//     * @param int $id
//     * @return News\Model
//     * @throws \Schoolbox\Mapper\Exception         if fetch failed
//     * @throws \Schoolbox\Mapper\Exception\Deleted if object was deleted
//     */
//    public function findById($id): Thread\Context\IThreadLocation
//    {
//        return $this->mapper->findById($id);
//    }
//
//
//    /**
//     * @inheritDoc
//     *
//     * @param Thread\Model $thread
//     * @return iterable|News\Model[]
//     */
//    public function findAssociations(Thread\Model $thread): iterable
//    {
//        return $this->mapper->findByColumn('discussion_thread_id', $thread->getId());
//    }
//
//
//    /**
//     * @inheritDoc
//     *
//     * @param Thread\Model $thread
//     * @param News\Model   $object
//     * @throws \TypeError                  if $object isn't a News\Model
//     * @throws \Schoolbox\Mapper\Exception if save failed
//     * @throws \UnexpectedValueException   if either $thread or $object is
//     *                                     associated with something else
//     */
//    public function associate(Thread\Model $thread, Thread\Context\IThreadLocation $object): void
//    {
//        if (!$object instanceof News\Model) {
//            throw new \TypeError('$object must be a ' . News\Model::class);
//        }
//
//        // Don't allow the thread to be associated with more than one object
//        if ($this->threadMapper->isThreadAlreadyAssociated($thread)) {
//            throw new \UnexpectedValueException('$thread is already associated with an object');
//        }
//
//        // Don't allow the object to be associated with a different thread
//        if ($object->getDiscussionThread()) {
//            throw new \UnexpectedValueException('$object is already associated with a thread');
//        }
//
//        $object->setDiscussionThread($thread);
//        $this->mapper->save($object);
//    }
//
//
//    /**
//     * @inheritDoc
//     *
//     * @param Thread\Model                                  $thread
//     * @param \Schoolbox\Discussion\Thread\Context\IThreadLocation $context
//     * @return bool
//     */
//    public function isContextForThread(Thread\Model $thread, Thread\Context\IThreadLocation $context): bool
//    {
//        $contextThread = $context->getDiscussionThread();
//        return $contextThread && $thread->getId() === $contextThread->getId();
//    }
//
//
//    /**
//     * @inheritDoc
//     *
//     * @param Thread\Model $thread
//     * @param Thread\Context\IThreadLocation $context
//     * @throws Exception
//     */
//    public function deleteThread(Thread\Model $thread, Thread\Context\IThreadLocation $context): void
//    {
//        $this->threadMapper->delete($thread);
//        $commentMapper = $this->threadMapper->getJoinMapper('comments');
//        // Explicitly associate the notifier to trigger notifications within the context of a comms article
//        $commentMapper->hasModuleNotifier($context->getCommentNotifier());
//        $commentMapper->deleteByColumn('discussion_thread_id', $thread->getId());
//    }
}
