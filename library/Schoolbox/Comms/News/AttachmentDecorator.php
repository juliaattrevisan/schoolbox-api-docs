<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News;

use Alaress\Json\Decorator\IDecorator;

/**
 * Class AttachmentDecorator
 *
 * Collate a count of attached files
 *
 * @package Schoolbox\Comms\News
 */
class AttachmentDecorator implements IDecorator
{

    /**
     * A set of (article_id, count) tuples
     *
     * @var array
     */
    private array $attachmentCounts;


    /**
     * AttachmentDecorator constructor.
     *
     * @param array $attachmentCounts
     */
    public function __construct(array $attachmentCounts)
    {
        $this->attachmentCounts = $attachmentCounts;
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

        $json['attachments'] = 0;

        if (isset($this->attachmentCounts[$model->getId()])) {
            $json['attachments'] = $this->attachmentCounts[$model->getId()];
        }

        return $json;
    }
}
