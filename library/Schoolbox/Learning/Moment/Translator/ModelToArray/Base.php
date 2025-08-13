<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Moment\Translator\ModelToArray;

use Alaress\HTMLPurifier\IPurifier;
use Error;
use Schoolbox\Curriculum\Node;

/**
 * Translator for translating a learning moment evidence model into an array,
 * ready for JSON encoding.
 *
 * Used when translating a model created by Schoolbox into a response.
 */
class Base
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
     * HTMLPurifier instance, or an adapter thereof.
     *
     * Required for purifying the evidence's body (if present).
     */
    private IPurifier $purifier;


    /**
     * Constructor
     *
     * @param callable  $userTranslator
     * @param callable  $storageTranslator
     * @param IPurifier $purifier
     */
    public function __construct(callable $userTranslator, callable $storageTranslator, IPurifier $purifier)
    {
        $this->userTranslator    = $userTranslator;
        $this->storageTranslator = $storageTranslator;
        $this->purifier          = $purifier;
    }


    /**
     * @param \Schoolbox\Learning\Moment\Model $model
     * @return array
     */
    public function __invoke(\Schoolbox\Learning\Moment\Model $model): array
    {
        // Creator and recipient cannot be null in persisted entities.
        // Allow them to be null when translating though?
        try {
            $creator = call_user_func($this->userTranslator, $model->getCreator());
        } catch (Error) {
            $creator = null;
        }
        try {
            $recipient = call_user_func($this->userTranslator, $model->getRecipient());
        } catch (Error) {
            $recipient = null;
        }

        $moment = [
            'id'               => $model->getId(),
            'createdAt'        => $model->getCreatedAt()?->getUsingFormat(DATE_ATOM),
            'updatedAt'        => $model->getUpdatedAt()?->getUsingFormat(DATE_ATOM),
            'body'             => $this->purifier->purify($model->getBody()),
            'creator'          => $creator,
            'discussionThread' => $model->getDiscussionThread()
                ? ['id' => $model->getDiscussionThread()->getId()]
                : null,
            'recipient'        => $recipient,
            'evidence'         => [],
            'currentState' => $model->getCurrentState(),
            'curriculumNodes' => $model->getViewableCurriculumNodes()
                ? $model->getViewableCurriculumNodes()
                    ->map(
                        static function (Node\Model $node) {
                            return [
                                'id'   => $node->getId(),
                                'name' => $node->getName(),
                                'code' => $node->getCode(),
                            ];
                        }
                    )
                    ->reindex()
                : null,
            'tags'            => $model->getTags()?->map(
                static function (\Schoolbox\Learning\Moment\Tag\Model $tag) {
                    return [
                        'id'   => $tag->getId(),
                        'name' => $tag->getName(),
                    ];
                }
            )->reindex(),
            '_links'           => [],
        ];

        /* Process storage instances separately, if any exist
         */
        foreach ($model->getEvidence() as $evidence) {
            $moment['evidence'][] = [
                'storage' => call_user_func($this->storageTranslator, $evidence->getStorage()),
                'filename' => $evidence->getFilename(),
            ];
        }

        return $moment;
    }
}
