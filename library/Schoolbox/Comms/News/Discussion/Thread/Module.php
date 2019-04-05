<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread;

use Schoolbox\Discussion\Thread\Module as BaseModule;

/**
 * Comms-specific module for discussion threads.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'commsDiscussionThreads';
}
