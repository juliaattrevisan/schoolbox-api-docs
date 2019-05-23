<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      https://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;

class MetaDataDecorator implements IDecorator
{

    private $commentCounts;

    public function __construct($commentCounts)
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
            // Match values with threadId from commentCounts array to get the total number of comments
            $commentCount = (int) $this->commentCounts[
                array_search(
                    $discussionThread->getId(),
                    array_column($this->commentCounts, 'threadId'),
                    false)
            ]['totalCount'];
        }

        $json['metaData'] = [
            'commentCount' => $commentCount
        ];

        return $json;
    }
}
