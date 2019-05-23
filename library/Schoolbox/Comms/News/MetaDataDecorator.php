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
        if (!$model instanceof Model) {
            // only attempt to decorate if the model is the right type
            return $json;
        }

        $discussionThread =  $model->getDiscussionThread();

        $json['metaData'] = [
            'commentCount' => $discussionThread ? $this->commentCounts[$discussionThread->getId()] : 0
        ];

        return $json;
    }
}
