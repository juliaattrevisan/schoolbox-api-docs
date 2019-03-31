<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\Discussion\Thread\Model as ThreadModel;

declare(strict_types=1);


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
     * Constructor
     *
     * @param News\Mapper $mapper
     */
    public function __construct(News\Mapper $mapper)
    {
        $this->mapper = $mapper;
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
     * @param  int $id
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
     * @param ThreadModel $thread
     * @return iterable|News\Model[]
     */
    public function findAssociations(ThreadModel $thread): iterable
    {
        return $this->mapper->findByColumn('discussion_thread_id', $thread->getId());
    }


    /**
     * @inheritDoc
     *
     * @param ThreadModel $thread
     * @param mixed       $object
     * @throws \Schoolbox\Mapper\Exception if save failed
     */
    public function associate(ThreadModel $thread, $object): void
    {
        if (!$object instanceof News\Model) {
            throw new \TypeError('$object must be a ' . News\Model::class);
        }
        $object->setDiscussionThread($thread);
        $this->mapper->save($object);
    }
}
