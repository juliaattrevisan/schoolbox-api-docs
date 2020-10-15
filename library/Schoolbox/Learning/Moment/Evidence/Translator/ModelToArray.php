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
     * Translator for converting a user within the evidence model.
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $userTranslator;


    /**
     * Translator for converting a storage object.
     *
     * (callable isn't a valid typedef)
     *
     * @var callable
     */
    private $storageTranslator;


    /**
     * Constructor
     *
     * @param callable $userTranslator
     * @param callable $storageTranslator
     */
    public function __construct(callable $userTranslator, callable $storageTranslator)
    {
        $this->userTranslator    = $userTranslator;
        $this->storageTranslator = $storageTranslator;
    }


    /**
     * @param Evidence\Model $model
     * @return array
     */
    public function __invoke(Evidence\Model $model): array
    {
        // Creator and recipient cannot be null in persisted entities.
        // Allow them to be null when translating though?
        try {
            $creator = call_user_func($this->userTranslator, $model->getCreator());
        } catch (Error $e) {
            $creator = null;
        }
        try {
            $recipient = call_user_func($this->userTranslator, $model->getRecipient());
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
                ? call_user_func($this->storageTranslator, $model->getStorage())
                : null,
        ];
    }
}
