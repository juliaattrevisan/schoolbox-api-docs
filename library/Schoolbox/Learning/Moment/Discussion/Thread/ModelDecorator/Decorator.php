<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Discussion\Thread\ModelDecorator;

use Schoolbox\Discussion\Thread\ModelDecorator\BaseDecorator;
use Schoolbox\Json\Api\AuraLink;

/**
 * Decorator for thread models: this one provides links specific to the evidence
 * variant of the discussion threads module.
 *
 * TODO this is the same as the comms variant, only the paths are different:
 *      attempt to unify them
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
            'getComments'   => new AuraLink('evidenceDiscussionThreads.getCommentsByThread', $params),
            'createComment' => new AuraLink('evidenceDiscussionThreads.createCommentInThread', $params),
        ];
    }
}
