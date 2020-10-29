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
}
