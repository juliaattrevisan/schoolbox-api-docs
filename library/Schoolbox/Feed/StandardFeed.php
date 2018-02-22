<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Feed;

use Schoolbox;
use SocialStream;

class StandardFeed extends Feed
{

    /**
     * @param Schoolbox\IConfigLoader $conf
     * @param int                     $context
     * @param string                  $roleType
     * @param int                     $limit
     * @param int                     $offset
     */
    public function __construct(
        Schoolbox\IConfigLoader $conf,
        $context,
        $roleType,
        $limit = SourceInterface::HARD_LIMIT,
        $offset = 0
    ) {
        parent::__construct();

        $this->aggregate(
            [
//                new Schoolbox\Assessment\Component\SubmissionBoxNotifier($conf),
//                new Schoolbox\Learning\Quiz\Notifier($conf),
                new Schoolbox\Resource\Category\NewsNotifier($conf),
                new Schoolbox\Blog\Homepage\Post\Notifier($conf),
                new Schoolbox\Blog\Homepage\Comment\Notifier($conf),
                new Schoolbox\Wiki\WikiNotifier($conf),
                new Schoolbox\Wiki\RevisionNotifier($conf),
                new Schoolbox\Resource\Folder\Notifier($conf),
                new Schoolbox\Resource\File\Notifier($conf),
                new Schoolbox\Resource\LinkNotifier($conf),
                new Schoolbox\Resource\Textbox\Notifier($conf),
                new SocialStream\PostNotifier($conf),
                new Schoolbox\Forum\ThreadNotifier($conf),
                new Schoolbox\Forum\PostNotifier($conf),
            ],
            $context,
            $roleType,
            $limit,
            $offset
        );
    }
}
