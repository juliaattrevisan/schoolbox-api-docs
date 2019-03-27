<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Permission;

use Schoolbox\Comms\News\Mapper;
use Schoolbox\Discussion\Thread\Authorizer\IStrategy;
use Schoolbox\Discussion\Thread\Model as ThreadModel;

/**
 * Base strategy for determining permissions on discussion threads which are
 * associated with comms articles.
 */
abstract class BaseStrategy implements IStrategy
{
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
     * @param ThreadModel $thread
     * @return iterable
     */
    public function retrieveAssociations(ThreadModel $thread): iterable
    {
        return $this->mapper->findByColumn('discussion_thread_id', $thread->getId());
    }
}
