<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Comment\ModelWrapper;

use Schoolbox\Discussion\Comment\ModelDecorator\BaseDecorator;
use Schoolbox\Json\Api\AuraLink;

/**
 * Wrapper for comment models: this one provides links specific to the comms
 * variant of the discussion thread comments module.
 */
class Decorator extends BaseDecorator
{
    /**
     * @return array
     */
    public function getJsonApiLinks(): array
    {
        return [
            'delete' => new AuraLink(
                'commsDiscussionComments.deleteComment',
                [
                    'id'        => $this->model->getId(),
                    'comment'   => $this->model,
                    'context'   => $this->context,
                    // TODO add getId to context
                    'contextId' => $this->context->getId(),
                ]
            ),
        ];
    }
}
