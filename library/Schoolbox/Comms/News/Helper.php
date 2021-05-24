<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Schoolbox\GenericCollection;

class Helper
{
    /**
     * @param GenericCollection $collections
     *
     * @return array
     */
    public static function getThreadIdsFromCollection(GenericCollection $collections): array
    {
        $threadIds = [];

        if (count($collections) === 0) {
            return $threadIds;
        }

        foreach (array_filter($collections->getPropertyValues('discussionThread')) as $thread) {
            $threadIds[] = $thread->getId();
        }

        return $threadIds;
    }
}
