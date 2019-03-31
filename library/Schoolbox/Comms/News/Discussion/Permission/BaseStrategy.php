<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Permission;

use Schoolbox\Comms\News\Mapper;
use Schoolbox\Comms\News\Model;
use Schoolbox\Discussion\Thread\Authorizer\IStrategy;
use Schoolbox\Discussion\Thread\Model as ThreadModel;

/**
 * Base strategy for determining permissions on discussion threads which are
 * associated with comms articles.
 */
abstract class BaseStrategy implements IStrategy
{
    private const CONTEXT = 'COMMS';


    /**
     * @var Mapper
     */
    private $mapper;


    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
    }


    /**
     * @param $id
     * @return \Schoolbox\Comms\News\Model
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     */
    public function findById($id)
    {
        return $this->mapper->findById($id);
    }


    /**
     * @param ThreadModel $thread
     * @return iterable
     */
    public function retrieveAssociations(ThreadModel $thread): iterable
    {
        return $this->mapper->findByColumn('discussion_thread_id', $thread->getId());
    }


    /**
     * @param ThreadModel $thread
     * @param Model       $model
     * @throws \Schoolbox\Mapper\Exception
     */
    public function associate(ThreadModel $thread, Model $model): void
    {
        $model->setDiscussionThread($thread);
        $this->mapper->save($model);
    }
}
