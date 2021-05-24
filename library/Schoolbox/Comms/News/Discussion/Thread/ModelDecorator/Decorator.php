<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Thread\ModelDecorator;

use Schoolbox\Discussion\Thread\ModelDecorator\BaseDecorator;
use Schoolbox\Json\Api\AuraLink;

/**
 * Decorator for thread models: this one provides links specific to the comms
 * variant of the discussion threads module.
 */
class Decorator extends BaseDecorator
{
    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        $params = [
            'id'        => $this->model->getId(),
            'thread'    => $this->model,
            'context'   => $this->context,
            'contextId' => $this->context->getId(),
        ];
        return [
            'getComments'   => new AuraLink('commsDiscussionThreads.getCommentsByThread', $params),
            'createComment' => new AuraLink('commsDiscussionThreads.createCommentInThread', $params),
        ];
    }
}
