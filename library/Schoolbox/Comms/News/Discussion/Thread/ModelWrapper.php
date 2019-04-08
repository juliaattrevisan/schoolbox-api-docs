<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread;

use Schoolbox\Discussion\Thread\ModelWrapper\BaseWrapper;
use Schoolbox\Json\Api\AuraLink;

/**
 * Wrapper for thread models: this one provides links specific to the comms
 * variant of the discussion threads module.
 */
class ModelWrapper extends BaseWrapper
{
    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        return [
            'getComments' => new AuraLink(
                'commsDiscussionThreads.getCommentsByThread',
                ['id' => $this->model->getId(), 'thread' => $this->model,]
            ),
            'createComment' => new AuraLink(
                'commsDiscussionThreads.createCommentInThread',
                ['id' => $this->model->getId(), 'thread' => $this->model,]
            ),
        ];
    }
}
