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
    function __construct(Schoolbox\IConfigLoader $conf, $context, $roleType, $limit = SourceInterface::HARD_LIMIT, $offset = 0)
    {
        parent::__construct();

        $this->aggregate(
             array(
                new Schoolbox\Assessment\Component\SubmissionBoxNotifier($conf),
                new Schoolbox\Assessment\Component\OnlineAssessmentNotifier($conf),
                new Schoolbox\Resource\Category\NewsNotifier($conf),
                new Schoolbox\Blog\BlogNotifier($conf),
                new Schoolbox\Blog\CommentNotifier($conf),
                new Schoolbox\Wiki\WikiNotifier($conf),
                new Schoolbox\Wiki\RevisionNotifier($conf),
                new Schoolbox\Resource\CategoryNotifier($conf),
                new Schoolbox\Resource\FileNotifier($conf),
                new Schoolbox\Resource\LinkNotifier($conf),
                new Schoolbox\Resource\TextboxNotifier($conf),
                new SocialStream\PostNotifier($conf)
            ),
            $context, $roleType, $limit, $offset
        );
    }



}