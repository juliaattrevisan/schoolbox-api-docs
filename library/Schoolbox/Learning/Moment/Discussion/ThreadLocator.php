<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Discussion;

use Schoolbox\Discussion\Thread;
use Schoolbox\Learning\Moment\Evidence;

/**
 * Discussion threads context for learning evidence.
 */
class ThreadLocator extends Thread\Context\BaseThreadLocator
{
    /**
     * Constructor
     *
     * @param Thread\Mapper   $threadMapper
     * @param Evidence\Mapper $mapper
     */
    public function __construct(Thread\Mapper $threadMapper, Evidence\Mapper $mapper)
    {
        parent::__construct($threadMapper, $mapper, 'discussion_thread_id');
    }
}
