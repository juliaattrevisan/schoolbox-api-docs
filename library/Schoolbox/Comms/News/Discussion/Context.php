<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion;

use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread;

/**
 * Discussion threads context for comms articles.
 */
class Context implements IContext
{
    public const TYPE = 'comms';


    /**
     * @var News\Mapper
     */
    private $mapper;


    /**
     * @var Thread\Mapper
     */
    private $threadMapper;


    /**
     * Constructor
     *
     * @param Thread\Mapper $threadMapper
     * @param News\Mapper   $mapper
     */
    public function __construct(Thread\Mapper $threadMapper, News\Mapper $mapper)
    {
        $this->threadMapper = $threadMapper;
        $this->mapper       = $mapper;
    }


    /**
     * @inheritDoc
     *
     * @param string $contextType
     * @return bool
     */
    public function isContextFor(string $contextType): bool
    {
        return $contextType === self::TYPE;
    }


    /**
     * @inheritDoc
     *
     * @param int $id
     * @return mixed|News\Model
     * @throws \Schoolbox\Mapper\Exception         if fetch failed
     * @throws \Schoolbox\Mapper\Exception\Deleted if object was deleted
     */
    public function findById($id)
    {
        return $this->mapper->findById($id);
    }


    /**
     * @inheritDoc
     *
     * @param Thread\Model $thread
     * @return iterable|News\Model[]
     */
    public function findAssociations(Thread\Model $thread): iterable
    {
        return $this->mapper->findByColumn('discussion_thread_id', $thread->getId());
    }


    /**
     * @inheritDoc
     *
     * @param Thread\Model $thread
     * @param mixed        $object
     * @throws \Schoolbox\Mapper\Exception if save failed
     * @throws \UnexpectedValueException   if either $thread or $object is
     *                                     associated with something else
     */
    public function associate(Thread\Model $thread, $object): void
    {
        if (!$object instanceof News\Model) {
            throw new \TypeError('$object must be a ' . News\Model::class);
        }

        // Don't allow the thread to be associated with more than one object
        if ($this->threadMapper->isThreadAlreadyAssociated($thread)) {
            throw new \UnexpectedValueException('$thread is already associated with an object');
        }

        // Don't allow the object to be associated with a different thread
        if ($object->getDiscussionThread()) {
            throw new \UnexpectedValueException('$object is already associated with a thread');
        }

        $object->setDiscussionThread($thread);
        $this->mapper->save($object);
    }
}
