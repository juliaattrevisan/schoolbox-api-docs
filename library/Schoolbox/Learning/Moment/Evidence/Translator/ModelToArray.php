<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Evidence\Translator;

use Error;
use Schoolbox\Learning\Moment\Evidence;

/**
 * Translator for translating a learning moment evidence model into an array,
 * ready for JSON encoding.
 *
 * Used when translating a model created by Schoolbox into a response.
 */
class ModelToArray
{
    /**
     * @param Evidence\Model $model
     * @return array
     */
    public function __invoke(Evidence\Model $model): array
    {
        // Creator and recipient cannot be null in persisted entities.
        // Allow them to be null when translating though?
        try {
            $creator = ['id' => $model->getCreator()->getId(),];
        } catch (Error $e) {
            $creator = null;
        }
        try {
            $recipient = ['id' => $model->getRecipient()->getId(),];
        } catch (Error $e) {
            $recipient = null;
        }

        return [
            'id'               => $model->getId(),
            'createdAt'        => $model->getCreatedAt() ? $model->getCreatedAt()->getUsingFormat(DATE_ATOM) : null,
            'updatedAt'        => $model->getUpdatedAt() ? $model->getUpdatedAt()->getUsingFormat(DATE_ATOM) : null,
//            'deletedAt'        => $model->getDeletedAt() ? $model->getDeletedAt()->getUsingFormat(DATE_ATOM) : null,
            'body'             => $model->getBody(),
            'creator'          => $creator,
            'discussionThread' => $model->getDiscussionThread()
                ? ['id' => $model->getDiscussionThread()->getId()]
                : null,
            'filename'         => $model->getFilename(),
            'recipient'        => $recipient,
            'storage'          => $model->getStorage()
                ? ['hash' => $model->getStorage()->getHash(),]
                : null,
        ];
    }
}
