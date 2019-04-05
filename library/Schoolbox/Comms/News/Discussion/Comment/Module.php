<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment;

use Schoolbox\Discussion\Comment\Module as BaseModule;

/**
 * Comms-specific module for discussion thread comments.
 */
class Module extends BaseModule
{
    /**
     * @var string
     */
    protected $basename = 'commsDiscussionComments';
}
