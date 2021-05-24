<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;

class MetadataDecorator implements IDecorator
{
    /**
     * @var array
     *
     * Expected structure:
     * [[$threadId => $count]]
     */
    private $commentCounts;

    /**
     * MetadataDecorator constructor.
     *
     * @param array $commentCounts
     */
    public function __construct(array $commentCounts)
    {
        $this->commentCounts = $commentCounts;
    }

    /**
     * @param array  $json
     * @param object $model
     * @return array
     */
    public function decorate(array $json, $model): array
    {
        $commentCount = 0;

        if (!$model instanceof Model) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        $discussionThread = $model->getDiscussionThread();

        if ($discussionThread) {
            $threadId = $discussionThread->getId();

            if ($this->commentCounts[$threadId]) {
                $commentCount = $this->commentCounts[$threadId];
            }
        }

        $json['metadata'] = [
            'commentCount' => $commentCount
        ];

        return $json;
    }
}
