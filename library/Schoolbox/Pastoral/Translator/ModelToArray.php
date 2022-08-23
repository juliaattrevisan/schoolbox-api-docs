<?php
/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Pastoral\Translator;

use Alaress\HTMLPurifier\Adapter\ScrubAdapter;
use Schoolbox\Pastoral\Comment\Translator\ModelToArray as CommentTranslator;
use Schoolbox\Pastoral\Model;
use Schoolbox\Pastoral\Tag\Model as Tag;
use Schoolbox\Resource\Storage\Translator\ModelToArray as StorageTranslator;
use Schoolbox\User\Translator\ModelToArray as UserTranslator;

/**
 * Translator for `\Schoolbox\Pastoral\Model` instances.
 */
class ModelToArray
{
    /**
     * Constructor
     *
     * @param UserTranslator    $userTranslator
     * @param CommentTranslator $commentTranslator
     * @param StorageTranslator $storageTranslator
     * @param ScrubAdapter      $scrubAdapter
     */
    public function __construct(
        private UserTranslator $userTranslator,
        private CommentTranslator $commentTranslator,
        private StorageTranslator $storageTranslator,
        private ScrubAdapter $scrubAdapter,
    ) {
    }

    /**
     * Translates a `\Schoolbox\Pastoral\Model` instance to an array representation.
     *
     * @param Model $model
     * @return array
     */
    public function __invoke(Model $model): array
    {
        return [
            // Standard fields
            'id'         => $model->getId(),
            'externalId' => $model->getExternalId(),
            'student'    => $model->getStudent()
                ? call_user_func($this->userTranslator, $model->getStudent())
                : null,
            'heading'    => $model->getHeading(),

            // TODO type/subtype translator?
            'type'       => $model->getType()
                ? ['id' => $model->getType()->getId(), 'name' => $model->getType()->getName()]
                : null,
            'subType'    => $model->getSubType()
                ? ['id' => $model->getSubType()->getId(), 'name' => $model->getSubType()->getName()]
                : null,

            'severity'               => $model->getSeverity(),
            'points'                 => $model->getPoints(),
            'tags'                   => $model->getTags()->map(
                function (Tag $tag) {
                    return [
                        'id'   => $tag->getId(),
                        'name' => $tag->getName(),
                    ];
                }
            ),
            'studentCommentsAllowed' => $model->getStudentCommentsAllowed(),
            'parentCommentsAllowed'  => $model->getParentCommentsAllowed(),

            // Text and comments
            'bodyPrivate'            => $this->scrubAdapter->purify($model->getBodyPrivate()),
            'bodyPublic'             => $this->scrubAdapter->purify($model->getBodyPublic()),
            'attachments'            => $model->getAttachments()->map($this->storageTranslator),
            'comments'               => $model->getComments()->map($this->commentTranslator),

            // TODO consider access?
            'access'                 => [],

            // TODO record actions
            'recordActions'          => [],

            // TODO related records?
            'relatedRecords'         => [],

            // TODO revisions?
            'revisions'              => [],

            // Ownership, last modifying user and time
            'createdBy'              => call_user_func($this->userTranslator, $model->getOwner()),
            'createdAt'              => $model->getCreatedAt()->toRfc3339String(),
            'updatedByBy'            => call_user_func($this->userTranslator, $model->getModifiedByDisplay()),
            'updatedAt'              => $model->getUpdatedAtDisplay()->toRfc3339String(),
        ];
    }
}
